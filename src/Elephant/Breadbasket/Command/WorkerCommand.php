<?php

/**
 * About file: PHP5
 */

namespace Elephant\Breadbasket\Command;

use Elephant\Breadbasket\Application;
use Elephant\Breadbasket\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommand extends AbstractCommand
{
    private $storage;
    private $num_children;
    private $children = array();

    public function __construct($name = 'worker')
    {
        $this->storage = new \SplObjectStorage();

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDescription('Start worker instance')
            ->addOption(
                'concurrency',
                'c',
                InputOption::VALUE_REQUIRED,
                'Number of child processes processing the queue.
The default is the number of CPUs available on your system.'
            )
            ->addOption(
                'workdir',
                '',
                InputOption::VALUE_REQUIRED,
                'Optional directory to change to after detaching.'
            )
            ->addOption(
                'detach',
                'D',
                InputOption::VALUE_NONE,
                ''
            )
            ->addOption(
                'pid-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Optional file used to store the process pid.
The program will not start if this file already exists
and the pid is still alive.'
            )
            ->addOption(
                'log-level',
                'l',
                InputOption::VALUE_REQUIRED,
                'Logging level, choose between DEBUG INFO NOTICE WARNING
ERROR ALERT CRITICAL or EMERGENCY.'
            )
            ->addOption(
                'log-file',
                'f',
                InputOption::VALUE_REQUIRED,
                'Path to log file.
If no logfile is specified, stderr is used.'
            )
            ->addOption(
                'uid',
                null,
                InputOption::VALUE_REQUIRED,
                'User id, or user name of the user to run as after detaching.'
            )
            ->addOption(
                'gid',
                null,
                InputOption::VALUE_REQUIRED,
                'Group id, or group name of the main group to change to after detaching.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Setup environment.
        $this->setupEnv($input, $output);

        // Detaching?
        if (true === $input->hasParameterOption(array('--detach', '-D'))) {
            # Открываем pid файл.
            # Создаем новый процесс (лидер).
            # Отсоединяемся от консоли.
            # От основоного, создаем дочерние == concurrency.
            $this->detaching($input, $output);
        }

        # Все ошибки, что происходили до открытия журнала, выводилось в stderr.
        # Большая часть из них - RuntimeException исключения.
        $this->openLog($input, $output);

        // TODO
        $this->log()->debug('{type} Message!', array('type' => 'Debug'));
        $this->log()->info('{type} Message!', array('type' => 'Info'));
        $this->log()->notice('{type} Message!', array('type' => 'Notice'));
        $this->log()->warning('{type} Message!', array('type' => 'Warning'));
        $this->log()->error('{type} Message!', array('type' => 'Error'));

        // Get available processors.
        if (true === $input->hasParameterOption(array('--concurrency', '-c'))) {
            $this->num_children = intval($input->getParameterOption(array('--concurrency', '-c')));
            if (1 > $this->num_children) {
                $this->num_children = $this->availableProcessors();
            }
        } else {
            $this->num_children = $this->availableProcessors();
        }

//        if (true === $input->hasParameterOption(array('--app', '-A'))) {
//            $app = $input->getParameterOption(array('--app', '-A'));
//            if (!$app || !file_exists("$app.php")) {
//                throw new \RuntimeException(sprintf('No module named "%s"', $app));
//            }
//
//            // Import module
//            include "$app.php";
//            // Add tasks to storage
//            Registry::fetchTasks($this->storage);
//        }

        $this->loop();
    }

    private function loop()
    {
        pcntl_signal(SIGCHLD, array($this, 'sigChild'));

        while (1) {
            pcntl_signal_dispatch();
            if (sizeof($this->children) < $this->num_children) {
                if ($pid = pcntl_fork()) {
                    $this->children[] = $pid;
                } else {
                    while(1);
                    exit(0);
                }
            }
        }
        exit(0);
    }

    private function sigChild($signal)
    {
        pcntl_signal(SIGCHLD, array($this, __FUNCTION__));
        while (($pid = pcntl_wait($status, WNOHANG)) > 0) {
            $this->children = array_diff($this->children, array($pid));
        }
    }

    private function setupEnv(InputInterface $input, OutputInterface $output)
    {
        // Change group id?
        if (true === $input->hasParameterOption(array('--gid'))) {
            $gid = $input->getParameterOption(array('--gid'));
            $gr = is_numeric($gid) ? posix_getgrgid($gid) : posix_getgrnam($gid);

            if (false === $gr) {
                if (is_numeric($gid)) {
                    throw new \RuntimeException(sprintf('gid not found: %d.', $gid));
                } else {
                    throw new \RuntimeException(sprintf('Group does not exist: \'%s\'.', $gid));
                }
            }
        }

        // Change user id?
        if (true === $input->hasParameterOption(array('--uid'))) {
            $uid = $input->getParameterOption(array('--uid'));
            $pw = is_numeric($uid) ? posix_getpwuid(intval($uid)) : posix_getpwnam($uid);

            if (false === $pw) {
                if (is_numeric($uid)) {
                    throw new \RuntimeException(sprintf('uid not found: %d.', $uid));
                } else {
                    throw new \RuntimeException(sprintf('User does not exist: \'%s\'.', $uid));
                }
            }
        }

        // This is root?
        if (
            (isset($pw['uid']) && 0 === $pw['uid']) || (!isset($pw['uid']) && 0 === posix_getuid())
            && !isset($_ENV['BB_ROOT_FORCE'])
        ) {
            $output->writeln("<info>Running a worker with superuser privileges is a very bad idea!</info>\n");
            $output->writeln('<info>If you really want to continue then you have to set the BB_FORCE_ROOT
environment variable (but please think about this before you do).
</info>');
            exit(1);
        }

        $clear_cache = false;
        if (isset($gr['gid']) && posix_getgid() !== $gr['gid']) {
            if (!posix_setgid($gr['gid'])) {
                throw new \RuntimeException("You can not change the group.\nSuperuser privileges are needed.");
            } else {
                $clear_cache = true;
            }
        }

        if (isset($pw['uid']) && posix_getuid() !== $pw['uid']) {
            if (!posix_setuid($pw['uid'])) {
                throw new \RuntimeException("You can not change the user.\nSuperuser privileges are needed.");
            } else {
                $clear_cache = true;
            }
        }

        $clear_cache && clearstatcache(true);

        // Change working dir?
        if (true === $input->hasParameterOption(array('--workdir'))) {
            $workdir = $input->getParameterOption(array('--workdir'));
            if (!is_dir($workdir)) {
                throw new \RuntimeException(sprintf('No such directory: \'%s\'.', $workdir));
            }
            if (!@chdir($workdir)) {
                throw new \RuntimeException(sprintf('Couldn\'t change directory to \'%s\'.', $workdir));
            }

            clearstatcache(true);
        }
    }

    private function detaching(InputInterface $input, OutputInterface $output)
    {
        $r_pid = $this->openPid($input, $output);

        if (pcntl_fork()) {
            $this->closePid($r_pid);
            exit(0);
        }
        posix_setsid();
        if (pcntl_fork()) {
            $this->closePid($r_pid);
            exit(0);
        }

        fwrite($r_pid, getmypid());
        fflush($r_pid);
        $this->closePid($r_pid);
    }

    /**
     * Открывает файловый дескриптор pid-файла.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return resource файловый дескриптор с установленнй эксклюзивной
     *                  блокировкрой на файл.
     */
    private function openPid(InputInterface $input, OutputInterface $output)
    {
        if (true === $input->hasParameterOption('--pid-file')) {
            $pid_file = $input->getParameterOption('--pid-file');
            $dir = dirname($pid_file);

            if (!is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory (%s) not found', $dir));
            }
        } else {
            $pid_file = strtolower(Application::NAME) . 'd.pid';
        }

        if (file_exists($pid_file)) {
            $r_pid = fopen($pid_file, 'r');
            $pid = (int) fgets($r_pid);
            fclose($r_pid);

            if ($pid && posix_kill($pid, 0)) {
                throw new \RuntimeException(
                    sprintf("Pidfile (%s) already exists.\nSeems we're already running? (pid: %d)", $pid_file, $pid)
                );
            }

            $this->errorMessage = null;
            set_error_handler(array($this, 'errorHandler'));
            $is_unlink = @unlink($pid_file);
            restore_error_handler();

            if (!$is_unlink) {
                throw new \RuntimeException(
                    sprintf('Pidfile \'%s\' could not be deleted: %s.', $pid_file, $this->errorMessage)
                );
            }

            $output->writeln('<info>Broken pidfile found. Removing it.</info>');
        }

        $this->errorMessage = null;
        set_error_handler(array($this, 'errorHandler'));
        $r_pid = @fopen($pid_file, 'a');
        restore_error_handler();

        if (!is_resource($r_pid)) {
            throw new \UnexpectedValueException(
                sprintf('Pidfile \'%s\' could not be opened: %s.', $pid_file, $this->errorMessage)
            );
        }

        if (!flock($r_pid, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException(sprintf('Pidfile \'%s\' unlocked.', $pid_file));
        }


        return $r_pid;
    }

    /**
     * Закрывает файловый дескрипотор pid-файла.
     *
     * @param resource $r_pid
     */
    private function closePid($r_pid)
    {
        if (is_resource($r_pid)) {
            flock($r_pid, LOCK_UN);
            fclose($r_pid);
        } else {
            throw new \InvalidArgumentException('Pidfile descriptor is not resource');
        }
    }

    private function availableProcessors()
    {
        $count = (int) `cat /proc/cpuinfo | grep processor | wc -l`;
        return $count ?: 1;
    }
}

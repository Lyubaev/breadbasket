<?php

/**
 * About file: PHP5
 */

namespace Elephant\Breadbasket\Command;

use Elephant\Breadbasket\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommand extends Command
{
    private $storage;

    public function __construct($name = null)
    {
        $this->storage = new \SplObjectStorage();

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('worker')
            ->setDescription('Start worker instance')
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
and the pid is still alive'
            )
            ->addOption(
                'log-level',
                'l',
                InputOption::VALUE_REQUIRED,
                'Logging level, choose between DEBUG, INFO, NOTICE, WARNING,
ERROR, CRITICAL, or EMERGENCY'
            )
            ->addOption(
                'log-file',
                'f',
                InputOption::VALUE_REQUIRED,
                'Path to log file.
If no logfile is specified, stderr is used'
            )
            ->addOption(
                'uid',
                null,
                InputOption::VALUE_REQUIRED,
                'User id, or user name of the user to run as after detaching'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (true === $input->hasParameterOption(array('--detach', '-D'))) {
            $pid_file = $this->getPidfile($input, $output);
            $log_file = $this->getLogfile($input, $output);
            $this->detach($pid_file, $log_file);
        }

        $this->setupEnv($input, $output);

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

        sleep(10);
    }

    private function setupEnv(InputInterface $input, OutputInterface $output)
    {
        if (true === $input->hasParameterOption(array('--uid'))) {
            $uid = $input->getParameterOption(array('--uid'));
            $pw = is_numeric($uid) ? posix_getpwuid(intval($uid)) : posix_getpwnam($uid);

            if (false === $pw) {
                if (is_numeric($uid)) {
                    throw new \RuntimeException(sprintf('uid not found: %d', $uid));
                } else {
                    throw new \RuntimeException(sprintf('User does not exist: %s', $uid));
                }
            }
        } else {
            $uid = posix_getuid();
            $pw = posix_getpwuid($uid);
        }

        if (0 === $pw['uid'] && !isset($_ENV['BB_ROOT_FORCE'])) {
            $output->writeln('<info>Running a worker with superuser privileges is a very bad idea!</info>');
            $output->writeln('');
            $output->writeln('<info>If you really want to continue then you have to set the BB_FORCE_ROOT
environment variable (but please think about this before you do).
</info>');
            exit(1);
        }

        if (!posix_setgid($pw['gid'])) {
            throw new \RuntimeException("You can not change the group.\nSuperuser privileges are needed");
        }

        if (!posix_setuid($pw['uid'])) {
            throw new \RuntimeException("You can not change the user.\nSuperuser privileges are needed");
        }
    }

    private function detach($pid_file)
    {
        if (pcntl_fork()) {
            exit(1);
        }
        posix_setsid();

        $fd = fopen($pid_file, 'w');
        flock($fd, LOCK_EX);
        fwrite($fd, getmypid());
        fflush($fd);
        flock($fd, LOCK_UN);
        fclose($fd);

        // TODO Обработка!
        pcntl_signal(SIGTERM, array($this, 'terminate'));
    }

    private function terminate($signal)
    {
        file_put_contents('fo', $signal);
    }

    private function getPidfile(InputInterface $input, OutputInterface $output)
    {
        $pid_file = 'breadbasket.pid';

        if (true === $input->hasParameterOption('--pid-file')) {
            $pid_file = $input->getParameterOption('--pid-file');
            $dir = dirname($pid_file);

            if (!is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory (%s) not found', $dir));
            }
        }

        if (file_exists($pid_file)) {
            $fd = fopen($pid_file, 'r');
            $pid = (int) fread($fd, 1024);
            fclose($fd);

            if ($pid && posix_kill($pid, 0)) {
                throw new \RuntimeException(
                    sprintf("Pidfile (%s) already exists.\nSeems we're already running? (pid: %d)", $pid_file, $pid)
                );
            }

            if (!@unlink($pid_file)) {
                throw new \RuntimeException(sprintf('Unable to remove the pidfile (%s)', $pid_file));
            }

            $output->writeln('<info>Broken pidfile found. Removing it.</info>');
        }

        return $pid_file;
    }

    private function getLogfile(InputInterface $input, OutputInterface $output)
    {
        $log_file = 'breadbasket.log';

        return $log_file;
    }
}

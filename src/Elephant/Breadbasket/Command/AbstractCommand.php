<?php
namespace Elephant\Breadbasket\Command;

use Elephant\Breadbasket\Application;
use Elephant\Breadbasket\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

abstract class AbstractCommand extends Command
{
    private $logger;
    protected $errorMessage;

    protected function openLog(InputInterface $input, OutputInterface $output)
    {
        // Get logger stream.
        if (true === $input->hasParameterOption(array('--log-file', '-f'))) {
            $stream = $input->getParameterOption(array('--log-file', '-f'));
            $stream = new StreamOutput(fopen($stream, 'a', false));
        } elseif (true === $input->hasParameterOption(array('--detach', '-D'))) {
            $stream = strtolower(Application::NAME) . '.log';
            $stream = new StreamOutput(fopen($stream, 'a', false));

            fclose(STDIN);
            fclose(STDOUT);
            fclose(STDERR);
        } else {
            if ($output instanceof ConsoleOutput) {
                $stream = $output->getErrorOutput();
            } else {
                $stream = new StreamOutput(fopen('php://stderr', 'w', false));
            }
        }

        // Get log level.
        if (true === $input->hasParameterOption(array('--log-level', '-l'))) {
            $level = $input->getParameterOption(array('--log-level', '-l'));
        } else {
            $level = Logger::WARNING;
        }

        $this->logger = new Logger(Application::NAME, $stream, $level);
    }

    protected function closeLog()
    {
        unset($this->logger);
    }

    /**
     * @return Logger
     */
    protected function log()
    {
        return $this->logger;
    }

    protected function errorHandler($code, $message)
    {
        if (false !== strpos($message, 'fopen')
            || false !== strpos($message, 'unlink')
        ) {
            $this->errorMessage = preg_replace('/^(fopen|unlink)\(.*?\): /', '', $message);
        }
    }
}

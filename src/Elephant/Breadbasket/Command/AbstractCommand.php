<?php
namespace Elephant\Breadbasket\Command;

use Elephant\Breadbasket\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

abstract class AbstractCommand extends Command
{
    private $logger;
    protected $errorMessage;

    protected function openLog(InputInterface $input)
    {
        // Get logger stream.
        if (true === $input->hasParameterOption(array('--log-file', '-f'))) {
            $stream = $input->getParameterOption(array('--log-file', '-f'));
        } elseif (true === $input->hasParameterOption(array('--detach', '-D'))) {
            $stream = 'breadbasket.log';

            fclose(STDIN);
            fclose(STDOUT);
            fclose(STDERR);
        } else {
            $stream = 'php://stderr';
        }

        // Get log level.
        if (true === $input->hasParameterOption(array('--log-level', '-l'))) {
            $level = $input->getParameterOption(array('--log-level', '-l'));
        } else {
            $level = Logger::ERROR;
        }

        $this->logger = new Logger($stream, $level);
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

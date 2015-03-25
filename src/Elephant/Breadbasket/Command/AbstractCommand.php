<?php
namespace Elephant\Breadbasket\Command;

use Elephant\Breadbasket\Application;
use Elephant\Breadbasket\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Class AbstractCommand
 *
 * @method null emergency($message, array $context = array())
 * @method null alert($message, array $context = array())
 * @method null critical($message, array $context = array())
 * @method null error($message, array $context = array())
 * @method null warning($message, array $context = array())
 * @method null notice($message, array $context = array())
 * @method null info($message, array $context = array())
 * @method null debug($message, array $context = array())
 * @method null log($level, $message, array $context = array())
 *
 * @package Elephant\Breadbasket\Command
 */
abstract class AbstractCommand extends Command
{
    private $logger;
    protected $errorMessage;

    /**
     * Override logger methods.
     *
     * @param $name
     * @param $args
     *
     * @return mixed
     */
    public function __call($name, $args)
    {
        switch ($name) {
            case 'emergency':
            case 'alert':
            case 'critical':
            case 'error':
            case 'warning':
            case 'notice':
            case 'info':
            case 'debug':
            case 'log':
                return call_user_func_array(array($this->logger, $name), $args);
            default:
                throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s.', __CLASS__, $name));
        }
    }

    protected function openLog(InputInterface $input, OutputInterface $output)
    {
        if (true === $input->hasParameterOption(array('--detach', '-D'))) {
            fclose(STDIN);
            fclose(STDOUT);
            fclose(STDERR);
        }

        if (true === $input->hasParameterOption(array('--log-file', '-f'))) {
            $stream = $input->getParameterOption(array('--log-file', '-f'));
            $stream = new StreamOutput(fopen($stream, 'a', false));
        } elseif (true === $input->hasParameterOption(array('--detach', '-D'))) {
            $stream = strtolower(Application::NAME) . '.log';
            $stream = new StreamOutput(fopen($stream, 'a', false));
        } else {
            if ($output instanceof ConsoleOutput) {
                $stream = $output->getErrorOutput();
            } else {
                $stream = new StreamOutput(STDERR);
            }
        }

        $this->logger = new Logger($stream);

        // Get log level.
        if (true === $input->hasParameterOption(array('--log-level', '-l'))) {
            $this->logger->setLevel($input->getParameterOption(array('--log-level', '-l')));
        }
    }

    protected function closeLog()
    {
        unset($this->logger);
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

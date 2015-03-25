<?php
namespace Elephant\Breadbasket;

use Psr\Log\LoggerInterface;
use Psr\Log\InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class Logger implements LoggerInterface
{
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    private $stream;
    private $level;
    private $levels = array(
        'EMERGENCY' => 0,
        'ALERT'     => 1,
        'CRITICAL'  => 2,
        'ERROR'     => 3,
        'WARNING'   => 4,
        'NOTICE'    => 5,
        'INFO'      => 6,
        'DEBUG'     => 7,
    );

    public function __construct(OutputInterface $stream, $level = self::NOTICE)
    {
        $stream->getFormatter()->setStyle('debug',     new OutputFormatterStyle('white', null));
        $stream->getFormatter()->setStyle('info',      new OutputFormatterStyle('green', null));
        $stream->getFormatter()->setStyle('notice',    new OutputFormatterStyle('cyan', null));
        $stream->getFormatter()->setStyle('warning',   new OutputFormatterStyle('yellow', null));
        $stream->getFormatter()->setStyle('error',     new OutputFormatterStyle('red', null));
        $stream->getFormatter()->setStyle('critical',  new OutputFormatterStyle('red', null));
        $stream->getFormatter()->setStyle('alert',     new OutputFormatterStyle('red', null));
        $stream->getFormatter()->setStyle('emergency', new OutputFormatterStyle('red', null));

        $this->stream = $stream;
        $this->setLevel($level);
    }

    public function __destruct()
    {
        if ($this->stream instanceof StreamOutput) {
            fclose($this->stream->getStream());
        }
        $this->stream = null;
    }

    public function setLevel($level)
    {
        $this->level = $level;
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function emergency($message, array $context = array())
    {
        return $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function alert($message, array $context = array())
    {
        return $this->log(self::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function critical($message, array $context = array())
    {
        return $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function error($message, array $context = array())
    {
        return $this->log(self::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function warning($message, array $context = array())
    {
        return $this->log(self::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function notice($message, array $context = array())
    {
        return $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function info($message, array $context = array())
    {
        return $this->log(self::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function debug($message, array $context = array())
    {
        return $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return null
     */
    public function log($level, $message, array $context = array())
    {
        if (!isset($this->levels[strtoupper($level)])) {
            throw new InvalidArgumentException(sprintf('The log level "%s" does not exist.', $level));
        }

        if ($this->levels[strtoupper($level)] > $this->levels[strtoupper($this->level)]) {
            return false;
        }

        $timezone = new \DateTimeZone(@date_default_timezone_get());
        $date = \DateTime::createFromFormat('U.u', microtime(true), $timezone)->setTimezone($timezone);

        $this->stream->writeln(sprintf('<%1$s>[%2$s] %3$s</%1$s>', $level, $date->format('c'), $this->interpolate($message, $context)));
    }

    /**
     * Interpolates context values into the message placeholders
     *
     * @author PHP Framework Interoperability Group
     *
     * @param string $message
     * @param array  $context
     *
     * @return string
     */
    private function interpolate($message, array $context)
    {
        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace[sprintf('{%s}', $key)] = $val;
            }
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}

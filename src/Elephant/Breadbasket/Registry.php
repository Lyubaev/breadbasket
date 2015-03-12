<?php
namespace Elephant\Breadbasket;

class Registry
{
    private static $instance;
    private $tasks;
    private $functions;

    private function __construct()
    {
        $this->tasks = new \SplQueue();
        $this->functions = new \SplQueue();
    }

    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function fetchTasks(\SplObjectStorage $storage)
    {
        $storage->attach(self::instance()->tasks, 'task');
    }

    public static function fetchFunctions(\SplObjectStorage $storage)
    {
        $storage->attach(self::instance()->functions, 'functions');
    }

    /**
     * Добавить задачу для асинхронного выполнения.
     *
     * @param callable $task задача для выполнения.
     */
    public static function addTask($task)
    {
        if (!is_callable($task)) {
            throw new \RuntimeException('Argument #1 must be callable');
        }

        self::instance()->tasks[] = $task;
    }

    public static function addFunction($function)
    {
        if (!is_callable($function)) {
            throw new \RuntimeException('Argument #1 must be callable');
        }

        self::instance()->functions[] = $function;
    }
}

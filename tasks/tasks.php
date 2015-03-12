<?php

use Elephant\Breadbasket\Registry;

Registry::addTask(function () {
    echo '[x] Task #1';
});

Registry::addTask(function () {
    echo '[x] Task #2';
});

Registry::addFunction(function () {
    echo '[x] Function #1';
});

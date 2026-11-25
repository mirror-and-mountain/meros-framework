<?php

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!class_exists(MM\Meros\Foundations\Testing\Bootstrap::class) && is_file($autoload)) {
    require_once $autoload;
}

if (!class_exists(MM\Meros\Foundations\Testing\Bootstrap::class)) {
    throw new RuntimeException('Install development dependencies before running tests.');
}

MM\Meros\Foundations\Testing\Bootstrap::load(dirname(__DIR__));

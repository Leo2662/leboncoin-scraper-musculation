<?php

// Test autoload
require_once __DIR__ . '/../vendor/autoload.php';

echo "Autoload loaded successfully\n";

// Test Monolog
try {
    $logger = new \Monolog\Logger('test');
    echo "Monolog Logger class found\n";
} catch (Error $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Test StreamHandler
try {
    $handler = new \Monolog\Handlers\StreamHandler('php://stdout');
    echo "Monolog StreamHandler class found\n";
} catch (Error $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "All tests passed!\n";

<?php

namespace App\Cli;

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application("PHP Symfony CLI", "v0.0.1");

$application->add(new EchoNameCommand());

try {
    $application->run();
} catch (\Exception $e) {}

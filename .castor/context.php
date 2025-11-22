<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\context;
use function configuration\ensureConfiguration;


#[AsContext(default: true)]
function getContext(): Context
{
    // Ensure configuration exists and load it
    // If the config file doesn't exist, it will be created interactively
    $config = ensureConfiguration();

    return new Context(environment: [
        'PROJECT_NAME' => $config['PROJECT_NAME'] ?? '',
        'APP_USER' => $config['APP_USER'] ?? 'symfony',
        'DATABASE_USERNAME' => $config['DATABASE_USERNAME'] ?? '',
        'DATABASE_PASSWORD' => $config['DATABASE_PASSWORD'] ?? '',
        'DATABASE_NAME' => $config['DATABASE_NAME'] ?? '',
        'ENV' => $config['ENV'] ?? 'dev',
    ]);
}

#[AsTask(description: 'Display default context', aliases: ['dc'])]
function displayDefaultContext(): void
{
    $context = context();
    dump($context);
}


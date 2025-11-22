<?php

namespace critical;


use Castor\Attribute\AsTask;
use function Castor\io;
use function Castor\run;
use function configuration\ensureConfiguration;

#[AsTask(description: 'Reinitialize the Symfony template by removing project files')]
function reinit(): void
{
    io()->title('Reinitializing Symfony template...');

    $itemsToRemove = [
        'bin',
        'config',
        'migrations',
        'public',
        'tmp',
        'src',
        'var',
        'vendor',
        '.env*',
        'composer.*',
        'compose.override.yaml',
        'symfony.lock',
        '.castor/configuration/config.php',
    ];

    foreach ($itemsToRemove as $item) {
        io()->writeln(sprintf('Removing: %s', $item));
        run(sprintf('rm -rf %s', $item));
    }

    io()->success('Template reinitialized successfully!');
}



#[AsTask(description: 'Configure or reconfigure the project settings')]
function config(): void
{
    $configFile = __DIR__ . '/.castor/config.php';

    // If config exists, ask for confirmation to reconfigure
    if (file_exists($configFile)) {
        if (!io()->confirm('La configuration existe déjà. Voulez-vous la reconfigurer ?', false)) {
            io()->info('Configuration annulée.');
            return;
        }

        // Remove existing config
        unlink($configFile);
        io()->writeln('Configuration existante supprimée.');
        io()->newLine();
    }

    // Create new configuration
    ensureConfiguration();
}

<?php

namespace configuration;

use function Castor\io;

/**
 * Ensures that the configuration file exists and is properly set up.
 * If the configuration file doesn't exist, prompts the user for values
 * and creates the file.
 *
 * @return array The configuration array
 */
function ensureConfiguration(): array
{
    $configFile = __DIR__ . '/config.php';
    $configDistFile = __DIR__ . '/config.php.dist';

    // If config file exists, load and return it
    if (file_exists($configFile)) {
        return require $configFile;
    }

    // Config file doesn't exist - we need to create it
    io()->title('Configuration du projet');
    io()->writeln('Le fichier de configuration n\'existe pas encore.');
    io()->writeln('Veuillez répondre aux questions suivantes pour configurer votre projet.');
    io()->newLine();

    // Load default values from the dist file
    $defaults = file_exists($configDistFile) ? require $configDistFile : [];

    // Ask for each configuration value
    $config = [];

    $config['PROJECT_NAME'] = io()->ask(
        'Nom du projet',
        $defaults['PROJECT_NAME'] ?? 'my-project'
    );

    $config['APP_USER'] = io()->ask(
        'Nom de l\'utilisateur système dans le container (APP_USER)',
        $defaults['APP_USER'] ?? 'symfony'
    );

    $config['DATABASE_USERNAME'] = io()->ask(
        'Nom d\'utilisateur de la base de données',
        $defaults['DATABASE_USERNAME'] ?? 'root'
    );

    $config['DATABASE_PASSWORD'] = io()->askHidden(
        'Mot de passe de la base de données'
    ) ?: ($defaults['DATABASE_PASSWORD'] ?? 'secret');

    $config['DATABASE_NAME'] = io()->ask(
        'Nom de la base de données Sylius',
        $defaults['DATABASE_NAME'] ?? 'sylius_db'
    );

    $config['ENV'] = io()->choice(
        'Environnement',
        ['dev', 'prod', 'test'],
        $defaults['ENV'] ?? 'dev'
    );

    // Save configuration to file
    $configContent = "<?php\n\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($configFile, $configContent);

    io()->newLine();
    io()->success('Configuration sauvegardée dans .castor/config.php');

    return $config;
}

/**
 * Loads the configuration from the config file.
 * Returns an empty array if the file doesn't exist.
 *
 * @return array The configuration array
 */
function loadConfiguration(): array
{
    $configFile = __DIR__ . '/config.php';

    if (!file_exists($configFile)) {
        return [];
    }

    return require $configFile;
}

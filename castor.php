<?php
use Castor\Attribute\AsTask;

use function Castor\import;
use function Castor\run;
use function Castor\io;
use function Castor\context;
use function docker\checkDockerLogin;

import(__DIR__ . '/.castor');


#[AsTask]
function up(): void
{
    io()->writeln('Building images...');
    run('docker compose build');

    io()->writeln('Starting containers...');
    run('docker compose up');
}

#[AsTask]
function build(): void
{
    run('docker compose build');
}

#[AsTask]
function down(): void
{
    run('docker compose down');
}

#[AsTask(description: 'Build and push Docker image with version tagging')]
function push(): void
{
    // Check Docker login status
    if (!checkDockerLogin()) {
        return;
    }

    $versionFile = __DIR__ . '/.version';
    $ctx = context();
    $projectName = $ctx->environment['PROJECT_NAME'] ?? null;
    $dockerHubUsername = $ctx->environment['DOCKER_HUB_USERNAME'];

    if (!$projectName) {
        io()->error('PROJECT_NAME not found in context. Please configure it.');
        return;
    }

    // Read last saved version
    $lastVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'none';
    io()->writeln(sprintf('Last version: <info>%s</info>', $lastVersion));

    // Ask for new version
    $newVersion = io()->ask('Enter the new version to push', $lastVersion !== 'none' ? $lastVersion : '1.0.0');

    if (empty($newVersion)) {
        io()->error('Version cannot be empty.');
        return;
    }

    io()->writeln(sprintf('Building and pushing version: <info>%s</info>', $newVersion));

    // Build the Docker image
    io()->writeln('Building Docker image...');
    $imageName = "{$dockerHubUsername}/{$projectName}:latest";
    run("docker build -f Dockerfile --target app_prod -t {$imageName} .");

    // Tag the image with version
    io()->writeln(sprintf('Tagging image as %s/%s:%s', $dockerHubUsername, $projectName, $newVersion));
    run(sprintf('docker tag %s/%s:latest %s/%s:%s', $dockerHubUsername, $projectName, $dockerHubUsername, $projectName, $newVersion));

    // Push versioned tag
    io()->writeln(sprintf('Pushing %s/%s:%s', $dockerHubUsername, $projectName, $newVersion));
    run(sprintf('docker push %s/%s:%s', $dockerHubUsername, $projectName, $newVersion));

    // Push latest tag
    io()->writeln(sprintf('Pushing %s/%s:latest', $dockerHubUsername, $projectName));
    run(sprintf('docker push %s/%s:latest', $dockerHubUsername, $projectName));

    // Save the new version
    file_put_contents($versionFile, $newVersion);
    io()->success(sprintf('Successfully pushed version %s and saved to %s', $newVersion, $versionFile));
}



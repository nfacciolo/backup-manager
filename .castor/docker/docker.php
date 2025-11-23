<?php

namespace docker;

use function Castor\io;
use function Castor\capture;
use function Castor\context;

function checkDockerLogin(): bool
{
    io()->writeln('Checking Docker Hub authentication...');

    $ctx = context();
    $expectedUsername = $ctx->environment['DOCKER_HUB_USERNAME'] ?? null;

    if (empty($expectedUsername)) {
        io()->error('DOCKER_HUB_USERNAME is not configured.');
        io()->writeln('Please set DOCKER_HUB_USERNAME in your project configuration (.castor/configuration/config.php)');
        return false;
    }

    try {
        // Execute docker system info and capture output
        $output = capture('docker system info 2>/dev/null | grep -E "Username|Registry"', onFailure: '');

        // Check if the output contains the expected username
        if (str_contains($output, "Username: {$expectedUsername}")) {
            io()->writeln('<info>✓</info> Docker Hub authentication verified');
            return true;
        }

        io()->error("You are not logged in to Docker Hub as {$expectedUsername}.");
        io()->writeln('Please run: <comment>docker login</comment>');
        return false;

    } catch (\Exception $e) {
        io()->error('Failed to check Docker authentication.');
        io()->writeln('Please run: <comment>docker login</comment>');
        return false;
    }
}

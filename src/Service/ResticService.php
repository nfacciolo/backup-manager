<?php

namespace App\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ResticService
{
    private const RESTIC_BINARY = 'restic';

    /**
     * Initialize a new restic repository.
     */
    public function init(string $repository, string $passwordFile, ?string $cacheDir = null): bool
    {
        $process = $this->createProcess(
            ['init'],
            $repository,
            $passwordFile,
            $cacheDir
        );

        $process->run();

        // Repository already initialized is not an error
        if ($process->getExitCode() === 0 || str_contains($process->getOutput(), 'already initialized')) {
            return true;
        }

        throw new ProcessFailedException($process);
    }

    /**
     * Check if repository exists and is accessible.
     */
    public function repositoryExists(string $repository, string $passwordFile, ?string $cacheDir = null): bool
    {
        try {
            $process = $this->createProcess(
                ['snapshots', '--json'],
                $repository,
                $passwordFile,
                $cacheDir
            );

            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Perform a backup.
     *
     * @param array<string, mixed> $options Additional options (tag, exclude, etc.)
     * @return array{files_new: int, files_changed: int, files_unmodified: int, bytes_added: int, bytes_processed: int, snapshot_id: string}
     */
    public function backup(
        string $repository,
        string $source,
        string $passwordFile,
        array $options = [],
        ?string $cacheDir = null
    ): array {
        $args = ['backup', $source, '--json'];

        if (isset($options['tag'])) {
            $args[] = '--tag';
            $args[] = $options['tag'];
        }

        if (isset($options['exclude']) && is_array($options['exclude'])) {
            foreach ($options['exclude'] as $pattern) {
                $args[] = '--exclude';
                $args[] = $pattern;
            }
        }

        if (isset($options['exclude_file'])) {
            $args[] = '--exclude-file';
            $args[] = $options['exclude_file'];
        }

        $process = $this->createProcess($args, $repository, $passwordFile, $cacheDir);
        $process->setTimeout(null); // No timeout for backup
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $this->parseBackupOutput($process->getOutput());
    }

    /**
     * Apply retention policy and prune old snapshots.
     *
     * @param array<string, int> $policy Retention policy (keep_daily, keep_weekly, keep_monthly, etc.)
     */
    public function forget(
        string $repository,
        string $passwordFile,
        array $policy,
        ?string $tag = null,
        ?string $cacheDir = null
    ): bool {
        $args = ['forget', '--prune'];

        if (isset($policy['keep_last'])) {
            $args[] = '--keep-last';
            $args[] = (string) $policy['keep_last'];
        }

        if (isset($policy['keep_hourly'])) {
            $args[] = '--keep-hourly';
            $args[] = (string) $policy['keep_hourly'];
        }

        if (isset($policy['keep_daily'])) {
            $args[] = '--keep-daily';
            $args[] = (string) $policy['keep_daily'];
        }

        if (isset($policy['keep_weekly'])) {
            $args[] = '--keep-weekly';
            $args[] = (string) $policy['keep_weekly'];
        }

        if (isset($policy['keep_monthly'])) {
            $args[] = '--keep-monthly';
            $args[] = (string) $policy['keep_monthly'];
        }

        if (isset($policy['keep_yearly'])) {
            $args[] = '--keep-yearly';
            $args[] = (string) $policy['keep_yearly'];
        }

        if ($tag !== null) {
            $args[] = '--tag';
            $args[] = $tag;
        }

        $process = $this->createProcess($args, $repository, $passwordFile, $cacheDir);
        $process->setTimeout(null); // No timeout for prune
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return true;
    }

    /**
     * Get repository statistics.
     *
     * @return array{total_size: int, total_file_count: int}
     */
    public function stats(
        string $repository,
        string $passwordFile,
        ?string $cacheDir = null,
        string $mode = 'raw-data'
    ): array {
        $process = $this->createProcess(
            ['stats', '--mode', $mode, '--json'],
            $repository,
            $passwordFile,
            $cacheDir
        );

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);

        return [
            'total_size' => $data['total_size'] ?? 0,
            'total_file_count' => $data['total_file_count'] ?? 0,
        ];
    }

    /**
     * Restore a snapshot.
     */
    public function restore(
        string $repository,
        string $passwordFile,
        string $target,
        ?string $snapshotId = 'latest',
        ?string $tag = null,
        ?string $cacheDir = null
    ): bool {
        $args = ['restore', $snapshotId, '--target', $target];

        if ($tag !== null) {
            $args[] = '--tag';
            $args[] = $tag;
        }

        $process = $this->createProcess($args, $repository, $passwordFile, $cacheDir);
        $process->setTimeout(null); // No timeout for restore
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return true;
    }

    /**
     * List all snapshots.
     *
     * @return array<int, array{id: string, time: string, hostname: string, username: string, paths: array<string>, tags: array<string>}>
     */
    public function snapshots(
        string $repository,
        string $passwordFile,
        ?string $cacheDir = null
    ): array {
        $process = $this->createProcess(
            ['snapshots', '--json'],
            $repository,
            $passwordFile,
            $cacheDir
        );

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();
        $snapshots = json_decode($output, true);

        return $snapshots ?? [];
    }

    /**
     * Create a Process instance with proper environment variables.
     *
     * @param array<int, string> $args
     */
    private function createProcess(
        array $args,
        string $repository,
        string $passwordFile,
        ?string $cacheDir = null
    ): Process {
        $command = array_merge([self::RESTIC_BINARY], $args);

        $env = [
            'RESTIC_REPOSITORY' => $repository,
            'RESTIC_PASSWORD_FILE' => $passwordFile,
        ];

        if ($cacheDir !== null) {
            $env['RESTIC_CACHE_DIR'] = $cacheDir;
        }

        $process = new Process($command, null, $env);
        $process->setTimeout(3600); // Default 1h timeout

        return $process;
    }

    /**
     * Parse backup JSON output to extract summary.
     *
     * @return array{files_new: int, files_changed: int, files_unmodified: int, bytes_added: int, bytes_processed: int, snapshot_id: string}
     */
    private function parseBackupOutput(string $output): array
    {
        $lines = explode("\n", trim($output));
        $summary = null;
        $snapshotId = null;

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $data = json_decode($line, true);
            if (!$data) {
                continue;
            }

            if (isset($data['message_type'])) {
                if ($data['message_type'] === 'summary') {
                    $summary = $data;
                } elseif ($data['message_type'] === 'snapshot' && isset($data['snapshot_id'])) {
                    $snapshotId = $data['snapshot_id'];
                }
            }
        }

        if (!$summary) {
            return [
                'files_new' => 0,
                'files_changed' => 0,
                'files_unmodified' => 0,
                'bytes_added' => 0,
                'bytes_processed' => 0,
                'snapshot_id' => $snapshotId ?? 'unknown',
            ];
        }

        return [
            'files_new' => $summary['files_new'] ?? 0,
            'files_changed' => $summary['files_changed'] ?? 0,
            'files_unmodified' => $summary['files_unmodified'] ?? 0,
            'bytes_added' => $summary['data_added'] ?? 0,
            'bytes_processed' => $summary['total_bytes_processed'] ?? 0,
            'snapshot_id' => $snapshotId ?? ($summary['snapshot_id'] ?? 'unknown'),
        ];
    }
}

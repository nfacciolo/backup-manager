<?php

namespace App\Command\Backup;

use App\Service\ResticService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backup:run',
    description: 'Run a restic backup with retention policy',
)]
class BackupRunCommand extends Command
{
    public function __construct(
        private readonly ResticService $resticService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('website', null, InputOption::VALUE_REQUIRED, 'Website/client identifier (e.g., digitalberry_fr)')
            ->addOption('source', null, InputOption::VALUE_OPTIONAL, 'Source directory to backup')
            ->addOption('password-file', null, InputOption::VALUE_OPTIONAL, 'File containing restic password', getenv('RESTIC_PASSWORD_FILE'))
            ->addOption('repository', null, InputOption::VALUE_OPTIONAL, 'Repository path (default: /backup-restic/{website})')
            ->addOption('cache-dir', null, InputOption::VALUE_OPTIONAL, 'Cache directory', '/var/cache/restic')
            ->addOption('tag', null, InputOption::VALUE_OPTIONAL, 'Backup tag', 'wordpress')
            ->addOption('keep-daily', null, InputOption::VALUE_OPTIONAL, 'Keep N daily snapshots', 7)
            ->addOption('keep-weekly', null, InputOption::VALUE_OPTIONAL, 'Keep N weekly snapshots', 4)
            ->addOption('keep-monthly', null, InputOption::VALUE_OPTIONAL, 'Keep N monthly snapshots', 6)
            ->addOption('restore', null, InputOption::VALUE_NONE, 'Restore latest backup after creating it')
            ->addOption('restore-target', null, InputOption::VALUE_OPTIONAL, 'Restore destination')
            ->addOption('force-init', null, InputOption::VALUE_NONE, 'Force repository initialization')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get and validate required parameters
        $website = $input->getOption('website');
        $source = $input->getOption('source');
        $passwordFile = $input->getOption('password-file');

        if (!$website) {
            $io->error('Missing required parameter: --website');
            return Command::FAILURE;
        }

        if (!$source) {
            $io->error('Missing required parameter: --source');
            return Command::FAILURE;
        }

        if (!$passwordFile) {
            $io->error('Missing required parameter: --password-file');
            return Command::FAILURE;
        }

        // Set defaults that depend on required parameters
        $repository = $input->getOption('repository') ?? sprintf('%s/backup-restic/%s', $_SERVER['HOME'] ?? '/root', $website);
        $cacheDir = $input->getOption('cache-dir');
        $tag = $input->getOption('tag');
        $keepDaily = (int) $input->getOption('keep-daily');
        $keepWeekly = (int) $input->getOption('keep-weekly');
        $keepMonthly = (int) $input->getOption('keep-monthly');
        $doRestore = $input->getOption('restore');
        $restoreTarget = $input->getOption('restore-target') ?? sprintf('./restore-%s', $website);
        $forceInit = $input->getOption('force-init');

        // Validate password file exists
        if (!file_exists($passwordFile)) {
            $io->error(sprintf('Password file does not exist: %s', $passwordFile));
            return Command::FAILURE;
        }

        // Validate source directory exists
        if (!is_dir($source)) {
            $io->error(sprintf('Source directory does not exist: %s', $source));
            return Command::FAILURE;
        }

        // Check password file permissions
        $perms = substr(sprintf('%o', fileperms($passwordFile)), -3);
        if ($perms !== '600' && $perms !== '400') {
            $io->warning(sprintf('Password file has insecure permissions: %s (should be 600 or 400)', $perms));
        }

        // Display configuration
        $io->title('Restic Backup Configuration');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['Website', $website],
                ['Source', $source],
                ['Repository', $repository],
                ['Cache Dir', $cacheDir],
                ['Tag', $tag],
                ['Retention', sprintf('daily=%d, weekly=%d, monthly=%d', $keepDaily, $keepWeekly, $keepMonthly)],
                ['Restore', $doRestore ? 'Yes' : 'No'],
                ['Restore Target', $doRestore ? $restoreTarget : 'N/A'],
            ]
        );

        try {
            // Initialize repository if needed
            if ($forceInit || !$this->resticService->repositoryExists($repository, $passwordFile, $cacheDir)) {
                $io->section('Initializing repository');
                $this->resticService->init($repository, $passwordFile, $cacheDir);
                $io->success('Repository initialized');
            } else {
                $io->info('Repository already exists, skipping initialization');
            }

            // Perform backup
            $io->section(sprintf('Starting backup of %s', $source));
            $io->text(sprintf('[%s] Starting backup...', date('Y-m-d H:i:s')));

            $result = $this->resticService->backup(
                $repository,
                $source,
                $passwordFile,
                ['tag' => $tag],
                $cacheDir
            );

            $io->success('Backup completed successfully');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Files New', number_format($result['files_new'])],
                    ['Files Changed', number_format($result['files_changed'])],
                    ['Files Unmodified', number_format($result['files_unmodified'])],
                    ['Bytes Added', $this->formatBytes($result['bytes_added'])],
                    ['Bytes Processed', $this->formatBytes($result['bytes_processed'])],
                    ['Snapshot ID', $result['snapshot_id']],
                ]
            );

            // Apply retention policy
            $io->section('Applying retention policy');
            $this->resticService->forget(
                $repository,
                $passwordFile,
                [
                    'keep_daily' => $keepDaily,
                    'keep_weekly' => $keepWeekly,
                    'keep_monthly' => $keepMonthly,
                ],
                $tag,
                $cacheDir
            );
            $io->success('Retention policy applied');

            // Display repository statistics
            $io->section('Repository statistics');
            try {
                $stats = $this->resticService->stats($repository, $passwordFile, $cacheDir);
                $io->table(
                    ['Metric', 'Value'],
                    [
                        ['Total Size', $this->formatBytes($stats['total_size'])],
                        ['Total Files', number_format($stats['total_file_count'])],
                    ]
                );
            } catch (\Exception $e) {
                $io->warning('Could not retrieve stats');
            }

            // Restore if requested
            if ($doRestore) {
                $io->section(sprintf('Restoring latest backup to %s', $restoreTarget));
                $this->resticService->restore(
                    $repository,
                    $passwordFile,
                    $restoreTarget,
                    'latest',
                    $tag,
                    $cacheDir
                );
                $io->success('Restore completed successfully');
            }

            $io->success(sprintf('All operations completed successfully for %s', $website));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Error: %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class MigrateToMariaDb extends Command
{
    protected $signature = 'app:migrate-to-mariadb
        {--source=sqlite : Source connection name}
        {--target=mariadb : Target connection name}
        {--chunk=500 : Rows per insert batch}';

    protected $description = 'Copy all application data from the SQLite database to MariaDB, preserving IDs.';

    /**
     * Tables ordered so that foreign key parents are copied before children.
     * Volatile tables (sessions, cache, jobs) are intentionally excluded.
     *
     * @var list<string>
     */
    private const TABLES = [
        'users',
        'setup_items',
        'astrophotos',
        'media',
        'settings',
        'site_settings',
        'newsletter_subscribers',
        'password_reset_tokens',
        'failed_jobs',
    ];

    public function handle(): int
    {
        $source = DB::connection($this->option('source'));
        $target = DB::connection($this->option('target'));
        $chunkSize = (int) $this->option('chunk');

        $this->components->info(sprintf(
            'Copying data from [%s] to [%s].',
            $source->getName(),
            $target->getName(),
        ));

        if (! $this->confirmTargetIsEmpty($target)) {
            return self::FAILURE;
        }

        $target->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::TABLES as $table) {
                $this->copyTable($source, $target, $table, $chunkSize);
            }
        } finally {
            $target->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $this->verifyCounts($source, $target) ? self::SUCCESS : self::FAILURE;
    }

    private function confirmTargetIsEmpty(Connection $target): bool
    {
        $nonEmpty = collect(self::TABLES)
            ->filter(fn (string $table) => $target->table($table)->exists())
            ->values();

        if ($nonEmpty->isEmpty()) {
            return true;
        }

        $this->components->warn('Target tables already contain data: '.$nonEmpty->implode(', '));

        return $this->confirm('Continue anyway? Existing rows may cause duplicate key errors.');
    }

    private function copyTable(
        Connection $source,
        Connection $target,
        string $table,
        int $chunkSize,
    ): void {
        $copied = 0;

        $source->table($table)
            ->orderBy($this->orderColumn($source, $table))
            ->chunk($chunkSize, function ($rows) use ($target, $table, &$copied) {
                $target->table($table)->insert(
                    $rows->map(fn ($row) => (array) $row)->all(),
                );
                $copied += $rows->count();
            });

        $this->components->twoColumnDetail($table, sprintf('%d rows', $copied));
    }

    private function orderColumn(Connection $source, string $table): string
    {
        return $source->getSchemaBuilder()->hasColumn($table, 'id') ? 'id' : 'email';
    }

    private function verifyCounts(
        Connection $source,
        Connection $target,
    ): bool {
        $this->components->info('Verifying row counts…');

        $allMatch = true;

        foreach (self::TABLES as $table) {
            $sourceCount = $source->table($table)->count();
            $targetCount = $target->table($table)->count();

            if ($sourceCount === $targetCount) {
                $this->components->twoColumnDetail($table, sprintf('<info>OK</info> (%d)', $targetCount));
            } else {
                $allMatch = false;
                $this->components->twoColumnDetail(
                    $table,
                    sprintf('<error>MISMATCH</error> source=%d target=%d', $sourceCount, $targetCount),
                );
            }
        }

        if ($allMatch) {
            $this->components->info('All row counts match. Point DB_CONNECTION to mariadb when ready.');
        }

        return $allMatch;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * پشتیبان روزانهٔ پایگاه دادهٔ Edge (جلسه‌ها، کش کاربران، صف سینک) روی دیسک Mini PC.
 * بازیابی MySQL: gunzip -c <file>.sql.gz | mysql -u<user> -p <database>
 */
class BackupDatabase extends Command
{
    protected $signature = 'edge:backup {--keep=14 : Number of backups to keep}';

    protected $description = 'Back up the edge database to storage/app/backups (gzip, rotating)';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $connection = config('database.default');
        $cfg = config("database.connections.{$connection}");
        $stamp = now()->format('Ymd-His');

        if (($cfg['driver'] ?? '') === 'sqlite') {
            $source = (string) $cfg['database'];
            if (! is_file($source)) {
                $this->error('SQLite database file not found.');

                return self::FAILURE;
            }
            $target = "{$dir}/edge-{$stamp}.sqlite.gz";
            file_put_contents($target, gzencode((string) file_get_contents($source), 6));
        } elseif (in_array($cfg['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            $target = "{$dir}/edge-{$stamp}.sql.gz";
            $dump = new Process([
                'mysqldump', '--single-transaction', '--quick', '--routines',
                '-h', (string) $cfg['host'], '-P', (string) $cfg['port'], '-u', (string) $cfg['username'], (string) $cfg['database'],
            ], null, ['MYSQL_PWD' => (string) $cfg['password']], null, 600);
            $dump->run();
            if (! $dump->isSuccessful()) {
                $this->error('mysqldump failed: '.trim($dump->getErrorOutput()));

                return self::FAILURE;
            }
            file_put_contents($target, gzencode($dump->getOutput(), 6));
        } else {
            $this->error('Unsupported database driver for backup.');

            return self::FAILURE;
        }

        $files = collect(File::glob("{$dir}/edge-*.gz"))->sort()->values();
        $keep = max(1, (int) $this->option('keep'));
        $files->slice(0, max(0, $files->count() - $keep))->each(fn ($f) => File::delete($f));

        $this->info('Backup written: '.basename($target).' ('.round(filesize($target) / 1024, 1).' KB)');

        return self::SUCCESS;
    }
}

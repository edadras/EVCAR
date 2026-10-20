<?php

namespace App\Console\Commands;

use App\Models\SyncCursor;
use App\Services\Sync\CloudPullService;
use Illuminate\Console\Command;

class PullCloudSnapshot extends Command
{
    protected $signature = 'sync:pull-snapshot {--full : Force full snapshot reset}';

    protected $description = 'Force full cloud snapshot pull (resets delta cursor)';

    public function handle(CloudPullService $pull): int
    {
        SyncCursor::where('stream', 'cloud_pull')->delete();

        try {
            $result = $pull->pull();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Snapshot applied: '.json_encode($result['stats'], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}

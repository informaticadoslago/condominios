<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function handle(): void
    {
        try {
            Artisan::call('backup:run');
            Log::info('BackupJob -- '.Artisan::output());
            Cache::put('backup_status', ['status' => 'completed'], 3600);
        } catch (\Throwable $e) {
            Log::error('BackupJob failed: '.$e->getMessage());
            Cache::put('backup_status', ['status' => 'failed', 'message' => $e->getMessage()], 3600);
        }
    }
}

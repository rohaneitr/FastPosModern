<?php

namespace App\Modules\Audit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Immutable Insertion
        // We strictly use DB::table()->insert() rather than an Eloquent Model.
        // This ensures no lifecycle hooks (like an accidental circular loop) are triggered, 
        // and physically prevents Eloquent `save()` or `delete()` methods from existing for this table.
        DB::table('audit_logs')->insert($this->payload);
    }
}

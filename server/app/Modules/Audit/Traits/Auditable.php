<?php

namespace App\Modules\Audit\Traits;

use App\Modules\Audit\Jobs\ProcessAuditLog;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Boot the Auditable trait to automatically bind to Eloquent lifecycle hooks.
     */
    public static function bootAuditable()
    {
        static::created(function ($model) {
            $model->queueAudit('created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            // Only capture the specific fields that actually changed
            $oldValues = array_intersect_key($model->getOriginal(), $model->getDirty());
            $newValues = $model->getDirty();
            
            // Ignore timestamps and meaningless data
            unset($oldValues['updated_at'], $newValues['updated_at']);
            
            if (!empty($newValues)) {
                $model->queueAudit('updated', $oldValues, $newValues);
            }
        });

        static::deleted(function ($model) {
            $model->queueAudit('deleted', $model->getAttributes(), []);
        });
    }

    /**
     * Offloads the heavy JSON serialization and database insertion to a background worker.
     * Ensures < 100ms response time on the main POS thread.
     */
    protected function queueAudit(string $event, array $oldValues, array $newValues)
    {
        $businessId = $this->business_id ?? null; // Nullable for system-level models
        $userId = Auth::id();
        $ipAddress = Request::ip();
        $userAgent = Request::header('User-Agent');

        $payload = [
            'business_id' => $businessId,
            'user_id' => $userId,
            'event' => $event,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now()->toDateTimeString(),
        ];

        // Dispatch to the Redis/RabbitMQ queue
        ProcessAuditLog::dispatch($payload);
    }
}

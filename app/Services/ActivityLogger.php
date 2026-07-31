<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    public function __construct(protected SettingService $settings) {}

    public function log(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        array $properties = [],
    ): void {
        if (! tenancy()->initialized) {
            return;
        }

        if (! $this->settings->activityLoggingEnabled()) {
            return;
        }

        try {
            ActivityLog::query()->create([
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'properties' => $properties ?: null,
                'ip_address' => Request::ip(),
            ]);
        } catch (\Throwable) {
            // Never break business flows if logging fails (e.g. before migrate).
        }
    }
}

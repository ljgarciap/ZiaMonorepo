<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    protected static function bootLogsActivity()
    {
        static::created(function ($model) {
            self::logActivity('created', $model);
        });

        static::updated(function ($model) {
            self::logActivity('updated', $model);
        });

        static::deleted(function ($model) {
            self::logActivity('deleted', $model);
        });
    }

    protected static function logActivity($action, $model)
    {
        if (!Auth::check()) return;

        $details = null;
        if ($action === 'updated') {
            $new = $model->getChanges();
            $old = [];
            foreach (array_keys($new) as $key) {
                $old[$key] = $model->getOriginal($key);
            }
            $details = ['old' => $old, 'new' => $new];
        } else if ($action === 'created') {
             $details = $model->getAttributes();
        }

        // Avoid logging hidden attributes like passwords
        if ($details) {
            foreach (['old', 'new'] as $side) {
                if (isset($details[$side]['password'])) unset($details[$side]['password']);
                if (isset($details[$side]['remember_token'])) unset($details[$side]['remember_token']);
            }
            if (isset($details['password'])) unset($details['password']);
            if (isset($details['remember_token'])) unset($details['remember_token']);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model' => get_class($model),
            'model_id' => $model->id,
            'details' => $details,
            'ip_address' => request()->ip()
        ]);
    }
}

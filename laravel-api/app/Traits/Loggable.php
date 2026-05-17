<?php

namespace App\Traits;

use App\Models\Activity;
use Illuminate\Support\Facades\Auth;

trait Loggable
{
    protected static function bootLoggable()
    {
        /**
         * @mixin \Illuminate\Database\Eloquent\Model
         * @mixin \Illuminate\Database\Query\Builder
         */
        static::created(fn ($model) => static::logToActivity($model, 'Created'));
        static::updated(fn ($model) => static::logToActivity($model, 'Updated'));
        static::deleted(fn ($model) => static::logToActivity($model, 'Deleted'));
    }

    protected static function logToActivity($model, $action)
    {
        // 1. Safety check: Don't log if the activity is performed by the system (no user)
        // or if we are modifying the Activity table itself.
        if (!Auth::check() || $model instanceof Activity) return;

        // 2. Prepare the data based on your specific columns
        $logData = [
            'user_id'     => Auth::id(),
            'table_name'  => $model->getTable(),
            'record_id'   => $model->id,
            'information' => $action, // Matches your ENUM: 'Created', 'Updated', 'Deleted'
            'before'      => null,
            'after'       => null,
            'new'         => null,
            'delete'      => null,
        ];

        // 3. Logic for each action to fill your longtext columns
        if ($action === 'Updated') {
            // GetOriginal gets data before change, getChanges gets the new values
            $logData['before'] = $model->getOriginal();
            $logData['after']  = $model->getChanges();
        } elseif ($action === 'Created') {
            $logData['new']    = $model->toArray();
        } elseif ($action === 'Deleted') {
            $logData['delete'] = $model->toArray();
        }

        Activity::create($logData);
    }
}

<?php

namespace App\Traits;

use App\Models\Activity;
use Illuminate\Support\Facades\Auth;

trait Loggable
{
    /**
     * Atribut yang tidak boleh masuk ke audit (rahasia autentikasi / data sensitif).
     *
     * @var array<int, string>
     */
    protected static array $auditExcludedAttributes = [
        'password',
        'password_confirmation',
        'password_new',
        'remember_token',
        'token',
        'access_token',
        'refresh_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

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

    /**
     * Hapus atribut sensitif dari array data audit.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function sanitizeForAudit(array $data): array
    {
        foreach (static::$auditExcludedAttributes as $attribute) {
            unset($data[$attribute]);
        }

        return $data;
    }

    protected static function logToActivity($model, $action)
    {
        // 1. Safety check: Don't log if the activity is performed by the system (no user)
        // or if we are modifying the Activity table itself.
        if (! Auth::check() || $model instanceof Activity) {
            return;
        }

        // 2. Prepare the data based on your specific columns
        $logData = [
            'user_id' => Auth::id(),
            'table_name' => $model->getTable(),
            'record_id' => $model->id,
            'information' => $action, // Matches your ENUM: 'Created', 'Updated', 'Deleted'
            'before' => null,
            'after' => null,
            'new' => null,
            'delete' => null,
        ];

        // 3. Logic for each action to fill your longtext columns.
        //    Semua nilai disaring agar password/hash/token tidak tersimpan di audit.
        if ($action === 'Updated') {
            // GetOriginal gets data before change, getChanges gets the new values
            $logData['before'] = static::sanitizeForAudit($model->getOriginal());
            $logData['after'] = static::sanitizeForAudit($model->getChanges());
        } elseif ($action === 'Created') {
            $logData['new'] = static::sanitizeForAudit($model->toArray());
        } elseif ($action === 'Deleted') {
            $logData['delete'] = static::sanitizeForAudit($model->toArray());
        }

        Activity::create($logData);
    }
}

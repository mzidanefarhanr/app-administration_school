<?php

namespace App\Traits;

use App\Models\Activity;
use Illuminate\Support\Facades\Auth;

trait Loggable
{
    /**
     * Flag reentrancy untuk mencegah loop logging rekursif.
     */
    protected static bool $isLoggingAudit = false;

    /**
     * Atribut yang secara ketat DILARANG masuk ke audit (secrets, tokens, hashes, cookies).
     *
     * @var array<int, string>
     */
    protected static array $auditStrictDeniedAttributes = [
        'password',
        'password_confirmation',
        'password_new',
        'remember_token',
        'token',
        'access_token',
        'refresh_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'secret',
        'cookie',
        'session_id',
        'authorization',
    ];

    protected static function bootLoggable(): void
    {
        static::created(fn ($model) => static::logToActivity($model, 'Created'));
        static::updated(fn ($model) => static::logToActivity($model, 'Updated'));
        static::deleted(fn ($model) => static::logToActivity($model, 'Deleted'));
    }

    /**
     * Dapatkan allowlist atribut untuk audit bila didefinisikan pada model.
     *
     * @return array<int, string>|null
     */
    public function getAuditableAttributes(): ?array
    {
        if (property_exists($this, 'auditableAttributes') && is_array($this->auditableAttributes)) {
            return $this->auditableAttributes;
        }

        return null;
    }

    /**
     * Filter atribut model menggunakan allowlist dan denylist rahasia.
     *
     * @param  mixed  $model
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function filterAttributesForAudit($model, array $data): array
    {
        $allowlist = method_exists($model, 'getAuditableAttributes')
            ? $model->getAuditableAttributes()
            : null;

        $filtered = [];

        foreach ($data as $key => $value) {
            // 1. Strict deny: rahasia tidak boleh masuk audit apapun alasannya
            if (in_array(strtolower((string) $key), static::$auditStrictDeniedAttributes, true)) {
                continue;
            }

            // 2. Jika model memiliki allowlist eksplisit, hanya ambil yang ada di allowlist
            if ($allowlist !== null && ! in_array($key, $allowlist, true)) {
                continue;
            }

            // 3. Jangan rekam timestamp otomatis jika tidak di allowlist
            if ($allowlist === null && in_array($key, ['created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            // 4. Jangan rekam hidden attributes jika tidak di allowlist
            if ($allowlist === null && method_exists($model, 'getHidden') && in_array($key, $model->getHidden(), true)) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    protected static function logToActivity($model, string $action): void
    {
        // 1. Safety & reentrancy guard: hindari loop rekursif, unauthenticated user, atau model Activity sendiri
        if (static::$isLoggingAudit || ! Auth::check() || $model instanceof Activity) {
            return;
        }

        static::$isLoggingAudit = true;

        try {
            $logData = [
                'user_id' => Auth::id(),
                'table_name' => $model->getTable(),
                'record_id' => $model->getKey(),
                'information' => $action,
                'before' => null,
                'after' => null,
                'new' => null,
                'delete' => null,
            ];

            if ($action === 'Updated') {
                $changes = $model->getChanges();
                unset($changes['updated_at']);

                $filteredAfter = static::filterAttributesForAudit($model, $changes);
                $filteredBefore = [];

                foreach (array_keys($filteredAfter) as $changedKey) {
                    $filteredBefore[$changedKey] = $model->getOriginal($changedKey);
                }

                $logData['before'] = ! empty($filteredBefore) ? $filteredBefore : null;
                $logData['after'] = ! empty($filteredAfter) ? $filteredAfter : null;
            } elseif ($action === 'Created') {
                $filteredNew = static::filterAttributesForAudit($model, $model->getAttributes());
                $logData['new'] = ! empty($filteredNew) ? $filteredNew : null;
            } elseif ($action === 'Deleted') {
                $filteredDelete = static::filterAttributesForAudit($model, $model->getAttributes());
                $logData['delete'] = ! empty($filteredDelete) ? $filteredDelete : null;
            }

            Activity::create($logData);
        } finally {
            static::$isLoggingAudit = false;
        }
    }
}

<?php

namespace App\Services\Archives;

use App\Models\ArchiveDocument;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Letter;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Resolusi subjek arsip dan visibilitas lintas domain (Fase 09a, DEC-37).
 *
 * Tipe subjek hanya dari whitelist config; tidak ada model/ID bebas. Dokumen
 * yang domain-nya tidak boleh dilihat aktor tidak pernah masuk daftar, dan
 * akses langsung diperlakukan 404 oleh pemanggil.
 */
class ArchiveSubjectService
{
    /**
     * @return array<string, array{label: string, ability: string}>
     */
    public function definitions(): array
    {
        return (array) config('archives.subject_types');
    }

    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        return array_keys($this->definitions());
    }

    public function isKnown(string $type): bool
    {
        return array_key_exists($type, $this->definitions());
    }

    public function abilityFor(string $type): ?string
    {
        $ability = config('archives.subject_types.'.$type.'.ability');

        return is_string($ability) ? $ability : null;
    }

    /**
     * @return array<int, string>
     */
    public function allowedTypes(User $actor): array
    {
        $allowed = [];

        foreach ($this->definitions() as $type => $definition) {
            if ($actor->hasAbility((string) $definition['ability'])) {
                $allowed[] = $type;
            }
        }

        return $allowed;
    }

    public function ensureDomainAbility(User $actor, string $type): void
    {
        $ability = $this->abilityFor($type);

        if ($ability === null || ! $actor->hasAbility($ability)) {
            abort(403, 'You are not allowed to access archives for this subject type.');
        }
    }

    /**
     * @param  Builder<ArchiveDocument>  $query
     */
    public function constrainVisible(Builder $query, User $actor): void
    {
        $allowed = $this->allowedTypes($actor);

        $query->where(function (Builder $sub) use ($allowed) {
            $sub->whereNull('subject_type');

            if ($allowed !== []) {
                $sub->orWhereIn('subject_type', $allowed);
            }
        });
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    public function resolveForCreate(?string $type, ?int $id, User $actor): array
    {
        if ($type === null && $id === null) {
            return [null, null];
        }

        if ($type === null || $type === '') {
            throw ValidationException::withMessages([
                'subject_type' => ['Select a subject type for the linked subject.'],
            ]);
        }

        if ($id === null) {
            throw ValidationException::withMessages([
                'subject_id' => ['Select a subject to link to this archive document.'],
            ]);
        }

        if (! $this->isKnown($type)) {
            throw ValidationException::withMessages([
                'subject_type' => ['The selected subject type is not valid.'],
            ]);
        }

        $this->ensureDomainAbility($actor, $type);

        if ($this->findSubject($type, $id) === null) {
            throw ValidationException::withMessages([
                'subject_id' => ['The selected subject was not found.'],
            ]);
        }

        return [$type, $id];
    }

    /**
     * @param  iterable<int, ArchiveDocument>  $documents
     * @return array<string, array<string, mixed>|null>
     */
    public function summaries(iterable $documents): array
    {
        $idsByType = [];

        foreach ($documents as $document) {
            if ($document->subject_type === null || $document->subject_id === null) {
                continue;
            }

            $idsByType[$document->subject_type][(int) $document->subject_id] = true;
        }

        $models = [];

        foreach ($idsByType as $type => $ids) {
            $models[$type] = $this->loadSubjects($type, array_keys($ids));
        }

        $summaries = [];

        foreach ($documents as $document) {
            if ($document->subject_type === null || $document->subject_id === null) {
                continue;
            }

            $key = $document->subject_type.':'.$document->subject_id;
            $model = $models[$document->subject_type][(int) $document->subject_id] ?? null;
            $summaries[$key] = $model === null ? null : $this->buildSummary($document->subject_type, $model);
        }

        return $summaries;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function summaryFor(?string $type, ?int $id): ?array
    {
        if ($type === null || $id === null) {
            return null;
        }

        $model = $this->findSubject($type, $id);

        return $model === null ? null : $this->buildSummary($type, $model);
    }

    /**
     * @return array<string, mixed>
     */
    public function picker(string $type, string $search, int $limit): array
    {
        if (! $this->isKnown($type)) {
            throw ValidationException::withMessages([
                'type' => ['The selected subject type is not valid.'],
            ]);
        }

        $search = trim($search);
        $limit = max(1, min($limit, 100));

        $query = match ($type) {
            ArchiveDocument::SUBJECT_STUDENT => Student::withTrashed()->select(['id', 'name', 'nis', 'deleted_at']),
            ArchiveDocument::SUBJECT_EMPLOYEE => Employee::withTrashed()->select(['id', 'name', 'nuptk', 'deleted_at']),
            ArchiveDocument::SUBJECT_LETTER => Letter::query()->select(['id', 'subject', 'direction', 'workflow_status']),
            ArchiveDocument::SUBJECT_LEAVE => LeaveRequest::query()->select(['id', 'subject_type', 'type', 'status', 'start_date', 'end_date']),
            default => null,
        };

        if ($query === null) {
            throw ValidationException::withMessages([
                'type' => ['The selected subject type is not valid.'],
            ]);
        }

        if ($search !== '') {
            $pattern = $this->likePattern($search);

            $query->where(function (Builder $sub) use ($type, $pattern, $search) {
                if ($type === ArchiveDocument::SUBJECT_STUDENT) {
                    $sub->where('name', 'like', $pattern)->orWhere('nis', 'like', $pattern);

                    return;
                }

                if ($type === ArchiveDocument::SUBJECT_EMPLOYEE) {
                    $sub->where('name', 'like', $pattern)->orWhere('nuptk', 'like', $pattern);

                    return;
                }

                if ($type === ArchiveDocument::SUBJECT_LETTER) {
                    $sub->where('subject', 'like', $pattern);

                    if (ctype_digit($search)) {
                        $sub->orWhere('id', (int) $search);
                    }

                    return;
                }

                $sub->where('status', 'like', $pattern);

                if (ctype_digit($search)) {
                    $sub->orWhere('id', (int) $search);
                }
            });
        }

        $query->orderByDesc('id')->limit($limit);

        $items = $query->get()
            ->map(fn (Model $model) => $this->buildSummary($type, $model))
            ->values()
            ->all();

        return [
            'type' => $type,
            'items' => $items,
        ];
    }

    private function findSubject(string $type, int $id): ?Model
    {
        return match ($type) {
            ArchiveDocument::SUBJECT_STUDENT => Student::withTrashed()->find($id),
            ArchiveDocument::SUBJECT_EMPLOYEE => Employee::withTrashed()->find($id),
            ArchiveDocument::SUBJECT_LETTER => Letter::query()->find($id),
            ArchiveDocument::SUBJECT_LEAVE => LeaveRequest::query()->find($id),
            default => null,
        };
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, Model>
     */
    private function loadSubjects(string $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $models = match ($type) {
            ArchiveDocument::SUBJECT_STUDENT => Student::withTrashed()
                ->whereIn('id', $ids)
                ->get(['id', 'name', 'nis', 'deleted_at']),
            ArchiveDocument::SUBJECT_EMPLOYEE => Employee::withTrashed()
                ->whereIn('id', $ids)
                ->get(['id', 'name', 'nuptk', 'deleted_at']),
            ArchiveDocument::SUBJECT_LETTER => Letter::query()
                ->whereIn('id', $ids)
                ->get(['id', 'subject', 'direction', 'workflow_status']),
            ArchiveDocument::SUBJECT_LEAVE => LeaveRequest::query()
                ->whereIn('id', $ids)
                ->get(['id', 'subject_type', 'type', 'status', 'start_date', 'end_date']),
            default => collect(),
        };

        return $models->keyBy('id')->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(string $type, Model $model): array
    {
        if ($type === ArchiveDocument::SUBJECT_STUDENT) {
            return [
                'type' => $type,
                'id' => $model->id,
                'label' => $model->name,
                'secondary' => $model->nis === null ? 'NIS -' : 'NIS '.$model->nis,
                'archived' => $model->trashed(),
            ];
        }

        if ($type === ArchiveDocument::SUBJECT_EMPLOYEE) {
            return [
                'type' => $type,
                'id' => $model->id,
                'label' => $model->name,
                'secondary' => $model->nuptk === null ? 'NUPTK -' : 'NUPTK '.$model->nuptk,
                'archived' => $model->trashed(),
            ];
        }

        if ($type === ArchiveDocument::SUBJECT_LETTER) {
            return [
                'type' => $type,
                'id' => $model->id,
                'label' => $model->subject === null || $model->subject === '' ? 'Letter #'.$model->id : $model->subject,
                'secondary' => '#'.$model->id.' · '.ucfirst((string) $model->direction).' · '.ucfirst((string) $model->workflow_status),
                'archived' => false,
            ];
        }

        return [
            'type' => $type,
            'id' => $model->id,
            'label' => $model->typeLabel(),
            'secondary' => '#'.$model->id.' · '.ucfirst((string) $model->status)
                .' · '.$model->start_date?->format('Y-m-d').' to '.$model->end_date?->format('Y-m-d'),
            'archived' => false,
        ];
    }

    private function likePattern(string $search): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        return '%'.$escaped.'%';
    }
}

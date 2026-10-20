<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\RfidCard;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AuditService
{
    /** @param  array<string, mixed>|null  $old */
    /** @param  array<string, mixed>|null  $new */
    public function record(
        string $action,
        Model $subject,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'ip_address' => request()->ip(),
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    /** @param  list<string>  $keys */
    public function snapshot(Model $model, array $keys): array
    {
        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => $model->getAttribute($key)])
            ->all();
    }

    public function forUserAssets(User $user, int $limit = 25): Collection
    {
        $vehicleIds = Vehicle::withTrashed()->where('user_id', $user->id)->pluck('id');
        $cardIds = RfidCard::withTrashed()->where('user_id', $user->id)->pluck('id');

        return AuditLog::with('actor')
            ->where(function ($q) use ($vehicleIds, $cardIds) {
                $q->where(function ($q2) use ($vehicleIds) {
                    $q2->where('subject_type', Vehicle::class)
                        ->whereIn('subject_id', $vehicleIds);
                })->orWhere(function ($q2) use ($cardIds) {
                    $q2->where('subject_type', RfidCard::class)
                        ->whereIn('subject_id', $cardIds);
                });
            })
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function forSubject(Model $subject, int $limit = 25): Collection
    {
        return AuditLog::with('actor')
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function recentForAdmin(int $limit = 50): Collection
    {
        return AuditLog::with('actor')
            ->whereIn('subject_type', [Vehicle::class, RfidCard::class])
            ->latest()
            ->limit($limit)
            ->get();
    }
}

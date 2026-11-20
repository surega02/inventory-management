<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

/** Read side of activity_log for the admin-only audit page. */
class AuditLogService
{
    /** Filter key => model class stored in subject_type. */
    public const SUBJECTS = [
        'asset' => Asset::class,
        'loan' => Loan::class,
        'movement' => AssetMovement::class,
        'maintenance' => Maintenance::class,
        'category' => Category::class,
        'location' => Location::class,
        'user' => User::class,
    ];

    /** Every event the app writes: model events, plus the custom ones from AssetService, UserService and the login listeners. */
    public const EVENTS = ['created', 'updated', 'deleted', 'restored', 'disposed', 'role_changed', 'login', 'login_failed'];

    /**
     * @param  array{causer_id?: int|null, subject_type?: string|null, event?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return Activity::query()
            ->with([
                'causer' => fn (MorphTo $m) => $m->constrain([User::class => fn (Builder $q) => $q->withoutGlobalScope(SoftDeletingScope::class)->select(['id', 'name'])]),
                // soft-deleted subjects still get a label; transaction rows show their asset code
                'subject' => fn (MorphTo $m) => $m
                    ->constrain([
                        Asset::class => fn (Builder $q) => $q->withoutGlobalScope(SoftDeletingScope::class),
                        Category::class => fn (Builder $q) => $q->withoutGlobalScope(SoftDeletingScope::class),
                        Location::class => fn (Builder $q) => $q->withoutGlobalScope(SoftDeletingScope::class),
                        User::class => fn (Builder $q) => $q->withoutGlobalScope(SoftDeletingScope::class),
                    ])
                    ->morphWith([Loan::class => ['asset'], AssetMovement::class => ['asset'], Maintenance::class => ['asset']]),
            ])
            ->when($filters['causer_id'] ?? null, fn (Builder $q, int $id) => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id))
            ->when($filters['subject_type'] ?? null, fn (Builder $q, string $type) => $q->where('subject_type', (new (self::SUBJECTS[$type]))->getMorphClass()))
            ->when($filters['event'] ?? null, fn (Builder $q, string $event) => $q->where('event', $event))
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Users who appear as causer at least once, for the "who" filter.
     *
     * @return Collection<int, User>
     */
    public function causers(): Collection
    {
        return User::withTrashed()
            ->whereIn('id', Activity::query()->where('causer_type', (new User)->getMorphClass())->select('causer_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return array{type: string|null, label: string|null, asset_id: int|null} what the entry is about, for display */
    public function subject(Activity $activity): array
    {
        $subject = $activity->subject;
        $type = array_search($activity->subject_type, array_map(fn (string $c) => (new $c)->getMorphClass(), self::SUBJECTS), true);

        $asset = match (true) {
            $subject instanceof Asset => $subject,
            $subject instanceof Loan, $subject instanceof AssetMovement, $subject instanceof Maintenance => $subject->asset,
            default => null,
        };

        $label = match (true) {
            $subject === null => null,
            $asset !== null => $asset->code,
            $subject instanceof Category, $subject instanceof Location => "{$subject->code} · {$subject->name}",
            $subject instanceof User => $subject->name,
            default => null,
        };

        return ['type' => $type === false ? null : $type, 'label' => $label, 'asset_id' => $asset?->id];
    }
}

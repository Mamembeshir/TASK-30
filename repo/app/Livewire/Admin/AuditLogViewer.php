<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogViewer extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $actionFilter  = '';
    public string $entityFilter  = '';

    /**
     * Investigation filters required by the auditability spec:
     *  - actor   → who performed the action (email, username, or UUID)
     *  - dateFrom/dateTo → time-bounded review (inclusive day range)
     *  - correlationId → trace a single request across services
     */
    public string $actorFilter         = '';
    public string $dateFromFilter      = '';
    public string $dateToFilter        = '';
    public string $correlationIdFilter = '';

    public function updatingSearch(): void              { $this->resetPage(); }
    public function updatingActionFilter(): void        { $this->resetPage(); }
    public function updatingEntityFilter(): void        { $this->resetPage(); }
    public function updatingActorFilter(): void         { $this->resetPage(); }
    public function updatingDateFromFilter(): void      { $this->resetPage(); }
    public function updatingDateToFilter(): void        { $this->resetPage(); }
    public function updatingCorrelationIdFilter(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset([
            'search', 'actionFilter', 'entityFilter',
            'actorFilter', 'dateFromFilter', 'dateToFilter', 'correlationIdFilter',
        ]);
        $this->resetPage();
    }

    public function render()
    {
        $query = AuditLog::with('actor.profile')->latest('created_at');

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('action', 'like', $term)
                  ->orWhere('entity_id', 'like', $term)
                  ->orWhere('ip_address', 'like', $term);
            });
        }

        if ($this->actionFilter !== '') {
            $query->where('action', $this->actionFilter);
        }

        if ($this->entityFilter !== '') {
            $query->where('entity_type', $this->entityFilter);
        }

        if ($this->actorFilter !== '') {
            // Accept UUID, email, or username — resolve to one or more actor IDs.
            // The `id` lookup is only attempted when the input parses as a UUID,
            // because PostgreSQL's uuid type rejects non-UUID strings outright.
            $isUuid = (bool) preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $this->actorFilter,
            );

            $actorIds = User::query()
                ->where(function ($q) use ($isUuid) {
                    $q->where('email', $this->actorFilter)
                      ->orWhere('username', $this->actorFilter);
                    if ($isUuid) {
                        $q->orWhere('id', $this->actorFilter);
                    }
                })
                ->pluck('id');

            // If nothing matches, force an empty result rather than silently
            // ignoring the filter (which would over-share unrelated entries).
            $query->whereIn('actor_id', $actorIds->all() ?: ['00000000-0000-0000-0000-000000000000']);
        }

        if ($this->dateFromFilter !== '') {
            try {
                $query->where('created_at', '>=', \Illuminate\Support\Carbon::parse($this->dateFromFilter)->startOfDay());
            } catch (\Throwable) {
                // Ignore unparseable input — UI will surface the field, not silently apply garbage.
            }
        }

        if ($this->dateToFilter !== '') {
            try {
                $query->where('created_at', '<=', \Illuminate\Support\Carbon::parse($this->dateToFilter)->endOfDay());
            } catch (\Throwable) {
                // Same as above.
            }
        }

        if ($this->correlationIdFilter !== '') {
            // correlation_id is a uuid column; non-UUID input would crash the
            // query in Postgres. Force a no-match in that case so the UI shows
            // an empty result instead of bubbling a 500 to the admin.
            $isUuid = (bool) preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $this->correlationIdFilter,
            );
            $query->where('correlation_id', $isUuid
                ? $this->correlationIdFilter
                : '00000000-0000-0000-0000-000000000000');
        }

        $logs = $query->paginate(25);

        $actionOptions = AuditLog::query()
            ->select('action')->distinct()->orderBy('action')->pluck('action');

        $entityOptions = AuditLog::query()
            ->select('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type');

        return view('livewire.admin.audit-log-viewer', [
            'logs'          => $logs,
            'actionOptions' => $actionOptions,
            'entityOptions' => $entityOptions,
        ])->layout('layouts.app', ['title' => 'Audit Log']);
    }
}

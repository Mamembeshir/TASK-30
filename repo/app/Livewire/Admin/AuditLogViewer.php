<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogViewer extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $actionFilter = '';
    public string $entityFilter = '';

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingActionFilter(): void { $this->resetPage(); }
    public function updatingEntityFilter(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'actionFilter', 'entityFilter']);
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

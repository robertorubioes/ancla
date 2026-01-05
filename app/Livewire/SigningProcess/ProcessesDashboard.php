<?php

declare(strict_types=1);

namespace App\Livewire\SigningProcess;

use App\Models\SigningProcess;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Livewire component for viewing signing processes dashboard.
 *
 * Features:
 * - View all signing processes created by user
 * - Filter by status (draft, sent, in_progress, completed, expired)
 * - View process completion progress
 * - View signer status timeline
 * - Dashboard statistics
 */
class ProcessesDashboard extends Component
{
    use WithPagination;

    /**
     * Filter by status.
     */
    #[Url(as: 'status')]
    public ?string $filterStatus = null;

    /**
     * Search query.
     */
    #[Url(as: 'q')]
    public string $search = '';

    /**
     * Number of items per page.
     */
    public int $perPage = 10;

    /**
     * Selected process for details modal.
     */
    public ?int $selectedProcessId = null;

    /**
     * Whether to show details modal.
     */
    public bool $showDetailsModal = false;

    /**
     * Reset pagination when filters change.
     */
    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when search changes.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Set filter status.
     */
    public function setFilter(?string $status): void
    {
        $this->filterStatus = $status;
        $this->resetPage();
    }

    /**
     * Clear all filters.
     */
    public function clearFilters(): void
    {
        $this->filterStatus = null;
        $this->search = '';
        $this->resetPage();
    }

    /**
     * Show process details.
     */
    public function showDetails(int $processId): void
    {
        $this->selectedProcessId = $processId;
        $this->showDetailsModal = true;
    }

    /**
     * Close details modal.
     */
    public function closeDetails(): void
    {
        $this->showDetailsModal = false;
        $this->selectedProcessId = null;
    }

    /**
     * Send a draft process (trigger notifications).
     */
    public function sendProcess(int $processId): void
    {
        $process = SigningProcess::query()
            ->where('id', $processId)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('created_by', auth()->id())
            ->where('status', SigningProcess::STATUS_DRAFT)
            ->firstOrFail();

        try {
            // Send notifications to signers
            $process->sendNotifications();

            session()->flash('message', __('Process sent successfully! Signers have been notified.'));
        } catch (\Exception $e) {
            session()->flash('error', __('Failed to send process. Please try again.'));
            
            \Illuminate\Support\Facades\Log::error('Failed to send process', [
                'process_id' => $processId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get processes query.
     */
    protected function getProcessesQuery()
    {
        $query = SigningProcess::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('created_by', auth()->id())
            ->with(['document', 'signers', 'createdBy'])
            ->orderBy('created_at', 'desc');

        // Apply status filter
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        // Apply search filter
        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('document', function ($docQuery) {
                    $docQuery->where('original_filename', 'like', "%{$this->search}%");
                })
                    ->orWhereHas('signers', function ($signerQuery) {
                        $signerQuery->where('name', 'like', "%{$this->search}%")
                            ->orWhere('email', 'like', "%{$this->search}%");
                    });
            });
        }

        return $query;
    }

    /**
     * Get paginated processes.
     */
    #[Computed]
    public function processes()
    {
        return $this->getProcessesQuery()->paginate($this->perPage);
    }

    /**
     * Get dashboard statistics.
     */
    #[Computed]
    public function statistics(): array
    {
        $userId = auth()->id();
        $tenantId = auth()->user()->tenant_id;

        $baseQuery = SigningProcess::query()
            ->where('tenant_id', $tenantId)
            ->where('created_by', $userId);

        return [
            'total' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', SigningProcess::STATUS_DRAFT)->count(),
            'sent' => (clone $baseQuery)->where('status', SigningProcess::STATUS_SENT)->count(),
            'in_progress' => (clone $baseQuery)->where('status', SigningProcess::STATUS_IN_PROGRESS)->count(),
            'completed' => (clone $baseQuery)->where('status', SigningProcess::STATUS_COMPLETED)->count(),
            'expired' => (clone $baseQuery)->where('status', SigningProcess::STATUS_EXPIRED)->count(),
            'cancelled' => (clone $baseQuery)->where('status', SigningProcess::STATUS_CANCELLED)->count(),
        ];
    }

    /**
     * Get selected process for details.
     */
    #[Computed]
    public function selectedProcess(): ?SigningProcess
    {
        if (! $this->selectedProcessId) {
            return null;
        }

        return SigningProcess::query()
            ->where('id', $this->selectedProcessId)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('created_by', auth()->id())
            ->with(['document', 'signers.evidencePackage', 'auditTrailEntries'])
            ->first();
    }

    /**
     * Get status badge color.
     */
    public function getStatusColor(string $status): string
    {
        return match ($status) {
            SigningProcess::STATUS_DRAFT => 'gray',
            SigningProcess::STATUS_SENT => 'blue',
            SigningProcess::STATUS_IN_PROGRESS => 'yellow',
            SigningProcess::STATUS_COMPLETED => 'green',
            SigningProcess::STATUS_EXPIRED => 'red',
            SigningProcess::STATUS_CANCELLED => 'red',
            default => 'gray',
        };
    }

    /**
     * Get signer status badge color.
     */
    public function getSignerStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'gray',
            'sent' => 'blue',
            'viewed' => 'yellow',
            'signed' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            SigningProcess::STATUS_DRAFT => 'Draft',
            SigningProcess::STATUS_SENT => 'Sent',
            SigningProcess::STATUS_IN_PROGRESS => 'In Progress',
            SigningProcess::STATUS_COMPLETED => 'Completed',
            SigningProcess::STATUS_EXPIRED => 'Expired',
            SigningProcess::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($status),
        };
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.signing-process.processes-dashboard');
    }
}

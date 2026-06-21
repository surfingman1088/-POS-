<?php

namespace App\Livewire\Employee;

use Livewire\Component;
use Livewire\WithPagination;
use App\Services\System\AuditLogsService;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    use WithPagination;

    // Search and filter properties
    public $search = '';
    public $statusFilter = '';
    public $sortBy = 'id';
    public $sortDirection = 'asc';

    // Selected employee for operations
    public $selectedEmployeeId = null;

    // Form properties
    public $name = '';
    public $contact_number = '';
    public $status = 'active';

    protected $rules = [
        'name' => 'required|string|max:255',
        'contact_number' => 'required|string|digits:11|regex:/^09[0-9]{9}$/',
        'status' => 'required|in:active,inactive',
    ];

    protected $messages = [
        'contact_number.required' => 'Contact number is required.',
        'contact_number.digits' => 'Contact number must be exactly 11 digits.',
        'contact_number.regex' => 'Contact number must start with 09 and be 11 digits long.',
    ];

    public function mount()
    {
        //
    }

    // Search method
    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    // Sort method
    public function sortByField($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    // Alpine.js modal helper methods
    public function loadEmployeeForEdit($employeeId)
    {
        $employee = Employee::find($employeeId);
        if ($employee) {
            $this->selectedEmployeeId = $employee->id;
            $this->name = $employee->name;
            $this->contact_number = $employee->contact_number;
            $this->status = $employee->status;
        }
    }

    public function setSelectedEmployee($employeeId)
    {
        $this->selectedEmployeeId = $employeeId;
    }

    // CRUD methods
    public function createEmployee(AuditLogsService $audit): void
    {
        $this->validate();

        $employee = Employee::create([
            'name'           => ucwords(trim($this->name)),
            'contact_number' => $this->contact_number,
            'status'         => $this->status,
            'is_archived'    => false,
        ]);

        $audit->recordEmployeeCreated(Auth::user(), $employee, request());

        $this->dispatch('close-create-modal');
        $this->dispatch('show-success', ['message' => __('Employee created successfully!')]);
        $this->resetForm();
    }

    public function updateEmployee(AuditLogsService $audit): void
    {
        $this->validate();

        $employee = Employee::find($this->selectedEmployeeId);

        if ($employee) {
            $oldValues = [
                'name'           => $employee->name,
                'contact_number' => $employee->contact_number,
                'status'         => $employee->status,
            ];

            $employee->update([
                'name'           => ucwords(trim($this->name)),
                'contact_number' => $this->contact_number,
                'status'         => $this->status,
            ]);

            $audit->recordEmployeeUpdated(Auth::user(), $employee, $oldValues, request());

            $this->dispatch('close-edit-modal');
            $this->dispatch('show-success', ['message' => __('Employee updated successfully!')]);
            $this->resetForm();
        } else {
            $this->dispatch('show-error', ['message' => __('Employee not found!')]);
        }
    }

    public function deleteEmployee(AuditLogsService $audit): void
    {
        $employee = Employee::find($this->selectedEmployeeId);

        if (! $employee) {
            $this->dispatch('show-error', ['message' => __('Employee not found!')]);
            return;
        }

        $ongoingOrders = $employee->orders()
            ->whereIn('status', ['pending', 'in_progress', 'out_for_delivery'])
            ->count();

        if ($ongoingOrders > 0) {
            $this->dispatch('show-error', ['message' => __('Cannot archive employee with ongoing orders!')]);
            return;
        }

        $this->dispatch('close-delete-modal');
        $employee->update(['is_archived' => true]);

        $audit->recordEmployeeArchived(Auth::user(), $employee, request());

        $this->dispatch('show-success', ['message' => __('Employee archived successfully!')]);
        $this->selectedEmployeeId = null;
    }

    // Add method to restore archived employee
    public function restoreEmployee(int $employeeId, AuditLogsService $audit): void
    {
        $employee = Employee::find($employeeId);

        if ($employee && $employee->is_archived) {
            $employee->update(['is_archived' => false]);
            $audit->recordEmployeeRestored(Auth::user(), $employee, request());
            $this->dispatch('show-success', ['message' => __('Employee restored successfully!')]);
        }
    }

    // Helper methods
    public function resetForm()
    {
        $this->name = '';
        $this->contact_number = '';
        $this->status = 'active';
        $this->selectedEmployeeId = null;
        $this->resetErrorBag();
    }

    public function getEmployeesProperty()
    {
        $query = Employee::query();

        // Apply search filter
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('contact_number', 'like', '%' . $this->search . '%');
            });
        }

        // Apply status filter
        if (!empty($this->statusFilter)) {
            if ($this->statusFilter === 'archived') {
                $query->where('is_archived', true);
            } else {
                $query->where('status', $this->statusFilter)->where('is_archived', false);
            }
        } else {
            // By default, only show non-archived employees
            $query->where('is_archived', false);
        }

        // Attach delivered counts so Blade can read per-employee totals
        $deliveredStatuses = ['delivered', 'completed'];
        $query->withCount([
            'orders as orders_delivered' => function ($q) use ($deliveredStatuses) {
                $q->whereIn('status', $deliveredStatuses);
            },
            'orders as orders_delivered_today' => function ($q) use ($deliveredStatuses) {
                $q->whereIn('status', $deliveredStatuses)->whereDate('updated_at', now());
            },
        ]);

        // Apply sorting
        if ($this->sortBy === 'orders_delivered') {
            $query->orderBy('orders_delivered', $this->sortDirection);
        } else {
            $query->orderBy($this->sortBy, $this->sortDirection);
        }

        return $query->paginate(10);
    }

    // Get all employees for stats (excluding archived)
    public function getAllEmployeesProperty()
    {
        return Employee::where('is_archived', false)->get();
    }

    public function render()
    {
        return view('livewire.employee.dashboard', [
            'employees' => $this->employees,
            'allEmployees' => $this->allEmployees,
        ]);
    }
}

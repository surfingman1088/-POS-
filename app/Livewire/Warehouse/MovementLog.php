<?php

namespace App\Livewire\Warehouse;

use App\Models\WarehouseMovement;
use Livewire\Component;
use Livewire\WithPagination;

class MovementLog extends Component
{
    use WithPagination;

    public string $search     = '';
    public string $filterType = '';
    public string $dateFrom   = '';
    public string $dateTo     = '';

    protected $queryString = ['search', 'filterType', 'dateFrom', 'dateTo'];

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo   = now()->format('Y-m-d');
    }

    public function render()
    {
        $movements = WarehouseMovement::with(['product', 'variant', 'user'])
            ->when($this->search, fn($q) => $q->whereHas('product', fn($p) =>
                $p->where('name', 'like', "%{$this->search}%")
            ))
            ->when($this->filterType, fn($q) => $q->where('type', $this->filterType))
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.warehouse.movement-log', compact('movements'));
    }
}

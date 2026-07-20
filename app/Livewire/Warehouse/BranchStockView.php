<?php

namespace App\Livewire\Warehouse;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class BranchStockView extends Component
{
    use WithPagination;

    public string $selectedBranch = '';
    public string $search         = '';
    public string $viewMode       = 'branch'; // branch / warehouse

    protected $queryString = ['search', 'selectedBranch', 'viewMode'];

    public function mount(): void
    {
        $user = Auth::user();

        // 分店人員：預設顯示自己的分店
        if ($user->role === 'branch') {
            $branch = $user->branches()->first();
            if ($branch) {
                $this->selectedBranch = (string) $branch->id;
            }
        }
    }

    public function render()
    {
        $user     = Auth::user();
        $branches = $this->getAccessibleBranches($user);

        if ($this->viewMode === 'warehouse') {
            // 顯示中央倉庫庫存
            $stocks = WarehouseStock::with(['product.categoryRecord', 'variant'])
                ->when($this->search, fn($q) => $q->whereHas('product', fn($p) =>
                    $p->where('name', 'like', "%{$this->search}%")
                ))
                ->orderBy('quantity')
                ->paginate(20);

            return view('livewire.warehouse.branch-stock-view', compact('stocks', 'branches'))
                ->with('viewMode', 'warehouse');
        }

        // 顯示分店庫存
        $query = BranchStock::with(['product.categoryRecord', 'variant', 'branch'])
            ->when($this->selectedBranch, fn($q) => $q->where('branch_id', $this->selectedBranch))
            ->when(! $this->selectedBranch && $user->role === 'branch', function ($q) use ($user) {
                $branchIds = $user->branches()->pluck('branches.id');
                $q->whereIn('branch_id', $branchIds);
            })
            ->when($this->search, fn($q) => $q->whereHas('product', fn($p) =>
                $p->where('name', 'like', "%{$this->search}%")
            ))
            ->orderBy('branch_id')
            ->orderBy('quantity');

        $stocks = $query->paginate(20);

        return view('livewire.warehouse.branch-stock-view', compact('stocks', 'branches'));
    }

    private function getAccessibleBranches($user): \Illuminate\Database\Eloquent\Collection
    {
        if ($user->role === 'branch') {
            return $user->branches()->where('is_active', true)->get();
        }
        return Branch::where('is_active', true)->orderBy('name')->get();
    }
}

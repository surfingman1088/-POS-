<?php

namespace App\Livewire\Product;

use App\Models\Product;
use App\Services\Products\InventoryService;
use App\Services\System\AuditLogsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Dashboard extends Component
{
    use WithPagination;
    use WithFileUploads;

    // Search and filter properties
    public $search = '';
    public $categoryFilter = 'all';
    public $stockFilter = '';
    public $sortBy = 'id';
    public $sortDirection = 'asc';

    public $selectedProductId = null;

    // 管理員密碼驗證 modal 屬性
    public bool $showAdminPasswordModal = false;
    public string $adminPasswordInput = '';
    public string $pendingAdminAction = ''; // 'save_product' | 'delete_product' | 'archive_product'

    // Form properties
    public $name = '';
    public $description = '';
    public $price = '';
    public $cost = 0;
    public $stocks = '';
    public $category = '';
    public $sold = 0;
    public $is_in_stock = true;
    public $color = '';

    /** @var TemporaryUploadedFile|null Newly chosen file, not yet persisted */
    public $image = null;

    /** Current image URL shown when editing an existing product */
    public $existingImageUrl = null;

    /** True once the user has explicitly removed the image (vs. just never uploading one) */
    public $removeExistingImage = false;

    /** Bumped to force the file input to re-render with a fresh, empty state */
    public $imageVersion = 0;

    /** Final square size (px) images are normalized to on disk */
    private const IMAGE_TARGET_SIZE = 800;

    protected function rules(): array
    {
        // Color is optional now — only validated when one is actually set.
        $colorRules = ['nullable'];

        if (! empty($this->color)) {
            $colorRules[] = 'regex:/^#[0-9A-Fa-f]{6}$/';
            $colorRules[] = Rule::unique('products', 'color')->ignore($this->selectedProductId);
        }

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stocks' => 'required|integer|min:0',
            'category' => 'required|exists:product_categories,id',
            'sold' => 'integer|min:0',
            'is_in_stock' => 'boolean',
            'image' => 'nullable|image|max:10000', // 10MB max for the original upload before processing
            'color' => $colorRules,
        ];
    }

    protected $messages = [
        'name.required' => 'Product name is required.',
        'price.required' => 'Price is required.',
        'price.numeric' => 'Price must be a valid number.',
        'price.min' => 'Price cannot be negative.',
        'stocks.required' => 'Stock quantity is required.',
        'stocks.integer' => 'Stock must be a whole number.',
        'stocks.min' => 'Stock cannot be negative.',
        'category.required' => 'Category is required.',
        'image.image' => 'The uploaded file must be an image.',
        'image.max' => 'Image size must not exceed 4MB.',
        'color.regex' => 'Please choose a valid color.',
        'color.unique' => 'This color is already assigned to another product. Please pick a different one.',
    ];

    public function mount()
    {
        //
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatedStockFilter()
    {
        $this->resetPage();
    }

    // Fires when the color property is committed from the client (see form.blade.php)
    public function updatedColor(): void
    {
        $this->validateOnly('color');
    }

    public function clearSearch()
    {
        $this->search = '';
        $this->categoryFilter = 'all';
        $this->stockFilter = '';
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->categoryFilter = 'all';
        $this->stockFilter = '';
        $this->resetPage();
    }

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

    public function openCreateModal()
    {
        $this->resetForm();
        $this->color = $this->generateUniqueColor();
    }

    public function openEditModal($productId)
    {
        $product = Product::find($productId);
        if ($product) {
            $this->selectedProductId = $product->id;
            $this->name = $product->name;
            $this->description = $product->description;
            $this->price = $product->price;
            $this->cost = $product->cost ?? 0;
            $this->stocks = $product->stocks;
            $this->category = $product->category;
            $this->sold = $product->sold;
            $this->is_in_stock = $product->is_in_stock;
            // Backfill a unique color for legacy products that don't have one yet
            $this->color = $product->color ?: $this->generateUniqueColor();
            $this->image = null;
            $this->existingImageUrl = $product->image_url;
            $this->removeExistingImage = false;

            $this->dispatch('edit-product-loaded');
        } else {
            $this->dispatch('show-error', ['message' => __('Product not found!')]);
            $this->dispatch('edit-product-load-failed');
        }
    }

    public function regenerateColor(): void
    {
        $this->color = $this->generateUniqueColor();
        $this->resetErrorBag('color');
    }

    /**
     * Explicitly clear the color label. The product falls back to a
     * neutral gray everywhere it's displayed until a color is reassigned.
     */
    public function removeColor(): void
    {
        $this->color = null;
        $this->resetErrorBag('color');
    }

    /**
     * Clear the chosen/persisted image client-side. Nothing is deleted
     * from disk yet — that only happens on save(), so cancelling the
     * modal leaves the original file untouched.
     */
    public function removeImage(): void
    {
        $this->image = null;
        $this->existingImageUrl = null;
        $this->removeExistingImage = true;
        $this->imageVersion++; // forces the file input to re-render empty
        $this->resetErrorBag('image');
    }

    public function openArchiveModal($productId)
    {
        $this->selectedProductId = $productId;
    }

    public function openDeleteModal($productId)
    {
        $this->selectedProductId = $productId;

        if (Product::find($productId)) {
            $this->dispatch('delete-product-loaded');
        } else {
            $this->dispatch('show-error', ['message' => __('Product not found!')]);
            $this->dispatch('delete-product-load-failed');
        }
    }

    public function setSelectedProduct($productId)
    {
        $this->selectedProductId = $productId;
    }

    /**
     * Single entry point the merged form submits to.
     * Routes to create or update depending on whether we're editing.
     * 如果是員工角色，修改庫存數量需要管理員密碼驗證。
     */
    public function save(AuditLogsService $audit): void
    {
        $this->normalizeColor();

        // 如果是員工，且正在編輯商品（可能修改庫存），需要管理員密碼驗證
        if (Auth::user()->isStaff() && $this->selectedProductId) {
            $product = Product::find($this->selectedProductId);
            if ($product && (int) $this->stocks !== (int) $product->stocks) {
                // 庫存數量有變動，要求管理員密碼
                $this->pendingAdminAction = 'save_product';
                $this->adminPasswordInput = '';
                $this->showAdminPasswordModal = true;
                return;
            }
        }

        if ($this->selectedProductId) {
            $this->updateProduct($audit);
        } else {
            $this->createProduct($audit);
        }
    }

    /**
     * 管理員密碼驗證成功後，執行原本的操作。
     */
    public function confirmAdminPassword(AuditLogsService $audit): void
    {
        // 找到任一管理員帳號驗證密碼
        $adminUser = \App\Models\User::where('role', 'admin')->first();

        if (! $adminUser || ! Hash::check($this->adminPasswordInput, $adminUser->password)) {
            $this->addError('adminPasswordInput', __('Incorrect admin password. Please try again.'));
            return;
        }

        $this->showAdminPasswordModal = false;
        $this->adminPasswordInput = '';

        // 執行原本操作
        match ($this->pendingAdminAction) {
            'save_product'    => $this->updateProduct($audit),
            'delete_product'  => $this->deleteProduct($audit),
            'archive_product' => $this->archiveProduct($audit),
            default           => null,
        };

        $this->pendingAdminAction = '';
    }

    /**
     * 關閉管理員密碼驗證 modal。
     */
    public function closeAdminPasswordModal(): void
    {
        $this->showAdminPasswordModal = false;
        $this->adminPasswordInput = '';
        $this->pendingAdminAction = '';
        $this->resetErrorBag('adminPasswordInput');
    }

    public function createProduct(AuditLogsService $audit): void
    {
        $this->validate();

        $imageUrl = $this->image ? $this->storeSquareImage($this->image) : null;

        $product = Product::create([
            'name'        => ucwords(trim($this->name)),
            'description' => trim($this->description),
            'price'       => $this->price,
            'cost'        => $this->cost ?? 0,
            'stocks'      => $this->stocks,
            'category'    => $this->category,
            'sold'        => $this->sold,
            'is_in_stock' => $this->is_in_stock,
            'color'       => $this->color,
            'image_url'   => $imageUrl,
        ]);

        $audit->recordProductCreated(Auth::user(), $product, request());

        $this->dispatch('show-success', ['message' => __('Product created successfully!')]);
        $this->dispatch('close-form-modal');
        $this->resetForm();
    }

    public function updateProduct(AuditLogsService $audit): void
    {
        $this->validate();

        $product = Product::find($this->selectedProductId);

        if (! $product) {
            $this->dispatch('show-error', ['message' => __('Product not found!')]);
            return;
        }

        $oldValues = [
            'name'        => $product->name,
            'description' => $product->description,
            'price'       => $product->price,
            'stocks'      => $product->stocks,
            'category'    => $product->category,
            'is_in_stock' => $product->is_in_stock,
            'color'       => $product->color,
            'image_url'   => $product->image_url,
        ];

        // Resolve the image up front (outside the DB transaction — file I/O shouldn't be rolled back with it)
        $imageUrl = $product->image_url;

        if ($this->image) {
            // New file replaces whatever was there
            if ($product->image_url) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $product->image_url));
            }
            $imageUrl = $this->storeSquareImage($this->image);
        } elseif ($this->removeExistingImage) {
            // User explicitly removed it without uploading a replacement
            if ($product->image_url) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $product->image_url));
            }
            $imageUrl = null;
        }

        try {
            DB::transaction(function () use ($product, $oldValues, $audit, $imageUrl) {
                $inventory   = app(InventoryService::class);
                $oldStocks   = (int) $product->stocks;
                $newStocks   = (int) $this->stocks;

                if ($newStocks > $oldStocks) {
                    $inventory->restore(
                        (int) $product->id,
                        $newStocks - $oldStocks,
                        'restock',
                        $product,
                        __('Manual restock from product dashboard.')
                    );
                } elseif ($newStocks < $oldStocks) {
                    $inventory->deduct(
                        (int) $product->id,
                        $oldStocks - $newStocks,
                        'manual_adjustment',
                        $product,
                        __('Manual stock deduction from product dashboard.')
                    );
                }

                $product->refresh();

                $product->update([
                    'name'        => ucwords(trim($this->name)),
                    'description' => trim($this->description),
                    'price'       => $this->price,
                    'cost'        => $this->cost ?? 0,
                    'stocks'      => $newStocks,
                    'category'    => $this->category,
                    'sold'        => $this->sold,
                    'is_in_stock' => $this->is_in_stock,
                    'color'       => $this->color,
                    'image_url'   => $imageUrl,
                ]);

                if ($oldStocks !== $newStocks) {
                    $audit->recordProductStockAdjusted(
                        Auth::user(),
                        $product,
                        $oldStocks,
                        $newStocks,
                        'manual',
                        request()
                    );
                }

                $audit->recordProductUpdated(Auth::user(), $product, $oldValues, request());
            });
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: __('Unable to update stock.');
            $this->dispatch('show-error', ['message' => $message]);
            return;
        }

        $this->dispatch('show-success', ['message' => __('Product :name updated successfully!', ['name' => $this->name])]);
        $this->dispatch('close-form-modal');
        $this->resetForm();
    }

    public function makeAvailable(int $productId, AuditLogsService $audit): void
    {
        $product = Product::find($productId);

        if ($product) {
            $product->update(['is_in_stock' => true]);
            $audit->recordProductRestored(Auth::user(), $product, request());
            $this->dispatch('show-success', ['message' => __(':name is now available for sale!', ['name' => $product->name])]);
        } else {
            $this->dispatch('show-error', ['message' => __('Product not found!')]);
        }
    }

    public function archiveProduct(AuditLogsService $audit): void
    {
        $product = Product::find($this->selectedProductId);

        if ($product) {
            $product->update(['is_in_stock' => false]);
            $audit->recordProductArchived(Auth::user(), $product, request());
            $this->dispatch('show-success', ['message' => __(':name has been marked as unavailable!', ['name' => $product->name])]);
            $this->dispatch('close-archive-modal');
            $this->selectedProductId = null;
        } else {
            $this->dispatch('show-error', ['message' => __('Product not found!')]);
        }
    }

    public function deleteProduct(AuditLogsService $audit): void
    {
        $product = Product::find($this->selectedProductId);

        if (! $product) {
            $this->dispatch('show-error', ['message' => __('Product not found!')]);
            return;
        }

        if ($product->orderItems()->count() > 0) {
            $this->dispatch('show-error', ['message' => __('Cannot permanently delete product with order history!')]);
            return;
        }

        $snapshot = [
            'name'     => $product->name,
            'category' => $product->category,
            'price'    => $product->price,
            'stocks'   => $product->stocks,
        ];

        if ($product->image_url) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $product->image_url));
        }

        $product->delete();

        $audit->recordProductDeleted(Auth::user(), $snapshot, request());

        $this->dispatch('show-success', ['message' => __('Product :name permanently deleted!', ['name' => $snapshot['name']])]);
        $this->dispatch('close-delete-modal');
        $this->selectedProductId = null;
    }

    public function resetForm()
    {
        $this->name = '';
        $this->description = '';
        $this->price = '';
        $this->cost = 0;
        $this->stocks = '';
        $this->category = '';
        $this->sold = 0;
        $this->is_in_stock = true;
        $this->color = '';
        $this->image = null;
        $this->existingImageUrl = null;
        $this->removeExistingImage = false;
        $this->selectedProductId = null;
        $this->resetErrorBag();
    }

    /**
     * Converts an empty-string color (the picker's "nothing chosen" state)
     * to a real null, since Laravel's `nullable` rule only recognizes
     * actual null — not an empty string — as "no value."
     */
    private function normalizeColor(): void
    {
        $this->color = $this->color !== '' ? $this->color : null;
    }

    /**
     * Generate a random hex color guaranteed not to collide with
     * any existing product's color in the database.
     */
    private function generateUniqueColor(): string
    {
        $attempts = 0;

        do {
            $color = $this->randomHexColor();
            $attempts++;
        } while (
            Product::where('color', $color)
                ->when($this->selectedProductId, fn ($q) => $q->where('id', '!=', $this->selectedProductId))
                ->exists()
            && $attempts < 50
        );

        return $color;
    }

    /**
     * Random, vibrant, easy-to-distinguish hex color (avoids near-white/near-black).
     */
    private function randomHexColor(): string
    {
        $hue = mt_rand(0, 359);
        [$r, $g, $b] = $this->hslToRgb($hue, 65, 55);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    private function hslToRgb(int $h, int $s, int $l): array
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $this->hueToRgb($p, $q, $h + 1 / 3);
            $g = $this->hueToRgb($p, $q, $h);
            $b = $this->hueToRgb($p, $q, $h - 1 / 3);
        }

        return [(int) round($r * 255), (int) round($g * 255), (int) round($b * 255)];
    }

    private function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    }

    /**
     * Reads the JPEG's EXIF Orientation tag and physically rotates/flips
     * the GD image so the pixels match what was actually displayed —
     * because GD itself ignores this tag when decoding.
     *
     * Orientation values per the EXIF spec:
     * 1=normal 2=flip-h 3=rotate180 4=flip-v
     * 5=flip-v+rotate90cw 6=rotate90cw 7=flip-h+rotate90cw 8=rotate90ccw
     */
    private function applyExifOrientation($image, string $path)
    {
        if (! function_exists('exif_read_data')) {
            return $image; // exif extension not installed — skip silently
        }

        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? 1;

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $image = imagerotate($image, 180, 0);
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                imageflip($image, IMG_FLIP_VERTICAL);
                $image = imagerotate($image, -90, 0);
                break;
            case 6:
                $image = imagerotate($image, -90, 0);
                break;
            case 7:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                $image = imagerotate($image, -90, 0);
                break;
            case 8:
                $image = imagerotate($image, 90, 0);
                break;
        }

        return $image;
    }

    /**
     * Crop the uploaded image to a centered square and resize it,
     * so the file stored on disk is square — not just square via CSS.
     */
    private function storeSquareImage(TemporaryUploadedFile $file): string
    {
        $sourcePath = $file->getRealPath();
        [$width, $height, $type] = getimagesize($sourcePath);

        $source = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            IMAGETYPE_GIF  => imagecreatefromgif($sourcePath),
            default        => imagecreatefromstring(file_get_contents($sourcePath)),
        };

        // Correct phone-camera rotation BEFORE cropping — only JPEGs carry this tag
        if ($type === IMAGETYPE_JPEG) {
            $source = $this->applyExifOrientation($source, $sourcePath);
            $width = imagesx($source);
            $height = imagesy($source);
        }

        // Centered square crop
        $side = min($width, $height);
        $srcX = (int) (($width - $side) / 2);
        $srcY = (int) (($height - $side) / 2);

        $targetSize = min($side, self::IMAGE_TARGET_SIZE);
        $canvas = imagecreatetruecolor($targetSize, $targetSize);

        // Preserve transparency for png/gif
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $targetSize, $targetSize, $transparent);
        }

        imagecopyresampled(
            $canvas, $source,
            0, 0, $srcX, $srcY,
            $targetSize, $targetSize, $side, $side
        );

        $extension = match ($type) {
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_GIF  => 'gif',
            default        => 'jpg',
        };

        $relativePath = 'products/' . uniqid('prod_', true) . '.' . $extension;
        Storage::disk('public')->makeDirectory('products');
        $fullPath = Storage::disk('public')->path($relativePath);

        match ($type) {
            IMAGETYPE_PNG  => imagepng($canvas, $fullPath),
            IMAGETYPE_WEBP => imagewebp($canvas, $fullPath, 90),
            IMAGETYPE_GIF  => imagegif($canvas, $fullPath),
            default        => imagejpeg($canvas, $fullPath, 88),
        };

        imagedestroy($source);
        imagedestroy($canvas);

        return Storage::url($relativePath);
    }

    public function render()
    {
        $query = Product::query()->withCount('orderItems');

        if ($this->search && trim($this->search) !== '') {
            $searchTerm = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm)
                    ->orWhereHas('categoryRecord', fn ($categoryQuery) => $categoryQuery->where('name', 'like', $searchTerm));
            });
        }

        if ($this->categoryFilter !== 'all' && !empty($this->categoryFilter)) {
            $query->where('category_id', (int) $this->categoryFilter);
        }

        if ($this->stockFilter && trim($this->stockFilter) !== '' && $this->stockFilter !== 'all') {
            switch ($this->stockFilter) {
                case 'in_stock':
                    $query->where('stocks', '>=', 10);
                    break;
                case 'low_stock':
                    $query->where('stocks', '<', 10)->where('stocks', '>', 0);
                    break;
                case 'out_of_stock':
                    $query->where('stocks', '<=', 0);
                    break;
                case 'available':
                    $query->where('is_in_stock', true);
                    break;
                case 'hidden':
                    $query->where('is_in_stock', false);
                    break;
            }
        }

        $query->orderBy($this->sortBy, $this->sortDirection);

        $products = $query->paginate(10);
        $allProducts = Product::query()->select(['id', 'stocks', 'category_id', 'is_in_stock'])->get();
        $categories = Product::getCategories();
        $existingCategories = Product::whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id', 'category_id')
            ->toArray();

        return view('livewire.product.dashboard', [
            'products' => $products,
            'allProducts' => $allProducts,
            'categories' => $categories,
            'existingCategories' => $existingCategories,
        ]);
    }
}

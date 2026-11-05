<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'sku', 'barcode', 'brand', 'description', 'picture'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, HasFactory;

    private const string PICTURE_DISK = 'public';

    /**
     * @return HasMany<BranchProduct, $this>
     */
    public function branchProducts(): HasMany
    {
        return $this->hasMany(BranchProduct::class);
    }

    /**
     * Find a product from a barcode scanner reading.
     */
    public static function findByBarcode(string $barcode): ?self
    {
        return static::query()->where('barcode', trim($barcode))->first();
    }

    /**
     * Products not offered by the branch yet. A product removed from it counts as not offered.
     */
    #[Scope]
    protected function notInBranch(Builder $query, Branch|int $branch): void
    {
        $query->whereDoesntHave('branchProducts', fn (Builder $rows) => $rows->forBranch($branch));
    }

    /**
     * Scanners can send surrounding whitespace, and an empty barcode must stay unique-safe.
     */
    protected function barcode(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? trim($value) : null);
    }

    /**
     * The picture URL, versioned so a replaced picture is not served from the browser cache.
     */
    protected function pictureUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->picture === null
            ? null
            : Storage::disk(self::PICTURE_DISK)->url($this->picture).'?v='.$this->updated_at?->timestamp);
    }

    /**
     * Store the picture under the product's id, replacing any previous one.
     */
    public function replacePicture(UploadedFile $file): void
    {
        $this->deletePictureFile();

        $this->update([
            'picture' => $file->storeAs('products', "{$this->id}.{$file->extension()}", self::PICTURE_DISK),
        ]);
    }

    public function removePicture(): void
    {
        $this->deletePictureFile();

        $this->update(['picture' => null]);
    }

    private function deletePictureFile(): void
    {
        if ($this->picture !== null) {
            Storage::disk(self::PICTURE_DISK)->delete($this->picture);
        }
    }
}

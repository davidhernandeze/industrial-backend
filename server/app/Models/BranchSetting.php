<?php

namespace App\Models;

use App\Enums\SettingType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\LabeledByKey;
use App\Support\CurrentBranch;
use Database\Factories\BranchSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Something every branch can configure. It holds no value: each branch's value is a
 * BranchSettingValue, which does not exist until the branch sets one.
 */
#[Fillable(['key', 'type', 'entity_type'])]
class BranchSetting extends Model
{
    /** @use HasFactory<BranchSettingFactory> */
    use Auditable, HasFactory, LabeledByKey;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'type' => 'string',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
        ];
    }

    /**
     * @return HasMany<BranchSettingValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(BranchSettingValue::class);
    }

    /**
     * The value of the branch the signed-in user is working in.
     *
     * @return HasOne<BranchSettingValue, $this>
     */
    public function currentValue(): HasOne
    {
        return $this->hasOne(BranchSettingValue::class)
            ->where('branch_id', app(CurrentBranch::class)->get()?->getKey())
            ->inverse('branchSetting');
    }

    /**
     * Save a branch's value, keeping its row when the value is emptied. A setting the branch
     * never used stays without a row.
     *
     * @param  array{value?: ?string, entity_id?: ?int}  $data
     */
    public function saveValueFor(Branch $branch, array $data): ?BranchSettingValue
    {
        $row = $this->values()->withTrashed()->forBranch($branch)->first();

        if ($row === null && blank(array_filter($data, filled(...)))) {
            return null;
        }

        $row ??= $this->values()->make();
        $row->branch_id = $branch->getKey();
        $row->fill($data)->save();

        if ($row->trashed()) {
            $row->restore();
        }

        $branch->unsetRelation('settingValues');
        $this->unsetRelation('currentValue');

        return $row;
    }

    /**
     * Entity logs name related records by their name attribute.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => $this->label);
    }
}

<?php

namespace App\Models;

use App\Enums\SettingType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasTypedValue;
use Database\Factories\BranchSettingValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One branch's value for a BranchSetting. A branch has a single row per setting, kept even
 * when its value is emptied. The setting decides the type and the entity's model.
 */
#[Fillable(['value', 'entity_id'])]
class BranchSettingValue extends Model
{
    /** @use HasFactory<BranchSettingValueFactory> */
    use Auditable, BelongsToBranch, HasFactory, HasTypedValue;

    /**
     * @return BelongsTo<BranchSetting, $this>
     */
    public function branchSetting(): BelongsTo
    {
        return $this->belongsTo(BranchSetting::class)->withTrashed();
    }

    /**
     * Only entity settings fill entity_id, so the fallback model is never queried.
     *
     * @return BelongsTo<Model, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo($this->branchSetting->entity_type ?? self::class, 'entity_id');
    }

    public function settingType(): SettingType
    {
        return $this->branchSetting->type;
    }
}

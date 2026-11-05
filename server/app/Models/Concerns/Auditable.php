<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Soft deletes and entity logs, required on every business table.
 *
 * Hidden attributes are never logged. Only changes made through a model are logged:
 * query builder updates such as Branch::query()->update() fire no model events.
 */
trait Auditable
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept([...$this->getHidden(), 'id', 'created_at', 'updated_at', 'deleted_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}

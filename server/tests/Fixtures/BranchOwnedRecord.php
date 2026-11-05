<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class BranchOwnedRecord extends Model
{
    use BelongsToBranch;

    protected $table = 'branch_owned_records';

    protected $guarded = [];
}

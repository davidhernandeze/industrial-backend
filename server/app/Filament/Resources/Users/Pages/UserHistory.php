<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Pages\EntityHistory;
use App\Filament\Resources\Users\UserResource;

class UserHistory extends EntityHistory
{
    protected static string $resource = UserResource::class;
}

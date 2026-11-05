<?php

namespace App\Filament\Forms\Components;

use App\Models\Branch;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Two lists to move branches between available and assigned. Works on any model
 * with AssignedToBranch. Assignments to soft-deleted branches are kept, ready for a restore.
 */
class BranchAssignment extends Field
{
    protected string $view = 'filament.forms.branch-assignment';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
        $this->dehydrated(false);

        $this->loadStateFromRelationshipsUsing(function (self $component, ?Model $record): void {
            $component->state(
                $record?->branches()->pluck('branches.id')->map(fn (int $id): string => (string) $id)->all() ?? [],
            );
        });

        $this->saveRelationshipsUsing(function (self $component, Model $record, ?array $state): void {
            $assignable = array_intersect(array_column($component->getOptions(), 'id'), $state ?? []);
            $softDeleted = $record->branches()->onlyTrashed()->pluck('branches.id')->all();

            $record->branches()->sync([...$assignable, ...$softDeleted]);
        });
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function getOptions(): array
    {
        return Branch::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Branch $branch): array => ['id' => (string) $branch->id, 'name' => $branch->name])
            ->all();
    }
}

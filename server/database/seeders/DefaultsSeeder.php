<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Creates the records the application expects, declared in code. Runs on every deploy, so it only
 * adds what is missing: existing records keep their values and deleted ones stay deleted.
 */
abstract class DefaultsSeeder extends Seeder
{
    /**
     * @var class-string<Model>
     */
    protected string $model;

    /**
     * The attributes that identify a record.
     *
     * @var list<string>
     */
    protected array $uniqueBy = ['key'];

    /**
     * @return list<array<string, mixed>>
     */
    abstract protected function defaults(): array;

    public function run(): void
    {
        foreach ($this->defaults() as $attributes) {
            $this->model::withTrashed()->firstOrCreate(
                Arr::only($attributes, $this->uniqueBy),
                Arr::except($attributes, $this->uniqueBy),
            );
        }
    }
}

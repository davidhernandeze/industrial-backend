<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {--name=} {--email=} {--password=}';

    protected $description = 'Create an admin user, assigned to every branch';

    public function handle(): int
    {
        $name = $this->option('name') ?? text('Name', required: true);
        $email = $this->option('email') ?? text('Email', required: true, validate: ['email', 'unique:users,email']);
        $password = $this->option('password') ?? password('Password', required: true, validate: ['min:8']);

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->components->error('A user with this email already exists.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => Role::Admin,
        ]);

        $this->components->info("Admin {$email} created.");

        return self::SUCCESS;
    }
}

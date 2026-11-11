<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateAdmin extends Command
{
    protected $signature = 'platform:create-admin
                            {email : Login email}
                            {--name=Administrator : Display name}
                            {--password= : Password (prompted if omitted)}
                            {--if-missing : Do nothing (successfully) if this email already exists}';

    protected $description = 'Create a platform administrator (also seeds roles and permissions if missing)';

    public function handle(): int
    {
        if ($this->option('if-missing') && User::where('email', $this->argument('email'))->exists()) {
            $this->line('Admin already exists, nothing to do.');

            return self::SUCCESS;
        }

        $password = $this->option('password') ?: $this->secret('Password (min 8 characters)');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'password' => $password],
            ['email' => 'required|email|unique:users,email', 'password' => 'required|min:8']
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! Role::where('name', 'platform-admin')->exists()) {
            $this->callSilent('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        }

        $user = User::create([
            'name' => $this->option('name'),
            'email' => $this->argument('email'),
            'password' => $password,
        ]);
        $user->assignRole('platform-admin');

        $this->info("Platform admin {$user->email} created.");

        return self::SUCCESS;
    }
}

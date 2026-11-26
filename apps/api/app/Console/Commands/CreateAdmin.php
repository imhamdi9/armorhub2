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
                            {--if-missing : Do nothing (successfully) if this email already exists}
                            {--reset-password : If this email already exists, set its password again, make it an active platform administrator and sign it out everywhere (account recovery)}';

    protected $description = 'Create a platform administrator (also seeds roles and permissions if missing)';

    public function handle(): int
    {
        // Account recovery for hosts with no shell: the owner of the deployment asks for it explicitly, with a new password.
        if ($this->option('reset-password') && ($existing = User::where('email', $this->argument('email'))->first())) {
            $password = (string) $this->option('password');

            if (strlen($password) < 8) {
                $this->error('Give a new password of at least 8 characters with --password.');

                return self::FAILURE;
            }

            if (! Role::where('name', 'platform-admin')->exists()) {
                $this->callSilent('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
            }

            $existing->forceFill(['password' => $password, 'is_active' => true, 'email_verified_at' => $existing->email_verified_at ?? now()])->save();
            $existing->syncRoles(['platform-admin']);
            $existing->tokens()->delete();
            \App\Support\Audit::record('auth.admin_password_reset', $existing, ['via' => 'platform:create-admin --reset-password'], $existing);
            $this->info("Password reset for {$existing->email}. Remove ADMIN_RESET_PASSWORD from the environment now.");

            return self::SUCCESS;
        }

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
            'email_verified_at' => now(),
        ]);
        $user->assignRole('platform-admin');

        $this->info("Platform admin {$user->email} created.");

        return self::SUCCESS;
    }
}

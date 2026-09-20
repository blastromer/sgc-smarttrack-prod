<?php

namespace App\Console\Commands;

use App\Http\Controllers\SetupController;
use App\Models\Cycle;
use App\Models\User;
use Illuminate\Console\Command;

class EnsureSuperAdmin extends Command
{
    protected $signature = 'sgc:ensure-super
        {--name= : Super Admin full name}
        {--email= : Super Admin email}
        {--password= : Super Admin password}';

    protected $description = 'Create the first Super Admin when production has no accounts';

    public function handle(): int
    {
        if (SetupController::superExists()) {
            $this->info('Super Admin already exists.');

            return self::SUCCESS;
        }

        $name = $this->option('name') ?: config('sgc.super_admin.name');
        $email = $this->option('email') ?: config('sgc.super_admin.email');
        $password = $this->option('password') ?: config('sgc.super_admin.password');

        if (! filled($name) || ! filled($email) || ! filled($password)) {
            $this->error('Set SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL, and SUPER_ADMIN_PASSWORD, or pass --name --email --password.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => strtolower((string) $email),
            'password' => $password,
            'role' => 'super',
            'status' => 'active',
            'office' => 'System',
            'email_verified_at' => now(),
        ]);

        Cycle::query()->firstOrCreate(
            ['name' => '2026 SGC Functionality Assessment'],
            [
                'level' => 'Public Elementary',
                'opens_at' => now()->toDateString(),
                'deadline_at' => now()->addDays(30)->toDateString(),
                'status' => 'open',
            ]
        );

        $this->info('Super Admin created: '.$email);

        return self::SUCCESS;
    }
}

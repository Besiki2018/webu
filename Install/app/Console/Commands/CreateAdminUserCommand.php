<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

class CreateAdminUserCommand extends Command
{
    protected $signature = 'app:create-admin
                            {--email= : Admin email}
                            {--password= : Admin password}
                            {--name= : Admin display name}';

    protected $description = 'Create the first admin user (use when no users exist and you cannot run the installer).';

    public function handle(): int
    {
        if (! Schema::hasTable('users')) {
            $this->error('Users table does not exist. Run: php artisan migrate');

            return self::FAILURE;
        }

        if (User::count() > 0) {
            $this->warn('At least one user already exists. Use password reset or create a new user from the app.');

            return self::SUCCESS;
        }

        $email = $this->option('email') ?: $this->ask('Admin email');
        $password = $this->option('password') ?: $this->secret('Admin password');
        $name = $this->option('name') ?: $this->ask('Admin name', 'Admin');

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['email' => $email, 'password' => $password, 'name' => $name],
            [
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', Password::defaults()],
                'name' => ['required', 'string', 'max:255'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $msg) {
                $this->error($msg);
            }

            return self::FAILURE;
        }

        $planId = null;
        $buildCredits = 0;
        if (Schema::hasTable('plans')) {
            try {
                $plan = Plan::orderBy('price')->first();
                if ($plan) {
                    $planId = $plan->id;
                    $buildCredits = (int) ($plan->monthly_build_credits ?? 0);
                }
            } catch (\Throwable $e) {
                // use defaults
            }
        }

        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'admin';
        $user->plan_id = $planId;
        $user->build_credits = $buildCredits;
        $user->email_verified_at = now();
        $user->locale = config('app.locale', 'en');
        $user->save();

        $this->info("Admin user created: {$email}");
        $this->info('You can now log in at '.config('app.url').'/login');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('darbaar:admin {email : Admin email address} {--name= : Display name}')]
#[Description('Create an admin user (or reset an existing user’s password and make them admin)')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        $password = $this->secret('Password (min 10 characters)');
        if (strlen((string) $password) < 10) {
            $this->error('Password must be at least 10 characters.');

            return self::FAILURE;
        }
        if ($password !== $this->secret('Confirm password')) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $this->option('name') ?: ($user->name ?: 'Admin');
        $user->password = $password;
        $user->is_admin = true;
        $user->save();

        $this->info("Admin ready: {$email}. Log in at ".route('admin.login'));

        return self::SUCCESS;
    }
}

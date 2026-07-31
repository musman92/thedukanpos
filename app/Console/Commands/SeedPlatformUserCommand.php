<?php

namespace App\Console\Commands;

use App\Models\PlatformUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedPlatformUserCommand extends Command
{
    protected $signature = 'dukan:seed-platform
        {--email=admin@dukanpos.test : Platform login email}
        {--password=password : Platform password}
        {--name=Platform Admin : Display name}';

    protected $description = 'Create or update the landlord platform operator account';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');
        $name = (string) $this->option('name');

        $user = PlatformUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ],
        );

        $this->info("Platform user ready: {$user->email}");
        $this->line("Login at /platform/login");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Support\OperationalReset;
use Illuminate\Console\Command;

class ResetOperationalData extends Command
{
    protected $signature = 'sgc:reset-operational {--confirm : Required. Type this flag to run.}';

    protected $description = 'Clear school accounts and FAT packets. Keeps Super Admin and Division Admin.';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('Refusing to run. Pass --confirm to clear school and FAT data.');

            return self::FAILURE;
        }

        OperationalReset::run();
        $this->info('School accounts, FAT packets, and MOVs were cleared.');

        return self::SUCCESS;
    }
}

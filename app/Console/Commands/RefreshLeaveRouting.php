<?php

namespace App\Console\Commands;

use App\Services\LeaveService;
use Illuminate\Console\Command;

class RefreshLeaveRouting extends Command
{
    protected $signature = 'leave:refresh-routing';

    protected $description = 'Audit and route pending leave using current eligible Supervisor assignments';

    public function handle(LeaveService $leave): int
    {
        $leave->refreshRouting();
        $this->info('Pending leave routing refreshed.');

        return self::SUCCESS;
    }
}

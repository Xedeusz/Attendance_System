<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProvisionUser extends Command
{
    protected $signature = 'app:provision-user';

    protected $description = 'Deprecated: use initial HR bootstrap, then HR-managed account invitations';

    public function handle(): int
    {
        $this->error('Direct development login creation is disabled. Use app:bootstrap-hr for the first HR; HR creates all subsequent accounts.');

        return self::FAILURE;
    }
}

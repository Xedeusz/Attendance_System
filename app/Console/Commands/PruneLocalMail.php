<?php

namespace App\Console\Commands;

use App\Models\LocalMailMessage;
use Illuminate\Console\Command;

class PruneLocalMail extends Command
{
    protected $signature = 'accounts:prune-local-mail';

    protected $description = 'Remove expired encrypted local-development mail previews';

    public function handle(): int
    {
        LocalMailMessage::where('expires_at', '<=', now())->delete();
        $this->info('Expired local previews removed.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\EssentialEmail;
use App\Services\EssentialEmailOutbox;
use Illuminate\Console\Command;

class ManageEssentialEmail extends Command
{
    protected $signature = 'mail:essential {action=list : list, recover or retry} {id? : Delivery ID for retry}';

    protected $description = 'Inspect and recover essential email deliveries without displaying addresses or tokens';

    public function handle(EssentialEmailOutbox $outbox): int
    {
        return match ($this->argument('action')) {
            'list' => $this->showDeliveries(),
            'recover' => $this->recover($outbox),
            'retry' => $this->retry($outbox),
            default => self::INVALID,
        };
    }

    private function showDeliveries(): int
    {
        $deliveries = EssentialEmail::query()->latest('id')->limit(30)->get();

        $this->table(
            ['ID', 'Type', 'Status', 'Attempts', 'Reason', 'Document reference'],
            $deliveries->map(fn (EssentialEmail $email): array => [
                $email->id, $email->type, $email->status, $email->attempts, $email->failure_reason, $email->document_reference,
            ]),
        );

        return self::SUCCESS;
    }

    private function recover(EssentialEmailOutbox $outbox): int
    {
        EssentialEmail::whereNotNull('auth_token')->where('expires_at', '<=', now())->update(['auth_token' => null]);
        EssentialEmail::where('status', 'sending')->where('last_attempt_at', '<', now()->subMinutes(5))
            ->update([
                'status' => 'uncertain', 'failure_reason' => 'worker_interrupted_during_submission',
                'processed_at' => now(), 'auth_token' => null,
            ]);

        if ($outbox->enabled()) {
            EssentialEmail::where('status', 'queued')->whereNull('enqueued_at')->select('id')
                ->chunkById(100, function ($emails) use ($outbox): void {
                    foreach ($emails as $email) {
                        $outbox->enqueue($email->id);
                    }
                });
        }

        return self::SUCCESS;
    }

    private function retry(EssentialEmailOutbox $outbox): int
    {
        $id = filter_var($this->argument('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $id || ! $outbox->enabled()) {
            $this->error('Supply a valid delivery ID and enable SMTP.');

            return self::INVALID;
        }

        $reset = EssentialEmail::whereKey($id)->where('status', 'failed')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->update(['status' => 'queued', 'enqueued_at' => null, 'processed_at' => null, 'failure_reason' => null]);
        if (! $reset) {
            $this->error('Only definitively failed deliveries with current authorization can be retried.');

            return self::FAILURE;
        }

        $outbox->enqueue($id);
        $email = EssentialEmail::findOrFail($id);
        $this->info('Delivery '.$id.' status: '.$email->status.($email->failure_reason ? ' ('.$email->failure_reason.')' : ''));

        return $email->enqueued_at ? self::SUCCESS : self::FAILURE;
    }
}

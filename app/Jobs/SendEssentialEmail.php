<?php

namespace App\Jobs;

use App\Mail\EssentialEmailFailure;
use App\Models\EssentialEmail;
use App\Services\EssentialEmailDelivery;
use App\Services\EssentialEmailOutbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SendEssentialEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(public int $emailId)
    {
        $this->onConnection('database')->onQueue('mail')->afterCommit();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(EssentialEmailDelivery $delivery, EssentialEmailOutbox $outbox): void
    {
        $claimed = EssentialEmail::whereKey($this->emailId)->whereIn('status', ['queued', 'retrying'])->update([
            'status' => 'sending', 'attempts' => DB::raw('attempts + 1'), 'last_attempt_at' => now(),
        ]);
        if (! $claimed) {
            return;
        }

        $email = EssentialEmail::findOrFail($this->emailId);
        try {
            $delivery->send($email);
        } catch (EssentialEmailFailure $failure) {
            $reason = $failure->getMessage();
            if ($failure->retryable && $this->attempts() < $this->tries) {
                $outbox->finish($email, 'retrying', $reason);
                throw new RuntimeException($reason);
            }

            $outbox->finish($email, $failure->outcome, $reason);
            if ($failure->outcome !== 'skipped') {
                $this->fail(new RuntimeException($reason));
            }

            return;
        } catch (Throwable) {
            $outbox->finish($email, 'uncertain', 'submission_outcome_unknown');
            $this->fail(new RuntimeException('submission_outcome_unknown'));

            return;
        }

        $outbox->finish($email, 'submitted');
    }

    public function failed(?Throwable $exception): void
    {
        $email = EssentialEmail::whereKey($this->emailId)->whereIn('status', ['queued', 'retrying', 'sending'])->first();
        if ($email) {
            $uncertain = $email->status === 'sending';
            app(EssentialEmailOutbox::class)->finish($email, $uncertain ? 'uncertain' : 'failed', $uncertain
                ? 'worker_interrupted_during_submission'
                : ($email->failure_reason ?? 'queue_attempts_exhausted'));
        }
    }
}

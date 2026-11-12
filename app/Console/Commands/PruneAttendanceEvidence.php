<?php

namespace App\Console\Commands;

use App\Models\AttendanceEvent;
use App\Models\AttendancePhotoEvidence;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PruneAttendanceEvidence extends Command
{
    protected $signature = 'attendance:prune-evidence {--orphans : Also scan for unreferenced private files older than one hour}';

    protected $description = 'Delete expired attendance photos/location detail while preserving core records and review decisions';

    public function handle(AuditService $audit): int
    {
        $count = 0;
        AttendanceEvent::whereNull('location_purged_at')->with(['photo', 'review'])->orderBy('id')->chunkById(100, function ($events) use (&$count, $audit) {
            foreach ($events as $candidate) {
                DB::transaction(function () use ($candidate, &$count, $audit) {
                    Role::whereKey(Role::HR)->lockForUpdate()->firstOrFail();
                    $event = AttendanceEvent::with(['photo', 'review'])->findOrFail($candidate->id);
                    $pending = $event->review_status === 'pending';
                    $days = max(1, (int) config($pending ? 'attendance.pending_review_hold_days' : 'attendance.photo_retention_days'));
                    $base = $event->review?->reviewed_at ?? $event->occurred_at;
                    if ($base->copy()->addDays($days)->isFuture()) {
                        return;
                    }
                    if ($event->photo && ! $event->photo->purged_at) {
                        Storage::disk('attendance')->delete($event->photo->storage_path);
                        $event->photo->update(['storage_path' => null, 'purged_at' => now()]);
                    }
                    $event->update(['location' => null, 'exception_reason' => null, 'location_purged_at' => now()]);
                    $audit->record('attendance.evidence_purged', $event);
                    $count++;
                });
            }
        });
        if ($this->option('orphans')) {
            $disk = Storage::disk('attendance');
            foreach ($disk->getDriver()->listContents('', true) as $entry) {
                if ($entry->isFile() && $disk->lastModified($entry->path()) < now()->subHour()->timestamp && ! AttendancePhotoEvidence::where('storage_path', $entry->path())->exists()) {
                    $disk->delete($entry->path());
                }
            }
        }
        $this->info('Expired evidence purged: '.$count.'. Core timestamps and review decisions were preserved.');

        return self::SUCCESS;
    }
}

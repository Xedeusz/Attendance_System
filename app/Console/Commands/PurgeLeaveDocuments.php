<?php

namespace App\Console\Commands;

use App\Models\LeaveSupportingDocument;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeLeaveDocuments extends Command
{
    protected $signature = 'leave:purge-documents';

    protected $description = 'Purge private leave document bytes when their configured retention expires';

    public function handle(AuditService $audit): int
    {
        LeaveSupportingDocument::whereNull('purged_at')->whereNotNull('purge_after')->where('purge_after', '<=', now())->eachById(function ($document) use ($audit) {
            Storage::disk('leave')->delete($document->storage_path);
            $document->update(['purged_at' => now()]);
            $audit->record('leave.document_purged', $document);
        });
        $this->info('Expired leave documents processed.');

        return self::SUCCESS;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leave_requests')->where('status', 'approved')
            ->whereExists(function (Builder $query): void {
                $query->selectRaw('1')->from('leave_approval_documents as document')
                    ->whereColumn('document.leave_request_id', 'leave_requests.id')
                    ->whereNotNull('document.revoked_at')->whereNull('document.superseded_by_document_id')
                    ->whereNotExists(function (Builder $newer): void {
                        $newer->selectRaw('1')->from('leave_approval_documents as replacement')
                            ->whereColumn('replacement.leave_request_id', 'document.leave_request_id')
                            ->whereColumn('replacement.version', '>', 'document.version');
                    });
            })->update(['status' => 'revoked', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Keep the recorded revocations when rolling back this data repair.
    }
};

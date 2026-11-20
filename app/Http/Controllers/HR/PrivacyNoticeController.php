<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\PrivacyNotice;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrivacyNoticeController extends Controller
{
    public function index(): View
    {
        return view('privacy.manage', [
            'notices' => PrivacyNotice::query()->withCount('acknowledgements')->latest('id')->paginate(15),
            'current' => PrivacyNotice::current(),
        ]);
    }

    public function show(int $notice): View
    {
        return view('privacy.edit', ['notice' => PrivacyNotice::findOrFail($notice), 'current' => PrivacyNotice::current()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $notice = PrivacyNotice::create($this->draftFields($request, new PrivacyNotice));

        return redirect()->route('hr.privacy.show', $notice)->with('status', 'Draft saved.');
    }

    public function update(Request $request, int $notice): RedirectResponse
    {
        $validated = $this->draftFields($request, PrivacyNotice::findOrFail($notice));
        DB::transaction(function () use ($notice, $validated): void {
            $notice = PrivacyNotice::query()->lockForUpdate()->findOrFail($notice);
            abort_if($notice->published_at, 409, 'Create a new version to revise a published notice.');
            $notice->update($validated);
        });

        return back()->with('status', 'Draft updated.');
    }

    public function publish(Request $request, int $notice, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'approval_reference' => ['required', 'string', 'max:150'],
            'approved' => ['required', 'accepted'],
            'revision' => ['required', 'string', 'size:64'],
        ]);

        DB::transaction(function () use ($request, $notice, $validated, $audit): void {
            $notice = PrivacyNotice::query()->lockForUpdate()->findOrFail($notice);
            abort_if($notice->published_at, 409, 'This version has already been published.');
            abort_unless(hash_equals($notice->draftFingerprint(), $validated['revision']), 409, 'The draft changed. Review the new preview before publishing.');
            $notice->forceFill([
                'published_at' => now(),
                'published_by' => $request->user()->id,
                'content_hash' => hash('sha256', $notice->content),
                'approval_reference' => $validated['approval_reference'],
            ])->save();
            $audit->record('privacy.notice.published', $notice, ['version' => $notice->version], includeRequestMetadata: false);
        });

        return back()->with('status', 'Notice published. Its effective date controls when it is shown.');
    }

    public function archive(int $notice, AuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($notice, $audit): void {
            $notice = PrivacyNotice::query()->lockForUpdate()->findOrFail($notice);
            $current = PrivacyNotice::current();
            abort_unless($current && $notice->published_at && ! $notice->archived_at && $notice->effective_at->lte(now()) && ! $notice->is($current), 409, 'Only a superseded version can be archived.');
            $notice->forceFill(['archived_at' => now()])->save();
            $audit->record('privacy.notice.archived', $notice, ['version' => $notice->version], includeRequestMetadata: false);
        });

        return back()->with('status', 'Superseded notice archived. Its text and acknowledgements remain in history.');
    }

    /** @return array{version: string, title: string, content: string, contact_email?: string|null, effective_at: string, requires_acknowledgement: bool} */
    private function draftFields(Request $request, PrivacyNotice $notice): array
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'max:40', 'regex:/\A[A-Za-z0-9._-]+\z/', Rule::unique('privacy_notices')->ignore($notice)],
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:50000'],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'effective_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'requires_acknowledgement' => ['sometimes', 'boolean'],
        ]);

        $validated['requires_acknowledgement'] = $request->boolean('requires_acknowledgement');

        return $validated;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PrivacyNotice;
use App\Models\PrivacyNoticeAcknowledgement;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrivacyNoticeController extends Controller
{
    public function index(Request $request): View
    {
        $notice = PrivacyNotice::current();
        $acknowledgements = config('privacy.enabled')
            ? PrivacyNoticeAcknowledgement::query()->whereBelongsTo($request->user())->with('privacyNotice')->latest('acknowledged_at')->paginate(10)
            : collect();

        return view('privacy.index', [
            'notice' => $notice,
            'canAcknowledge' => $notice && ! $notice->acknowledgedBy($request->user()),
            'acknowledgements' => $acknowledgements,
            'versions' => config('privacy.enabled') ? PrivacyNotice::available()->latest('effective_at')->paginate(10, pageName: 'versions') : collect(),
        ]);
    }

    public function show(int $notice): View
    {
        abort_unless(config('privacy.enabled'), 404);
        $notice = PrivacyNotice::available()->findOrFail($notice);

        return view('privacy.notice', ['notice' => $notice]);
    }

    public function acknowledge(Request $request, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'notice_id' => ['required', 'integer'],
            'acknowledged' => ['required', 'accepted'],
        ]);

        DB::transaction(function () use ($request, $validated, $audit): void {
            $notice = PrivacyNotice::current(lock: true);
            abort_unless($notice && $notice->id === (int) $validated['notice_id'], 409, 'The notice changed. Return to Privacy & Data Protection and review the current version.');

            $acknowledgement = $notice->acknowledgements()->firstOrCreate(
                ['user_id' => $request->user()->getKey()],
                ['acknowledged_at' => now()],
            );

            if ($acknowledgement->wasRecentlyCreated) {
                $audit->record('privacy.notice.acknowledged', $notice, ['version' => $notice->version], includeRequestMetadata: false);
            }
        }, attempts: 3);

        if ($request->session()->get('url.intended') === route('leave.verify.scan')) {
            $request->session()->forget('url.intended');

            return redirect()->route('leave.verify.scan', status: 303);
        }

        return redirect()->route($request->user()->workspaceRoute(), status: 303);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\LeaveApprovalDocument;
use App\Services\AuditService;
use App\Services\LeaveApplicationDocumentService;
use App\Services\LeaveVerificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class LeaveVerificationController extends Controller
{
    public function landing(): Response
    {
        return response()->view('leave.verification-landing');
    }

    public function capture(Request $request): RedirectResponse
    {
        $token = $request->input('token');
        $request->session()->put('leave_scan_hash', is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) ? hash('sha256', $token) : null);

        if (! $request->user()) {
            $request->session()->put('url.intended', route('leave.verify.scan'));

            return redirect()->route('login');
        }

        return redirect()->route('leave.verify.scan');
    }

    public function scan(Request $request, LeaveVerificationService $verification, AuditService $audit): Response|RedirectResponse
    {
        $digest = $request->session()->get('leave_scan_hash');

        try {
            $document = $digest ? LeaveApprovalDocument::where('verification_token_hash', $digest)->first() : null;
            if ($document && hash_equals($document->verification_token_hash, $digest) && $verification->canView($request->user(), $document->leave)) {
                $request->session()->forget('leave_scan_hash');

                return redirect()->route('leave.verify.show', $document);
            }
        } catch (QueryException) {
            return $this->result('unavailable');
        }

        $audit->record('leave.document_verified', $request->user(), ['status' => 'invalid']);

        return $this->result('invalid', code: 404);
    }

    public function search(Request $request): Response|RedirectResponse
    {
        abort_unless($request->user()->isHr(), 403);
        $search = $request->validate(['reference' => ['nullable', 'string', 'max:100']]);
        if (blank($search['reference'] ?? null)) {
            return $this->result('search');
        }

        try {
            $document = LeaveApprovalDocument::where('document_reference', $search['reference'])->first();
        } catch (QueryException) {
            return $this->result('unavailable');
        }

        return $document ? redirect()->route('leave.verify.show', $document) : $this->result('invalid', code: 404);
    }

    public function show(Request $request, int $document, LeaveVerificationService $verification, AuditService $audit): Response
    {
        try {
            $record = LeaveApprovalDocument::with('leave.approvalDocument')->find($document);
            if (! $record || ! $verification->canView($request->user(), $record->leave)) {
                $audit->record('leave.document_verified', $request->user(), ['status' => 'invalid']);

                return $this->result('invalid', code: 404);
            }
            $state = $verification->inspect($record);
            $audit->record('leave.document_verified', $record->leave, ['document_id' => $record->id, 'version' => $record->version, ...$state]);

            return $this->result($state['status'], $record, $state['integrity'], $state['status'] === 'unavailable' ? 503 : 200);
        } catch (QueryException) {
            return $this->result('unavailable');
        }
    }

    public function original(Request $request, LeaveApprovalDocument $document, LeaveVerificationService $verification, LeaveApplicationDocumentService $documents, AuditService $audit): Response
    {
        abort_unless($verification->canView($request->user(), $document->leave) && in_array($document->leave->status, ['approved', 'revoked'], true) && $document->leave->finalized_at, 404);
        $pdf = $documents->bytes($document);
        $audit->record('leave.approved_pdf_accessed', $document->leave, [
            'document_id' => $document->id, 'version' => $document->version, 'document_reference' => $document->document_reference, 'mode' => 'verification_original',
        ]);
        $response = response($pdf, 200, ['Content-Type' => 'application/pdf', 'Cross-Origin-Resource-Policy' => 'same-origin']);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $document->document_reference.'.pdf'));

        return $response;
    }

    public function revoke(Request $request, LeaveApprovalDocument $document, LeaveVerificationService $verification): RedirectResponse
    {
        abort_unless($request->user()->isHr(), 403);
        $data = $request->validate($this->decisionRules());
        $verification->revoke($request->user(), $document, $data['reason']);

        return redirect()->route('leave.verify.show', $document)->with('status', 'Leave request and official document revoked.');
    }

    public function replace(Request $request, LeaveApprovalDocument $document, LeaveVerificationService $verification): RedirectResponse
    {
        abort_unless($request->user()->can('review', $document->leave), 403);
        $data = $request->validate($this->decisionRules() + ['signature_version' => ['required', 'integer', 'min:1']]);
        $replacement = $verification->replace($request->user(), $document, $data['reason'], (int) $data['signature_version']);

        return redirect()->route('leave.verify.show', $replacement)->with('status', 'Replacement document signed and issued.');
    }

    private function decisionRules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:500'], 'current_password' => ['required', 'string', 'current_password'], 'confirmation' => ['accepted']];
    }

    private function result(string $state, ?LeaveApprovalDocument $document = null, ?string $integrity = null, int $code = 503): Response
    {
        return response()->view('leave.verification', [
            'state' => $state,
            'document' => $document,
            'integrity' => $integrity,
        ], $state === 'search' ? 200 : $code);
    }
}

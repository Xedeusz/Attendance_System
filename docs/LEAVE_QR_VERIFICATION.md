# Approved leave document QR verification

A successful Supervisor **Approve & Sign** issues the official LMPC HRD No. 23/Rev.2 PDF. BaconQrCode, already installed with the application, embeds a 27 mm QR in the footer. The 4-module quiet zone sits outside the watermarked form. Each issue has an immutable `LMPC-LA-{request ID}-{version}` reference and a random 256-bit token. The database stores a unique SHA-256 digest of the token. The encrypted private PDF contains the token in its QR and clickable verification link.

## Scan or verify an issued document

Open the QR link on a phone or select **Verify Document** from the approved leave detail. HR can also select **Find official document** in Leave Monitoring and search the exact reference. QR links use the URL fragment for the token, keeping it out of the HTTP request URL and access logs. The verification landing page uses JavaScript and a CSRF-protected form to submit the token and keep its digest in the session. Guests sign in through the existing login and return to the verification scan. Never log POST bodies for the scan route.

HR can verify company documents. Employees can verify their own documents. Supervisors can verify their own leave or records they are currently assigned to review. Invalid references and records outside the viewer's scope receive the same result. Each successful lookup shows the issuance metadata and verifies the SHA-256 of the decrypted stored PDF. An inaccessible file reports verification unavailable. Altered decrypted bytes show an integrity mismatch. **View Official PDF** streams the exact stored version for comparison. A QR scan identifies the issued record. Compare any printed or supplied copy with the authoritative PDF before relying on its contents.

## Revocation and replacement

HR can revoke the current document from the leave details with their current password, a reason and confirmation. The assigned Supervisor can issue a replacement from the existing approved request with the current password, registered signature version, reason and explicit signing confirmation. Replacements use the original approved application and chargeable-day data. A replacement has its own reference, token and private PDF, and the earlier document resolves as a previous version. Revocation and replacement preserve document history and balances. Revocation changes the leave request to Revoked and preserves the original approval and PDF for history. Replacement is available while the leave is Approved.

Legacy PDFs remain available for reference and internal verification. They keep their original issued bytes. If a legacy approval has no saved application snapshot, replacement is unavailable until an authorized correction process establishes the original details.

## Deployment

Use the authorized browser-accessible HTTPS hostname in `APP_URL`. The optional `LEAVE_VERIFICATION_URL` overrides the QR base URL and must point to the same authenticated application. Production rejects insecure or localhost QR URLs. Phone tests require a reachable, configured test host. The local loopback URL works on the development machine. Apply the additive migration with `php artisan migrate --no-interaction` before issuing new PDFs.

Issuance, verification results, PDF access, revocation and supersession are audited. QR verification itself requires neither a queue worker nor a Reverb connection.

## Verification

The MariaDB migration and Vite build succeeded. All 258 PHPUnit tests and the targeted Playwright workflow passed. The Playwright check generated a real A4 PDF, opened its verification URL, signed in from a mobile-sized Edge viewport, verified the document and confirmed the authoritative PDF action. A rendered one-page sample showed the QR in the clear footer alongside the preserved watermark and signature. Physical phone-camera decoding and a physical printer were outside these checks.

## Files

Added app/Http/Controllers/LeaveVerificationController.php, app/Services/LeaveVerificationService.php, database/migrations/2026_11_18_023245_add_verification_to_leave_approval_documents.php, resources/views/leave/verification.blade.php, resources/views/leave/verification-landing.blade.php, and docs/LEAVE_QR_VERIFICATION.md.

Updated app/Http/Controllers/LeaveController.php, app/Http/Middleware/RestrictSupervisor.php, app/Http/Responses/RoleLoginResponse.php, app/Models/LeaveApprovalDocument.php, app/Models/LeaveRequest.php, app/Services/LeaveApplicationDocumentService.php, app/Services/WorkspaceSync.php, config/leave.php, and routes/phase5.php.

Updated resources/views/components/topbar.blade.php, resources/views/leave/index.blade.php, resources/views/leave/official-form.blade.php, resources/views/leave/show.blade.php, tests/Browser/phase5.spec.js, and tests/Feature/OfficialLeaveFormTest.php.

Updated docs/DATABASE_SCHEMA.md, docs/LEAVE_APPROVAL_WORKFLOW.md, docs/LIMCOMA_OFFICIAL_LEAVE_FORM.md, docs/PHASE_5_IMPLEMENTATION.md, docs/RBAC_PERMISSIONS.md, and docs/SUPERVISOR_E_SIGNATURE.md.

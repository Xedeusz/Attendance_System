# Camera implementation — Phase 4

Primary files: `resources/js/attendance.js`, `resources/views/attendance/capture.blade.php`, `app/Services/AttendancePhotoStorage.php`, and `app/Http/Requests/AttendanceSubmissionRequest.php`.

## Browser lifecycle

Opening the dashboard or attendance page does not start the camera. After selecting an action, the employee explicitly presses Start live camera. `navigator.mediaDevices.getUserMedia({video: ..., audio: false})` requests a user-facing camera with ideal 1280×720 resolution. The video uses `autoplay`, `playsinline`, and `muted` for mobile compatibility. CSS contains the whole frame rather than cropping/stretching it.

After permission, `enumerateDevices()` discovers cameras. Switch Camera stops the previous tracks and requests another available device ID. A single camera has no switch control. If the device rejects a selection, the employee can restart the default camera. Device names/IDs are not saved to attendance records.

Capture Photo copies the actual video frame to Canvas at its original aspect ratio, with maximum long side 1280, and makes a JPEG Blob at quality 0.82. The employee previews the Blob through a temporary object URL. Retake revokes/discards the previous image and restarts preview. Cancel, capture completion, navigation/page hide, component destruction and camera changes stop media tracks. Backgrounding stops preview. No image is retained by the server before submission.

There is no file input, capture-attribute upload, drag-and-drop or gallery fallback. Camera failure leads to HR assistance. The multipart HTTP photo field transports the generated Blob; it is not a gallery-selection feature.

## Validation and failure handling

Permission denial, unavailable camera, camera busy, insecure context, unsupported API, initialization/switch failure, invalid image, expired capture, timeout and uncertain network results have actionable messages. No image GPS/timestamp stamps are overlaid as authenticated evidence.

Backend accepts actual JPEG/PNG only, at most 2 MiB, decoded dimensions at least 240 per side, no side above 4096, and at most 8 megapixels. GD decodes and normalizes to a JPEG with long side at most 1280 and quality 82. This strips metadata and appended source content; original filenames are discarded. Normalized hashes signal repeats by the same employee. Repeats require a retake or explicit HR review; matching images do not by themselves prove fraud.

The project helper loads `scripts/php/attendance.ini` using process-only `PHP_INI_SCAN_DIR`; no global PHP configuration was changed. Always use `scripts/dev.ps1` for serve/test, or enable GD in your actual PHP runtime. Artisan test launches a child PHP process, so `php -d extension=gd artisan test` alone does not propagate that option. Apache/FPM needs its own correct GD configuration.

## Mobile HTTPS testing

Both camera and location need a secure context; desktop `http://127.0.0.1:8000` qualifies for local testing. A phone visiting `http://<PC-LAN-IP>:8000` generally does not. Do not enable insecure browser flags as a workaround. [MDN getUserMedia](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia) and [MDN secure contexts](https://developer.mozilla.org/en-US/docs/Web/Security/Secure_Contexts).

Reliable local option using the existing XAMPP Apache, configured manually by the workstation owner/IT:

1. Obtain a development server certificate from a trusted company/development CA. Its SAN must contain the actual LAN IP or DNS name used by the phone. Install only the CA public certificate in the phone's trust store; keep every private key on the PC. iOS may require explicitly enabling full trust; use the device's documented certificate-management procedure.
2. Configure an Apache HTTPS VirtualHost on 443 with `DocumentRoot "C:/xampp/htdocs/Attendance_System/public"`, the matching `ServerName`, `SSLEngine on`, and the certificate/key file paths. Allow overrides/Require all granted **only for `public/`**, with rewrite enabled. Serve Laravel directly through a compatible PHP runtime (the app requires PHP 8.3+), not the repository root. Confirm that this web PHP has GD, PDO and the required extensions. Do not assume Apache and CLI use identical PHP configuration.
3. In the existing private `.env`, set `APP_URL=https://<certificate-name-or-IP>` and `SESSION_SECURE_COOKIE=true`; keep credentials/APP_KEY unchanged. Run `php artisan config:clear`. Rebuild assets if needed and stop a Vite dev server that advertises an unreachable HTTP host. Use compiled assets for this test.
4. Resolve the certificate DNS name to the PC on both devices, or use the certificate's IP SAN. Put PC/phone on the authorized LAN and allow only that private network through the firewall to port 443. Restart Apache through XAMPP. Do not expose MySQL, private storage, or the workspace root.
5. Open the exact HTTPS URL on the phone. Require a clean trusted padlock and `window.isSecureContext === true`. Sign in, allow camera/location, and exercise the checklist in `PHASE_4_TEST_RESULTS.md`. An HTTPS certificate warning is not a passing setup.
6. Restore the local URL/cookie settings if returning to the HTTP localhost server; clear config again. If compatible Apache PHP/CA trust is unavailable, use a company-approved private HTTPS staging host. No deployment or new web server package was installed in this phase.

## Limitations

The UI uses real camera APIs, but browsers and HTTP cannot cryptographically attest physical camera origin or freshness. Virtual cameras, modified requests and fabricated pixels remain possible. Capture tokens coordinate transactions; they do not prove capture authenticity. This implementation does not recognize identities, require a visible face, or perform liveness detection. Test real iOS Safari and Android Chrome before rollout; automated Edge streams are synthetic.

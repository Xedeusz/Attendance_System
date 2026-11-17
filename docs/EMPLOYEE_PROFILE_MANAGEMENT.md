# Employee profile pictures

## Profile and ownership

The existing `/employee/profile` and `/profile` views retain HR-controlled read-only identity, employee number, department, branch, role, employment and account status. The existing contact-number editing workflow is preserved. A header now displays the employee identity, department, branch, status and circular avatar.

One nullable `users.profile_photo_path` stores the authoritative relative file reference. It is not mass assignable and is hidden from User JSON. No Employee photo column, biometric data, or attendance-photo linkage is introduced. Initials use the employee first/last name fields (preserving compound family-name initials), with name-word fallback for unlinked accounts, with a purple circular fallback. Missing/inaccessible photos fall back after image-load failure rather than displaying a broken icon.

Employees with active linked profiles may upload/replace/remove only their own photo. HR photo-editing permissions were not added. Supervisors retain leave-only restrictions and existing personal utilities. The own-image GET is available to an authenticated account, including a former employee promoted to another role, but never accepts a target user ID. No route can read a colleague's image by substituting an account ID.

## Upload and storage

`POST /employee/profile/photo` validates a maximum 2 MB upload and actual JPEG, PNG or WebP MIME/content. SVG, executable content and spoofed protected fields are rejected. GD validates decoded content, 32–4096 pixels per side and a maximum eight megapixels, then normalizes it to a JPEG of at most 512 pixels on its longest side. Aspect ratio is preserved; metadata is stripped and transparency is placed on white. No new image-processing library is required.

Files are named with generated UUIDs and stored under `storage/app/private/profile-photos` using the existing non-served `local` disk. There is no public storage link or arbitrary filename/path input. Private owner-only `GET /profile/photo/{version}` resolves the currently authenticated account's saved reference; the version is a SHA-256 of its generated path. An old version or missing file returns 404. Responses use image/jpeg, nosniff and private/no-store headers. Every replacement has a new URL, avoiding stale image caches.

The new file is staged before an own-user row-locked transaction. Current eligibility is rechecked inside the transaction; the reference and audit change commit together. The old image is deleted only after commit. Failed writes/transactions keep the old reference and attempt staged-file cleanup. Cleanup errors are reported without changing the confirmed committed result. Cleanup accepts only generated paths in the dedicated profile-photos directory, never attendance images, leave documents or shared defaults.

Audit actions: `profile.photo_updated`, `profile.photo_removed`. The additive migration is `2026_11_17_032344_add_profile_photo_path_to_users_table.php`; it was applied locally without resets or account duplication. Rollback refuses to discard populated image references.

## Interface and synchronization

Change Photo opens the native PC/mobile image picker. Selection produces a local circular preview via an object URL, with Save Photo and Cancel. Choosing or canceling a preview does not update the saved avatar. Object URLs are revoked on cancellation, replacement or component destruction. Saving disables conflicting controls and displays progress. Failures retain the current saved avatar and show an inline error.

Remove Photo uses the existing SweetAlert confirmation, then an authenticated DELETE to the own-photo endpoint. The database reference is cleared before initials replace the image. Ordinary successful operations use the existing compact three-second SweetAlert feedback, without duplicate error toasts.

One Alpine avatar store supplies the profile header, dashboard, navbar menu trigger and account-dropdown summary. The direct successful response updates every avatar immediately without navigation, even when WebSockets are unavailable. A request-start guard prevents an older background state response from overwriting a newer direct save. Existing private workspace revisions propagate changes to other open sessions, which retrieve only their own avatar metadata through `/realtime/state`; no private image path or image binary is broadcast. The shared avatar component is for the authenticated account, not a colleague directory.

Authentication, active-account checks, CSRF and separate upload/read rate limits remain in force. Photo routes cannot modify role, employee ownership, branch, department or other HR fields. Attendance remains live-camera-only and its photo/GPS verification code is unchanged.

## Verification and limitations

Eight focused PHP tests cover JPG/PNG/WebP, normalized private serving, own-account isolation, replacement cleanup, removal and missing files, invalid/oversized/spoofed uploads, HR/Supervisor/unlinked-account denial, real CSRF rejection, transaction rollback and filesystem write failure. The final full suite passed 217 tests/1,493 assertions. The focused avatar tests were rerun after adding compound-family-name initials coverage.

Microsoft Edge passed one multi-session scenario covering preview/cancel, explicit save with the uploading session's WebSocket disconnected, direct navbar/dropdown updates, a second session's dashboard updates, versioned replacement, confirmed removal, no main-frame navigation, and widths 320/390/768/1440. Build, formatter and patch checks passed. Early test runs caught a Blade/Alpine `@error` name collision (corrected to `x-on:error`) and an ambiguous test selector after adding the dropdown avatar; affected tests were rerun.

Optional interactive cropping was not introduced. Circular presentation uses aspect-preserving object-fit; the stored normalized image retains its rectangular proportions. Actual Android/iPhone picker behavior and production storage/HTTPS were not tested. No profile-photo permission was added for editing other users. Deployment must provide GD with JPEG/PNG/WebP support, persistent private storage and the additive migration.

## Files

- `app/Models/User.php`: private reference hiding and own-avatar metadata/initials.
- `database/migrations/2026_11_17_032344_add_profile_photo_path_to_users_table.php`: nullable reference.
- `app/Http/Requests/ProfilePhotoRequest.php`: employee authorization, content/size/protected-field validation.
- `app/Services/ProfilePhotoService.php`: normalization, transactional replacement and safe cleanup.
- `app/Http/Controllers/ProfilePhotoController.php`, `routes/web.php`: own-account upload/remove/private reads.
- `app/Http/Middleware/RestrictSupervisor.php`: own-avatar read utility only.
- `app/Http/Controllers/WorkspaceSyncController.php`, `resources/js/realtime.js`: private metadata synchronization.
- `resources/js/profile-photo.js`, `resources/js/app.js`: preview/upload/remove and shared store.
- `resources/views/components/user-avatar.blade.php`, `resources/views/components/topbar.blade.php`: shared avatar and account menu.
- `resources/views/employee/profile.blade.php`, `resources/views/employee/dashboard.blade.php`: profile header/controls and dashboard display.
- `tests/Feature/ProfilePhotoTest.php`, `tests/Browser/profile-photo.spec.js`, `tests/Browser/fixture.php`: regression coverage and scoped QA-file cleanup.
- This document and README cross-reference. The existing landing-page stylesheet/image changes belong to the earlier task and were preserved.
## Attendance avatar integration

Attendance pages reuse the existing user-avatar component and private, versioned profile-photo routes. A shared attendance-identity component shows employee name, Employee ID and branch with a 56 px avatar on desktop, 48 px on tablet and 44 px on small screens. HR Attendance Details and employee Attendance Details display the record employee; employee history and My Workday display the authenticated employee. HR Monitoring uses 34 px avatars beside employee names without adding a table column. Initials and failed-image fallback retain the existing LIMCOMA style. No new image storage, dependencies, migrations, image endpoints or permissions are introduced.

AttendanceQueries and the details controller eager-load employee.user.employee so avatarData does not cause a separate inverse employee query per row. A regression compares database query counts for one versus five employees. Existing private hash-version URLs change on successful photo replacement; old URLs stop serving the replaced photo. Current avatars may change while historical Time In/Out camera photos, their encrypted bytes, event coordinates, timestamps and review records remain unchanged.

The existing per-user revisions, Reverb updates and polling fallback refresh HR pages; the authenticated employee avatar store updates own pages. No image bytes or storage paths are broadcast. Supervisors retain leave-only access, and unrelated employees cannot fetch another employee's protected avatar. A missing avatar never blocks attendance, map or camera workflows.

Integration files:

- resources/views/components/attendance-identity.blade.php (new)
- resources/views/components/user-avatar.blade.php (early image-error fallback)
- resources/views/attendance/show.blade.php
- resources/views/attendance/index.blade.php
- resources/views/employee/dashboard.blade.php
- app/Services/AttendanceQueries.php
- app/Http/Controllers/EmployeeAttendanceController.php
- resources/css/app.css
- tests/Feature/AttendanceAvatarTest.php (new)
- tests/Browser/attendance-location-map.spec.js
- docs/EMPLOYEE_PROFILE_MANAGEMENT.md

Tests exercise all attendance avatar surfaces, HR/owner and denied role boundaries, missing/removed avatars, image versions, immutable evidence and fixed query counts. The existing Edge attendance regression additionally checks real uploads, own Workday/history/details avatars, HR table/details avatars, failed-image fallback, responsive layouts and photo removal propagation with the viewer's Reverb disconnected.

The shared avatar also checks for an already-failed image after initialization, covering failures that happen before its error listener attaches. The browser test foregrounds the HR page for lazy images and visibility-driven synchronization, consistent with the application's background-tab behavior.

Attendance avatar verification: the full PHP suite passed 243 tests / 1,831 assertions. After hardening image fallback, the focused attendance-avatar, profile-photo and profile-access suites passed 18 tests / 210 assertions. The Edge attendance regression passed with own dashboard/history/details avatars, HR monitoring/details avatars, forced image failure initials, responsive widths, map controls, tile failure, and profile photo removal updating the foreground HR page through existing sync while original camera photos remained present. Synthetic fixtures were cleaned up. Production build, Blade compilation, Pint, Prettier and whitespace checks passed.

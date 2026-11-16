# SweetAlert2 notifications

SweetAlert2 11.26.25 is installed through npm and imported once, with its CSS, by `resources/js/notifications.js`. Vite bundles it; `resources/js/app.js` initializes it for HR, Employee, guest and authentication pages. No notification dependency or backend business rule changed in this redesign.

## Shared API

```js
import { notify } from './notifications';
notify.success('Changes saved.');
notify.error('Unable to Save', 'Review the form and try again.');
notify.warning('Review the highlighted fields.');
notify.info('Attendance pending review.');
notify.toast('success', 'Changes saved.'); // Compatible shorthand
if (await notify.confirm({ title: 'Confirm?', text: 'Review this action.', confirmText: 'Continue' })) {
    // Resume the existing authorized action.
}
notify.destructive({ title: 'Delete?', confirmText: 'Yes, Delete' });
notify.loading('Please wait...');
notify.closeLoading();
notify.close();
notify.ajaxError(response.status);
```

`window.notify` is the same service. All ordinary success/error/warning/info methods use one toast mixin. Optional second arguments are combined with the title as plain text. Only trusted static SVG icon markup is interpreted as HTML; server and user messages are never HTML.

## Global ordinary toast configuration

- `toast: true`, `position: 'top-end'`, `timer: 3000`.
- No Confirm, Cancel, OK or close button; no progress bar or backdrop.
- No hover/focus timer pause and no mouse-driven timer restart.
- Maximum 330px width bounded by the viewport, 12px edge offsets with top/right safe-area support.
- White card, 1px border, 12px corners, soft shadow, 10px/12px padding, left-aligned 12.5px/600 dark text and 18px static icons.
- Success icon: dark teal #087C68; error: red #DC2626; warning: amber #D97706; information: LIMCOMA purple #2F1B72. Semantic meaning is also conveyed through text and icons.
- Status announcements are set during render; ordinary feedback does not take keyboard focus.

Matching notifications are deduplicated for 3000ms, with a bounded 50-entry cache. A different ordinary message replaces the current toast rather than creating a stack. Confirmations are serialized and cannot be replaced by ordinary feedback: while a confirmation or operation-loading indicator is active, only the latest ordinary message is retained and shown afterward.

## Animation and timing

`limcoma-toast-enter` slides 6px from the right and fades in over 220ms. `limcoma-toast-exit` fades and slides right over 200ms. There is no bounce, scale or animated success circle. Reduced-motion users receive no animation. These classes apply to ordinary toasts, not destructive confirmations.

SweetAlert starts its **3000ms dismissal timer** when the toast opens. On expiry it runs the 200ms exit animation, then removes the popup; the full normal-motion lifecycle is therefore approximately 3200ms. There is no extra delay before the exit animation and hovering does not extend the timer. Reduced-motion removal occurs at approximately 3000ms. Browser scheduling may affect measured wall-clock timing.

## Integration and persistent information

`resources/views/components/notifications.blade.php` safely serializes Laravel status/success/error/warning/info flashes once into an escaped data attribute. The consumed marker prevents duplicate initialization. Both global layouts use the service. Validation takes precedence over success feedback. Duplicate branch codes now show an ordinary error toast and retain their inline field error. Other validation failures show a warning summary. Profile/password status identifiers receive readable labels. The shared workspace validation banner is a JavaScript-disabled fallback, avoiding a duplicate banner behind the toast.

HR branch, department, employee, dashboard, review, profile and settings pages inherit the shared flash integration. Employee dashboard, profile and attendance feedback use the same configuration. Photon errors and account activation AJAX failures use `notify.ajaxError`, without exposing internal server exceptions or clearing map/form state. Search progress/no-results remain inline.

Camera/GPS errors, geofence warnings and uncertain attendance responses now use ordinary toasts alongside persistent page guidance. Attendance upload retains an operation-bound loading indicator. Server-confirmed results alone trigger success; pending/unverified attendance retains warning wording. Rejection reasons, result cards, inline field validation, authentication form errors, instructions, Notifications history and audit records remain available after the toast disappears. Camera capture, GPS checks, timestamps, idempotency, authorization and CSRF protections are unchanged.

## Confirmation and loading exceptions

Logout, deactivate/reactivate, inactive-status transitions, HR review decisions and existing DELETE forms retain meaningful Confirm/Cancel actions. Confirmation focuses Cancel and supports Escape and focus restoration. No 3000ms timer applies, and no action is automatically approved or canceled. Confirmations retain their compact top-right presentation without ordinary-toast slide animations. The original protected forms, submitters, acknowledgment checkboxes and duplicate-submit guards are preserved. No new deletion endpoint is introduced. Photon location selection now applies available branch details immediately, without an inline confirmation, popup or selection toast; genuine operation confirmations remain unchanged.

Loading indicators have no timer and remain until the pending operation completes or fails. Ordinary messages cannot replace them. `closeLoading` closes only operation feedback; it never closes an active confirmation.

## Files modified for this redesign

- `resources/js/notifications.js`
- `resources/css/app.css`
- `tests/Browser/notifications.spec.js`
- `tests/Browser/attendance.spec.js`
- `tests/Browser/location-search.spec.js`
- `docs/SWEETALERT2_NOTIFICATIONS.md`

## Verification

Use the local Node toolchain and XAMPP PHP with the configuration from `scripts/dev.ps1`. The application and MariaDB must be running for browser tests.

```powershell
npm.cmd run format:check
npm.cmd run build
npm.cmd run test:browser
```

The notification suite checks all four public message methods, real timed dismissal while hovered, no buttons/progress bar, semantic colors, plain-text safety, duplicate prevention, top-right coordinates, compact alignment at 320/360/375/390/430/1440px, reduced motion, deferred feedback, confirmation survival beyond three seconds, Escape/Cancel behavior, exactly-once employee deactivation, logout cancellation and actual branch validation/save flash feedback. Attendance and Photon tests now await automatic dismissal instead of clicking OK.

Executed for this redesign: **all 14 browser tests passed (2.5 minutes)**, including all four notification tests, attendance, account management, authentication, branch/map and Photon workflows. Vite production build, Prettier format check and `git diff --check` passed. The error-toast screenshot was visually inspected. Backend tests were not rerun because this change modifies frontend presentation and browser assertions only.

Physical Android Chrome/iPhone Safari and notched-device safe-area testing remain manual; automated browser checks use desktop Edge with mobile viewport sizes. No automated integration failures remain.

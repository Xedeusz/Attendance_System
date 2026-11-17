# Real-time verification and manual checks

## Automated setup

Keep XAMPP MySQL, the HTTP app on 8000, Reverb on 8080 and the realtime/default queue worker running as described in REALTIME_SYSTEM_ARCHITECTURE.md. Build assets or run Vite. Use the project-scoped PHP extensions:

```powershell
$env:PHP_INI_SCAN_DIR = Join-Path (Get-Location) 'scripts/php'
& 'C:\xampp\php\php.exe' artisan test --compact
```

Browser tests use the installed Microsoft Edge channel and local MariaDB, with isolated QA accounts and cleanup limited to those fixtures. Run through the helper (all specs), or use the bundled Node/npm executable to target:

```powershell
$env:PATH = (Join-Path (Get-Location) '.tools/node-v26.13.1-win-x64') + ';' + $env:PATH
npx playwright test tests/Browser/realtime.spec.js tests/Browser/attendance.spec.js tests/Browser/phase5.spec.js
```

`PLAYWRIGHT_BASE_URL` may point to an isolated HTTP server; its APP_URL must match because the app uses named absolute routes. Synthetic camera/GPS inputs test workflow behavior rather than hardware identity or physical presence.

## Executed checks

- WorkspaceSyncTest: 13 passing tests, including private channel ownership/role/version rejection, own-user revision isolation, supervisor restrictions, committed attendance recipients, rollback suppression, queued recipient revocation, minimal payloads, notification creation/read, filtered authorized fragments, same-second stale/missing edit versions, queue dispatch outage without persistence loss, background flash preservation, and Supervisor/leave-policy stale-edit rejection.
- Final complete PHP suite: 203 tests passed, 1,376 assertions.
- Microsoft Edge combined targeted run: all five scenarios passed (4.6 minutes). Realtime scenarios passed: two HR sessions receiving branch edits with no navigation; filter and unsaved draft preservation; stale-save error; WebSocket disconnect, polling update and reconnect; employee submission appearing in the Supervisor queue; final Supervisor approval updating an employee's already-open history/balance and notification badge; department edits and employee creation updating another HR session's directory/dashboard counts.
- Microsoft Edge attendance scenario passed with a separate live HR monitoring window, synthetic camera/GPS, normal Time In/Out, malformed HTML 429, lost response after commit, refresh recovery, private evidence, HR review, mobile widths and no SweetAlert warnings.
- Microsoft Edge Phase 5 scenario passed with live final decisions, correction queue/detail updates, protected documents, HR-only correction review and responsive screens.
- Vite production build passed. Pint and diff whitespace checks passed during implementation.

Initial browser runs found a missing visible stale-save message and background requests consuming navigation flash feedback; both were repaired and the affected scenarios rerun successfully. The first combined observer run also timed out at camera startup; the affected attendance scenario passed on its focused rerun. Do not count those initial failed attempts as passing runs.

## Manual multi-user scenarios

1. HR to HR: keep the dashboard open in one browser and Employee Management in another; create an employee and observe counts/recent employees without navigation. Repeat with a filtered directory, branch status/name/geofence and department edits.
2. Safe branch editing: leave an unsaved marker/name/radius draft in A; save the same branch in B. A retains its draft and shows saved-data feedback. Saving the stale draft is rejected; deliberately review latest data before reapplying changes.
3. Employee to HR: keep HR Attendance Monitoring and employee history open; perform valid live-camera/GPS Time In/Out. Monitoring/history and action availability update from committed data. Interrupt WebSocket delivery; direct attendance submission must still work.
4. Employee to Supervisor: assign the employee to an active Supervisor, open the Supervisor pending queue, then submit leave from a separate employee browser. The assigned queue and unread badge update. Another supervisor cannot fetch the request or its supporting file.
5. Supervisor to Employee: keep the owner's leave history open, then approve/reject as the assigned Supervisor. The final status/balance appears without reload. HR monitoring has no final-approval action.
6. Corrections: keep HR's correction queue and an employee's correction detail/history open. Submit and approve/reject. Only HR and the owner receive the resulting attendance/correction changes; original evidence is retained.
7. Notifications: open the same account in two tabs. Create a notice or mark it read. Counts/list state converge automatically. A Supervisor sees only leave notices.
8. Revocation: revoke an assignment or deactivate/change a user's role while its tab is open. Protected fetches immediately use current permissions; previous-version jobs cannot publish new protected payloads. A stale private subscription never carries record details.
9. Network: disconnect the client socket and change data elsewhere. Expect polling within the configured interval, then automatic resynchronization after reconnect. Hidden tabs skip polls and resynchronize when visible.
10. Worker outage: stop only the queue worker, perform a valid change, and verify persistence/direct HTTP feedback. A still-connected socket may lag until the 60-second quiet heartbeat. Restart the worker and check delayed updates. Monitor failed jobs rather than discarding them silently.
11. Mobile/tunnel: use HTTPS plus separately reachable WSS, correct allowed origins and secure cookies. Verify Android/iPhone camera permissions and suspension/reconnect. Do not substitute desktop viewport tests for physical hardware verification.

## Limits and rollout work

No production deployment, remote HTTPS/WSS tunnel, physical Android/iPhone browser, reverse proxy, high-volume load test, or multi-worker database lock stress test was executed. Routine background fragments reuse existing controller queries, so load should be measured against the actual employee/branch volume before production scaling. The healthy-socket heartbeat is 60 seconds; stalled workers do not guarantee instant delivery. Active write forms retain their options/drafts and require a deliberate review of changed saved data.
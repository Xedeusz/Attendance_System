# Real-time system architecture

The existing Blade/Alpine application now uses Laravel Reverb 1.13, Laravel Echo 2.6, Pusher JS, and Alpine Morph. Laravel is 13.42 and PHP is 8.5. No frontend framework was replaced. MySQL remains authoritative.

## Committed changes and delivery

Existing audited domain operations call `WorkspaceSync::record` through `AuditService`. Notification creation/read calls `touch` for the owner. The service maps each successful change to authorized recipients and topic revisions. `workspace_revisions` has one row per user/topic, with a UUID revision, and is written in the same transaction as the domain change. Rollback removes both. No attendance validation rejection creates a success revision.

A database after-commit callback dispatches `BroadcastWorkspaceChange` to the `realtime` queue. Queue dispatch errors are reported without turning an already committed business operation into an HTTP failure. The queued job checks the recipient's current active account, role, and session version before publishing `WorkspaceChanged` through Reverb. Transport errors retry after 2, 10 and 30 seconds, up to four attempts, then use Laravel's failed-jobs reporting. A stopped worker delays delivery but never prevents core persistence.

The only WebSocket event name is `.workspace.changed`. Its payload contains a map of topic names to opaque revision UUIDs. It deliberately contains no record IDs, employee profiles, medical information, images, file URLs, coordinates, or business action success assertions. UUIDs identify invalidated data scopes rather than expose private record identities. The audit trail retains domain-specific operation names and timestamps. Clients never write records through WebSocket messages.

## Private channels and revocation

Channel: `private-workspace.{userId}.{sessionVersion}.{roleId}`. `/broadcasting/auth` uses the existing web authentication/session and active-account middleware. Its callback reloads the account and requires an exact match to its own channel. There are no public, company-wide employee, or record-based supervisor subscriptions.

HR receives authorized administrative invalidations. Employees receive their own attendance, leave, correction, and notification changes, plus relevant directory/policy changes. Supervisors receive only leave, account, and their own notification topics. Reassigned former supervisors may receive a generic leave invalidation so their queue can remove data; they receive no leave identifiers or details in that message. Protected refresh routes reevaluate current assignment/policies on every request.

When role/session version changes, new events go to the new channel. Queued jobs for an obsolete channel are discarded. Deactivated accounts receive no new protected data. Existing middleware remains responsible for backend revocation. The client rechecks its channel/session on reconnect, visibility restoration, fallback polling, and the quiet-connection heartbeat.

## Authoritative refreshes

One Echo connection is initialized per page in `resources/js/realtime.js`. Events are debounced by 250 ms. One state request may run at a time; repeated events coalesce. Clients compare current server revisions rather than treating event ordering as authoritative, so duplicate or delayed signals cannot add duplicate rows or roll data back.

`GET /realtime/state` is authenticated and separately rate limited. It returns only the caller's allowed revisions, unread count, display name, channel identity, and optional own attendance availability. It cannot fetch another user's data.

Affected page datasets are retrieved from their existing authorized GET route, retaining the full current query string. `X-Workspace-Sync: 1` requests receive only the marked content fragment, without the shell/assets. The server still executes the existing controller's authoritative queries; it does not maintain a parallel set of dashboard calculations. Alpine Morph changes only differing DOM nodes. Filters, current page, sorting, and scroll remain in place. Unchanged map configurations are left alone; saved read-only maps are recreated only when their configuration changes.

All write forms and GET filter inputs are preserved during background refresh. Changed saved versions show a compact notice with a deliberate link to review saved data. Branch, department, employee, supervisor, and leave-policy edit forms carry an HMAC of the saved record state. Employee signatures include account and current-assignment state. Under the existing administration transaction lock, a missing/stale signature rejects the write and preserves submitted input. Leave decisions and corrections keep their existing serialized final-decision/version rules.

The camera/capture subtree is never morphed. It receives only authoritative attendance availability from the state endpoint; live camera, image preview, geolocation, pending identifiers, and uncertain-submission reconciliation are preserved. Other tabs and HR/history pages still refresh normally. Submission success continues to come from its direct HTTP response or original UUID reconciliation, independent of WebSocket delivery.

Background state, fragment, and channel authorization requests reflash session feedback so they cannot consume messages intended for a normal navigation.

## Connection recovery

- Live: a private subscription is established. Data refreshes follow events; a quiet-connection state heartbeat runs every 60 seconds to catch stalled workers, session revocation, missed changes and date-sensitive data on the next invalidation.
- Reconnecting/polling: when the connection is unavailable, state is checked every 15 seconds by default, configurable with `REALTIME_POLL_SECONDS` (minimum 10). Changed revisions refresh affected fragments. No independent per-widget polls are created.
- Offline: keep displayed data and working forms; resume on the browser's online event.
- Hidden tabs: skip polling. Visibility restoration forces an authorized resynchronization.
- Reconnect: Echo reauthorizes subscriptions and forces resynchronization. No repeated error toasts are emitted.

If a WebSocket remains connected but its worker stops, the 60-second heartbeat provides eventual recovery; it is not an instant delivery guarantee. Failed jobs must still be monitored/retried operationally.

## Windows/XAMPP development

The additive migration has been applied locally. Existing attendance and employee data were not reset. From the project root, open separate terminals:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/dev.ps1 serve
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/dev.ps1 reverb
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/dev.ps1 queue
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/dev.ps1 assets
```

The HTTP app listens on 127.0.0.1:8000; Reverb listens on 127.0.0.1:8080. XAMPP MySQL must be running. The queue helper processes `realtime,default` with four attempts and a 30-second timeout. The existing database queue and failed-job tables are used. XAMPP does not launch Reverb or the queue worker.

The helper preserves project-scoped GD/opcode-cache configuration using `artisan serve --no-reload`. Restart after `.env` changes. Ordinary PHP changes apply on the next HTTP request. Alternatively, Windows `composer run dev` now starts HTTP, Reverb, queue and Vite together. Do not launch duplicate processes on the same ports. A built frontend is sufficient without Vite: `scripts/dev.ps1 build` through PowerShell with the same execution-policy option.

The ignored local `.env` contains generated random Reverb key/secret values. `.env.example` has blank credential placeholders. Set unique app ID/key/secret on new installations. `REVERB_HOST/PORT/SCHEME` control internal server publishing. `REVERB_PUBLIC_HOST/PORT/SCHEME` control the browser endpoint and default to internal settings when omitted. Only the public app key/endpoint are rendered into the authenticated page; the app secret is never sent to the browser. Frontend configuration is read at runtime from the server, so VITE_REVERB_* duplication is unnecessary.

## Deployment and mobile HTTPS

Production requires supervised HTTP/PHP, a long-running Reverb process, and a dedicated queue worker with `--queue=realtime,default`. Set unique secrets, an internal publishing endpoint, a public WSS endpoint, and explicit comma-separated allowed origin hostnames. Reverb disables client-originated events. Configure the proxy to forward WebSocket Upgrade/Connection headers and `/app` traffic to Reverb; keep signed publishing APIs/internal ports private. Use normal trusted proxies, secure session cookies, HTTPS, and same-origin broadcasting authorization with CSRF/session protection as applicable to the framework's standard auth endpoint. Do not expose `.env` or private storage.

An HTTP-only tunnel for port 8000 does not expose port 8080. For remote mobile tests, separately route WSS through the TLS proxy/tunnel, set the public host/443/https variables, add the tunnel hostname to allowed origins, and restart HTTP/Reverb/workers. Keep camera/geolocation on HTTPS. Do not disable role, origin, session, GPS, or camera controls. Actual tunnels, TLS termination and physical mobile hardware were not provisioned by this change.

See REALTIME_EVENT_MATRIX.md and REALTIME_TESTING.md for coverage and verification.
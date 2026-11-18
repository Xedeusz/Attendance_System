# Notification dropdown

HR, Supervisors and Employees open notifications from the bell in the shared top bar. The bell shows the authenticated account's unread total and displays `99+` above 99. The dropdown shows the 10 newest notifications, with an unread indicator, relative time, and the stored summary and description where available. The history link opens the existing paginated notification view. The sidebar notification links were removed for all roles.

The bell accepts keyboard activation. The panel supports Escape, focus return, outside click, loading feedback, retry, reduced motion, and a scrollable mobile layout. The existing SweetAlert2 toasts continue to handle transient action feedback.

## Actions and access

Opening a notification marks it read and resolves a permitted local destination. The user can also mark an item read in place or mark every eligible notification read, including items outside the most recent ten. The authenticated, rate-limited endpoints use Laravel's existing database notifications and CSRF protection. Every query is scoped to the authenticated recipient. Supervisor queries also require the leave category.

Destination resolution checks the current record and policy before returning a named leave or attendance correction route. Missing or inaccessible destinations show an inline message. Notification history uses the same checks when opening an item.

## Live updates

The shared workspace synchronization publishes private user-specific revision signals through Reverb and Echo. The badge updates from the current workspace state, and an open dropdown reloads its bounded list when the notification revision changes. Notifications are keyed by database ID. Reopening the dropdown within 15 seconds reuses the latest successful list for that page. A changed revision or unread count, a reconnect, or a workspace fallback refresh invalidates it. Marking notifications read also refreshes an open dropdown. The browser keeps the list in the component's memory for the lifetime of the page.

## Verification

All 257 PHPUnit tests and both targeted Playwright scenarios passed. The tests checked account ownership, Supervisor filtering, HR correction destinations, revoked access, individual and bulk read status, the live unread count, opening a notification, Escape and focus return, mobile viewport fit, retry, and workspace fallback polling. The Vite production build passed. Mobile checks used an emulated viewport in Edge.

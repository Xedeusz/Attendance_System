# UI Foundation and Reusable Components

Blade + compiled Tailwind CSS 4 + Alpine.js, light theme only, extended through Phase 4. Branding is provisionally **BranchSync — Employee Attendance System**. No external font/CDN request is necessary; the system font stack falls back from Inter to installed UI fonts.

## Visual system

| Token/use | Value |
| --- | --- |
| Sidebar/auth panel | Deep navy `#111e34` |
| Main action/active navigation | Professional blue `#2458d3` |
| Accent | Teal `#6ce0cf` on dark background |
| Page / cards | Soft gray `#f5f7fb` / white |
| Main text | Slate `#27354b` |
| Secondary text | Slate gray; styles in `resources/css/app.css` |
| Borders | `#e5eaf2` |
| Success / warning / error | Green/teal, amber, red; always paired with text |
| Focus | Visible cyan/blue outline |

Titles use a 24–30px scale on application screens, section headings about 15px, form fields/body 14px, and smaller labels/captions. Shared CSS defines card, field, button, navigation, table and empty-state styles; Tailwind handles composition, spacing and responsive utility adjustments. CSS-first `@theme` and Vite produce local assets. No animated/decorative chart data is invented.

## Component inventory and exact paths

All paths below are relative to `C:\xampp\htdocs\Attendance_System`.

| Blade component path under `resources/views/components/` | Purpose |
| --- | --- |
| `layouts/guest.blade.php` | Login/recovery shell with responsive branding and asset includes |
| `layouts/app.blade.php` | Authenticated shell, breadcrumbs, navigation, footer and logout dialog |
| `sidebar.blade.php`, `topbar.blade.php` | Named-route navigation, active states, account menu, desktop collapse/mobile drawer |
| `brand.blade.php`, `icon.blade.php` | Consistent branding and decorative inline SVG icons |
| `breadcrumbs.blade.php`, `page-header.blade.php` | Current location, title, description and action slot |
| `button.blade.php` | Native button or valid link; primary/secondary/disabled variants |
| `input.blade.php`, `password.blade.php` | Labeled fields, validation associations, accessible visibility toggle |
| `form-errors.blade.php`, `alert.blade.php` | Validation summary and live status/error messages |
| `badge.blade.php`, `stat-card.blade.php` | Text status badges; unavailable metrics use a dash, never a fabricated count |
| `table.blade.php`, `search.blade.php` | Semantic headers/caption, focusable controlled scroll region, labeled disabled search |
| `empty-state.blade.php` | Reusable icon/title/explanation and optional slot |
| `loading.blade.php` | Reduced-motion-aware progress indicator; used during login submission |
| `modal.blade.php`, `confirm-logout.blade.php` | Named dialog, Escape/backdrop close, focus trap/return, real CSRF-protected sign-out |

Example composition:

```blade
<x-layouts.app title="Employees">
    <x-page-header title="Employees" description="Your people, in one place.">
        <x-button disabled>Add employee</x-button>
    </x-page-header>
    <x-empty-state title="Not connected yet" description="Employee management is a later phase." />
</x-layouts.app>
```

## Screens and honest states

- `resources/views/auth/`: working Fortify login; conditional recovery views and framework password confirmation.
- `resources/views/dashboard.blade.php`: Historical design previews; Phase 3 replaced them with actual HR and Employee dashboards. See PHASE_3_IMPLEMENTATION.md.
- `resources/views/workspace/module.blade.php`: Employees, Attendance, Branches, Leave, Reports and Notifications foundations driven by `config/workspace.php`.
- `resources/views/workspace/profile.blade.php`: only the authenticated user's real login name/email, read-only.
- `resources/views/workspace/settings.blade.php`: profile link, real logout, light appearance information, disabled future administrative settings.

Phase 3 master-data and Phase 4 attendance metrics now query real records; no employee/branch/attendance count is hardcoded. The dedicated attendance page uses 64px Time In/Out controls, native and Alpine eligibility disabling, a whole-frame live camera preview, retake/switch/cancel, GPS retry and explicit review requests. `resources/js/attendance.js` owns streams, object URLs, pending bodies and status handling. See [camera lifecycle](CAMERA_IMPLEMENTATION.md). History/monitoring filters, private photos and HR review are functional; leave/report exports remain unavailable. Review updates are database-backed refresh lists, not invented read/unread state. Server-synchronized display time is informational; authoritative punch time comes from Laravel.

## Responsive and interaction behavior

- At >=1024px, a fixed 244px sidebar accompanies the workspace; the header button collapses it to an 82px icon rail.
- Below 1024px, navigation becomes a drawer with backdrop, focus trapping, close button, Escape behavior and scroll lock. Closed navigation is translated offscreen; the drawer restores keyboard focus on exit.
- At <=640px, content padding is reduced, profile metadata is hidden, cards use two columns, action groups wrap and the auth page becomes one column.
- Two-column content sections stack below the desktop breakpoint. Grid children use zero minimum widths so wide table content stays inside its scroll region.
- Tables intentionally scroll inside a labeled keyboard-focusable container. The page itself must not overflow horizontally.
- Account popover uses normal links/buttons, outside-click and Escape. Logout is confirmed in an accessible dialog.
- Semantic landmarks, skip link, input labels, `aria-current`, `aria-expanded`, validation feedback, focus indicators and reduced-motion handling are included.

Automated Edge viewport coverage and known limitations are recorded in [PHASE_2_TEST_RESULTS.md](PHASE_2_TEST_RESULTS.md). Physical mobile devices, Safari/Firefox, keyboard-only review, contrast audit, 200% zoom and screen readers remain manual acceptance tasks; this document does not claim WCAG certification.

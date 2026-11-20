# LIMCOMA appearance implementation

## Phase 1 — Audit

Tailwind 4, Vite 8, Alpine, Leaflet 1.9.4, and SweetAlert2 11.26.25 are installed. The settings screen had a Light placeholder and no account appearance field. Existing CSS already provided brand and neutral tokens. The guest and authenticated layouts share the Vite stylesheet. Official documents use separate templates. Existing leave UI edits were present before this task.

## Phase 2 — Architecture

One root preference controls native color-scheme and semantic tokens. The selected Light, Dark, or System value is stored for this browser only. System uses native CSS preference resolution. The early shared head initializes the preference before Vite assets. Browser storage access is guarded, and storage events synchronize open tabs.

## Phases 3–7 — Controls and surfaces

The account menu, Account Settings and guest layout expose the same labeled native selector. Both shared layouts establish the scheme before the Vite entry point. Shared cards, tables, inputs, pagination, calendar states, toast and dialog surfaces, chart axes and series, and Leaflet UI use the theme palette. The employee ID has a constrained light scheme. Official printable documents and email templates use their existing presentation. Photo and map tile pixels are untouched.

## Phases 8–9 — Verification and handoff

- Edge, Firefox, and WebKit passed the live system change, explicit override, reload, cross-tab, and blocked-storage scenarios. Tests drive the browser's actual native appearance selector.
- Each of 23 Light captures and 23 Dark captures had no page-level horizontal overflow. The final four include authorized Supervisor review details on desktop and mobile. The matrix includes sign-in, HR dashboard and attendance monitoring, schedules, holidays, settings, Employee dashboard and attendance camera, work calendar, and Supervisor leave. Views use 1440 × 1024, 390 × 844, and a short 844 × 390 landscape viewport for attendance.
- The authenticated Edge scenario verified that the guest selection carries across sign-in, stays synchronized between the account menu and Account Settings, and survives sign-out. The final public HTTPS sign-in and versioned theme stylesheet each returned HTTP 200.
- 37 targeted Laravel tests passed with 638 assertions across official leave documents, attendance workflow, account activation, and the employee work calendar. Pint passed.
- Sample contrast ratios in Dark Mode are 13.98:1 for primary card text, 7.47:1 for muted card text, 4.30:1 for input borders, 5.74:1 for the white primary-button label, and 7.92:1 for the focus accent on a card. Success, warning, and error text on their respective dark surfaces measured 8.51:1, 10.89:1, and 9.95:1. These are palette samples.
- Vite emitted approximately 25.2 KB of gzipped application CSS, up from 23.6 KB. Theme selection uses the current document and makes no theme network request. The small in-head initializer runs before Vite. A frame-by-frame cold-render study and field Core Web Vitals were outside the lab scope.

### Guidance applied

- **dark-mode** informed the page-wide native color scheme, initial canvas metadata, and locally fixed appearance of the official employee ID.
- **dark-mode-toggle** informed head initialization from browser storage and keeping the selected override synchronized with the color-scheme metadata. System uses native CSS instead of a media-query event watcher.

### Files changed in this pass

- Added THEME_IMPLEMENTATION_PROGRESS.md, resources/css/appearance.css, resources/views/components/appearance-head.blade.php, resources/views/components/appearance-select.blade.php, and tests/Browser/appearance.spec.js.
- Updated resources/css/app.css, resources/views/components/layouts/app.blade.php, resources/views/components/layouts/guest.blade.php, resources/views/components/topbar.blade.php, resources/views/components/notification-dropdown.blade.php, resources/views/components/brand.blade.php, and resources/views/components/employee-identity-card.blade.php.
- Updated resources/views/workspace/settings.blade.php, resources/views/attendance/policies/employee-calendar.blade.php, resources/views/leave/calendar.blade.php, resources/views/leave/create.blade.php, resources/views/leave/verification.blade.php, and resources/views/leave/verification-landing.blade.php.

### Build and remaining verification

The production build uses npm run build. No migration or account configuration is needed. Preferences are per browser and per origin. Browsers with blocked storage can select a theme for the current document. Older browsers without CSS light-dark() were outside the compatibility matrix.

Screenshots are in [.tools/theme-check/light](.tools/theme-check/light) and [.tools/theme-check/dark](.tools/theme-check/dark). Physical mobile devices, screen readers, real camera and GPS hardware, map tile and live notification states, and exhaustive contrast across every data state remain manual verification items. Review those workflows with synthetic accounts on a target deployment before claiming full WCAG coverage.

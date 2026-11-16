# LIMCOMA brand theme

Implemented 2026-11-16 using the requested approximate digital palette; these are not verified official brand guideline values.

| Token | Value | Application |
| --- | --- | --- |
| `--limcoma-purple` | #2F1B72 | HR/Employee sidebar, login story, primary buttons, headings, links |
| `--limcoma-purple-dark` | #241454 | Navigation/button hover and active-item text |
| `--limcoma-teal` | #10AB8C | Active navigation, office marker and geographic circle |
| `--limcoma-teal-dark` | #087C68 | Teal actions, focus outlines, readable radius status |
| `--limcoma-teal-light` | #E7F7F2 | Success/informational surfaces and search hover |
| `--limcoma-purple-light` | #F0ECF8 | Tables, secondary surfaces, dashboard icons |
| `--background-main` / `--background-card` | #F8FAFC / #FFFFFF | Pages and cards |
| `--text-primary` / `--text-secondary` | #1E293B / #64748B | Body text and metadata |
| `--border-color` | #E2E8F0 | Form/card borders |

The tokens live in `resources/css/app.css`. Tailwind v4 theme aliases connect existing blue/navy utility names to the purple palette without rewriting every page. Shared sidebar, card, button, table, form, avatar, modal and notification styles cover both roles, management forms, profile/settings and attendance views. Error styles remain red and success styles remain green/teal. Existing data calculations and navigation permissions are unchanged. Existing chart placeholders remain placeholders; no chart data was invented.

Primary buttons use purple/white; secondary buttons use white with purple text/borders. Teal actions use dark teal with white text. Calculated WCAG contrast: purple/white **13.81:1**, dark teal/white **5.13:1**, and dark-purple text on active teal **5.55:1**. Bright teal/white is only **2.91:1**, so bright teal is not used behind ordinary white button/navigation text. Visible keyboard focus outlines use dark teal. This checks the principal pairings, not a complete independent accessibility audit.

## Logo and company identity

No approved LIMCOMA logo image was found in the repository. The existing checkmark branding symbol is retained as a fallback, with LIMCOMA / Multipurpose Cooperative text. It is not represented as the official logo. Place an approved PNG at **`public/limcoma-logo.png`**; the shared brand component will render it on login and both sidebars. Do not replace it with an invented logo. Application titles/footers, login identity and activation invitation subject/body use LIMCOMA. Existing environment-specific application/session configuration was not changed.

## Files changed for branding

- `resources/css/app.css`
- `resources/views/components/brand.blade.php`
- `resources/views/components/layouts/app.blade.php`
- `resources/views/components/layouts/guest.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/workspace/module.blade.php`
- `resources/views/mail/activation.blade.php`
- `app/Mail/AccountInvitationMail.php`
- `resources/views/components/branch-location-map.blade.php`
- `resources/js/branch-location-map.js`
- `tests/Browser/branch-map.spec.js` (branded marker anchor assertion)
- `tests/Browser/location-search.spec.js`
- This guide, `README.md` and the branch/search guides.

Rebuild with `powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Task build`. Run `-Task browser` for actual login/HR/Employee color assertions, responsive overflow/navigation checks and saved map workflows. Physical Android/iPhone testing remains pending. The approved logo asset remains outstanding; the documented fallback is intentional.

Verification completed: 124 backend tests (820 assertions), 9 browser tests, plus a final 5-test map/search rerun after marker alignment. Build, Pint, Prettier and whitespace checks passed. See [search test details and limitations](BRANCH_LOCATION_SEARCH.md#verification).

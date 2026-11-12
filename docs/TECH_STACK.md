# Technology Stack and Decision Record

Planning date: 2026-11-12 (Asia/Singapore)  
Status: Phase 1 planning baseline, with Phase 2 implementation decisions recorded below.

## Phase 2 implementation record — 2026-11-12

The subsequent user instruction authorized actual setup. Laravel 13.42.0/Fortify 1.42.0, Blade, Tailwind 4.3.5, Alpine 3.17.5, Vite 8.3.8, PHP 8.5.12 and **MariaDB 10.11.16** are installed. Compatible XAMPP MariaDB was explicitly permitted in Phase 2; it replaces the original proposed MySQL 8.4 baseline for this local setup. Reconfirm CI/production parity before domain migrations.

Plain Blade + official Fortify was selected over Livewire/Flux to retain the approved minimal frontend architecture. Fortify owns authentication; no custom password-check/session implementation was introduced. Recovery is implemented/tested but disabled until private delivery is configured. No role/branch authorization or domain schema exists yet.

Composer 2.11.2 and Node 26.13.1/npm 11.20.0 are workspace-local ignored tools. Framework scaffold instructions required Boost 2.10.4 as a development dependency; browser logging is disabled. Playwright 1.65.0/installed Edge provide requested browser checks; Prettier 3.10.0 formats shared frontend sources. Lockfiles record all exact package versions. Details: [Phase 2 setup](PHASE_2_SETUP.md) and [test results](PHASE_2_TEST_RESULTS.md).

The sections below retain the original planning decisions; statements about the then-empty repository describe Phase 1, not the current installed state.

See [System Blueprint](SYSTEM_BLUEPRINT.md), [Requirements](REQUIREMENTS.md), and [Roadmap](DEVELOPMENT_ROADMAP.md).

## 1. Recommended baseline

| Component | Recommendation | Reason and boundary |
| --- | --- | --- |
| Application | Laravel 13.x, latest supported patch at setup | Conventional MVC, validation, authorization, migrations, queues, and testing. PHP 8.3–8.5 is supported; security support runs to March 17, 2028. Recheck before installation. |
| PHP | Maintain patched PHP 8.5.x if dependency checks pass | Existing CLI reports 8.5.12. Check Apache PHP separately; CLI and web runtimes can differ. |
| Database | MySQL 8.4 LTS, InnoDB, utf8mb4 | Transactions, row locks, constraints, and indexed relational reporting suit attendance. Confirm hosting support and patch lifecycle before provisioning. |
| Local environment | XAMPP Apache/PHP where compatible; actual database engine verified | XAMPP commonly ships MariaDB. Use separate MySQL for local/test parity if MySQL is selected. MariaDB is an alternative only after an explicit engine decision and compatibility tests. |
| Views | Laravel Blade + HTML5 | Server rendering keeps most screens simple and usable on slow networks. |
| Styling | Tailwind CSS 4.x, compiled locally through Vite | Reusable light-theme components; no production CDN compiler. Device inventory must meet browser baseline. |
| Interaction | JavaScript modules; Alpine.js 3.x for small interactions | Camera/location lifecycle, dialogs, menus, and notifications; avoid a full SPA. |
| Assets | Laravel-compatible Vite and plugins; committed lockfiles | Select compatible stable versions in Phase 2; reproducible production asset builds. |
| Build tooling | Composer 2.x; patched Node.js 26 LTS with npm, subject to Vite compatibility | Node is build tooling, not the attendance backend. Pin runtime and package versions after verification. |
| Browser auth | Laravel session guard and password broker; Fortify considered for headless authentication actions | Blade login with backend protection; no public registration. Select the minimal supported integration in Phase 2, not an obsolete tutorial starter kit. |
| Authorization | Laravel policies/gates + role/permission tables + explicit branch grants | Backend checks combine permission, branch scope, ownership, and account state. Avoid adding an RBAC package unless requirements justify it. |
| Background tasks | Laravel database queue and scheduler initially | In-app notifications, exports, cleanup, absence reconciliation; Redis can follow measured contention. Run supervised workers in production. |
| Files | Private storage outside web root; encrypted private object storage in production | Separate leave documents, temporary captures, and biometric references; authorized retrieval only. |
| Testing | PHPUnit through Laravel; browser automation chosen in its phase | Unit, feature, MySQL integration, concurrency, authorization, and real-device tests. |
| Source control | Git and private GitHub repository | Reviewable changes and CI without committing secrets or employee data. |
| Production | Maintained Linux host or managed platform; HTTPS reverse proxy, PHP-FPM, workers, scheduler, private DB | XAMPP is local development tooling, not a production deployment. |

Laravel support details: [official release policy](https://laravel.com/framework/docs/releases). XAMPP database and deployment distinctions: [Apache Friends FAQ](https://www.apachefriends.org/faq_windows.html). Node status: [official release schedule](https://github.com/nodejs/Release).

Tailwind 4 targets Chrome 111+, Safari 16.4+, and Firefox 128+. Test current Edge as well. Confirm actual employee devices before locking this choice; if older browsers are mandatory, revisit styling support rather than silently accepting broken pages. [Tailwind compatibility](https://tailwindcss.com/docs/compatibility)

## 2. Architecture decisions

| ID | Decision | Justification / revisit condition |
| --- | --- | --- |
| ADR-01 | Modular Laravel monolith with a separate biometric adapter | One deployable application simplifies student development and transactional business rules. Biometric compute/security has a separate boundary. |
| ADR-02 | One centralized database for one company | Branch is an access boundary, not a separate tenant database. Employee count and synchronized traffic, not branch count alone, determine capacity. |
| ADR-03 | Same-origin session authentication and CSRF protection | No need to introduce JWT storage or refresh-token flows for this web MVP. Add Sanctum only for an approved separate API client. |
| ADR-04 | Laravel computes geofence decisions without a mapping dependency | Haversine calculation needs coordinates, not a paid maps request. |
| ADR-05 | Provider-independent biometric contract | Allows evaluated commercial or self-hosted implementation without coupling attendance rules to a model. |
| ADR-06 | UTC storage with branch IANA timezone and local shift date | Overnight shifts and later reporting must survive schedule and branch changes. Default display timezone proposed: Asia/Manila. |
| ADR-07 | Server receipt timestamp persisted before verification | Provider latency must not arbitrarily change lateness; only a successfully accepted, fresh attempt becomes attendance. |
| ADR-08 | No offline automatic attendance in MVP | Offline client timestamps/evidence cannot meet the proposed trust model; use an audited correction process. |

## 3. Facial verification options and recommendation

Camera capture, face detection, face comparison, and liveness are different capabilities. No UI flag, JavaScript landmark detector, or blink prompt alone proves identity or resistance to presentation/injection attacks.

| Criterion | Dedicated Python/FastAPI service | Commercial candidate: AWS Rekognition Face Liveness + CompareFaces |
| --- | --- | --- |
| Verification | Licensed model produces a 1:1 score against a selected employee reference | Compare selected employee reference with the liveness-derived reference frame; require exactly one face |
| Liveness | Must separately procure/evaluate PAD and capture/injection defenses | Managed liveness session returns a probabilistic score; still requires device and attack testing |
| Licensing | Audit code, weights, datasets, PAD assets, and redistribution independently | Review contract, acceptable use, processing terms, region, and subprocessors |
| Operations | Own CPU/GPU sizing, model loading, TLS, patches, isolation, scaling, and monitoring | Vendor availability, network dependency, quotas, per-check charges, and storage/egress costs |
| Privacy | Can keep evidence within controlled infrastructure | Requires assessment of transfers, region availability, retention, and deletion |
| Frontend impact | Custom capture flow must meet evaluated PAD protocol | Documented web liveness component uses React; a small isolated React component may be needed within Blade |
| Evaluation | Local accuracy, attack testing, device compatibility, and model change control are our responsibility | Same local acceptance testing; vendor score is not a universal probability of identity |

**Recommendation:** prioritize a commercial liveness + comparison proof of concept if the company approves budget and processing terms. AWS is an evaluation candidate, not an approved purchase or guaranteed fit. Permit a small React capture component only if this candidate is selected; this is a proposed exception to the Blade/Alpine frontend, not a whole-application rewrite. Without that approval, continue evaluating a commercially licensed self-hosted service or another browser SDK.

For a self-hosted candidate, FastAPI is a service framework, not a recognition engine. InsightFace is one possible model ecosystem, but code licensing does not establish pretrained-weight licensing; vendor material distinguishes code and model rights. Do not ship downloaded research-only weights into a company deployment. [InsightFace licensing](https://www.insightface.ai/trust-compliance), [FastAPI deployment concepts](https://fastapi.tiangolo.com/deployment/concepts/)

AWS documents separate liveness and face comparison capabilities and recommends risk-based decisions. Its supported capture flow must be tested on the target browsers. [Face Liveness](https://docs.aws.amazon.com/rekognition/latest/dg/face-liveness.html), [CompareFaces](https://docs.aws.amazon.com/rekognition/latest/dg/faces-comparefaces.html)

Provider selection gate: establish false-match/non-match targets, attack-test protocol, independent PAD evidence where available, accessibility, latency, retention, model versions, data location, export/deletion capability, incident obligations, and total cost. Conduct a representative, lawfully approved pilot; do not collect real employee biometrics just to test an unapproved model.

## 4. Integration contract

Laravel owns employee identity, enrollment authorization, attempt IDs, expiry, policy versions, and attendance. Provider/service owns evidence processing and returns a typed result: request ID, subject/reference version, quality outcome, match score, liveness outcome/score, model/PAD versions, and failure code. Scores have provider-specific meaning; record the threshold policy used.

Self-hosted service: private network, TLS/mTLS or scoped short-lived service credentials, strict body/dimension limits, no direct employee/browser access, no arbitrary URL fetching, and restricted outbound access. Do not give it attendance DB write privileges. Fetch results over authenticated server-to-server calls. Validate signed callback signatures, timestamps, and replay IDs if callbacks are required.

Commercial capture: Laravel creates a subject-bound session and retrieves authoritative results. Browser may stream directly through a provider SDK using short-lived narrowly scoped credentials where required; it cannot select another employee's reference or approve attendance. Never put permanent provider credentials in JavaScript.

## 5. GPS and mapping

Use browser Geolocation only during an explicit attendance action, with permission, fresh readings, accuracy reporting, and timeouts. Request high accuracy as a hint; desktop location may use Wi-Fi/IP rather than a GPS chip. Do not track employees continuously.

Google Maps Platform is proposed for branch pin placement/display, subject to company approval. Coordinate entry and server distance calculation remain available without it. Billing setup and SKU usage must be reviewed; apply browser-key referrer/API restrictions, separate server keys, quotas and alerts. Alerts alone are not a guaranteed spending cap. Do not route facial evidence or unnecessary employee location history through Maps. [Google pricing overview](https://developers.google.com/maps/billing-and-pricing/overview)

## 6. Verified local state and deferred work

- Repository currently contains a standalone login prototype, `index.php`; it is not secure working authentication.
- CLI PHP was observed as 8.5.12 with `pdo_mysql`, OpenSSL, mbstring, and other usual extensions. Installation compatibility remains to be tested.
- Composer, Node.js, and npm were not discovered on the current PATH; this does not prove they are absent elsewhere.
- Actual running DB product/version, web PHP, SMTP/recovery channel, TLS setup, and hosting are unconfirmed.
- No framework, package, provider account, database, schema, or infrastructure is created in Phase 1.

## 7. Dependency and operational policy

Choose stable supported versions at the start of the authorized phase; commit Composer/npm lockfiles, document minimum runtimes, run dependency audits, and schedule upgrades. Separate development/test/production credentials and storage. Keep `.env`, tokens, captures, templates, and database dumps out of Git. Use synthetic fixtures in CI. No dependency is approved merely by appearing in an option comparison.

@props(['title' => 'Sign in'])
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#2f1b72"><title>{{ $title }} · LIMCOMA</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="auth-layout">
    <aside class="auth-story" aria-label="About LIMCOMA">
        <x-brand />
        <div class="auth-story-content"><p class="auth-kicker">Employee attendance &amp; management</p><h2>Your workday,<br>in one place.</h2><p>Attendance, branch assignments, and employee information for LIMCOMA Multipurpose Cooperative.</p><div class="auth-features"><div class="auth-feature"><x-icon name="clock" /><span>Record your attendance</span></div><div class="auth-feature"><x-icon name="building" /><span>Stay connected to your branch</span></div><div class="auth-feature"><x-icon name="file" /><span>Review your workday records</span></div></div></div>
        <div class="auth-story-footer">LIMCOMA Multipurpose Cooperative<br>Employee workspace</div>
    </aside>
    <main class="auth-main" id="main-content" tabindex="-1"><div class="auth-topline">LIMCOMA · Employee workspace</div><div class="auth-form">{{ $slot }}</div><footer class="auth-footer">© {{ now()->year }} LIMCOMA Multipurpose Cooperative<br>Employee Attendance System</footer></main>
</div>
<x-notifications />
</body>
</html>

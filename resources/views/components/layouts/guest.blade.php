@props(['title' => 'Sign in'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <x-favicons /><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#2f1b72"><title>{{ $title }} · LIMCOMA</title>@vite(['resources/css/app.css', 'resources/js/guest.js'])</head>
<body class="auth-page">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="auth-layout">
    <main class="auth-main" id="main-content" tabindex="-1">
        <div class="auth-brand"><x-brand /></div>
        <div class="auth-form">
            <x-notifications inline-fallback />
            {{ $slot }}
        </div>
        <footer class="auth-footer">© {{ now()->year }} LIMCOMA Multipurpose Cooperative<br>Employee Attendance System</footer>
    </main>
    <div class="auth-story" aria-hidden="true"></div>
</div>
</body>
</html>

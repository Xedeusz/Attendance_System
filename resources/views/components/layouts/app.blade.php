@props(['title' => 'Dashboard'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#2f1b72">
    <title>{{ $title }} · LIMCOMA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <div class="shell" x-data="workspaceShell" :class="{ 'is-collapsed': collapsed }" @keydown.escape.window="mobileOpen = false">
        <div class="drawer-overlay" x-show="mobileOpen" x-cloak @click="mobileOpen = false" aria-hidden="true"></div>
        <x-sidebar />
        <div class="main-shell">
            <x-topbar :title="$title" />
            <main class="workspace-content" id="main-content" tabindex="-1">
                <x-breadcrumbs :title="$title" />
                <noscript>@if($errors->any())<div class="mt-5"><x-form-errors /></div>@endif</noscript>
                {{ $slot }}
                <footer class="workspace-footer"><span>© {{ now()->year }} LIMCOMA · Employee Attendance System</span><span>Attendance & Employee Management</span></footer>
            </main>
        </div>
        <x-confirm-logout />
    </div>
    <x-notifications />
</body>
</html>

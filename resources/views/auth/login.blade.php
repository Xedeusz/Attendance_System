<x-layouts.guest><div class="empty-icon" style="color:#2458d3;background:#edf3ff"><x-icon name="lock" /></div><h1>Welcome back</h1><p class="auth-subtitle">Sign in to your account to get your day started.</p><x-form-errors />
@if(session('status'))<div class="mb-5"><x-alert>{{ session('status') }}</x-alert></div>@endif
<form method="POST" action="{{ route('login.store') }}" x-data="{ submitting: false }" @submit="submitting = true">@csrf
<x-input name="email" label="Email address" type="email" autocomplete="username" placeholder="you@company.com" maxlength="254" required autofocus />
<x-password />
<div class="flex items-center justify-between gap-3 mb-6"><label class="flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="remember" class="h-4 w-4 accent-blue-600">Remember me</label>@if(Route::has('password.request'))<a class="text-xs font-semibold text-blue-700" href="{{ route('password.request') }}">Forgot password?</a>@endif</div>
<x-button type="submit" class="full" x-bind:disabled="submitting"><span x-show="!submitting">Sign in</span><span x-show="submitting" x-cloak>Signing in</span><x-icon name="arrow" x-show="!submitting" /><x-loading x-show="submitting" x-cloak /></x-button>
</form><div class="auth-help">Need an account or help signing in?<br><strong>Contact your administrator.</strong></div></x-layouts.guest>

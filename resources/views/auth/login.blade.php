<x-layouts.guest><p class="auth-kicker">LIMCOMA Employee Workspace</p><h1>Welcome back</h1><p class="auth-subtitle">Sign in to access your LIMCOMA workspace.</p><x-form-errors />
<form method="POST" action="{{ route('login.store') }}" x-data="{ submitting: false }" @submit="submitting = true">@csrf
<x-input name="email" label="Email address" type="email" autocomplete="username" placeholder="you@company.com" maxlength="254" required autofocus />
<x-password />
<div class="flex items-center justify-between gap-3 mb-6"><label class="flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="remember" class="h-4 w-4 accent-blue-600">Remember me</label>@if(Route::has('password.request'))<a class="text-xs font-semibold text-blue-700" href="{{ route('password.request') }}">Forgot password?</a>@endif</div>
<x-button type="submit" class="full" aria-label="Sign in" x-bind:disabled="submitting"><span x-show="!submitting">Sign In</span><span x-show="submitting" x-cloak>Signing in</span><x-icon name="arrow" x-show="!submitting" /><x-loading x-show="submitting" x-cloak /></x-button>
</form><div class="auth-help">Need an account or assistance? Contact HR.</div></x-layouts.guest>

<x-layouts.guest title="Activate your account">
    <div class="empty-icon"><x-icon name="shield" /></div>
    <div x-data="accountActivation">
        <p x-show="state === 'checking'">Checking your invitation…</p>
        <noscript>Enable JavaScript to check your invitation.</noscript>
        <div x-cloak x-show="state === 'activated'">
            <h1>Account already activated</h1>
            <p>Your LIMCOMA account has already been set up. Sign in with your registered email and password.</p>
        </div>
        <p x-cloak x-show="state === 'invalid'">This invitation is invalid or expired. Contact HR for assistance.</p>
        <div x-cloak x-show="error" role="alert"><x-alert variant="error"><span x-text="error"></span></x-alert></div>
        <div x-html="form"></div>
        <div class="auth-help"><a href="{{ route('login') }}">Go to login</a></div>
    </div>
</x-layouts.guest>

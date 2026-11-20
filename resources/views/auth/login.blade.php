<x-layouts.guest>
    <h1>Sign in</h1>
    <p class="auth-subtitle">Use your work email to access the LIMCOMA employee workspace.</p>
    <x-form-errors />

    <form method="POST" action="{{ route('login.store') }}" x-data="{ submitting: false }" @submit="submitting = true" :aria-busy="submitting">
        @csrf
        <x-input name="email" label="Email address" type="email" autocomplete="username" spellcheck="false" enterkeyhint="next" maxlength="254" required />
        <x-password />

        <div class="auth-form-options">
            <label class="flex min-h-11 items-center gap-2">
                <input type="checkbox" name="remember" class="size-4"> Remember me
            </label>
            @if(Route::has('password.request'))
                <a class="text-link min-h-11" href="{{ route('password.request') }}">Forgot password?</a>
            @endif
        </div>

        <x-button type="submit" class="full" x-bind:aria-label="submitting ? 'Signing in' : 'Sign in'" aria-label="Sign in" x-bind:disabled="submitting">
            <span x-show="!submitting">Sign in</span>
            <span x-show="submitting" x-cloak>Signing in</span>
            <x-icon name="arrow" x-show="!submitting" />
            <x-loading x-show="submitting" x-cloak />
        </x-button>
    </form>

    <p class="auth-help">Contact HR for account access or sign-in help.</p>
</x-layouts.guest>

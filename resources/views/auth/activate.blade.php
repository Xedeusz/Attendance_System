<x-layouts.guest title="Activate your account">
    <div class="empty-icon"><x-icon name="shield" /></div>
    <h1>Your workspace awaits</h1>
    <p class="auth-subtitle">Set a private password to activate the account created by HR. Use 12+ characters with mixed case, a number and a symbol.</p>
    <form x-data="accountActivation" @submit.prevent="submit" method="POST" action="{{ route('activation.store') }}">
        @csrf
        <div class="mb-5" x-show="error" x-cloak><x-alert variant="error"><span x-text="error"></span></x-alert></div>
        <p class="small muted leading-6 mb-5" x-show="!token">Open the complete invitation link supplied by HR. The invitation code is required.</p>
        <x-password label="New password" autocomplete="new-password" />
        <x-password name="password_confirmation" label="Confirm password" autocomplete="new-password" />
        <x-button class="full" type="submit" x-bind:disabled="busy || !token"><span x-text="busy ? 'Activating…' : 'Activate account'">Activate account</span></x-button>
    </form>
    <div class="auth-help"><a href="{{ route('login') }}">Back to sign in</a><br>Invitation expired or unavailable? Contact HR.</div>
</x-layouts.guest>

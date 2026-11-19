<h1>Your workspace awaits</h1>
<p class="auth-subtitle">Set a private password to activate the account created by HR. Use 12+ characters with mixed case, a number and a symbol.</p>
<form @submit.prevent="submit" method="POST" action="{{ route('activation.store') }}">
    @csrf
    <x-password label="New password" autocomplete="new-password" />
    <x-password name="password_confirmation" label="Confirm password" autocomplete="new-password" />
    <x-button class="full" type="submit" x-bind:disabled="busy">Activate account</x-button>
</form>

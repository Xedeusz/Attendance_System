<x-layouts.guest title="Document Verification">
    <h1 class="mb-4 text-lg font-bold text-(--limcoma-purple)">LIMCOMA Document Verification</h1>
    <p class="mb-4">Verify an official leave approval document.</p>
    <form id="leave-scan" method="POST" action="{{ route('leave.verify.capture', absolute: false) }}">
        @csrf
        <input type="hidden" name="token">
        <x-button type="submit">Continue to verification</x-button>
    </form>
    <noscript><p class="mt-3">Enable JavaScript to read the scanned code.</p></noscript>
    <script>
        (() => {
            const token = location.hash.slice(1);
            if (location.hash) history.replaceState(null, "", location.pathname);
            if (!/^[a-f0-9]{64}$/.test(token)) return;
            const form = document.getElementById("leave-scan");
            form.elements.namedItem("token").value = token;
            form.requestSubmit();
        })();
    </script>
</x-layouts.guest>

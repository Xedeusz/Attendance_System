@props(['title'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-favicons />
    <title>{{ $title }} | LIMCOMA</title>
    @vite('resources/css/app.css')
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-8 sm:py-10">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 [&_.brand-caption]:text-(--text-secondary)!">
            <x-brand />
            <form method="POST" action="{{ route('logout') }}" aria-label="Sign out">
                @csrf
                <x-button type="submit" variant="secondary">Sign out</x-button>
            </form>
        </div>
        {{ $slot }}
    </main>
</body>
</html>

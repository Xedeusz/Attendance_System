@props(['name' => 'grid'])
@php
    $paths = [
        'grid' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'clock' => 'M12 8v4l3 2 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
        'building' => 'M4 21V5l8-3v19 M12 9h8v12 M2 21h20 M8 7v1 M8 11v1 M8 15v1 M16 13h1 M16 17h1',
        'calendar' => 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2 M8 14h2 M14 14h2 M8 18h2',
        'chart' => 'M4 3v18h17 M8 16v-4 M13 16V8 M18 16V5',
        'bell' => 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9 M10 21h4',
        'settings' => 'M9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        'logout' => 'M9 4H4v16h5 M10 12h11 M17 8l4 4-4 4',
        'menu' => 'M4 6h16 M4 12h16 M4 18h16',
        'close' => 'm6 6 12 12 M6 18 18 6',
        'chevron' => 'm9 5 7 7-7 7',
        'down' => 'm6 9 6 6 6-6',
        'check' => 'm5 12 4 4L19 6',
        'shield' => 'm12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6z M8 12l3 3 5-6',
        'arrow' => 'M4 12h16 M15 7l5 5-5 5',
        'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12 M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        'lock' => 'M8 10V7a4 4 0 0 1 8 0v3 M5 10h14v11H5z M12 14v3',
        'info' => 'M12 11v6 M12 7v.1 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
        'search' => 'M21 21l-5-5 M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
        'plus' => 'M12 5v14 M5 12h14',
        'camera' => 'M8 5l2-2h4l2 2h5v15H3V5z M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'pin' => 'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0 M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        'file' => 'M14 2H4v20h16V8z M14 2v6h6 M8 13h8 M8 17h5',
    ];
@endphp
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['grid'] }}" /></svg>

@props(['tone' => 'neutral'])
<span {{ $attributes->class(['badge', 'blue' => $tone === 'blue', 'teal' => $tone === 'teal', 'warning' => $tone === 'warning', 'danger' => $tone === 'danger']) }}>{{ $slot }}</span>

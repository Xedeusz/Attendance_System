@props(['tone' => 'neutral'])
<span {{ $attributes->class(['badge', 'blue' => $tone === 'blue', 'teal' => $tone === 'teal']) }}>{{ $slot }}</span>

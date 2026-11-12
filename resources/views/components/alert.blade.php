@props(['variant' => 'info'])
<div {{ $attributes->class(['alert', 'error' => $variant === 'error']) }} role="{{ $variant === 'error' ? 'alert' : 'status' }}"><x-icon name="info" /><div>{{ $slot }}</div></div>

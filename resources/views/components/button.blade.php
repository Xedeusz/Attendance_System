@props(['href' => null, 'variant' => 'primary', 'disabled' => false])
@if($href && !$disabled)
    <a href="{{ $href }}" {{ $attributes->class(['button', 'secondary' => $variant === 'secondary', 'danger' => $variant === 'danger']) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class(['button', 'secondary' => $variant === 'secondary', 'danger' => $variant === 'danger'])->merge(['type' => 'button']) }} @disabled($disabled)>{{ $slot }}</button>
@endif

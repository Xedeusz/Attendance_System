@props(['href' => null, 'variant' => 'primary', 'disabled' => false])
@if($href && !$disabled)
    <a href="{{ $href }}" {{ $attributes->class(['button', 'secondary' => $variant === 'secondary']) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class(['button', 'secondary' => $variant === 'secondary'])->merge(['type' => 'button']) }} @disabled($disabled)>{{ $slot }}</button>
@endif

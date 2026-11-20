<div class="brand {{ is_file(public_path('LIMCOMA-LOGO.png')) ? 'brand-with-logo' : 'brand-wordmark' }}">
    @if(is_file(public_path('LIMCOMA-LOGO.png')))
        <picture class="shrink-0">
            <source type="image/webp" srcset="{{ asset('limcoma-logo-40.webp') }} 1x, {{ asset('limcoma-logo-80.webp') }} 2x, {{ asset('limcoma-logo-120.webp') }} 3x">
            <img src="{{ asset('LIMCOMA-LOGO.png') }}" alt="LIMCOMA Multipurpose Cooperative logo" width="38" height="38" class="h-10 w-10 shrink-0 rounded-lg bg-white object-contain">
        </picture>
    @endif
    <div class="brand-copy"><div class="brand-name">LIMCOMA<span class="brand-accent">.</span></div><div class="brand-caption">Multipurpose Cooperative</div></div>
</div>

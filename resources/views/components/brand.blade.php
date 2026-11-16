<div class="brand {{ is_file(public_path('limcoma-logo.png')) ? 'brand-with-logo' : 'brand-wordmark' }}">
    @if(is_file(public_path('limcoma-logo.png')))
        <img src="{{ asset('limcoma-logo.png') }}" alt="LIMCOMA Multipurpose Cooperative logo" width="38" height="38" class="h-10 w-10 shrink-0 rounded-lg bg-white object-contain">
    @endif
    <div class="brand-copy"><div class="brand-name">LIMCOMA<span class="brand-accent">.</span></div><div class="brand-caption">Multipurpose Cooperative</div></div>
</div>

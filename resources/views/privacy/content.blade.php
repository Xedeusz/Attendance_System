@foreach(preg_split('/^##[ \t]+/m', $notice->content) as $part)
    @if($loop->first)
        @if(filled($part))<p class="leading-7 whitespace-pre-wrap wrap-anywhere">{{ trim($part) }}</p>@endif
    @else
        @php([$heading, $body] = array_pad(explode("\n", $part, 2), 2, ''))
        <section class="space-y-2">
            <h3 class="text-lg font-semibold wrap-anywhere">{{ trim($heading) }}</h3>
            <p class="leading-7 whitespace-pre-wrap wrap-anywhere">{{ trim($body) }}</p>
        </section>
    @endif
@endforeach

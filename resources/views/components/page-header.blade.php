@props(['title', 'description'])
<header class="page-heading"><div class="page-heading-copy"><h1>{{ $title }}</h1><p>{{ $description }}</p></div>@if($slot->isNotEmpty())<div class="page-actions">{{ $slot }}</div>@endif</header>

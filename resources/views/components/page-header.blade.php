@props(['title', 'description'])
<header class="page-heading"><div class="page-heading-copy"><h1>{{ $title }}</h1><p>{{ $description }}</p></div><div class="page-actions">{{ $slot }}</div></header>

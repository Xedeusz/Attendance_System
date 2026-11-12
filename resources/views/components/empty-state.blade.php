@props(['title', 'description', 'icon' => 'file'])
<div class="empty-state"><div class="empty-icon"><x-icon :name="$icon" /></div><h3>{{ $title }}</h3><p>{{ $description }}</p>{{ $slot }}</div>

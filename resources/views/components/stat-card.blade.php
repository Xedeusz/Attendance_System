@props(['label', 'icon' => 'users'])
<div class="card stat-card"><div class="stat-top"><p>{{ $label }}</p><span class="stat-icon"><x-icon :name="$icon" /></span></div><div class="stat-value" aria-label="Not available">&mdash;</div><p class="stat-caption">Not connected yet</p></div>

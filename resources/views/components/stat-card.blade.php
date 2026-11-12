@props(['label', 'icon' => 'users', 'value' => null])
<div class="card stat-card"><div class="stat-top"><p>{{ $label }}</p><span class="stat-icon"><x-icon :name="$icon" /></span></div><div class="stat-value">{{ $value === null ? '—' : number_format($value) }}</div><p class="stat-caption">{{ $value === null ? 'Not connected yet' : 'From company records' }}</p></div>

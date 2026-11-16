@props(['label', 'icon' => 'users', 'value' => null])
<div class="card stat-card"><div class="stat-top"><p>{{ $label }}</p><span class="stat-icon"><x-icon :name="$icon" /></span></div><div class="stat-value">{{ $value === null ? '—' : number_format($value) }}</div>@if($value === null)<p class="stat-caption">Not available</p>@endif</div>

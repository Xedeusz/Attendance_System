@props(['user', 'large' => false])
@if($user->id !== auth()->id())
<span {{ $attributes->class(['avatar relative shrink-0 overflow-hidden']) }} @if($large) style="width:96px;height:96px;font-size:28px" @endif role="img" aria-label="{{ $user->name }} profile picture" x-data="{ failed: false }" key="avatar-{{ $user->id }}-{{ $user->avatarData()['url'] }}">
<span aria-hidden="true">{{ $user->avatarData()['initials'] }}</span>
@if($user->avatarData()['url'])<img src="{{ $user->avatarData()['url'] }}" x-show="!failed" x-on:error="failed = true" x-init="$nextTick(() => { if ($el.complete && !$el.naturalWidth) failed = true })" class="absolute inset-0 h-full w-full object-cover" alt="" loading="lazy" decoding="async">@endif
</span>
@else

<span {{ $attributes->class(['avatar relative shrink-0 overflow-hidden']) }} @if($large) style="width:96px;height:96px;font-size:28px" @endif role="img" aria-label="{{ $user->name }} profile picture" x-data="{ initial: @js($user->avatarData()), failed: false }" x-init="if (!$store.profileAvatar.ready) Object.assign($store.profileAvatar, initial, {ready:true}); $watch('$store.profileAvatar.url', () => failed = false)">
<span aria-hidden="true" x-text="$store.profileAvatar.initials">{{ $user->avatarData()['initials'] }}</span>
<img x-show="$store.profileAvatar.url && !failed" x-cloak :src="$store.profileAvatar.url || undefined" x-on:error="failed = true" x-init="$nextTick(() => { if ($el.complete && !$el.naturalWidth) failed = true })" class="absolute inset-0 h-full w-full object-cover" alt="" decoding="async">
</span>

@endif

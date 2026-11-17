@props(['user', 'large' => false])
<span {{ $attributes->class(['avatar relative shrink-0 overflow-hidden']) }} @if($large) style="width:96px;height:96px;font-size:28px" @endif role="img" aria-label="{{ $user->name }} profile picture" x-data="{ initial: @js($user->avatarData()), failed: false }" x-init="if (!$store.profileAvatar.ready) Object.assign($store.profileAvatar, initial, {ready:true}); $watch('$store.profileAvatar.url', () => failed = false)">
<span aria-hidden="true" x-text="$store.profileAvatar.initials">{{ $user->avatarData()['initials'] }}</span>
<img x-show="$store.profileAvatar.url && !failed" x-cloak :src="$store.profileAvatar.url || undefined" x-on:error="failed = true" class="absolute inset-0 h-full w-full object-cover" alt="" decoding="async">
</span>

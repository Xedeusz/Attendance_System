@props(['title'])

<header class="topbar">
    <div class="topbar-left">
        <button x-ref="navigationToggle" type="button" class="icon-button" @click="toggleNavigation()" aria-label="Toggle navigation"
                :aria-expanded="media && media.matches ? !collapsed : mobileOpen" aria-controls="workspace-navigation">
            <x-icon name="menu" />
        </button>
        <x-breadcrumbs :title="$title" />
    </div>

    <div class="topbar-right">
        <span data-sync-status class="sync-indicator" role="status" aria-live="polite">Connecting</span>
        @if(app()->environment(['local', 'testing']))
            <span class="topbar-badge"><x-badge tone="blue">Development workspace</x-badge></span>
        @endif
        <x-notification-dropdown :unread="$unreadNotices" />
        <div class="relative" x-data="{ profileOpen: false }" @click.outside="profileOpen = false"
             @keydown.escape="if (profileOpen) { profileOpen = false; $refs.accountToggle.focus() }">
            <button x-ref="accountToggle" class="user-trigger" type="button" aria-label="Account menu for {{ auth()->user()->name }}"
                    @click="profileOpen = !profileOpen; if (profileOpen) $nextTick(() => $refs.profileLink.focus())"
                    :aria-expanded="profileOpen" aria-controls="profile-menu">
                <x-user-avatar :user="auth()->user()" />
                <span class="user-meta">
                    <span class="user-name block">{{ auth()->user()->name }}</span>
                    <span class="user-caption block">{{ auth()->user()->roleLabel() }}</span>
                </span>
                <x-icon name="down" class="size-3.5" />
            </button>
            <div class="dropdown" id="profile-menu" x-show="profileOpen" x-cloak>
                <div class="flex items-center gap-3 p-3">
                    <x-user-avatar :user="auth()->user()" />
                    <span class="small font-semibold break-words">{{ auth()->user()->name }}</span>
                </div>
                <a x-ref="profileLink" href="{{ route('profile') }}">Your profile</a>
                <a href="{{ route('settings') }}">Account settings</a>
                <button type="button" @click="profileOpen = false; $dispatch('open-modal', 'logout')">Sign out</button>
            </div>
        </div>
    </div>
</header>

<aside class="sidebar" :class="{ 'is-open': mobileOpen }" id="workspace-navigation" aria-label="Workspace navigation" x-trap.inert="mobileOpen">
    <x-brand /><button type="button" class="icon-button mobile-close" @click="mobileOpen = false" aria-label="Close navigation"><x-icon name="close" /></button>
    <p class="nav-label">WORKSPACE</p><nav aria-label="Main">
    @foreach(['dashboard' => ['Dashboard', 'grid'], 'employees' => ['Employees', 'users'], 'attendance' => ['Attendance', 'clock'], 'branches' => ['Branches', 'building'], 'leave' => ['Leave Management', 'calendar'], 'reports' => ['Reports and Analytics', 'chart']] as $name => [$label, $icon])
        <a class="nav-link {{ request()->routeIs($name) ? 'active' : '' }}" href="{{ route($name) }}" title="{{ $label }}" @if(request()->routeIs($name)) aria-current="page" @endif><x-icon :name="$icon" /><span class="nav-text">{{ $label }}</span></a>
    @endforeach
    </nav><div class="sidebar-bottom"><p class="nav-label">YOUR WORKSPACE</p><nav aria-label="Account">
    @foreach(['notifications' => ['Notifications', 'bell'], 'settings' => ['Settings', 'settings']] as $name => [$label, $icon])
        <a class="nav-link {{ request()->routeIs($name) ? 'active' : '' }}" href="{{ route($name) }}" title="{{ $label }}" @if(request()->routeIs($name)) aria-current="page" @endif><x-icon :name="$icon" /><span class="nav-text">{{ $label }}</span></a>
    @endforeach
    </nav><div class="sidebar-note"><strong>One connected workspace</strong>Built for every branch and every workday.</div><button type="button" class="nav-link logout" title="Sign out" @click="$dispatch('open-modal', 'logout')"><x-icon name="logout" /><span class="nav-text">Sign out</span></button></div>
</aside>

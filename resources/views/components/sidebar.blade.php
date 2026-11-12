@php
    $items = auth()->user()->isHr()
        ? ['hr.dashboard' => ['Dashboard', 'grid'], 'hr.employees.index' => ['Employee Management', 'users'], 'hr.branches.index' => ['Branch Management', 'building'], 'hr.departments.index' => ['Departments', 'building'], 'attendance' => ['Attendance Monitoring', 'clock'], 'leave' => ['Leave Management', 'calendar'], 'reports' => ['Reports and Analytics', 'chart'], 'notifications' => ['Notifications', 'bell'], 'settings' => ['Settings', 'settings']]
        : ['employee.dashboard' => ['Dashboard', 'grid'], 'attendance' => ['Time In / Time Out', 'clock'], 'attendance.history' => ['Attendance History', 'file'], 'leave' => ['Leave Application', 'calendar'], 'employee.profile' => ['My Profile', 'users'], 'notifications' => ['Notifications', 'bell'], 'settings' => ['Account Settings', 'settings']];
@endphp
<aside class="sidebar" :class="{ 'is-open': mobileOpen }" id="workspace-navigation" aria-label="Workspace navigation" x-trap.inert="mobileOpen">
    <x-brand /><button type="button" class="icon-button mobile-close" @click="mobileOpen = false" aria-label="Close navigation"><x-icon name="close" /></button>
    <p class="nav-label">{{ auth()->user()->isHr() ? 'HR WORKSPACE' : 'MY WORKSPACE' }}</p>
    <nav aria-label="Main">
    @foreach($items as $name => [$label, $icon])
        @php($active = request()->routeIs(str_ends_with($name, '.index') ? substr($name, 0, -6).'.*' : $name))
        <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ route($name) }}" title="{{ $label }}" @if($active) aria-current="page" @endif><x-icon :name="$icon" /><span class="nav-text">{{ $label }}</span></a>
    @endforeach
    </nav>
    <div class="sidebar-bottom"><a class="nav-link" href="{{ route('employee.dashboard') }}" title="My Workday"><x-icon name="calendar" /><span class="nav-text">My Workday</span></a><button type="button" class="nav-link logout" title="Sign out" @click="$dispatch('open-modal', 'logout')"><x-icon name="logout" /><span class="nav-text">Sign out</span></button></div>
</aside>

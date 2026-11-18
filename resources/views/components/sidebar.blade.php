@php
    $actor = auth()->user();
    $groups = match (true) {
        $actor->isHr() => [
            'Overview' => ['hr.dashboard' => ['Dashboard', 'grid']],
            'Workforce' => ['hr.employees.index'=>['Employee Management','users'],'hr.supervisors.index'=>['Supervisors','users'],'hr.branches.index'=>['Branch Management','building'],'hr.departments.index'=>['Departments','building']],
            'Attendance & leave' => ['attendance'=>['Attendance Monitoring','clock'],'hr.corrections.index'=>['Attendance Corrections','file'],'hr.leave.index'=>['Leave Management','calendar'],'hr.leave.calendar.index'=>['Leave Calendar','calendar']],
            'Workspace' => ['reports'=>['Reports and Analytics','chart'],'settings'=>['Settings','settings']],
        ],
        $actor->isSupervisor() => [
            'Leave Management' => ['supervisor.leave.index'=>['Leave Overview','grid'],'supervisor.leave.pending'=>['Pending Leave Requests','calendar'],'supervisor.leave.history'=>['Leave History','file'],'supervisor.leave.calendar.index'=>['Team Leave Calendar','calendar']],
            'My account' => array_filter(['employee.leave.index'=>$actor->employee ? ['My Leave','calendar'] : null,'settings'=>['My Account','settings'],'supervisor.signature.show'=>['Electronic Signature','file']]),
        ],
        default => [
            'My workday' => ['employee.dashboard'=>['Dashboard','grid'],'attendance'=>['Time In / Time Out','clock'],'attendance.history'=>['Attendance History','file'],'employee.leave.index'=>['My Leave','calendar'],'employee.leave.calendar.index'=>['My Leave Calendar','calendar'],'employee.corrections.index'=>['Attendance Corrections','file']],
            'My account' => ['employee.profile'=>['My Profile','users'],'settings'=>['Account Settings','settings']],
        ],
    };
@endphp
<aside class="sidebar" :class="{ 'is-open': mobileOpen }" id="workspace-navigation" aria-label="Workspace navigation" x-bind:inert="!mobileOpen &amp;&amp; !(media &amp;&amp; media.matches)" x-trap.inert="mobileOpen">
<div class="sidebar-brand-row"><x-brand/><button type="button" class="icon-button mobile-close" @click="mobileOpen = false" aria-label="Close navigation"><x-icon name="close"/></button></div>
<nav aria-label="Main">@foreach($groups as $group=>$items)<div class="nav-group"><p class="nav-label">{{ $group }}</p>
@foreach($items as $name=>[$label,$icon])
@php
$active = match($name) {
'attendance' => $actor->isHr() ? request()->routeIs('hr.attendance.*','attendance.show') && !request()->routeIs('hr.corrections.*') : request()->routeIs('employee.attendance','attendance.show'),
'attendance.history'=>request()->routeIs('employee.attendance.history'),
'hr.leave.index'=>request()->routeIs('hr.leave.*') && !request()->routeIs('hr.leave.calendar.*'),
'employee.leave.index'=>request()->routeIs('employee.leave.*') && !request()->routeIs('employee.leave.calendar.*'),
'supervisor.leave.index'=>request()->routeIs('supervisor.leave.index','supervisor.leave.show'),
default=>request()->routeIs(str_ends_with($name,'.index') ? substr($name,0,-6).'.*' : $name),
};
@endphp
<a class="nav-link {{ $active ? 'active' : '' }}" href="{{ route($name) }}" title="{{ $label }}" @if($active) aria-current="page" @endif><x-icon :name="$icon"/><span class="nav-text">{{ $label }}</span></a>
@endforeach</div>@endforeach</nav>
<div class="sidebar-bottom">@if($actor->isHr())<a class="nav-link {{ request()->routeIs('employee.*') ? 'active' : '' }}" href="{{ route('employee.dashboard') }}" title="My Workday"><x-icon name="calendar"/><span class="nav-text">My Workday</span></a>@endif
<button type="button" class="nav-link logout" title="Sign out" @click="$dispatch('open-modal', 'logout')"><x-icon name="logout"/><span class="nav-text">Sign out</span></button></div></aside>

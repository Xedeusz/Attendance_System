@php
    $actor = auth()->user();
    $groups = match (true) {
        $actor->isHr() => [
            'Overview' => ['hr.dashboard' => ['Dashboard', 'grid']],
            'Workforce' => ['hr.employees.index'=>['Employee Management','users'],'hr.supervisors.index'=>['Supervisors','users'],'hr.branches.index'=>['Branch Management','building'],'hr.departments.index'=>['Departments','building']],
            'Attendance management' => ['attendance'=>['Attendance Records','clock'],'hr.schedules.index'=>['Schedules & Policies','clock'],'hr.holidays.index'=>['Holiday Management','calendar'],'hr.corrections.index'=>['Attendance Corrections','file']],
            'Leave' => ['hr.leave.index'=>['Leave Management','calendar'],'hr.leave.calendar.index'=>['Leave Calendar','calendar']],
            'Workspace' => ['reports'=>['Reports and Analytics','chart'],'settings'=>['Settings','settings']],
        ],
        $actor->isSupervisor() => [
            'Leave Management' => ['supervisor.leave.index'=>['Leave Overview','grid'],'supervisor.leave.pending'=>['Pending Leave Requests','calendar'],'supervisor.leave.history'=>['Leave History','file'],'supervisor.leave.calendar.index'=>['Team Leave Calendar','calendar']],
            'My account' => array_filter(['employee.leave.index'=>$actor->employee ? ['My Leave','calendar'] : null,'settings'=>['My Account','settings'],'supervisor.signature.show'=>['Electronic Signature','file']]),
        ],
        default => [
            'My workday' => ['employee.dashboard'=>['Dashboard','grid'],'attendance'=>['Time In / Time Out','clock'],'attendance.history'=>['Attendance History','file'],'employee.work-calendar'=>['My Work Calendar','calendar'],'employee.leave.index'=>['My Leave','calendar'],'employee.leave.calendar.index'=>['My Leave Calendar','calendar'],'employee.corrections.index'=>['Attendance Corrections','file']],
            'My account' => ['employee.profile'=>['My Profile','users'],'settings'=>['Account Settings','settings']],
        ],
    };
@endphp
<aside class="sidebar" :class="{ 'is-open': mobileOpen }" id="workspace-navigation" aria-label="Workspace navigation" x-bind:inert="!mobileOpen &amp;&amp; !(media &amp;&amp; media.matches)" x-trap.inert.noscroll.noreturn="mobileOpen" :role="mobileOpen ? 'dialog' : null" :aria-modal="mobileOpen ? 'true' : null">
<div class="sidebar-brand-row"><x-brand/><button type="button" class="icon-button mobile-close" @click="closeNavigation()" aria-label="Close navigation"><x-icon name="close"/></button></div>
<nav class="sidebar-navigation" aria-label="Main">@foreach($groups as $group=>$items)<div class="nav-group"><p class="nav-label">{{ $group }}</p>
@foreach($items as $name=>[$label,$icon])
@php
$active = match($name) {
'hr.schedules.index' => request()->routeIs('hr.schedules.*', 'hr.schedule-exceptions.*'),
'hr.holidays.index' => request()->routeIs('hr.holidays.*', 'hr.holiday-import.*'),
'attendance' => $actor->isHr() ? request()->routeIs('hr.attendance.*','attendance.show') && !request()->routeIs('hr.corrections.*') : request()->routeIs('employee.attendance','attendance.show'),
'attendance.history'=>request()->routeIs('employee.attendance.history'),
'hr.leave.index'=>request()->routeIs('hr.leave.*') && !request()->routeIs('hr.leave.calendar.*'),
'employee.leave.index'=>request()->routeIs('employee.leave.*') && !request()->routeIs('employee.leave.calendar.*'),
'supervisor.leave.index'=>request()->routeIs('supervisor.leave.index','supervisor.leave.show'),
default=>request()->routeIs(str_ends_with($name,'.index') ? substr($name,0,-6).'.*' : $name),
};
@endphp
<a class="nav-link {{ $active ? 'active' : '' }}" href="{{ route($name) }}" aria-label="{{ $label }}" title="{{ $label }}" @if($active) aria-current="page" @endif><x-icon :name="$icon"/><span class="nav-text">{{ $label }}</span></a>
@endforeach</div>@endforeach</nav>
<div class="sidebar-bottom"><button type="button" class="nav-link logout" aria-label="Sign out" title="Sign out" @click="closeNavigation(); $nextTick(() => $dispatch('open-modal', 'logout'))"><x-icon name="logout"/><span class="nav-text">Sign out</span></button></div></aside>

@include(auth()->user()->isHr() ? 'hr.dashboard' : 'employee.dashboard')

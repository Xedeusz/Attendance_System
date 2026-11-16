<x-layouts.app :title="$definition['title']">
    <x-page-header :title="$definition['title']" :description="$definition['description']"><x-button disabled>{{ $definition['action'] }}</x-button></x-page-header>
    <section class="card availability-panel" aria-labelledby="availability-heading">
        <x-badge>Not enabled</x-badge>
        <h2 id="availability-heading">{{ $definition['empty'] }}</h2>
        <p>{{ $definition['detail'] }}</p>
        <p class="mt-3">This page is a structural preview. {{ $module === 'leave' ? 'Leave applications and approvals are not enabled. Contact HR about leave.' : 'Report filters, charts and exports are not enabled. Use Attendance Monitoring to inspect recorded activity.' }}</p>
        <div class="page-actions"><x-button variant="secondary" :href="auth()->user()->isHr() ? route('hr.attendance.index') : route('employee.attendance')">{{ auth()->user()->isHr() ? 'Monitor attendance' : 'Open attendance' }}</x-button><a class="text-link" href="{{ route('dashboard') }}">Back to dashboard <x-icon name="arrow" /></a></div>
    </section>
</x-layouts.app>

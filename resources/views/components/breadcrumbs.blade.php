@props(['title'])
<nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('dashboard') }}">Workspace</a><x-icon name="chevron" style="width:10px;height:10px" /><span class="topbar-title" aria-current="page" title="{{ $title }}">{{ $title }}</span></nav>

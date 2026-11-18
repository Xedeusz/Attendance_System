@props(['unread'])

<div class="relative shrink-0"
     x-data="notificationDropdown(@js($unread), @js(route('notifications.dropdown.index', absolute: false)), @js(route('notifications.dropdown.read-all', absolute: false)))"
     @workspace:notifications.window="sync($event.detail)" @click.outside="close()" @keydown.escape.window="close(true)">
    <button x-ref="bell" type="button" aria-label="Notifications" title="Notifications" aria-expanded="false"
            :aria-expanded="open" aria-controls="topbar-notifications" @click="toggle()"
            class="relative grid size-10 place-items-center rounded-xl text-[#2F1B72] transition-colors hover:bg-[#F0ECF8] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F1B72]">
        <span class="[&>svg]:size-5" aria-hidden="true"><x-icon name="bell" /></span>
        <span data-sync-unread aria-hidden="true" :hidden="!unread" @if(!$unread) hidden @endif
              class="absolute top-0 right-0 -translate-y-1/5 translate-x-1/5 rounded-full bg-[#087C68] px-1 text-[10px] leading-4 font-bold text-white ring-2 ring-white"
              x-text="unread > 99 ? '99+' : unread">{{ $unread > 99 ? '99+' : $unread }}</span>
    </button>
    <span class="sr-only" role="status" x-text="unread + ' unread notifications'">{{ $unread }} unread notifications</span>

    <section id="topbar-notifications" x-ref="panel" tabindex="-1" aria-labelledby="topbar-notifications-heading"
             x-show="open" x-cloak
             x-transition:enter="transition duration-150 ease-out motion-reduce:transition-none"
             x-transition:enter-start="-translate-y-1 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             class="fixed inset-x-3 top-16 z-[60] flex max-h-[min(29rem,calc(100dvh-5rem))] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-md focus-visible:outline-2 focus-visible:outline-[#2F1B72] md:absolute md:inset-x-auto md:top-[calc(100%+0.5rem)] md:right-0 md:w-[24rem]">
        <header class="shrink-0 border-b border-slate-200 px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <h2 id="topbar-notifications-heading" class="font-bold text-[#2F1B72]">Notifications</h2>
                <span class="text-xs font-semibold text-[#087C68]" x-text="unread + ' unread'"></span>
            </div>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-x-3">
                <p class="text-xs text-slate-600">Your latest updates</p>
                <button type="button" class="text-link min-h-8 text-xs disabled:text-slate-500" :disabled="!unread" :aria-disabled="busy" @click="mark(null, false, $refs.panel)">Mark all as read</button>
            </div>
        </header>

        <div class="min-h-0 overflow-y-auto overscroll-contain">
            <p x-show="loading && !loaded" class="px-4 py-5 text-sm text-slate-600" role="status">Loading notifications…</p>
            <div x-show="loadError" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <p role="status">Unable to load notifications.</p>
                <button type="button" @click="refresh()" class="text-link min-h-9">Retry</button>
            </div>
            <p x-show="message" x-text="message" role="status" class="mx-3 mt-3 rounded bg-slate-100 px-3 py-2 text-sm"></p>
            <div x-show="loaded && !loadError && !items.length" class="px-4 py-6 text-center">
                <span class="mx-auto mb-2 grid size-9 place-items-center rounded-lg bg-slate-100 text-slate-600"><x-icon name="bell" /></span>
                <p class="text-sm font-semibold">You’re all caught up</p>
                <p class="mt-1 text-xs text-slate-600">No new notifications right now.</p>
            </div>
            <ul class="divide-y divide-slate-100">
                <template x-for="item in items" :key="item.id">
                    <li class="flex min-w-0 items-center gap-1 px-3" :class="item.unread ? 'bg-[#E7F7F2]' : 'bg-white'">
                        <button type="button" @click="mark(item, item.can_open)" :aria-disabled="busy"
                                class="flex min-w-0 flex-1 items-start gap-3 rounded px-1 py-3 text-left hover:bg-[#F0ECF8] focus-visible:outline-2 focus-visible:outline-[#2F1B72]">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-[#F0ECF8] text-[#2F1B72]" aria-hidden="true">
                                <span x-show="item.category === 'leave'"><x-icon name="calendar" /></span>
                                <span x-show="item.category === 'correction'"><x-icon name="clock" /></span>
                                <span x-show="!['leave', 'correction'].includes(item.category)"><x-icon name="bell" /></span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block wrap-break-word text-sm" :class="item.unread && 'font-semibold'" x-text="item.title"></span>
                                <span x-show="item.message" class="mt-1 block wrap-break-word text-xs text-slate-600" x-text="item.message"></span>
                                <time class="mt-1 block text-xs text-slate-600" :datetime="item.datetime" x-text="item.time"></time>
                            </span>
                            <span x-show="item.unread" class="mt-1.5 size-2 shrink-0 rounded-full bg-[#087C68]"><span class="sr-only">Unread</span></span>
                        </button>
                        <button type="button" x-show="item.unread" @click="mark(item, false, $event.currentTarget.previousElementSibling)" :aria-disabled="busy"
                                :aria-label="'Mark as read: ' + item.title" title="Mark as read"
                                class="grid size-9 shrink-0 place-items-center rounded text-[#087C68] hover:bg-white focus-visible:outline-2 focus-visible:outline-[#2F1B72]">
                            <x-icon name="check" />
                        </button>
                    </li>
                </template>
            </ul>
        </div>
        <footer class="shrink-0 border-t border-slate-200 px-4 py-2 text-center">
            <a class="text-link inline-block py-1 text-sm" href="{{ route(auth()->user()->isSupervisor() ? 'supervisor.leave.notices' : 'notifications') }}" @click="close()">View notification history</a>
        </footer>
    </section>
</div>

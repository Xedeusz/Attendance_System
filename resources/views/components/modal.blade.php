@props(['id', 'title'])
<div x-data="{ open: false }" @open-modal.window="if ($event.detail === '{{ $id }}') open = true" @keydown.escape.window="open = false">
    <div class="modal-overlay" x-show="open" x-cloak x-trap.inert.noscroll="open" @click.self="open = false">
        <section class="modal" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title"><div class="flex items-center justify-between gap-3"><h2 id="{{ $id }}-title">{{ $title }}</h2><button type="button" class="icon-button" @click="open = false" aria-label="Close dialog"><x-icon name="close" /></button></div>{{ $slot }}</section>
    </div>
</div>

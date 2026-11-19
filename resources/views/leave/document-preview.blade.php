<dialog x-ref="viewer" aria-labelledby="document-preview-name" @close="clear()" @click.self="$el.close()"
    class="fixed inset-0 m-auto w-[94vw] max-w-[1100px] max-h-[90dvh] overflow-hidden rounded-lg border border-slate-200 bg-white p-0 text-slate-900 backdrop:bg-black/55 md:w-[86vw]">
    <div class="flex h-[84dvh] flex-col">
        <header class="flex items-center justify-between gap-4 border-b border-slate-200 px-4 py-3">
            <h2 id="document-preview-name" class="min-w-0 wrap-anywhere text-base font-semibold" x-text="name"></h2>
            <button type="button" class="icon-button shrink-0" autofocus aria-label="Close document preview" @click="$refs.viewer.close()"><x-icon name="close" /></button>
        </header>
        <div class="relative min-h-0 flex-1 overflow-auto bg-slate-50">
            <p role="status" class="p-4" x-show="status === 'loading'">Loading document…</p>
            <template x-if="status === 'pdf'"><iframe class="block h-full w-full border-0" :src="source" :title="name"></iframe></template>
            <template x-if="status === 'image'"><img class="mx-auto block h-full max-w-full object-contain" :src="source" :alt="name" x-on:error="status = 'error'"></template>
            <div x-show="status === 'unsupported'" class="p-4">
                <h3 class="font-semibold">Preview Not Available</h3>
                <p>This document format cannot be previewed. Please download the file to view it.</p>
            </div>
            <p x-show="status === 'error'" role="alert" class="p-4">The document could not be loaded. Close the preview and try again.</p>
        </div>
        <footer class="flex justify-end border-t border-slate-200 px-4 py-3"><a class="text-link" :href="download">Download document</a></footer>
    </div>
</dialog>

export default function registerLeaveDocumentPreview(Alpine) {
    Alpine.data("leaveDocumentPreview", () => ({
        name: "", download: "", source: "", status: "", request: null,
        async view(button) {
            this.name = button.dataset.name;
            this.download = button.dataset.download;
            this.status = "loading";
            this.$refs.viewer.showModal();
            const request = new AbortController();
            this.request = request;
            try {
                const response = await fetch(button.dataset.preview, {
                    signal: request.signal, redirect: "error", credentials: "same-origin",
                });
                if (response.status === 415) {
                    this.status = "unsupported";
                    return;
                }
                if (!response.ok) throw new Error();
                const blob = await response.blob();
                if (request.signal.aborted) return;
                if (!["application/pdf", "image/jpeg", "image/png", "image/webp"].includes(blob.type)) {
                    this.status = "unsupported";
                    return;
                }
                this.source = URL.createObjectURL(blob);
                this.status = blob.type === "application/pdf" ? "pdf" : "image";
            } catch {
                if (!request.signal.aborted) this.status = "error";
            }
        },
        clear() {
            this.request?.abort();
            if (this.source) URL.revokeObjectURL(this.source);
            this.source = "";
            this.status = "";
        },
        destroy() { this.clear(); },
    }));
}

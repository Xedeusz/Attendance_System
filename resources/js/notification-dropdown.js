export default function registerNotificationDropdown(Alpine) {
    Alpine.data(
        "notificationDropdown",
        (initialUnread, listUrl, readAllUrl) => ({
            open: false,
            unread: initialUnread,
            items: [],
            loaded: false,
            loading: false,
            busy: false,
            loadError: false,
            message: "",
            revision: undefined,
            requestId: 0,
            updatedAt: 0,
            toggle() {
                if (this.open) {
                    this.close(true);
                    return;
                }
                this.open = true;
                this.message = "";
                this.$nextTick(() => this.$refs.panel.focus());
                this.refresh();
            },
            close(restoreFocus = false) {
                if (!this.open) return;
                this.open = false;
                if (restoreFocus) this.$refs.bell.focus();
            },
            sync(state) {
                if (this.busy || state.startedAt < this.updatedAt) return;
                const changed =
                    this.revision !== state.revision ||
                    this.unread !== state.unread;
                this.revision = state.revision;
                this.unread = state.unread;
                if (this.open && (changed || state.force)) this.refresh();
            },
            async refresh() {
                const requestId = ++this.requestId;
                this.loading = true;
                this.loadError = false;
                try {
                    const response = await fetch(listUrl, {
                        headers: { Accept: "application/json" },
                        signal: AbortSignal.timeout(12000),
                    });
                    if (!response.ok)
                        throw new Error("Notifications unavailable");
                    const result = await response.json();
                    if (requestId !== this.requestId) return;
                    this.items = result.items;
                    this.unread = result.unread;
                    this.loaded = true;
                } catch {
                    if (requestId === this.requestId) this.loadError = true;
                } finally {
                    if (requestId === this.requestId) this.loading = false;
                }
            },
            async mark(item, open = false, focusTarget = null) {
                if (this.busy) return;
                this.requestId++;
                this.loading = false;
                this.busy = true;
                this.message = "";
                try {
                    const response = await fetch(item?.read_url || readAllUrl, {
                        method: "POST",
                        headers: {
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({ open }),
                        signal: AbortSignal.timeout(12000),
                    });
                    if (!response.ok)
                        throw new Error("Notification action unavailable");
                    const result = await response.json();
                    this.updatedAt = performance.now();
                    this.unread = result.unread;
                    if (result.destination) {
                        this.close(true);
                        window.location.assign(result.destination);
                        return;
                    }
                    this.message = result.message || "";
                    if (this.open && focusTarget?.isConnected)
                        focusTarget.focus();
                    this.items = this.items.map((notice) =>
                        !item || item.id === notice.id
                            ? { ...notice, unread: false }
                            : notice,
                    );
                } catch {
                    this.message =
                        "Unable to update notifications. Please try again.";
                } finally {
                    this.busy = false;
                    if (this.open) this.refresh();
                }
            },
        }),
    );
}

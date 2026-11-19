export default function registerWorkCalendar(Alpine) {
    Alpine.data("workCalendar", () => ({
        filters: { work: true, rest: true, holidays: true, leave: true },
        details: "",
        pending: null,
        reset() {
            Object.keys(this.filters).forEach((filter) => {
                this.filters[filter] = true;
            });
        },
        async openDate(event) {
            if (
                event.ctrlKey ||
                event.metaKey ||
                event.shiftKey ||
                event.altKey
            )
                return;

            event.preventDefault();
            this.pending?.abort();
            const request = new AbortController();
            this.pending = request;
            const fallback = event.currentTarget.href;
            const url = new URL(fallback);
            url.searchParams.set("details", "1");

            try {
                const response = await fetch(url, { signal: request.signal });
                if (!response.ok || response.redirected)
                    throw new Error("Calendar details unavailable");
                const details = await response.text();
                if (request.signal.aborted) return;
                this.details = details;
                this.$dispatch("open-modal", "work-date");
            } catch {
                if (!request.signal.aborted) window.location.assign(fallback);
            } finally {
                if (this.pending === request) this.pending = null;
            }
        },
        destroy() {
            this.pending?.abort();
        },
    }));
}

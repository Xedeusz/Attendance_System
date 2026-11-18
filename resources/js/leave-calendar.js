const dayMilliseconds = 86400000;
const views = ["month", "week", "list"];
const dateFormatter = new Intl.DateTimeFormat("en-PH", {
    timeZone: "UTC",
    month: "short",
    day: "numeric",
    year: "numeric",
});
const monthFormatter = new Intl.DateTimeFormat("en-PH", {
    timeZone: "UTC",
    month: "long",
    year: "numeric",
});
const overlapLabels = {
    approved_approved: "Approved leave overlaps approved leave",
    approved_pending: "Pending leave overlaps approved leave",
    pending_pending: "Multiple pending requests overlap",
};
const statusColors = {
    approved: "bg-[#E7F7F2] text-[#087C68]",
    pending_supervisor_approval: "bg-amber-100 text-amber-900",
    awaiting_supervisor_assignment: "bg-[#F0ECF8] text-[#2F1B72]",
    rejected: "bg-red-100 text-red-900",
    revoked: "bg-red-100 text-red-900",
    cancelled: "bg-slate-100 text-slate-700",
};

function civil(date) {
    return new Date(date + "T00:00:00Z");
}

function iso(date) {
    return date.toISOString().slice(0, 10);
}

function shift(date, days) {
    return iso(new Date(civil(date).getTime() + days * dayMilliseconds));
}

function monthStart(date) {
    return date.slice(0, 7) + "-01";
}

function shiftMonth(date, months) {
    const first = civil(monthStart(date));
    first.setUTCMonth(first.getUTCMonth() + months);
    return iso(first);
}

function weekStart(date) {
    return shift(date, -((civil(date).getUTCDay() + 6) % 7));
}

function validDate(date) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date || "")) return false;
    const parsed = civil(date);
    return (
        Number.isFinite(parsed.getTime()) &&
        iso(parsed) === date &&
        date >= "0002-01-01" &&
        date < "9999-01-01"
    );
}

export default function registerLeaveCalendar(Alpine) {
    Alpine.data(
        "leaveCalendar",
        ({ initialToday, eventsUrl, role, statuses, filterNames }) => ({
            role,
            statuses,
            today: initialToday,
            anchor: initialToday,
            selectedDay: initialToday,
            view: "month",
            filters: {},
            events: [],
            byDay: {},
            staffing: null,
            team: [],
            cursor: null,
            loading: true,
            error: false,
            detailId: null,
            detail: null,
            detailError: false,
            eventRequest: 0,
            detailRequest: 0,
            refreshTimer: null,
            init() {
                const query = new URLSearchParams(location.search);
                this.view = views.includes(query.get("view"))
                    ? query.get("view")
                    : matchMedia("(max-width: 639px)").matches
                      ? "list"
                      : "month";
                const savedDate = query.get("date");
                this.anchor = validDate(savedDate) ? savedDate : this.today;
                this.selectedDay = this.anchor;
                this.filters = Object.fromEntries(
                    filterNames.map((key) => [key, query.get(key) || ""]),
                );
                this.load();
            },
            destroy() {
                clearTimeout(this.refreshTimer);
                this.eventRequest++;
                this.detailRequest++;
            },
            get range() {
                if (this.view === "week") {
                    const from = weekStart(this.anchor);
                    return { from, to: shift(from, 6) };
                }
                const first = monthStart(this.anchor);
                const last = shift(shiftMonth(this.anchor, 1), -1);
                return this.view === "list"
                    ? { from: first, to: last }
                    : { from: weekStart(first), to: shift(weekStart(last), 6) };
            },
            get heading() {
                return this.view === "week"
                    ? this.dates(this.range.from, this.range.to)
                    : monthFormatter.format(civil(this.anchor));
            },
            get days() {
                const { from, to } = this.range;
                const count = (civil(to) - civil(from)) / dayMilliseconds + 1;
                return Array.from({ length: count }, (_, index) =>
                    shift(from, index),
                );
            },
            get dayStaffing() {
                return (
                    this.staffing?.days[this.selectedDay] || {
                        approved: 0,
                        pending: 0,
                        approved_half_day: 0,
                        overlaps: [],
                    }
                );
            },
            get overlapMessages() {
                return this.dayStaffing.overlaps.map(
                    (overlap) => overlapLabels[overlap],
                );
            },
            get selectedEvents() {
                return this.byDay[this.selectedDay] || [];
            },
            get legend() {
                if (this.filters.status) return [this.filters.status];
                return [
                    "approved",
                    "pending_supervisor_approval",
                    "awaiting_supervisor_assignment",
                ].filter((status) => this.statuses[status]);
            },
            get detailLinkLabel() {
                if (this.role === "employee") return "View My Leave Details";
                if (
                    this.role === "supervisor" &&
                    this.detail?.status === "pending_supervisor_approval"
                )
                    return "Review Application";
                return "View Leave Application";
            },
            format(date) {
                return dateFormatter.format(civil(date));
            },
            dates(start, end) {
                return start === end
                    ? this.format(start)
                    : this.format(start) + " – " + this.format(end);
            },
            statusColor(status) {
                return statusColors[status] || statusColors.cancelled;
            },
            shortStatus(status) {
                return status === "pending_supervisor_approval"
                    ? "Pending"
                    : this.statuses[status];
            },
            halfDay(event) {
                return event.half_day === "am"
                    ? "Morning half-day"
                    : event.half_day === "pm"
                      ? "Afternoon half-day"
                      : "";
            },
            dayNumber(date) {
                return civil(date).getUTCDate();
            },
            navigate(step) {
                this.selectPeriod(
                    this.view === "week"
                        ? shift(this.anchor, step * 7)
                        : shiftMonth(this.anchor, step),
                );
            },
            selectPeriod(date) {
                if (!validDate(date)) return;
                this.anchor = date;
                this.selectedDay = date;
                this.change();
            },
            changeView(view) {
                if (this.view === view) return;
                this.view = view;
                this.selectPeriod(this.selectedDay);
            },
            clearFilters() {
                for (const key of Object.keys(this.filters))
                    this.filters[key] = "";
                this.change();
            },
            change(delay = 0) {
                const query = new URLSearchParams({
                    view: this.view,
                    date: this.anchor,
                });
                for (const [key, value] of Object.entries(this.filters)) {
                    if (value) query.set(key, value);
                }
                history.replaceState(
                    history.state,
                    "",
                    location.pathname + "?" + query,
                );
                this.schedule(delay);
            },
            schedule(delay = 100) {
                clearTimeout(this.refreshTimer);
                this.eventRequest++;
                this.loading = true;
                this.error = false;
                this.events = [];
                this.byDay = {};
                this.staffing = null;
                this.cursor = null;
                this.refreshTimer = setTimeout(() => this.load(), delay);
                if (this.detailId) this.fetchDetails(this.detailId);
            },
            async load(append = false) {
                const request = ++this.eventRequest;
                this.loading = true;
                this.error = false;
                const { from, to } = this.range;
                const query = new URLSearchParams({ from, to });
                for (const [key, value] of Object.entries(this.filters)) {
                    if (value) query.set(key, value);
                }
                if (append && this.cursor) query.set("cursor", this.cursor);

                try {
                    const data = await this.fetchJson(eventsUrl + "?" + query);
                    if (request !== this.eventRequest) return;
                    this.today = data.today;
                    this.events = append
                        ? [
                              ...new Map(
                                  [...this.events, ...data.events].map(
                                      (entry) => [entry.id, entry],
                                  ),
                              ).values(),
                          ]
                        : data.events;
                    this.cursor = data.next_cursor;
                    if (!append) {
                        this.staffing = data.staffing;
                        this.team = data.team || [];
                        if (
                            this.role === "supervisor" &&
                            this.filters.employee_id &&
                            !this.team.some(
                                (employee) =>
                                    String(employee.id) ===
                                    this.filters.employee_id,
                            )
                        ) {
                            this.filters.employee_id = "";
                            this.change();
                            return;
                        }
                    }
                    const byDay = {};
                    for (const entry of this.events) {
                        for (
                            let day = entry.start < from ? from : entry.start;
                            day <= entry.end && day <= to;
                            day = shift(day, 1)
                        ) {
                            (byDay[day] ??= []).push(entry);
                        }
                    }
                    this.byDay = byDay;
                } catch {
                    if (request === this.eventRequest) this.error = true;
                } finally {
                    if (request === this.eventRequest) this.loading = false;
                }
            },
            openDetails(id) {
                this.detailId = id;
                this.fetchDetails(id);
            },
            closeDetails() {
                this.detailId = null;
                this.detail = null;
                this.detailRequest++;
            },
            async fetchDetails(id) {
                const request = ++this.detailRequest;
                this.detail = null;
                this.detailError = false;
                try {
                    const data = await this.fetchJson(eventsUrl + "/" + id);
                    if (request === this.detailRequest) this.detail = data;
                } catch {
                    if (request === this.detailRequest) this.detailError = true;
                }
            },
            async fetchJson(url) {
                const response = await fetch(url, {
                    headers: { Accept: "application/json" },
                    signal: AbortSignal.timeout(12000),
                });
                if (!response.ok)
                    throw new Error("Leave calendar request failed");
                return response.json();
            },
        }),
    );
}

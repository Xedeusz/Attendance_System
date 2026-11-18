import Echo from "laravel-echo";
import Pusher from "pusher-js";

export default function initializeRealtime(Alpine) {
    const element = document.getElementById("workspace-sync-config");
    if (!element) return;
    const config = JSON.parse(element.textContent);
    const region = document.querySelector('[data-sync-region="page"]');
    const status = document.querySelector("[data-sync-status]");
    const leaveCalendar = document.querySelector("[data-leave-calendar]");
    const notice = document.getElementById("workspace-update-notice");
    const path = location.pathname;
    const pageTopics = path.includes("dashboard")
        ? [
              "employees",
              "branches",
              "departments",
              "attendance",
              "leave",
              "corrections",
          ]
        : path.includes("corrections")
          ? ["corrections", "attendance"]
          : path.includes("attendance")
            ? ["attendance", "employees", "branches", "departments"]
            : path.includes("leave") || path.includes("supervisors")
              ? ["leave", "employees", "departments"]
              : path.includes("employees")
                ? ["employees", "branches", "departments"]
                : path.includes("branches")
                  ? ["branches", "employees"]
                  : path.includes("departments")
                    ? ["departments", "employees"]
                    : path.includes("notifications")
                      ? ["notifications", "attendance", "leave", "corrections"]
                      : ["account", "employees"];
    if (path.includes("notifications")) pageTopics.push("notifications");
    let echo,
        channel,
        subscribed = false,
        destroyed = false;
    let revisions = null,
        pending = false,
        pendingForce = false,
        running = false,
        timer;
    let lastHeartbeat = 0;
    let businessDate;
    const setStatus = (text) => {
        if (status) status.textContent = text;
    };
    const markDirty = (event) => {
        const form = event.target.closest("form");
        if (form && form.method.toLowerCase() !== "get")
            form.dataset.syncDirty = "true";
    };
    document.addEventListener("input", markDirty);
    document.addEventListener("change", markDirty);
    function showConflict() {
        if (notice) notice.hidden = false;
    }
    function authorize(response) {
        if ([401, 403, 419].includes(response.status) || response.redirected) {
            echo?.disconnect();
            setStatus("Session changed");
            location.assign(
                response.redirected ? response.url : config.loginUrl,
            );
            throw new Error("Session no longer authorized");
        }
        if (!response.ok) throw new Error("Synchronization unavailable");
        return response;
    }
    async function refreshPage() {
        if (leaveCalendar) return;
        // The active camera/reconciliation component is updated only through its state API.
        if (document.querySelector('[x-data^="attendanceCapture"]')) return;
        const response = authorize(
            await fetch(location.href, {
                credentials: "same-origin",
                headers: { "X-Workspace-Sync": "1" },
                signal: AbortSignal.timeout(15000),
            }),
        );
        const text = await response.text();
        const incoming = new DOMParser()
            .parseFromString(text, "text/html")
            .querySelector('[data-sync-region="page"]');
        if (!incoming || destroyed)
            throw new Error("Unexpected synchronization response");
        const scroll = [window.scrollX, window.scrollY];
        Alpine.morph(region, incoming.outerHTML, {
            updating(from, to, childrenOnly, skip) {
                if (from instanceof HTMLFormElement) {
                    if (
                        from.hasAttribute("data-sync-clean") &&
                        !from.dataset.syncDirty
                    )
                        return;
                    if (from.method.toLowerCase() === "get") {
                        for (const select of from.querySelectorAll(
                            "select[name]",
                        )) {
                            const incoming = to.querySelector(
                                `select[name="${CSS.escape(select.name)}"]`,
                            );
                            if (
                                !incoming ||
                                document.activeElement === select ||
                                select.innerHTML === incoming.innerHTML
                            )
                                continue;
                            const value = select.value;
                            if (
                                [...incoming.options].some(
                                    (option) => option.value === value,
                                )
                            ) {
                                select.replaceChildren(
                                    ...[...incoming.options].map((option) =>
                                        option.cloneNode(true),
                                    ),
                                );
                                select.value = value;
                            }
                        }
                        skip();
                        return;
                    }
                    const previous = from.querySelector(
                        '[name="_edit_version"]',
                    );
                    const latest = to.querySelector('[name="_edit_version"]');
                    if (
                        previous && latest
                            ? previous.value !== latest.value
                            : from.outerHTML !== to.outerHTML
                    )
                        showConflict();
                    skip();
                }
                if (from.matches?.("[data-map-config]")) {
                    if (from.dataset.mapConfig !== to.dataset.mapConfig) {
                        const replacement = to.cloneNode(true);
                        Alpine.destroyTree(from);
                        from.replaceWith(replacement);
                        Alpine.initTree(replacement);
                    }
                    skip();
                }
            },
            removing(node, skip) {
                if (node instanceof HTMLFormElement && node.dataset.syncDirty) {
                    showConflict();
                    skip();
                }
            },
        });
        window.scrollTo(...scroll);
    }
    async function synchronize(force = false) {
        if (destroyed || document.hidden || !navigator.onLine) return;
        if (running) {
            pending = true;
            pendingForce ||= force;
            return;
        }
        running = true;
        const requestStartedAt = performance.now();
        try {
            const url = new URL(config.stateUrl);
            if (document.querySelector('[x-data^="attendanceCapture"]'))
                url.searchParams.set("attendance", "1");
            const response = authorize(
                await fetch(url, {
                    credentials: "same-origin",
                    headers: { Accept: "application/json" },
                    signal: AbortSignal.timeout(15000),
                }),
            );
            const state = await response.json();
            if (state.channel !== config.channel) {
                location.reload();
                return;
            }
            const changed = Object.keys(state.revisions).filter(
                (topic) => revisions?.[topic] !== state.revisions[topic],
            );
            if (
                force ||
                (businessDate && businessDate !== state.business_date) ||
                (revisions &&
                    changed.some((topic) => pageTopics.includes(topic)))
            )
                await refreshPage();
            if (
                state.avatar &&
                Alpine.store("profileAvatar").updatedAt <= requestStartedAt
            )
                Object.assign(Alpine.store("profileAvatar"), state.avatar, {
                    ready: true,
                });
            if (
                leaveCalendar &&
                (force ||
                    businessDate !== state.business_date ||
                    changed.some((topic) =>
                        [
                            "leave",
                            "employees",
                            "departments",
                            "branches",
                        ].includes(topic),
                    ))
            ) {
                window.dispatchEvent(new Event("workspace:leave-calendar"));
            }
            revisions = state.revisions;
            businessDate = state.business_date;
            const name = document.querySelector(".user-name");
            if (name && state.user_name) name.textContent = state.user_name;
            window.dispatchEvent(
                new CustomEvent("workspace:notifications", {
                    detail: {
                        unread: state.unread,
                        revision: state.revisions.notifications,
                        startedAt: requestStartedAt,
                        force,
                    },
                }),
            );
            window.dispatchEvent(
                new CustomEvent("workspace:attendance", {
                    detail: state.attendance,
                }),
            );
            setStatus(subscribed ? "Live" : "Polling · delayed updates");
        } catch {
            setStatus(navigator.onLine ? "Reconnecting · retrying" : "Offline");
        } finally {
            running = false;
            if (pending) {
                pending = false;
                schedule(pendingForce);
                pendingForce = false;
            }
        }
    }
    function schedule(force = false) {
        clearTimeout(timer);
        timer = setTimeout(() => synchronize(force), 250);
    }
    if (config.key) {
        window.Pusher = Pusher;
        echo = new Echo({
            broadcaster: "reverb",
            key: config.key,
            wsHost: config.host,
            wsPort: config.port,
            wssPort: config.port,
            forceTLS: config.scheme === "https",
            enabledTransports: ["ws", "wss"],
            disableStats: true,
            authEndpoint: "/broadcasting/auth",
            auth: {
                headers: {
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]',
                    ).content,
                },
            },
        });
        window.workspaceEcho = echo;
        channel = echo
            .private(config.channel)
            .listen(".workspace.changed", () => schedule());
        channel.subscribed(() => {
            subscribed = true;
            schedule(true);
        });
        channel.error(() => {
            subscribed = false;
            setStatus("Polling · delayed updates");
        });
        echo.connector.pusher.connection.bind("state_change", ({ current }) => {
            if (current !== "connected") {
                subscribed = false;
                setStatus(
                    navigator.onLine ? "Reconnecting · polling" : "Offline",
                );
            }
        });
    }
    const interval = setInterval(() => {
        if (document.hidden) return;
        // A quiet connection is checked for stalled workers and expired/revoked sessions.
        if (!subscribed || Date.now() - lastHeartbeat >= 60000) {
            lastHeartbeat = Date.now();
            synchronize();
        }
    }, config.poll_seconds * 1000);
    const onVisible = () => {
        if (!document.hidden) schedule(true);
    };
    const onOnline = () => schedule(true);
    const onOffline = () => setStatus("Offline");
    const onRestore = (event) => {
        if (event.persisted) {
            echo?.connector.pusher.connect();
            schedule(true);
        }
    };
    document.addEventListener("visibilitychange", onVisible);
    window.addEventListener("online", onOnline);
    window.addEventListener("offline", onOffline);
    window.addEventListener("pageshow", onRestore);
    synchronize(true);
    window.addEventListener("pagehide", (event) => {
        if (event.persisted) {
            echo?.connector.pusher.disconnect();
            subscribed = false;
            return;
        }
        destroyed = true;
        clearTimeout(timer);
        clearInterval(interval);
        document.removeEventListener("input", markDirty);
        document.removeEventListener("change", markDirty);
        document.removeEventListener("visibilitychange", onVisible);
        window.removeEventListener("online", onOnline);
        window.removeEventListener("offline", onOffline);
        window.removeEventListener("pageshow", onRestore);
        echo?.leave(config.channel);
        echo?.disconnect();
    });
}

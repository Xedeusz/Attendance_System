import Alpine from "alpinejs";
function notifyActivationError(status) {
    import("./notifications")
        .then(({ notify }) => notify.ajaxError(status))
        .catch(() => {
            // The activation form already displays the error inline.
        });
}

window.Alpine = Alpine;
Alpine.data("accountActivation", () => ({
    token: "",
    error: "",
    busy: false,
    state: "checking",
    form: "",
    async init() {
        this.token = window.location.hash.slice(1);
        window.history.replaceState(null, "", window.location.pathname);
        if (!/^[a-f0-9]{64}$/.test(this.token)) {
            this.token = "";
            this.state = "invalid";
            return;
        }
        try {
            const response = await fetch(location.pathname, {
                headers: { Accept: "application/json", Authorization: "Bearer " + this.token },
                cache: "no-store",
            });
            if (!response.ok) throw new Error();
            const result = await response.json();
            this.state = result.state;
            this.form = result.form || "";
        } catch {
            this.state = "error";
            this.error = "Could not check the invitation. Reopen the original link and try again.";
        }
    },
    async submit(event) {
        if (this.busy || !this.token) return;
        this.busy = true;
        this.error = "";
        const form = event.currentTarget;
        const body = new FormData(form);
        body.set("token", this.token);
        try {
            const response = await fetch(form.action, {
                method: "POST",
                body,
                headers: { Accept: "application/json" },
                credentials: "same-origin",
            });
            const result = await response.json();
            if (!response.ok) {
                if (result.errors?.token) {
                    this.form = "";
                    this.state = "invalid";
                    return;
                }
                this.error =
                    Object.values(result.errors || {})
                        .flat()
                        .join(" ") ||
                    "Activation could not be completed. Wait and try again, or contact HR.";
                notifyActivationError(response.status);
                return;
            }
            this.token = "";
            window.location.replace(result.redirect);
        } catch {
            notifyActivationError();
            this.error =
                "The request could not be completed. Refresh the original invitation link and try again.";
        } finally {
            this.busy = false;
        }
    },
}));
Alpine.start();

if (document.querySelector("[data-notifications]")) {
    import("./notifications")
        .then(({ initializeNotifications }) => {
            initializeNotifications();
            document.querySelector("[data-notification-fallback]")?.remove();
        })
        .catch(() => {
            // Server-rendered feedback remains visible if the module fails.
        });
}

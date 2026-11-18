import Alpine from "alpinejs";
import { notify, initializeNotifications } from "./notifications";

window.Alpine = Alpine;
Alpine.data("accountActivation", () => ({
    token: "",
    error: "",
    busy: false,
    init() {
        this.token = window.location.hash.slice(1);
        window.history.replaceState(null, "", window.location.pathname);
        if (!/^[a-f0-9]{64}$/.test(this.token)) {
            this.token = "";
            this.error =
                "A complete, valid invitation link is required. Contact HR.";
        }
    },
    async submit() {
        if (this.busy || !this.token) return;
        this.busy = true;
        this.error = "";
        const body = new FormData(this.$el);
        body.set("token", this.token);
        try {
            const response = await fetch(this.$el.action, {
                method: "POST",
                body,
                headers: { Accept: "application/json" },
                credentials: "same-origin",
            });
            const result = await response.json();
            if (!response.ok) {
                this.error =
                    Object.values(result.errors || {})
                        .flat()
                        .join(" ") ||
                    "Activation could not be completed. Wait and try again, or contact HR.";
                notify.ajaxError(response.status);
                return;
            }
            this.token = "";
            window.location.assign(result.redirect);
        } catch {
            notify.ajaxError();
            this.error =
                "The request could not be completed. Refresh the original invitation link and try again.";
        } finally {
            this.busy = false;
        }
    },
}));
Alpine.start();
initializeNotifications();

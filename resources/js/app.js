import Alpine from "alpinejs";
import focus from "@alpinejs/focus";

Alpine.plugin(focus);
Alpine.data("workspaceShell", () => ({
    mobileOpen: false,
    collapsed: false,
    init() {
        this.$watch("mobileOpen", (open) => {
            document.body.style.overflow = open ? "hidden" : "";
        });
        this.media = window.matchMedia("(min-width: 1024px)");
        this.onResize = () => {
            if (this.media.matches) this.mobileOpen = false;
        };
        this.media.addEventListener("change", this.onResize);
    },
    destroy() {
        this.media.removeEventListener("change", this.onResize);
        document.body.style.overflow = "";
    },
    toggleNavigation() {
        this.media.matches
            ? (this.collapsed = !this.collapsed)
            : (this.mobileOpen = !this.mobileOpen);
    },
}));
Alpine.data("displayClock", () => ({
    value: "",
    init() {
        const update = () => {
            this.value = new Intl.DateTimeFormat("en-PH", {
                timeZone: "Asia/Manila",
                dateStyle: "full",
                timeStyle: "medium",
            }).format(new Date());
        };
        update();
        this.timer = setInterval(update, 1000);
    },
    destroy() {
        clearInterval(this.timer);
    },
}));
window.Alpine = Alpine;
Alpine.start();

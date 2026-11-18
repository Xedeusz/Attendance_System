import Alpine from "alpinejs";
import { initializeNotifications } from "./notifications";
import registerProfilePhoto from "./profile-photo";
import registerNotificationDropdown from "./notification-dropdown";
import registerLeaveCalendar from "./leave-calendar";
import focus from "@alpinejs/focus";
import morph from "@alpinejs/morph";
import initializeRealtime from "./realtime";
import registerAttendance from "./attendance";
import registerBranchLocationMap from "./branch-location-map";
import registerAttendanceLocationMap from "./attendance-location-map";

registerProfilePhoto(Alpine);
registerNotificationDropdown(Alpine);
registerLeaveCalendar(Alpine);
Alpine.plugin(focus);
Alpine.plugin(morph);
registerAttendance(Alpine);
registerBranchLocationMap(Alpine);
registerAttendanceLocationMap(Alpine);
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
        const formatter = new Intl.DateTimeFormat("en-PH", {
            timeZone: "Asia/Manila",
            dateStyle: "full",
            timeStyle: "medium",
        });
        const update = () => {
            this.value = formatter.format(new Date());
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
initializeNotifications();
initializeRealtime(Alpine);

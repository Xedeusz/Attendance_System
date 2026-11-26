import Alpine from "alpinejs";
import { initializeNotifications } from "./notifications";
import registerProfilePhoto from "./profile-photo";
import registerNotificationDropdown from "./notification-dropdown";
import registerLeaveDocumentPreview from "./leave-document-preview";
import registerLeaveCalendar from "./leave-calendar";
import registerWorkCalendar from "./work-calendar";
import focus from "@alpinejs/focus";
import morph from "@alpinejs/morph";
import initializeRealtime from "./realtime";
import registerAttendance from "./attendance";
import registerBranchLocationMap from "./branch-location-map";
import registerAttendanceLocationMap from "./attendance-location-map";
import { formatLeaveDays, formatLeaveDaysWithUnit } from "./leave-days";

registerProfilePhoto(Alpine);
registerNotificationDropdown(Alpine);
registerLeaveDocumentPreview(Alpine);
registerLeaveCalendar(Alpine);
registerWorkCalendar(Alpine);
Alpine.plugin(focus);
Alpine.plugin(morph);
registerAttendance(Alpine);
registerBranchLocationMap(Alpine);
registerAttendanceLocationMap(Alpine);
Alpine.data("workspaceShell", () => ({
    mobileOpen: false,
    collapsed: false,
    init() {
        this.media = window.matchMedia("(min-width: 1024px)");
        this.onResize = () => {
            if (this.media.matches) this.closeNavigation();
        };
        this.media.addEventListener("change", this.onResize);
    },
    destroy() {
        this.media.removeEventListener("change", this.onResize);
    },
    toggleNavigation() {
        if (this.media.matches) {
            this.collapsed = !this.collapsed;
        } else if (this.mobileOpen) {
            this.closeNavigation();
        } else {
            this.mobileOpen = true;
        }
    },
    closeNavigation() {
        if (!this.mobileOpen) return;
        this.mobileOpen = false;
        this.$nextTick(() => this.$refs.navigationToggle.focus());
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
Alpine.magic("leaveDays", () => formatLeaveDays);
Alpine.magic("leaveDaysWithUnit", () => formatLeaveDaysWithUnit);
window.Alpine = Alpine;
Alpine.start();
initializeNotifications();
initializeRealtime(Alpine);

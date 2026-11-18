import Swal from "sweetalert2/dist/sweetalert2.js";
import "sweetalert2/dist/sweetalert2.css";

const defaults = {
    position: "top-end",
    backdrop: false,
    width: "min(330px, calc(100vw - 24px))",
    buttonsStyling: false,
    customClass: {
        container: "limcoma-toast-container",
        popup: "limcoma-notification limcoma-compact-toast",
        title: "limcoma-compact-toast-title",
        icon: "limcoma-compact-toast-icon",
        confirmButton: "button swal-confirm",
        cancelButton: "button secondary",
        actions: "limcoma-notification-actions",
    },
    heightAuto: false,
    returnFocus: true,
    showClass: { popup: "", icon: "" },
    hideClass: { popup: "" },
};
let active = false;
const toastIcons = {
    success: '<path d="m5 12 4 4L19 6"/>',
    error: '<path d="m6 6 12 12M6 18 18 6"/>',
    warning: '<path d="m12 3 10 18H2L12 3Zm0 6v5m0 3v.1"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.1"/>',
};
function iconMarkup(type) {
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${toastIcons[type] || toastIcons.info}</svg>`;
}
const toastDefaults = { ...defaults };
for (const option of ["backdrop", "heightAuto", "returnFocus"]) {
    delete toastDefaults[option];
}
const Toast = Swal.mixin({
    ...toastDefaults,
    toast: true,
    position: "top-end",
    width: "min(330px, calc(100vw - 24px))",
    showConfirmButton: false,
    showCancelButton: false,
    showCloseButton: false,
    timer: 3000,
    timerProgressBar: false,
    customClass: {
        container: "limcoma-toast-container",
        popup: "limcoma-compact-toast",
        title: "limcoma-compact-toast-title",
        icon: "limcoma-compact-toast-icon",
    },
    showClass: { popup: "limcoma-toast-enter", icon: "" },
    hideClass: { popup: "limcoma-toast-exit" },
    didRender: (popup) => popup.setAttribute("role", "status"),
});
let loading = false;
let deferredFeedback = null;
const pending = [];
const recent = new Map();

function dialog(options) {
    return new Promise((resolve) => {
        pending.push({ options, resolve });
        drain();
    });
}

async function drain() {
    if (active || !pending.length) return;
    if (Swal.isVisible()) Swal.close();
    const { options, resolve } = pending.shift();
    active = true;
    try {
        resolve(
            await Swal.fire({
                ...defaults,
                ...options,
                iconHtml: iconMarkup(options.icon),
            }),
        );
    } finally {
        active = false;
        drain();
        flushFeedback();
    }
}

function message(icon, title, text = "") {
    return showFeedback(icon, text ? `${title} — ${text}` : String(title));
}

function flushFeedback() {
    if (active || loading || pending.length || !deferredFeedback) return;
    const feedback = deferredFeedback;
    deferredFeedback = null;
    showFeedback(feedback.icon, feedback.text);
}

function showFeedback(icon, text) {
    if (active || loading || pending.length) {
        deferredFeedback = { icon, text };
        return Promise.resolve();
    }
    const type = Object.hasOwn(toastIcons, icon) ? icon : "info";
    const key = `${type}:${text}`;
    if (Date.now() - (recent.get(key) || 0) < 3000) return Promise.resolve();
    recent.set(key, Date.now());
    if (recent.size > 50) recent.delete(recent.keys().next().value);
    return Toast.fire({
        icon: type,
        iconHtml: iconMarkup(type),
        titleText: String(text),
    });
}

export const notify = {
    success: (title, text) => message("success", title, text),
    error: (title, text) => message("error", title, text),
    warning: (title, text) => message("warning", title, text),
    info: (title, text) => message("info", title, text),
    toast(icon, text) {
        return showFeedback(icon, text);
    },
    async confirm({
        title = "Confirm Action?",
        text = "",
        confirmText = "Confirm",
        danger = false,
    } = {}) {
        const result = await dialog({
            titleText: String(title),
            text: String(text),
            icon: "warning",
            timer: undefined,
            showCancelButton: true,
            confirmButtonText: String(confirmText),
            cancelButtonText: "Cancel",
            focusCancel: true,
            customClass: {
                ...defaults.customClass,
                confirmButton: danger
                    ? "button swal-danger"
                    : "button swal-confirm",
            },
        });
        return result.isConfirmed;
    },
    destructive(options) {
        return this.confirm({ ...options, danger: true });
    },
    loading(title = "Please wait...") {
        if (active) return;
        loading = true;
        Swal.fire({
            ...defaults,
            titleText: String(title),
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading(),
        });
    },
    close() {
        if (!active) {
            loading = false;
            Swal.close();
            flushFeedback();
        }
    },
    closeLoading() {
        if (loading) {
            loading = false;
            if (!active) Swal.close();
            flushFeedback();
        }
    },
    ajaxError(status) {
        return this.error(
            status === 401 || status === 403
                ? "Access Denied"
                : "Request Failed",
            status === 401 || status === 403
                ? "You do not have access. Check your sign-in session."
                : status === 422
                  ? "Some fields contain errors. Review the highlighted fields and try again."
                  : "Unable to complete the request. Check your connection and try again.",
        );
    },
};

export function initializeNotifications() {
    window.notify = notify;
    document.querySelectorAll("[data-notifications]").forEach((element) => {
        const data = JSON.parse(element.dataset.notifications);
        element.remove();
        if (data.validation) {
            if (data.duplicateBranchCode)
                notify.error(
                    "Branch Code Already Exists",
                    "Please enter a unique branch code.",
                );
            else
                notify.toast(
                    "warning",
                    "Please check your information. Review the highlighted fields.",
                );
            return;
        }
        const flashes = data.messages || [];
        const selected =
            flashes.find((item) => item.type === "error") ||
            flashes.find((item) => item.type === "warning") ||
            flashes[0];
        if (!selected) return;
        let { type, text } = selected;
        const labels = {
            "password-updated": "Your password was updated.",
            "profile-information-updated": "Your profile was updated.",
            "verification-link-sent": "Verification link requested.",
        };
        text = labels[text] || text;
        if (/failed|unable|could not/i.test(text)) type = "error";
        else if (
            /pending|captured in|submitted to|not guaranteed|if.*eligible/i.test(
                text,
            )
        )
            type = "info";
        type === "error"
            ? notify.error("Unable to Complete", text)
            : notify.toast(type, text);
    });

    window.addEventListener("open-modal", async (event) => {
        if (event.detail !== "logout" || document.body.dataset.logoutPending)
            return;
        document.body.dataset.logoutPending = "true";
        try {
            if (
                await notify.confirm({
                    title: "Log Out?",
                    text: "Are you sure you want to end your session?",
                    confirmText: "Yes, Log Out",
                })
            ) {
                document.querySelector("[data-logout-form]")?.requestSubmit();
            }
        } finally {
            delete document.body.dataset.logoutPending;
        }
    });

    const approved = new WeakSet(),
        busy = new WeakSet();
    const originalStatuses = new WeakMap();
    document.querySelectorAll("form").forEach((form) => {
        originalStatuses.set(form, form.elements.namedItem("status")?.value);
    });
    document.addEventListener("submit", async (event) => {
        const form = event.target;
        if (approved.has(form)) {
            approved.delete(form);
            return;
        }
        if (event.defaultPrevented || !form.matches("form")) return;
        const action = form.elements.namedItem("action")?.value;
        const customTitle = form.dataset.confirmTitle;
        const inactive =
            form.elements.namedItem("status")?.value === "inactive" &&
            originalStatuses.get(form) !== "inactive" &&
            form.querySelector('[name="confirmation"]');
        const review =
            form.querySelector('[name="decision"]') &&
            form.querySelector('[name="confirmation"]');
        const deleting = form.elements.namedItem("_method")?.value === "DELETE";
        if (
            !inactive &&
            !review &&
            !deleting &&
            !customTitle &&
            !["deactivate", "reactivate"].includes(action)
        )
            return;
        event.preventDefault();
        if (busy.has(form)) return;
        busy.add(form);
        const title =
            customTitle ||
            (deleting
                ? "Delete This Record?"
                : review
                  ? "Save Review Decision?"
                  : action === "reactivate"
                    ? "Reactivate Account?"
                    : inactive
                      ? "Deactivate This Record?"
                      : "Deactivate Account?");
        try {
            const confirmed = await notify.confirm({
                title,
                text:
                    form.dataset.confirmText ||
                    (deleting
                        ? "This action may permanently remove the selected record."
                        : review
                          ? "Original evidence and server timestamps will remain unchanged."
                          : "This changes access or availability according to the existing account and assignment rules."),
                confirmText:
                    form.dataset.confirmButton ||
                    (review
                        ? "Save Decision"
                        : deleting
                          ? "Yes, Delete"
                          : action === "reactivate"
                            ? "Yes, Reactivate"
                            : "Yes, Deactivate"),
                danger: customTitle
                    ? form.dataset.confirmDanger === "true"
                    : !review && action !== "reactivate",
            });
            if (confirmed && form.isConnected) {
                const confirmation = form.querySelector(
                    "[data-confirm-on-accept]",
                );
                if (confirmation) confirmation.value = "1";
                approved.add(form);
                form.requestSubmit(event.submitter || undefined);
            }
        } finally {
            busy.delete(form);
        }
    });
}

import { notify } from "./notifications";
export default function registerProfilePhoto(Alpine) {
    Alpine.store("profileAvatar", {
        url: null,
        initials: "",
        ready: false,
        updatedAt: 0,
    });
    Alpine.data("profilePhoto", (url) => ({
        file: null,
        preview: "",
        busy: false,
        error: "",
        select(event) {
            const file = event.target.files[0];
            this.cancel();
            if (!file) return;
            if (
                !["image/jpeg", "image/png", "image/webp"].includes(file.type)
            ) {
                this.error = "Please select a valid JPG, PNG or WebP image.";
                return;
            }
            if (file.size > 2097152) {
                this.error = "Image size must not exceed 2 MB.";
                return;
            }
            this.file = file;
            this.preview = URL.createObjectURL(file);
        },
        cancel() {
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = "";
            this.file = null;
            this.error = "";
            if (this.$refs.file) this.$refs.file.value = "";
        },
        destroy() {
            if (this.preview) URL.revokeObjectURL(this.preview);
        },
        async save() {
            if (this.file && !this.busy) await this.send("POST");
        },
        async remove() {
            if (this.busy) return;
            if (
                await notify.confirm({
                    title: "Remove Profile Photo?",
                    text: "Your initials avatar will be restored.",
                    confirmText: "Remove Photo",
                    danger: true,
                })
            )
                await this.send("DELETE");
        },
        async send(method) {
            this.busy = true;
            this.error = "";
            const data = new FormData();
            if (method === "POST") data.append("photo", this.file);
            try {
                const response = await fetch(url, {
                    method,
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                    body: method === "POST" ? data : null,
                    signal: AbortSignal.timeout(30000),
                });
                let result;
                try {
                    result = await response.json();
                } catch {
                    throw Error(
                        "Unable to confirm your profile photo update. Check your connection and sign-in session.",
                    );
                }
                if (!response.ok || response.redirected)
                    throw Error(
                        Object.values(result.errors || {})
                            .flat()
                            .join(" ") ||
                            result.message ||
                            "Unable to update your profile picture. Please try again.",
                    );
                Object.assign(Alpine.store("profileAvatar"), result.avatar, {
                    ready: true,
                    updatedAt: performance.now(),
                });
                this.cancel();
                notify.success(result.message);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },
    }));
}

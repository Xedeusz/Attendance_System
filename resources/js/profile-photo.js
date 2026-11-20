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
        sourceUrl: "",
        cropImage: null,
        cropReady: false,
        cropped: false,
        zoom: 1,
        offsetX: 0,
        offsetY: 0,
        dragging: false,
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
            this.sourceUrl = URL.createObjectURL(file);
            this.loadCropImage();
        },
        async loadCropImage() {
            this.error = "";
            const image = new Image();
            image.onload = () => {
                this.cropImage = image;
                this.cropReady = true;
                this.zoom = 1;
                this.resetCropPosition();
                this.$nextTick(() => this.drawCrop());
            };
            image.onerror = () => {
                this.error =
                    "Unable to read this image. Please choose another file.";
                this.clearPreview();
            };
            image.src = this.sourceUrl;
        },
        resetCropPosition() {
            const canvas = this.$refs.cropCanvas;
            if (!canvas || !this.cropImage) return;
            const scale = Math.max(
                canvas.width / this.cropImage.naturalWidth,
                canvas.height / this.cropImage.naturalHeight,
            );
            this.offsetX =
                (canvas.width - this.cropImage.naturalWidth * scale) / 2;
            this.offsetY =
                (canvas.height - this.cropImage.naturalHeight * scale) / 2;
        },
        drawCrop() {
            const canvas = this.$refs.cropCanvas;
            if (!canvas || !this.cropImage) return;
            const context = canvas.getContext("2d");
            const scale = this.cropScale();
            const width = this.cropImage.naturalWidth * scale;
            const height = this.cropImage.naturalHeight * scale;
            this.clampCropPosition(width, height);
            context.clearRect(0, 0, canvas.width, canvas.height);
            context.fillStyle = "#ffffff";
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(
                this.cropImage,
                this.offsetX,
                this.offsetY,
                width,
                height,
            );
        },
        cropScale() {
            const canvas = this.$refs.cropCanvas;
            const baseScale = Math.max(
                canvas.width / this.cropImage.naturalWidth,
                canvas.height / this.cropImage.naturalHeight,
            );

            return baseScale * this.zoom;
        },
        clampCropPosition(width, height) {
            const canvas = this.$refs.cropCanvas;
            this.offsetX = Math.min(
                0,
                Math.max(canvas.width - width, this.offsetX),
            );
            this.offsetY = Math.min(
                0,
                Math.max(canvas.height - height, this.offsetY),
            );
        },
        startCropDrag(event) {
            if (!this.cropReady || this.cropped) return;
            this.dragging = {
                startX: event.clientX,
                startY: event.clientY,
                offsetX: this.offsetX,
                offsetY: this.offsetY,
            };
            event.currentTarget.setPointerCapture(event.pointerId);
        },
        moveCrop(event) {
            if (!this.dragging) return;
            this.offsetX =
                this.dragging.offsetX + event.clientX - this.dragging.startX;
            this.offsetY =
                this.dragging.offsetY + event.clientY - this.dragging.startY;
            this.drawCrop();
        },
        endCropDrag() {
            this.dragging = false;
        },
        async applyCrop() {
            if (!this.cropImage || this.busy) return;
            try {
                const blob = await this.createCrop();
                const previousPreview = this.preview;
                this.file = new File([blob], "profile-photo.jpg", {
                    type: "image/jpeg",
                });
                this.preview = URL.createObjectURL(blob);
                this.cropped = true;
                this.cropReady = false;
                this.cropImage = null;
                if (previousPreview) URL.revokeObjectURL(previousPreview);
                if (this.sourceUrl) {
                    URL.revokeObjectURL(this.sourceUrl);
                    this.sourceUrl = "";
                }
            } catch {
                this.error =
                    "Unable to crop this image. Please choose another file.";
            }
        },
        createCrop() {
            return new Promise((resolve, reject) => {
                const canvas = document.createElement("canvas");
                canvas.width = 512;
                canvas.height = 512;
                const context = canvas.getContext("2d");
                const scale = this.cropScale();
                const sourceSize = this.$refs.cropCanvas.width / scale;
                const sourceX = Math.max(0, -this.offsetX / scale);
                const sourceY = Math.max(0, -this.offsetY / scale);
                context.fillStyle = "#ffffff";
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(
                    this.cropImage,
                    sourceX,
                    sourceY,
                    sourceSize,
                    sourceSize,
                    0,
                    0,
                    canvas.width,
                    canvas.height,
                );
                canvas.toBlob(
                    (blob) =>
                        blob ? resolve(blob) : reject(new Error("Crop failed")),
                    "image/jpeg",
                    0.9,
                );
            });
        },
        cancel() {
            this.clearPreview();
        },
        clearPreview() {
            if (this.preview && this.preview !== this.sourceUrl)
                URL.revokeObjectURL(this.preview);
            if (this.sourceUrl) URL.revokeObjectURL(this.sourceUrl);
            this.preview = "";
            this.sourceUrl = "";
            this.file = null;
            this.cropImage = null;
            this.cropReady = false;
            this.cropped = false;
            this.error = "";
            if (this.$refs.file) this.$refs.file.value = "";
        },
        destroy() {
            this.clearPreview();
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

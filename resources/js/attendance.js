export default (Alpine) => {
    Alpine.data("attendanceCapture", (options) => ({
        canTimeIn: options.canTimeIn,
        canTimeOut: options.canTimeOut,
        action: "",
        capture: null,
        location: null,
        geo: null,
        stream: null,
        cameras: [],
        cameraIndex: 0,
        photo: null,
        previewUrl: "",
        cameraActive: false,
        cameraMessage: "Camera is off.",
        locationMessage:
            "Location is requested only when you start attendance.",
        busy: false,
        locating: false,
        submitting: false,
        uncertain: false,
        error: "",
        result: null,
        requestReview: false,
        reason: "",
        body: null,
        clock: "",
        remaining: 0,
        init() {
            this.offset = Date.parse(options.serverTime) - Date.now();
            const tick = () => {
                this.clock = new Intl.DateTimeFormat("en-PH", {
                    timeZone: "Asia/Manila",
                    dateStyle: "full",
                    timeStyle: "medium",
                }).format(new Date(Date.now() + this.offset));
                this.remaining = this.capture
                    ? Math.max(
                          0,
                          Math.ceil(
                              (Date.parse(this.capture.expires_at) -
                                  Date.now() -
                                  this.offset) /
                                  1000,
                          ),
                      )
                    : 0;
            };
            tick();
            this.timer = setInterval(tick, 1000);
            this.onHide = () => {
                if (document.hidden) this.stopCamera();
            };
            this.onExit = () => this.stopCamera();
            document.addEventListener("visibilitychange", this.onHide);
            window.addEventListener("pagehide", this.onExit);
        },
        destroy() {
            this.stopCamera();
            this.clearPhoto();
            clearInterval(this.timer);
            document.removeEventListener("visibilitychange", this.onHide);
            window.removeEventListener("pagehide", this.onExit);
        },
        async api(url, method = "POST", body = null) {
            const headers = {
                Accept: "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,
            };
            if (body && !(body instanceof FormData))
                headers["Content-Type"] = "application/json";
            const response = await fetch(url, {
                method,
                headers,
                credentials: "same-origin",
                body: body
                    ? body instanceof FormData
                        ? body
                        : JSON.stringify(body)
                    : null,
                signal: AbortSignal.timeout(30000),
            });
            let result;
            try {
                result = await response.json();
            } catch {
                throw new Error(
                    "The server could not complete the request. Check your connection and sign-in session.",
                );
            }
            if (!response.ok) {
                const failure = new Error(
                    Object.values(result.errors || {})
                        .flat()
                        .join(" ") ||
                        result.message ||
                        "The request could not be completed.",
                );
                failure.status = response.status;
                throw failure;
            }
            return result;
        },
        async begin(action) {
            if (this.busy || this.submitting) return;
            this.cancel();
            this.action = action;
            this.busy = true;
            this.error = "";
            this.result = null;
            try {
                this.capture = await this.api(options.startUrl, "POST", {
                    action,
                });
                this.remaining = options.captureSeconds;
                await this.locate();
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },
        async locate() {
            if (!this.capture || this.uncertain || this.locating) return;
            this.locating = true;
            this.location = null;
            this.geo = null;
            try {
                if (!window.isSecureContext)
                    throw new Error(
                        "Location access requires HTTPS or localhost. Use a trusted secure connection.",
                    );
                if (!navigator.geolocation)
                    throw new Error(
                        "This browser does not support location. Request HR assistance.",
                    );
                this.locationMessage = "Requesting your current location…";
                const position = await new Promise((resolve, reject) =>
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        maximumAge: 0,
                        timeout: 15000,
                    }),
                );
                this.location = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                    retrieved_at: Math.round(position.timestamp),
                };
                this.geo = await this.api(
                    `${options.captureBase}/${this.capture.id}/location`,
                    "POST",
                    { token: this.capture.token, ...this.location },
                );
                this.locationMessage = this.geo.validated
                    ? "Reported location meets the configured geofence and accuracy checks."
                    : `Location not validated: ${this.geo.status.replaceAll("_", " ")}. Retry or explicitly request HR review.`;
            } catch (error) {
                this.locationMessage =
                    error.code === 1
                        ? "Location permission denied. Allow access, retry, or request HR review."
                        : error.code === 2
                          ? "Location is unavailable. Check device location services or request HR review."
                          : error.code === 3
                            ? "Location timed out. Move to a clearer area and retry."
                            : error.message;
            } finally {
                this.locating = false;
            }
        },
        stopCamera() {
            if (this.stream)
                this.stream.getTracks().forEach((track) => track.stop());
            this.stream = null;
            this.cameraActive = false;
            if (this.$refs.video) this.$refs.video.srcObject = null;
        },
        clearPhoto() {
            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
            this.previewUrl = "";
            this.photo = null;
            this.body = null;
        },
        async startCamera(deviceId = null) {
            if (!this.capture || this.submitting || this.uncertain) return;
            this.stopCamera();
            this.error = "";
            try {
                if (
                    !window.isSecureContext ||
                    !navigator.mediaDevices?.getUserMedia
                )
                    throw new Error(
                        "Camera access requires HTTPS or localhost and a supported browser.",
                    );
                const video = deviceId
                    ? {
                          deviceId: { exact: deviceId },
                          width: { ideal: 1280 },
                          height: { ideal: 720 },
                      }
                    : {
                          facingMode: { ideal: "user" },
                          width: { ideal: 1280 },
                          height: { ideal: 720 },
                      };
                const stream = await navigator.mediaDevices.getUserMedia({
                    video,
                    audio: false,
                });
                if (!this.capture || document.hidden) {
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }
                this.stream = stream;
                this.$refs.video.srcObject = stream;
                await this.$refs.video.play();
                this.cameraActive = true;
                this.cameraMessage =
                    "Live preview is active. Capture an appropriate office photo; no face matching is performed.";
                this.cameras = [];
                try {
                    if (navigator.mediaDevices.enumerateDevices) {
                        this.cameras = (
                            await navigator.mediaDevices.enumerateDevices()
                        ).filter((device) => device.kind === "videoinput");
                    }
                } catch {
                    this.cameraMessage +=
                        " Camera switching is unavailable; you can use the current preview.";
                }
                this.cameraIndex = Math.max(
                    0,
                    this.cameras.findIndex(
                        (device) =>
                            device.deviceId ===
                            stream.getVideoTracks()[0].getSettings().deviceId,
                    ),
                );
            } catch (error) {
                this.stopCamera();
                this.cameraMessage =
                    error.name === "NotAllowedError"
                        ? "Camera permission denied. Allow access in browser settings or request HR assistance."
                        : error.name === "NotFoundError"
                          ? "No camera was found. Request HR assistance."
                          : ["NotReadableError", "AbortError"].includes(
                                  error.name,
                              )
                            ? "The camera is unavailable or used by another app. Close that app and retry."
                            : error.name === "OverconstrainedError"
                              ? "The selected camera could not start. Try Start live camera again."
                              : error.message;
            }
        },
        async switchCamera() {
            if (this.cameras.length > 1) {
                this.cameraIndex = (this.cameraIndex + 1) % this.cameras.length;
                await this.startCamera(this.cameras[this.cameraIndex].deviceId);
            }
        },
        async takePhoto() {
            const video = this.$refs.video;
            if (!this.cameraActive || !video.videoWidth) {
                this.error = "Wait for the live preview before taking a photo.";
                return;
            }
            const scale = Math.min(
                1,
                1280 / Math.max(video.videoWidth, video.videoHeight),
            );
            const canvas = document.createElement("canvas");
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            canvas
                .getContext("2d")
                .drawImage(video, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise((resolve) =>
                canvas.toBlob(resolve, "image/jpeg", 0.82),
            );
            if (!blob || blob.size > 2097152) {
                this.error =
                    "The photo could not be prepared within the size limit. Try again.";
                return;
            }
            this.clearPhoto();
            this.photo = blob;
            this.previewUrl = URL.createObjectURL(blob);
            this.stopCamera();
            this.cameraMessage =
                "Photo captured. Review, retake or submit. This is visual evidence, not independent identity verification.";
        },
        async retake() {
            this.clearPhoto();
            await this.startCamera();
        },
        async checkStatus() {
            if (!this.capture) return false;
            const response = await this.api(
                `${options.captureBase}/${this.capture.id}/status`,
                "GET",
            );
            if (response.submitted) {
                this.finish(response.result);
                return true;
            }
            this.uncertain = false;
            if (response.expired)
                this.error =
                    "This capture expired without a saved result. Start again and take a fresh photo.";
            return false;
        },
        finish(result) {
            this.result = result;
            this.stopCamera();
            this.clearPhoto();
            this.capture = null;
            this.uncertain = false;
        },
        async submit() {
            if (this.submitting || !this.capture || !this.photo) return;
            this.submitting = true;
            this.error = "";
            try {
                if (this.uncertain && (await this.checkStatus())) return;
                if (!this.body) {
                    if (
                        this.location &&
                        Date.now() + this.offset - this.location.retrieved_at >
                            options.locationMaxAge * 1000
                    )
                        await this.locate();
                    const data = new FormData();
                    data.append("token", this.capture.token);
                    data.append("photo", this.photo, "camera-frame.jpg");
                    if (this.location)
                        Object.entries(this.location).forEach(([key, value]) =>
                            data.append(key, String(value)),
                        );
                    data.append(
                        "request_review",
                        this.requestReview ? "1" : "0",
                    );
                    if (this.requestReview)
                        data.append("exception_reason", this.reason);
                    this.body = data;
                }
                this.finish(
                    await this.api(
                        `${options.captureBase}/${this.capture.id}/submit`,
                        "POST",
                        this.body,
                    ),
                );
            } catch (error) {
                this.error = error.message;
                if (!error.status || error.status >= 500) {
                    this.uncertain = true;
                    this.error +=
                        " Server outcome is uncertain. Check submission status before starting another action.";
                } else {
                    this.body = null;
                    this.uncertain = false;
                }
            } finally {
                this.submitting = false;
            }
        },
        cancel() {
            this.stopCamera();
            this.clearPhoto();
            this.capture = null;
            this.location = null;
            this.geo = null;
            this.uncertain = false;
            this.requestReview = false;
            this.reason = "";
            this.remaining = 0;
        },
    }));
};

import { notify } from "./notifications";
import locationMonitoring from "./attendance-location";

export default (Alpine) => {
    Alpine.data("attendanceCapture", (options) => ({
        ...locationMonitoring(),
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
        cameraStarting: false,
        cameraGeneration: 0,
        cameraMessage: "Camera is off.",
        locationMessage:
            "Location is requested only when you start attendance.",
        busy: false,
        locating: false,
        submitting: false,
        uncertain: false,
        checking: false,
        recoveryStopped: false,
        attendanceLabel: options.attendanceLabel,
        employeeName: options.employeeName,
        employeeSummary: options.employeeSummary,
        eligibilityError: options.eligibilityError,
        pendingReview: options.pendingReview,
        error: "",
        result: null,
        requestReview: false,
        reason: "",
        body: null,
        clock: "",
        remaining: 0,
        init() {
            this.$watch("cameraMessage", (value) => {
                if (
                    /permission denied|No camera|unavailable|could not start|requires HTTPS/i.test(
                        value,
                    )
                )
                    notify.error(
                        "Camera Access Required",
                        "Please allow camera access or review the camera guidance on this page.",
                    );
            });
            this.offset = Date.parse(options.serverTime) - Date.now();
            const clockFormatter = new Intl.DateTimeFormat("en-PH", {
                timeZone: "Asia/Manila",
                dateStyle: "full",
                timeStyle: "medium",
            });
            const tick = () => {
                this.clock = clockFormatter.format(new Date(Date.now() + this.offset));
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
            this.timer = setInterval(() => {
                tick();
                this.refreshLocationPreview();
            }, 1000);
            this.onHide = () => {
                if (document.hidden) {
                    this.stopCamera();
                    this.stopLocation();
                    this.geo = null;
                    this.location = null;
                } else {
                    this.startLocation();
                }
            };
            this.onExit = () => {
                this.stopCamera();
                this.stopLocation();
            };
            document.addEventListener("visibilitychange", this.onHide);
            window.addEventListener("pagehide", this.onExit);
            this.onWorkspaceAttendance = (event) => {
                this.applyAttendance(event.detail);
                if (this.uncertain && !this.checking)
                    this.checkStatus().catch(() => {});
            };
            window.addEventListener(
                "workspace:attendance",
                this.onWorkspaceAttendance,
            );
            this.storageKey = `attendance-pending:${options.employeeId}`;
            try {
                const pending = JSON.parse(
                    sessionStorage.getItem(this.storageKey),
                );
                if (pending?.id) {
                    this.capture = pending;
                    this.action = pending.action;
                    this.uncertain = true;
                    this.reconcile();
                }
            } catch {
                // Storage may be unavailable in private browser sessions.
            }
        },
        destroy() {
            this.stopLocation();
            this.stopCamera();
            this.clearPhoto();
            clearInterval(this.timer);
            this.recoveryStopped = true;
            window.removeEventListener(
                "workspace:attendance",
                this.onWorkspaceAttendance,
            );
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
            let result = null;
            try {
                result = await response.json();
            } catch {}
            if (!response.ok) {
                const messages = {
                    401: "Your session ended. Sign in again before continuing.",
                    403: "You do not have permission to perform this attendance action.",
                    419: "Your session expired. Refresh the page and sign in again.",
                    409: "Attendance changed or this capture expired. Check your attendance history.",
                    413: "The photo exceeds the upload limit. Take a smaller fresh photo.",
                    422: "Review the photo and location requirements and try again.",
                    429: `Too many requests. Wait ${response.headers.get("Retry-After") || "60"} seconds before trying again.`,
                };
                const failure = new Error(
                    messages[response.status] &&
                        response.status !== 422 &&
                        response.status !== 409
                        ? messages[response.status]
                        : Object.values(result?.errors || {})
                              .flat()
                              .join(" ") ||
                              (response.status < 500 && result?.message) ||
                              messages[response.status] ||
                              "The server could not confirm the request result.",
                );
                failure.status = response.status;
                throw failure;
            }
            if (!result || typeof result !== "object" || response.redirected) {
                throw new Error(
                    "The server returned an unexpected response. Check your sign-in session.",
                );
            }
            return result;
        },
        async begin(action) {
            if (this.busy || this.submitting || this.uncertain || this.checking)
                return;
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
                this.startLocation();
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },
        locate() {
            this.startLocation(true);
        },
        stopCamera() {
            this.cameraGeneration++;
            this.cameraStarting = false;
            if (this.stream)
                this.stream.getTracks().forEach((track) => track.stop());
            this.stream = null;
            this.cameraActive = false;
            this.cameraMessage = "Camera is off.";
            if (this.$refs.video) this.$refs.video.srcObject = null;
        },
        clearPhoto() {
            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
            this.previewUrl = "";
            this.photo = null;
            this.body = null;
        },
        async startCamera(deviceId = null) {
            if (!this.capture || this.submitting || this.uncertain || this.cameraStarting) return;
            this.stopCamera();
            const generation = this.cameraGeneration;
            this.cameraStarting = true;
            this.cameraMessage = "Waiting for camera access.";
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
                if (generation !== this.cameraGeneration || !this.capture || document.hidden) {
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }
                this.stream = stream;
                this.$refs.video.srcObject = stream;
                await this.$refs.video.play();
                if (generation !== this.cameraGeneration) return;
                this.cameraActive = true;
                this.cameraMessage =
                    "Live preview is active. Capture an appropriate office photo; no face matching is performed.";
                this.cameras = [];
                try {
                    if (navigator.mediaDevices.enumerateDevices) {
                        const devices = await navigator.mediaDevices.enumerateDevices();
                        if (generation !== this.cameraGeneration) return;
                        this.cameras = devices.filter((device) => device.kind === "videoinput");
                    }
                } catch {
                    if (generation !== this.cameraGeneration) return;
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
                if (generation !== this.cameraGeneration) return;
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
            } finally {
                if (generation === this.cameraGeneration) this.cameraStarting = false;
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
            const generation = this.cameraGeneration;
            const blob = await new Promise((resolve) =>
                canvas.toBlob(resolve, "image/jpeg", 0.82),
            );
            if (generation !== this.cameraGeneration) return;
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
            if (!this.capture || this.checking) return false;
            this.checking = true;
            try {
                const response = await this.api(
                    `${options.captureBase}/${this.capture.id}/status`,
                    "GET",
                );
                if (
                    response.status === "succeeded" &&
                    response.result?.event_id
                ) {
                    this.finish(response.result);
                    return true;
                }
                if (response.status === "not_recorded") {
                    this.uncertain = false;
                    this.forgetPending();
                    this.applyAttendance(response.attendance);
                    this.checking = false;
                    this.resetCapture();
                    this.error =
                        "This capture expired without a saved result. Start again and take a fresh photo.";
                    return true;
                }
                this.uncertain = true;
                return false;
            } finally {
                this.checking = false;
            }
        },
        rememberPending() {
            try {
                sessionStorage.setItem(
                    this.storageKey,
                    JSON.stringify({
                        id: this.capture.id,
                        action: this.action,
                        expires_at: this.capture.expires_at,
                    }),
                );
            } catch {}
        },
        forgetPending() {
            try {
                sessionStorage.removeItem(this.storageKey);
            } catch {}
        },
        applyAttendance(attendance) {
            if (!attendance) return;
            this.canTimeIn = attendance.canTimeIn;
            this.canTimeOut = attendance.canTimeOut;
            this.attendanceLabel = attendance.label;
            this.employeeName = attendance.employeeName ?? this.employeeName;
            this.employeeSummary =
                attendance.employeeSummary ?? this.employeeSummary;
            this.eligibilityError = attendance.eligibilityError;
            this.pendingReview = attendance.pendingReview;
            if (
                this.result &&
                attendance.latestResult &&
                !this.capture &&
                !this.submitting &&
                !this.uncertain
            ) {
                this.result = attendance.latestResult;
            }
        },
        async reconcile() {
            this.error =
                "We couldn't confirm your last submission. We're checking whether your attendance was recorded.";
            for (const delay of [0, 2000, 4000, 8000]) {
                if (delay)
                    await new Promise((resolve) => setTimeout(resolve, delay));
                if (this.recoveryStopped || !this.uncertain) return;
                try {
                    if (await this.checkStatus()) return;
                } catch (failure) {
                    if ([401, 403, 419, 429].includes(failure.status)) {
                        this.error =
                            failure.message +
                            " Your pending submission is preserved. Check submission status after access is restored.";
                        return;
                    }
                }
            }
            if (!this.recoveryStopped && this.uncertain)
                this.error =
                    "We couldn't verify the result yet. Check submission status before trying again, or request HR assistance. Do not submit another punch.";
        },
        finish(result) {
            if (!result?.event_id || !result?.occurred_at) {
                throw new Error(
                    "The server did not confirm a saved attendance event.",
                );
            }
            notify.close();
            notify.toast(
                result.review_status === "pending" ||
                    result.verification_status !== "location_validated"
                    ? "warning"
                    : "success",
                result.review_status === "pending" ||
                    result.verification_status !== "location_validated"
                    ? "Saved for HR review; this is not yet verified."
                    : result.action === "time_out"
                      ? "Time Out Recorded"
                      : "Time In Recorded",
            );
            this.stopLocation();
            this.result = result;
            this.error = "";
            this.forgetPending();
            this.applyAttendance(result.attendance);
            this.stopCamera();
            this.clearPhoto();
            this.capture = null;
            this.uncertain = false;
        },
        async submit() {
            if (
                this.submitting ||
                this.uncertain ||
                !this.capture ||
                !this.photo
            )
                return;
            this.submitting = true;
            notify.loading("Submitting Attendance...");
            this.error = "";
            try {
                await this.ensureFreshLocation();
                if (!this.body) {
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
                this.stopLocation();
                this.rememberPending();
                this.finish(
                    await this.api(
                        `${options.captureBase}/${this.capture.id}/submit`,
                        "POST",
                        this.body,
                    ),
                );
            } catch (error) {
                if (
                    !error.status ||
                    error.status >= 500 ||
                    error.status === 408
                ) {
                    this.uncertain = true;
                    this.stopLocation();
                    this.rememberPending();
                    await this.reconcile();
                } else {
                    this.error = error.message;
                    this.forgetPending();
                    this.body = null;
                    this.uncertain = false;
                }
            } finally {
                notify.closeLoading();
                this.submitting = false;
                if (this.capture && !this.uncertain) this.startLocation();
            }
        },
        cancel() {
            if (this.submitting || this.uncertain || this.checking) return;
            this.resetCapture();
        },
        resetCapture() {
            this.stopLocation();
            this.locationDenied = false;
            this.locationRetries = 0;
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

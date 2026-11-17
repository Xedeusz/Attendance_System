export function evaluateLocation(branch, location, now) {
    if (
        branch?.latitude == null ||
        branch?.longitude == null ||
        !Number(branch.radius)
    )
        return {
            status: "branch_not_configured",
            distance: null,
            validated: false,
        };
    if (!location)
        return {
            status: "location_unavailable",
            distance: null,
            validated: false,
        };
    const { latitude, longitude, accuracy, retrieved_at } = location;
    if (
        ![latitude, longitude, accuracy, retrieved_at].every(Number.isFinite) ||
        Math.abs(latitude) > 90 ||
        Math.abs(longitude) > 180 ||
        accuracy < 0.1 ||
        accuracy > 100000
    )
        return { status: "location_error", distance: null, validated: false };
    const rad = (value) => (value * Math.PI) / 180;
    const a =
        Math.sin(rad(Number(branch.latitude) - latitude) / 2) ** 2 +
        Math.cos(rad(latitude)) *
            Math.cos(rad(Number(branch.latitude))) *
            Math.sin(rad(Number(branch.longitude) - longitude) / 2) ** 2;
    const distance =
        6371008.8 * 2 * Math.asin(Math.sqrt(Math.max(0, Math.min(1, a))));
    const age = now - retrieved_at;
    const status =
        age > Number(branch.max_age_seconds) * 1000 || age < -15000
            ? "stale_location"
            : accuracy > Number(branch.accuracy_limit)
              ? "accuracy_insufficient"
              : distance > Number(branch.radius)
                ? "outside_geofence"
                : distance + accuracy > Number(branch.radius)
                  ? "boundary_uncertain"
                  : "within_geofence";
    return {
        status,
        distance: Math.round(distance * 100) / 100,
        validated: status === "within_geofence",
    };
}

export default function locationMonitoring() {
    return {
        locationWatchId: null,
        locationGeneration: 0,
        locationRetries: 0,
        locationRetryTimer: null,
        locationRecovery: false,
        locationDenied: false,
        locationAge: null,
        stopLocation() {
            this.locationGeneration++;
            if (this.locationWatchId !== null)
                navigator.geolocation?.clearWatch(this.locationWatchId);
            this.locationWatchId = null;
            clearTimeout(this.locationRetryTimer);
            this.locationRetryTimer = null;
            this.locating = false;
        },
        refreshLocationPreview() {
            if (
                !this.capture ||
                this.uncertain ||
                this.submitting ||
                document.hidden
            )
                return;
            if (this.remaining === 0) {
                this.stopLocation();
                this.geo = null;
                this.locationMessage =
                    "Capture expired. Begin again with a fresh photo and location.";
                return;
            }
            if (!this.location) return;
            this.locationAge = Math.max(
                0,
                Math.floor(
                    (Date.now() + this.offset - this.location.retrieved_at) /
                        1000,
                ),
            );
            this.geo = evaluateLocation(
                this.capture.branch,
                this.location,
                Date.now() + this.offset,
            );
            const messages = {
                within_geofence: "Inside authorized branch area",
                outside_geofence: "Outside authorized branch area",
                boundary_uncertain:
                    "Near the boundary. Waiting for a more precise reading...",
                accuracy_insufficient:
                    "Location accuracy is insufficient. Waiting for a better reading...",
                stale_location: "Waiting for an updated location...",
                branch_not_configured:
                    "Branch geofence is not configured. Contact HR.",
                location_error:
                    "Invalid location reading. Waiting for a usable reading...",
            };
            this.locationMessage =
                messages[this.geo.status] ||
                "Waiting for an updated location...";
            if (
                this.geo.status === "stale_location" &&
                !this.locationRetryTimer
            ) {
                if (this.locationRetries < 3) {
                    this.locationRetries++;
                    this.locationRetryTimer = setTimeout(
                        () => this.startLocation(),
                        5000,
                    );
                } else this.locationRecovery = true;
            }
        },
        acceptLocation(position) {
            this.location = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
                retrieved_at: Math.floor(position.timestamp),
            };
            this.locating = false;
            this.locationRecovery = false;
            clearTimeout(this.locationRetryTimer);
            this.locationRetryTimer = null;
            this.refreshLocationPreview();
            if (this.geo?.status !== "stale_location") this.locationRetries = 0;
        },
        locationFailure(error) {
            this.stopLocation();
            this.location = null;
            this.geo = null;
            if (error.code === 1) {
                this.locationDenied = true;
                this.locationRecovery = true;
                this.locationMessage =
                    "Location access is required. Please enable location permission in your browser.";
                return;
            }
            this.locationMessage =
                error.code === 3
                    ? "Location is taking longer than expected. Checking again..."
                    : "Your device could not determine its location. Waiting for location availability.";
            if (
                this.locationRetries < 3 &&
                this.capture &&
                !document.hidden &&
                !this.submitting &&
                !this.uncertain
            ) {
                const delay = [2000, 5000, 10000][this.locationRetries++];
                this.locationRetryTimer = setTimeout(
                    () => this.startLocation(),
                    delay,
                );
            } else {
                this.locationRecovery = true;
                this.locationMessage +=
                    " Automatic recovery paused. Check device location settings and try again.";
            }
        },
        startLocation(manual = false) {
            if (
                !this.capture ||
                this.uncertain ||
                this.submitting ||
                document.hidden ||
                this.remaining === 0 ||
                (this.locationDenied && !manual)
            )
                return;
            this.stopLocation();
            if (manual) {
                this.locationDenied = false;
                this.locationRetries = 0;
            }
            this.location = null;
            this.geo = null;
            this.locationAge = null;
            this.locationRecovery = false;
            if (
                !window.isSecureContext ||
                !navigator.geolocation?.watchPosition
            ) {
                this.locationMessage = !window.isSecureContext
                    ? "Location access requires HTTPS or localhost."
                    : "Your browser does not support location access.";
                return;
            }
            this.locating = true;
            this.locationMessage = "Checking your current location...";
            const generation = this.locationGeneration;
            const id = navigator.geolocation.watchPosition(
                (position) => {
                    if (generation === this.locationGeneration)
                        this.acceptLocation(position);
                },
                (error) => {
                    if (generation === this.locationGeneration)
                        this.locationFailure(error);
                },
                { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 },
            );
            if (generation === this.locationGeneration)
                this.locationWatchId = id;
            else navigator.geolocation.clearWatch(id);
        },
        async ensureFreshLocation() {
            this.geo = evaluateLocation(
                this.capture.branch,
                this.location,
                Date.now() + this.offset,
            );
            if (this.geo?.validated) return;
            if (
                !this.locationDenied &&
                window.isSecureContext &&
                navigator.geolocation
            ) {
                const generation = this.locationGeneration;
                try {
                    const position = await new Promise((resolve, reject) =>
                        navigator.geolocation.getCurrentPosition(
                            resolve,
                            reject,
                            {
                                enableHighAccuracy: true,
                                maximumAge: 0,
                                timeout: 15000,
                            },
                        ),
                    );
                    if (
                        generation === this.locationGeneration &&
                        this.capture &&
                        !document.hidden
                    ) {
                        this.acceptLocation(position);
                        // Submission freezes the sample below; evaluate even while submitting.
                        this.geo = evaluateLocation(
                            this.capture.branch,
                            this.location,
                            Date.now() + this.offset,
                        );
                    }
                } catch (error) {
                    this.locationFailure(error);
                }
            }
            this.geo = evaluateLocation(
                this.capture.branch,
                this.location,
                Date.now() + this.offset,
            );
            if (
                !this.geo.validated &&
                (!this.requestReview || this.reason.trim().length < 10)
            ) {
                const failure = new Error(
                    "A fresh, accurate location inside your branch is required. Monitoring will continue; wait for a usable reading or request HR review.",
                );
                failure.status = 422;
                throw failure;
            }
        },
    };
}

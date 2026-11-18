import { notify } from "./notifications";
const officeIcon = {
    className: "office-marker",
    html: '<span aria-hidden="true"></span>',
    iconSize: [26, 32],
    iconAnchor: [13, 31],
};
const detailLimits = {
    branch_name: 150,
    address: 255,
    city: 100,
    province: 100,
    postal_code: 20,
};

export default function registerBranchLocationMap(Alpine) {
    Alpine.data("branchLocationMap", () => {
        let L, map, marker, circle, tiles, observer, form, onSubmit;
        let disposed = false;
        let loadingMap = false;
        let config;
        let searchController;
        let searchSequence = 0;
        let onDetailsEdited;
        let applyingDetails = false;
        const autofilled = new Map();

        return {
            searchQuery: "",
            searchResults: [],
            searchMessage: "",
            searchBusy: false,
            searchEnabled: false,
            latitude: "",
            longitude: "",
            radius: "",
            manual: false,
            mapError: "",
            locationError: "",
            ready: false,
            touchDevice: window.matchMedia("(pointer: coarse)").matches,
            panning: !window.matchMedia("(pointer: coarse)").matches,

            init() {
                config = JSON.parse(this.$el.dataset.mapConfig);
                this.searchEnabled = config.searchEnabled;
                this.latitude = config.latitude ?? "";
                this.longitude = config.longitude ?? "";
                this.radius = config.radius ?? "";
                this.$nextTick(() => this.startMap());
                if (!config.readonly) {
                    for (const field of ["latitude", "longitude", "radius"]) {
                        this.$watch(field, () => this.syncLayers());
                    }
                    form = this.$el.closest("form");
                    onDetailsEdited = (event) => {
                        if (
                            !applyingDetails &&
                            Object.hasOwn(detailLimits, event.target.name)
                        ) {
                            autofilled.delete(event.target.name);
                        }
                    };
                    form.addEventListener("input", onDetailsEdited);
                    form.addEventListener("change", onDetailsEdited);
                    onSubmit = (event) => {
                        const required =
                            form.elements.status.value === "active";
                        const hasValues = [
                            this.latitude,
                            this.longitude,
                            this.radius,
                        ].some((value) => String(value).trim() !== "");
                        if ((required || hasValues) && !this.validLocation) {
                            event.preventDefault();
                            this.locationError =
                                "Please select the branch location on the map.";
                            this.$refs.map.focus();
                        }
                    };
                    form.addEventListener("submit", onSubmit);
                }
            },

            async searchLocation() {
                if (this.searchBusy || !this.searchEnabled) return;
                const query = this.searchQuery.trim().replace(/\s+/g, " ");
                this.searchMessage = "";
                this.searchResults = [];
                if (
                    query.length < 3 ||
                    query.length > 200 ||
                    !/[\p{L}\p{N}]/u.test(query)
                ) {
                    this.searchMessage = "Enter a valid location to search.";
                    return;
                }
                this.searchBusy = true;
                const controller = new AbortController();
                searchController = controller;
                const sequence = ++searchSequence;
                const timer = setTimeout(() => controller.abort(), 12000);
                try {
                    const response = await fetch(config.searchUrl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                        },
                        credentials: "same-origin",
                        body: JSON.stringify({ query }),
                        signal: controller.signal,
                    });
                    const result = await response.json();
                    if (sequence !== searchSequence) return;
                    if (!response.ok) {
                        notify.ajaxError(response.status);
                        this.searchMessage =
                            response.status === 429
                                ? "Location search is temporarily limited. Please try again later."
                                : result.message ||
                                  "Location search is temporarily unavailable. You can still select a location directly on the map.";
                        return;
                    }
                    this.searchResults = Array.isArray(result.results)
                        ? result.results
                        : [];
                    if (!this.searchResults.length)
                        this.searchMessage =
                            "No matching locations found. Try another address or select a location manually on the map.";
                } catch (error) {
                    if (sequence !== searchSequence) return;
                    notify.ajaxError();
                    this.searchMessage =
                        error.name === "AbortError"
                            ? "Location search timed out. Please try again."
                            : "Location search is temporarily unavailable. You can still select a location directly on the map.";
                } finally {
                    clearTimeout(timer);
                    if (sequence === searchSequence) this.searchBusy = false;
                }
            },

            chooseSearchResult(result) {
                const latitude = result.latitude,
                    longitude = result.longitude;
                if (
                    typeof latitude !== "number" ||
                    typeof longitude !== "number" ||
                    !Number.isFinite(latitude) ||
                    !Number.isFinite(longitude) ||
                    Math.abs(latitude) > 90 ||
                    Math.abs(longitude) > 180 ||
                    !map
                ) {
                    this.searchMessage =
                        "The selected result has invalid coordinates. Select another result or use the map.";
                    return;
                }
                searchSequence++;
                searchController?.abort();
                this.searchBusy = false;
                this.selectLocation({ lat: latitude, lng: longitude });
                this.fillBranchDetails(result.details);
                this.searchResults = [];
                this.searchMessage = "";
                map.setView(
                    [latitude, longitude],
                    Math.min(16, config.maxZoom),
                    { animate: true },
                );
                this.$nextTick(() =>
                    this.$refs.searchInput.focus({ preventScroll: true }),
                );
            },

            fillBranchDetails(details) {
                if (!form) return;
                const source =
                    details && typeof details === "object" ? details : {};
                applyingDetails = true;
                try {
                    for (const [name, limit] of Object.entries(detailLimits)) {
                        const input = form.elements.namedItem(name);
                        if (!input) continue;
                        const value =
                            typeof source[name] === "string"
                                ? Array.from(source[name].trim())
                                      .slice(0, limit)
                                      .join("")
                                : "";
                        if (value) {
                            input.value = value;
                            autofilled.set(name, value);
                        } else if (
                            autofilled.has(name) &&
                            input.value === autofilled.get(name)
                        ) {
                            input.value = "";
                            autofilled.delete(name);
                        } else {
                            autofilled.delete(name);
                            continue;
                        }
                        input.dispatchEvent(
                            new Event("input", { bubbles: true }),
                        );
                        input.dispatchEvent(
                            new Event("change", { bubbles: true }),
                        );
                    }
                } finally {
                    applyingDetails = false;
                }
            },

            get validLocation() {
                return (
                    [this.latitude, this.longitude].every(
                        (value) =>
                            String(value).trim() !== "" &&
                            Number.isFinite(Number(value)),
                    ) &&
                    Math.abs(Number(this.latitude)) <= 90 &&
                    Math.abs(Number(this.longitude)) <= 180
                );
            },

            get validRadius() {
                const radius = Number(this.radius);
                return (
                    /^\+?(0|[1-9]\d*)$/.test(String(this.radius).trim()) &&
                    Number.isInteger(radius) &&
                    radius >= config.minRadius &&
                    radius <= config.maxRadius
                );
            },

            async startMap() {
                if (disposed || loadingMap) return;
                this.mapError = "";
                loadingMap = true;
                try {
                    if (map) {
                        tiles.redraw();
                        map.invalidateSize();
                        return;
                    }
                    L ??= (await import("./leaflet-map")).default;
                    if (disposed) return;
                    map = L.map(this.$refs.map, {
                        scrollWheelZoom: true,
                        dragging: this.panning,
                        maxZoom: config.maxZoom,
                    }).setView(config.center, config.zoom);
                    tiles = L.tileLayer(config.tileUrl, {
                        attribution: config.attribution,
                        maxZoom: config.maxZoom,
                        keepBuffer: 0,
                        updateWhenIdle: true,
                        referrerPolicy: "strict-origin-when-cross-origin",
                    }).addTo(map);
                    if (config.searchEnabled) {
                        map.attributionControl.addAttribution(
                            '<a href="https://photon.komoot.io/" target="_blank" rel="noopener noreferrer">Photon</a>',
                        );
                    }
                    tiles.on("tileerror", () => {
                        this.mapError =
                            "Unable to load the map tiles. Check your internet connection and try again. Your form values are preserved.";
                    });
                    if (!config.readonly) {
                        map.on("click", ({ latlng }) =>
                            this.selectLocation(latlng),
                        );
                    }
                    this.ready = true;
                    this.syncLayers();
                    if (this.validLocation) {
                        this.validRadius
                            ? this.fitGeofence()
                            : map.setView(
                                  [
                                      Number(this.latitude),
                                      Number(this.longitude),
                                  ],
                                  16,
                              );
                    }
                    observer = new ResizeObserver(() =>
                        map?.invalidateSize({ pan: false }),
                    );
                    observer.observe(this.$refs.map);
                    map.invalidateSize();
                } catch {
                    this.mapError =
                        "Unable to load the map. Check your internet connection and try again.";
                    map?.remove();
                    map = marker = circle = tiles = null;
                    this.ready = false;
                } finally {
                    loadingMap = false;
                }
            },

            selectLocation(latlng) {
                const point = latlng.wrap?.() ?? latlng;
                this.latitude = point.lat.toFixed(7);
                this.longitude = point.lng.toFixed(7);
                this.locationError = "";
                this.syncLayers();
            },

            syncLayers() {
                if (!map) return;
                if (!this.validLocation) {
                    marker?.remove();
                    circle?.remove();
                    marker = circle = null;
                    return;
                }
                this.locationError = "";
                const center = [Number(this.latitude), Number(this.longitude)];
                if (!marker) {
                    marker = L.marker(center, {
                        icon: L.divIcon(officeIcon),
                        draggable: !config.readonly,
                        title: config.readonly
                            ? "Saved branch location"
                            : "Branch location: drag to adjust",
                        alt: "Branch office location",
                    }).addTo(map);
                    if (!config.readonly) {
                        marker.on("drag", () =>
                            this.selectLocation(marker.getLatLng()),
                        );
                    }
                } else {
                    marker.setLatLng(center);
                }
                if (!this.validRadius) {
                    circle?.remove();
                    circle = null;
                    return;
                }
                if (!circle) {
                    circle = L.circle(center, {
                        radius: Number(this.radius),
                        color: getComputedStyle(this.$el)
                            .getPropertyValue("--limcoma-teal")
                            .trim(),
                        fillColor: getComputedStyle(this.$el)
                            .getPropertyValue("--limcoma-teal")
                            .trim(),
                        fillOpacity: 0.15,
                        weight: 2,
                        interactive: false,
                    }).addTo(map);
                } else {
                    circle.setLatLng(center);
                    circle.setRadius(Number(this.radius));
                }
            },

            fitGeofence() {
                if (map && circle) {
                    map.invalidateSize();
                    map.fitBounds(circle.getBounds(), {
                        padding: [32, 32],
                        maxZoom: 18,
                    });
                }
            },

            resetLocation() {
                this.latitude = this.longitude = "";
                this.locationError = "";
                this.syncLayers();
            },

            togglePanning() {
                this.panning = !this.panning;
                if (map) {
                    this.panning
                        ? map.dragging.enable()
                        : map.dragging.disable();
                }
            },

            destroy() {
                disposed = true;
                searchSequence++;
                searchController?.abort();
                if (form && onDetailsEdited) {
                    form.removeEventListener("input", onDetailsEdited);
                    form.removeEventListener("change", onDetailsEdited);
                }
                observer?.disconnect();
                if (form && onSubmit)
                    form.removeEventListener("submit", onSubmit);
                map?.remove();
                map = marker = circle = tiles = null;
            },
        };
    });
}

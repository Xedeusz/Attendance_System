function popup(title, lines) {
    const element = document.createElement("div");
    const heading = document.createElement("strong");
    heading.textContent = title;
    element.append(heading);
    for (const line of lines) {
        const paragraph = document.createElement("p");
        paragraph.textContent = line;
        element.append(paragraph);
    }
    return element;
}
const branchIcon = {
    className: "attendance-branch-marker",
    html: '<span aria-hidden="true"></span>',
    iconSize: [28, 34],
    iconAnchor: [14, 34],
};
const employeeIcon = {
    className: "attendance-employee-marker",
    html: '<span aria-hidden="true"></span>',
    iconSize: [22, 22],
    iconAnchor: [11, 11],
};
const timeOutIcon = {
    className: "attendance-time-out-marker",
    html: '<span aria-hidden="true"></span>',
    iconSize: [34, 34],
    iconAnchor: [17, 17],
};
const validPoint = (point) =>
    Array.isArray(point) &&
    point.length === 2 &&
    point.every(
        (value) => typeof value === "number" && Number.isFinite(value),
    ) &&
    Math.abs(point[0]) <= 90 &&
    Math.abs(point[1]) <= 180;

export default function registerAttendanceLocationMap(Alpine) {
    Alpine.data("attendanceLocationMap", () => {
        let map,
            geometry,
            observer,
            config,
            disposed = false;
        const markers = new Map();
        return {
            ready: false,
            mapError: "",
            init() {
                try {
                    config = JSON.parse(this.$el.dataset.mapConfig);
                } catch {
                    this.fail(
                        "Recorded location data could not be displayed. Technical details remain available.",
                    );
                    return;
                }
                this.$nextTick(() => this.startMap());
            },
            async startMap() {
                if (
                    disposed ||
                    map ||
                    !this.$refs.map ||
                    (!config.events.some((event) =>
                        validPoint(event.employee),
                    ) &&
                        !validPoint(config.branch))
                )
                    return;
                try {
                    const { default: L } = await import("./leaflet-map");
                    if (disposed || map) return;
                    map = L.map(this.$refs.map, {
                        scrollWheelZoom: true,
                        zoomAnimation: false,
                        dragging: true,
                        touchZoom: true,
                        maxZoom: config.maxZoom,
                    });
                    map.setView(
                        validPoint(config.branch)
                            ? config.branch
                            : config.events.find((event) =>
                                  validPoint(event.employee),
                              ).employee,
                        Math.min(16, config.maxZoom),
                    );
                    geometry = L.featureGroup().addTo(map);
                    const tiles = L.tileLayer(config.tileUrl, {
                        attribution: config.attribution,
                        maxZoom: config.maxZoom,
                        keepBuffer: 0,
                        updateWhenIdle: true,
                        referrerPolicy: "strict-origin-when-cross-origin",
                    });
                    tiles.on("tileerror", () =>
                        this.fail(
                            "Some map tiles could not load. Recorded coordinates and geofence details remain available below.",
                        ),
                    );
                    tiles.addTo(map);
                    if (validPoint(config.branch)) {
                        const branchMarker = L.marker(config.branch, {
                            icon: L.divIcon(branchIcon),
                            draggable: false,
                            title: "Recorded branch: " + config.branchName,
                            alt: "Recorded branch location",
                            zIndexOffset: 100,
                        })
                            .bindPopup(
                                popup(config.branchName, [
                                    "Authorized branch location (recorded)",
                                    "Coordinates: " + config.branch.join(", "),
                                    "Recorded radius: " +
                                        (config.radius === null
                                            ? "Not available"
                                            : config.radius + " metres"),
                                ]),
                            )
                            .addTo(geometry);
                        markers.set("branch", branchMarker);
                        if (
                            typeof config.radius === "number" &&
                            Number.isFinite(config.radius) &&
                            config.radius > 0
                        ) {
                            L.circle(config.branch, {
                                radius: config.radius,
                                color: "#087C68",
                                fillColor: "#10AB8C",
                                fillOpacity: 0.12,
                                weight: 2,
                                interactive: false,
                            }).addTo(geometry);
                        }
                    }
                    for (const event of config.events) {
                        if (!validPoint(event.employee)) continue;
                        const timeOut = event.type === "time_out";
                        const eventMarker = L.marker(event.employee, {
                            icon: L.divIcon(timeOut ? timeOutIcon : employeeIcon),
                            draggable: false,
                            title:
                                event.employeeName +
                                " - " +
                                event.action +
                                " reported location",
                            alt: event.action + " device-reported location",
                            zIndexOffset: timeOut ? 500 : 600,
                        })
                            .bindPopup(
                                popup(event.employeeName, [
                                    event.action,
                                    "Reported coordinates: " +
                                        event.employee.join(", "),
                                    "Reported GPS accuracy: " +
                                        (event.accuracy === null
                                            ? "Not available"
                                            : event.accuracy + " metres"),
                                    "Device-reported capture: " +
                                        event.capturedAt,
                                    "Server-recorded attendance: " +
                                        event.recordedAt,
                                ]),
                            )
                            .addTo(geometry);
                        markers.set(event.type, eventMarker);
                    }
                    this.ready = true;
                    this.fitEvidence();
                    observer = new ResizeObserver(() => this.fitEvidence());
                    observer.observe(this.$refs.map);
                } catch {
                    this.fail(
                        "The map could not be loaded. Recorded coordinates and geofence details remain available below.",
                    );
                    observer?.disconnect();
                    map?.remove();
                    map = geometry = null;
                    this.ready = false;
                }
            },
            hasMarker(type) {
                return this.ready && markers.has(type);
            },
            openEvidence(type) {
                markers.get(type)?.openPopup();
            },
            fitEvidence() {
                if (
                    !map ||
                    !geometry ||
                    !this.$refs.map.clientWidth ||
                    !this.$refs.map.clientHeight
                )
                    return;
                map.invalidateSize({ pan: false });
                map.fitBounds(geometry.getBounds(), {
                    padding: [36, 36],
                    maxZoom: Math.min(17, config.maxZoom),
                    animate: false,
                });
            },
            fail(message) {
                this.mapError = message;
                const technical = this.$el
                    .closest("[data-attendance-record]")
                    ?.querySelector("[data-attendance-technical]");
                if (technical) technical.open = true;
            },
            destroy() {
                disposed = true;
                markers.clear();
                observer?.disconnect();
                map?.remove();
                map = geometry = null;
            },
        };
    });
}

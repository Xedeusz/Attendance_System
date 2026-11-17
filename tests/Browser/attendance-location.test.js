import test from "node:test";
import assert from "node:assert/strict";
import monitoring, {
    evaluateLocation,
} from "../../resources/js/attendance-location.js";
const branch = {
    latitude: 14.5995,
    longitude: 120.9842,
    radius: 150,
    accuracy_limit: 50,
    max_age_seconds: 45,
};
const now = 1_800_000_000_000;
const sample = {
    latitude: 14.5995,
    longitude: 120.9842,
    accuracy: 5,
    retrieved_at: now,
};
for (const [name, changes, status] of [
    ["inside", {}, "within_geofence"],
    ["outside", { latitude: 14.7 }, "outside_geofence"],
    ["poor accuracy", { accuracy: 150 }, "accuracy_insufficient"],
    [
        "boundary uncertainty",
        { latitude: 14.6008, accuracy: 20 },
        "boundary_uncertain",
    ],
    ["stale", { retrieved_at: now - 46000 }, "stale_location"],
    ["future", { retrieved_at: now + 16000 }, "stale_location"],
    ["malformed", { latitude: NaN }, "location_error"],
    ["invalid range", { longitude: 181 }, "location_error"],
])
    test(name, () =>
        assert.equal(
            evaluateLocation(branch, { ...sample, ...changes }, now).status,
            status,
        ),
    );
test("missing location and unconfigured branch cannot validate", () => {
    assert.equal(evaluateLocation(branch, null, now).validated, false);
    assert.equal(
        evaluateLocation({ ...branch, latitude: null }, sample, now).status,
        "branch_not_configured",
    );
});
function setup() {
    const watchers = new Map();
    let serial = 0;
    Object.defineProperty(globalThis, "navigator", {
        configurable: true,
        value: {
            geolocation: {
                watchPosition(success, error) {
                    const id = ++serial;
                    watchers.set(id, [success, error]);
                    return id;
                },
                clearWatch(id) {
                    watchers.delete(id);
                },
                getCurrentPosition(success) {
                    success({ coords: { ...sample }, timestamp: Date.now() });
                },
            },
        },
    });
    globalThis.window = { isSecureContext: true };
    globalThis.document = { hidden: false };
    const state = {
        ...monitoring(),
        capture: { branch },
        remaining: 180,
        offset: 0,
        uncertain: false,
        submitting: false,
        requestReview: false,
        reason: "",
        location: null,
        geo: null,
    };
    return {
        state,
        watchers,
        emit(changes = {}) {
            const reading = { ...sample, retrieved_at: Date.now(), ...changes };
            for (const [success] of [...watchers.values()])
                success({ coords: reading, timestamp: reading.retrieved_at });
        },
    };
}
test("one active watcher processes movement and accuracy without HTTP and ignores late callbacks", () => {
    const { state, watchers, emit } = setup();
    state.startLocation();
    const late = [...watchers.values()][0][0];
    state.startLocation();
    assert.equal(watchers.size, 1);
    emit({ latitude: 14.7 });
    assert.equal(state.geo.status, "outside_geofence");
    emit();
    assert.equal(state.geo.validated, true);
    emit({ accuracy: 100 });
    assert.equal(state.geo.status, "accuracy_insufficient");
    emit({ retrieved_at: Date.now() - 46000 });
    assert.equal(state.geo.status, "stale_location");
    emit();
    assert.equal(state.geo.validated, true);
    assert.equal(state.locationRetryTimer, null);
    state.stopLocation();
    assert.equal(watchers.size, 0);
    late({ coords: { ...sample, latitude: 14.7 }, timestamp: Date.now() });
    assert.equal(state.geo.validated, true);
});
test("denied permission does not auto-prompt again and hidden/expired/uncertain workflows do not watch", () => {
    const { state, watchers } = setup();
    state.startLocation();
    [...watchers.values()][0][1]({ code: 1 });
    assert.equal(watchers.size, 0);
    state.startLocation();
    assert.equal(watchers.size, 0);
    state.startLocation(true);
    assert.equal(watchers.size, 1);
    state.stopLocation();
    document.hidden = true;
    state.startLocation();
    assert.equal(watchers.size, 0);
    document.hidden = false;
    state.remaining = 0;
    state.startLocation();
    assert.equal(watchers.size, 0);
    state.remaining = 180;
    state.uncertain = true;
    state.startLocation();
    assert.equal(watchers.size, 0);
});
test("submission obtains fresh evidence rather than rewriting a stale timestamp", async () => {
    const { state } = setup();
    state.location = { ...sample, retrieved_at: Date.now() - 60000 };
    state.submitting = true;
    await state.ensureFreshLocation();
    assert.equal(state.geo.validated, true);
    assert.ok(state.location.retrieved_at > Date.now() - 1000);
    state.stopLocation();
});
test("outside evidence blocks normal submission while explicit exception review is preserved", async () => {
    const { state } = setup();
    navigator.geolocation.getCurrentPosition = (success) =>
        success({
            coords: { ...sample, latitude: 14.7 },
            timestamp: Date.now(),
        });
    state.submitting = true;
    await assert.rejects(
        state.ensureFreshLocation(),
        (error) => error.status === 422,
    );
    state.requestReview = true;
    state.reason = "Requesting a documented HR exception.";
    await state.ensureFreshLocation();
    assert.equal(state.geo.validated, false);
    state.stopLocation();
});

import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
const php = process.env.PHP_BINARY || "C:/xampp/php/php.exe";
function fixture(...args) {
    return JSON.parse(
        execFileSync(php, ["tests/Browser/fixture.php", ...args.map(String)], {
            encoding: "utf8",
        }),
    );
}
async function login(page, user) {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(user.email);
    await page.getByLabel("Password", { exact: true }).fill(user.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/dashboard$|supervisor\/leave$/);
}
async function submit(page, action, latitude, file, mimeType) {
    const headers = {
        "X-CSRF-TOKEN": await page
            .locator('meta[name="csrf-token"]')
            .getAttribute("content"),
        Accept: "application/json",
    };
    const capture = await page.request.post("/employee/attendance/captures", {
        data: { action },
        headers,
    });
    expect(capture.status()).toBe(200);
    const data = await capture.json();
    const result = await page.request.post(
        "/employee/attendance/captures/" + data.id + "/submit",
        {
            headers,
            multipart: {
                token: data.token,
                latitude: String(latitude),
                longitude: "120.9842",
                accuracy: "5",
                retrieved_at: String(Date.now() - 1000),
                photo: {
                    name:
                        "synthetic." +
                        (mimeType === "image/png" ? "png" : "jpg"),
                    mimeType,
                    buffer: readFileSync(file),
                },
            },
        },
    );
    expect(result.status()).toBe(200);
    return result.json();
}
test("one shared day map, photo comparison, missing checkout, read-only controls and fallback", async ({
    browser,
}) => {
    test.setTimeout(150000);
    const hr = fixture("create");
    const contexts = [];
    try {
        const employee = fixture("attendance", hr.id);
        contexts.push(await browser.newContext(), await browser.newContext());
        const owner = await contexts[0].newPage(),
            admin = await contexts[1].newPage();
        for (const page of [owner, admin]) page.setDefaultTimeout(15000);
        const errors = [];
        admin.on("pageerror", (e) => errors.push(e.message));
        await login(owner, employee);
        const first = await submit(
            owner,
            "time_in",
            14.5995,
            "resources/images/limcoma logo.jpg",
            "image/jpeg",
        );
        await admin.addInitScript(() => {
            navigator.geolocation.getCurrentPosition = () => {
                throw Error("Historical evidence must not request live GPS");
            };
            navigator.geolocation.watchPosition = () => {
                throw Error("Historical evidence must not watch live GPS");
            };
        });
        await login(admin, hr);
        await admin.setViewportSize({ width: 1440, height: 1000 });
        await admin.goto(first.record_url);
        await expect(admin.locator(".attendance-evidence-map")).toHaveCount(1);
        await expect(admin.locator(".attendance-employee-marker")).toHaveCount(
            1,
        );
        await expect(admin.locator(".attendance-time-out-marker")).toHaveCount(
            0,
        );
        await expect(
            admin.getByText("No Time Out submission recorded.", {
                exact: true,
            }),
        ).toBeVisible();
        await submit(
            owner,
            "time_out",
            14.5996,
            "resources/images/LIMCOMA.png",
            "image/png",
        );
        await admin.reload();
        const sections = admin.locator(".attendance-location");
        const maps = admin.locator(".attendance-evidence-map");
        await expect(maps).toHaveCount(1);
        await expect(maps).toHaveClass(/leaflet-container/);
        await expect(sections.locator(".attendance-branch-marker")).toHaveCount(
            1,
        );
        await expect(
            sections.locator(".attendance-employee-marker"),
        ).toHaveCount(1);
        await expect(
            sections.locator(".attendance-time-out-marker"),
        ).toHaveCount(1);
        await expect(
            sections.locator(".leaflet-overlay-pane path"),
        ).toHaveCount(1);
        await expect(
            sections.locator(".leaflet-control-attribution"),
        ).toContainText("OpenStreetMap");
        const configs = await sections.evaluateAll((elements) =>
            elements.map((e) => JSON.parse(e.dataset.mapConfig)),
        );
        expect(configs[0].events.map((event) => event.employee)).toEqual([
            [14.5995, 120.9842],
            [14.5996, 120.9842],
        ]);
        expect(configs[0].radius).toBe(150);
        await sections.first().locator(".attendance-employee-marker").click();
        await expect(sections.first().locator(".leaflet-popup")).toContainText(
            "Time In",
        );
        await expect(sections.first().locator(".leaflet-popup")).toContainText(
            "Reported GPS accuracy: 5 metres",
        );
        await sections.first().locator(".leaflet-popup-close-button").click();
        await expect(sections.locator(".leaflet-popup")).toHaveCount(0);
        await sections.first().locator(".leaflet-control-zoom-in").click();
        await sections
            .first()
            .getByRole("button", { name: "Fit recorded locations" })
            .click();
        await expect
            .poll(async () => {
                const area = await maps.first().boundingBox();
                const marker = await sections
                    .first()
                    .locator(".attendance-employee-marker")
                    .boundingBox();
                return (
                    marker.x >= area.x &&
                    marker.y >= area.y &&
                    marker.x + marker.width <= area.x + area.width &&
                    marker.y + marker.height <= area.y + area.height
                );
            })
            .toBe(true);
        await expect(
            sections.first().locator(".attendance-employee-marker"),
        ).not.toHaveClass(/leaflet-marker-draggable/);
        await sections
            .getByRole("button", {
                name: "Show Recorded branch location details",
                exact: true,
            })
            .click();
        await expect(sections.first().locator(".leaflet-popup")).toContainText(
            "Recorded radius: 150 metres",
        );
        await sections.first().locator(".leaflet-popup-close-button").click();
        await expect(sections.locator(".leaflet-popup")).toHaveCount(0);
        await sections
            .getByRole("button", {
                name: "Show Time Out location details",
                exact: true,
            })
            .click();
        await expect(sections.locator(".leaflet-popup")).toContainText(
            "Time Out",
        );
        await expect(sections.locator(".leaflet-popup")).toContainText(
            "Device-reported capture:",
        );
        await sections.locator(".leaflet-popup-close-button").click();
        await expect(sections.locator(".leaflet-popup")).toHaveCount(0);
        await expect(sections.locator(".leaflet-marker-draggable")).toHaveCount(
            0,
        );
        await expect(admin.locator("img.attendance-video")).toHaveCount(2);
        for (const photo of await admin.locator("img.attendance-video").all()) {
            await photo.scrollIntoViewIfNeeded();
            await expect
                .poll(() =>
                    photo.evaluate(
                        (image) => image.complete && image.naturalWidth > 0,
                    ),
                )
                .toBe(true);
        }
        for (const width of [1440, 768, 390, 320]) {
            await admin.setViewportSize({ width, height: 1000 });
            await expect
                .poll(() =>
                    admin.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                )
                .toBe(true);
            await sections
                .first()
                .getByRole("button", { name: "Fit recorded locations" })
                .click();
            await admin.evaluate(() => window.scrollTo(0, 0));
            const photoCards = admin.locator(".attendance-photo-card");
            const firstPhoto = await photoCards.nth(0).boundingBox(),
                secondPhoto = await photoCards.nth(1).boundingBox();
            const mapBox = await maps.boundingBox();
            if (width >= 768)
                expect(Math.abs(firstPhoto.y - secondPhoto.y)).toBeLessThan(2);
            else
                expect(secondPhoto.y).toBeGreaterThanOrEqual(
                    firstPhoto.y + firstPhoto.height,
                );
            expect(mapBox.y).toBeGreaterThan(
                secondPhoto.y + secondPhoto.height,
            );
            const locationCard = await sections.boundingBox();
            const timeIn = await admin
                .locator('[data-attendance-event="time_in"]')
                .boundingBox();
            const timeOut = await admin
                .locator('[data-attendance-event="time_out"]')
                .boundingBox();
            const branch = await admin
                .locator(".attendance-branch-details")
                .boundingBox();
            expect(mapBox.height).toBeGreaterThanOrEqual(280);
            expect(mapBox.height).toBeLessThanOrEqual(340);
            if (width >= 1280) {
                expect(timeIn.x).toBeGreaterThanOrEqual(
                    locationCard.x + locationCard.width,
                );
                expect(Math.abs(timeIn.y - locationCard.y)).toBeLessThan(2);
                expect(timeOut.y).toBeGreaterThanOrEqual(
                    timeIn.y + timeIn.height,
                );
                const row = await admin
                    .locator(".attendance-location-comparison")
                    .boundingBox();
                expect(locationCard.width / row.width).toBeGreaterThan(0.59);
                expect(locationCard.width / row.width).toBeLessThan(0.66);
            } else {
                expect(timeIn.y).toBeGreaterThanOrEqual(
                    locationCard.y + locationCard.height,
                );
                if (width < 768)
                    expect(timeOut.y).toBeGreaterThanOrEqual(
                        timeIn.y + timeIn.height,
                    );
            }
            expect(branch.y).toBeGreaterThanOrEqual(
                Math.max(
                    locationCard.y + locationCard.height,
                    timeOut.y + timeOut.height,
                ),
            );
            await admin.screenshot({
                path: "test-results/attendance-location-map-" + width + ".png",
                fullPage: true,
            });
        }
        const identifiers = await maps.evaluateAll((elements) =>
            elements.map((e) => e._leaflet_id),
        );
        // A visibility refresh exercises the existing morph hook without replacing unchanged maps.
        const refresh = admin.waitForResponse(
            (response) =>
                response.request().headers()["x-workspace-sync"] === "1" &&
                response.url().includes("/employee/attendance/records/"),
        );
        await admin.evaluate(() =>
            document.dispatchEvent(new Event("visibilitychange")),
        );
        await refresh;
        await expect(maps).toHaveCount(1);
        expect(
            await maps.evaluateAll((elements) =>
                elements.map((e) => e._leaflet_id),
            ),
        ).toEqual(identifiers);
        await contexts[1].route("https://tile.openstreetmap.org/**", (route) =>
            route.abort(),
        );
        await admin.reload();
        await expect(
            sections
                .first()
                .getByText("Some map tiles could not load.", { exact: false }),
        ).toBeVisible();
        await expect(
            admin.locator("[data-attendance-technical]"),
        ).toHaveAttribute("open", "");
        await expect(
            admin.locator("[data-attendance-technical]"),
        ).toContainText("14.5995");
        await expect(admin.locator(".attendance-branch-details")).toContainText(
            "150 metres",
        );
        await expect(
            admin.locator("img.attendance-video").first(),
        ).toBeVisible();
        expect(errors).toEqual([]);
    } finally {
        await Promise.all(contexts.map((c) => c.close()));
        execFileSync(php, [
            "tests/Browser/fixture.php",
            "cleanup",
            String(hr.id),
        ]);
    }
});

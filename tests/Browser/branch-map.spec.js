import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { mkdirSync } from "node:fs";

const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";
let hr;
test.beforeAll(() => {
    hr = JSON.parse(
        execFileSync(php, ["tests/Browser/fixture.php", "create"], {
            encoding: "utf8",
        }),
    );
    mkdirSync("test-results/screenshots", { recursive: true });
});
test.afterAll(() => {
    if (hr)
        execFileSync(php, [
            "tests/Browser/fixture.php",
            "cleanup",
            String(hr.id),
        ]);
});

async function login(page) {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(hr.email);
    await page.getByLabel("Password", { exact: true }).fill(hr.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

test("map selection, drag, live meters, reset, MariaDB save, restore, validation, read-only and cancel", async ({
    page,
}) => {
    test.setTimeout(180000);
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await login(page);
    await page.goto("/hr/branches/create");
    const map = page.getByRole("region", {
        name: "Branch location map",
        exact: true,
    });
    const radius = page.getByLabel("Geofencing Radius (Meters)", {
        exact: true,
    });
    const latitude = page.getByLabel("Latitude", { exact: true });
    const longitude = page.getByLabel("Longitude", { exact: true });
    const marker = page.locator(".leaflet-marker-icon");
    const circle = page.locator(".leaflet-overlay-pane path");

    await expect(map).toHaveClass(/leaflet-container/);
    await expect(page.locator(".leaflet-control-attribution")).toContainText(
        "OpenStreetMap",
    );
    await expect(latitude).toHaveValue("");
    await expect(longitude).toHaveValue("");
    await expect(marker).toHaveCount(0);
    await page
        .getByLabel("Branch code", { exact: true })
        .fill(hr.prefix + "-MAP");
    await page
        .getByLabel("Branch name", { exact: true })
        .fill("Synthetic Branch Map QA");
    await page
        .getByLabel("Street address", { exact: true })
        .fill("Synthetic QA address");
    await page.getByLabel("City", { exact: true }).fill("Synthetic city");
    await page
        .getByLabel("Province", { exact: true })
        .fill("Synthetic province");
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await expect(
        page.getByText("Please select the branch location on the map.", {
            exact: true,
        }),
    ).toBeVisible();
    await radius.fill("100");
    await map.click({ position: { x: 150, y: 170 } });
    await expect(marker).toHaveCount(1);
    await expect(circle).toHaveCount(1);
    await page.getByRole("button", { name: "Fit Geofence to View" }).click();
    await page.waitForTimeout(400);
    const center = [await latitude.inputValue(), await longitude.inputValue()];
    const width100 = (await circle.boundingBox()).width;
    const mapElement = await map.elementHandle();
    await radius.fill("200");
    await expect(
        page.getByText("Geofence Radius: 200 meters", { exact: true }),
    ).toBeVisible();
    expect((await circle.boundingBox()).width / width100).toBeCloseTo(2, 1);
    expect([await latitude.inputValue(), await longitude.inputValue()]).toEqual(
        center,
    );
    expect(await mapElement.evaluate((element) => element.isConnected)).toBe(
        true,
    );
    await radius.fill("50");
    await expect(
        page.getByText("Geofence Radius: 50 meters", { exact: true }),
    ).toBeVisible();
    expect((await circle.boundingBox()).width / width100).toBeCloseTo(0.5, 1);
    for (const invalidRadius of [
        "0",
        "-1",
        "50.5",
        "100001",
        "1e2",
        "100.0",
        "",
    ]) {
        await radius.fill(invalidRadius);
        await expect(circle).toHaveCount(0);
        await expect(
            page.getByText("Enter a valid geofence radius in meters.", {
                exact: true,
            }),
        ).toBeVisible();
    }
    await radius.fill("150");
    await expect(circle).toHaveCount(1);
    const icon = await marker.boundingBox();
    await page.mouse.move(icon.x + icon.width / 2, icon.y + icon.height / 2);
    await page.mouse.down();
    await page.mouse.move(
        icon.x + icon.width / 2 + 45,
        icon.y + icon.height / 2 + 25,
        { steps: 12 },
    );
    await page.mouse.up();
    expect(await latitude.inputValue()).not.toBe(center[0]);
    expect(await longitude.inputValue()).not.toBe(center[1]);
    await expect(radius).toHaveValue("150");
    const markerAfter = await marker.boundingBox();
    const circleAfter = await circle.boundingBox();
    expect(
        Math.abs(markerAfter.x + 13 - (circleAfter.x + circleAfter.width / 2)),
    ).toBeLessThan(3);
    expect(
        Math.abs(markerAfter.y + 31 - (circleAfter.y + circleAfter.height / 2)),
    ).toBeLessThan(3);
    await page.getByRole("button", { name: "Reset Location" }).click();
    await expect(marker).toHaveCount(0);
    await expect(circle).toHaveCount(0);
    await expect(latitude).toHaveValue("");
    await expect(page.getByLabel("Branch name", { exact: true })).toHaveValue(
        "Synthetic Branch Map QA",
    );

    await radius.fill("");
    await page
        .getByLabel("Branch status", { exact: true })
        .selectOption("inactive");
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await expect(page).toHaveURL(/\/hr\/branches\/\d+$/);
    await expect(
        page.getByText("Location is not configured for this branch.", {
            exact: true,
        }),
    ).toBeVisible();
    const savedUrl = page.url();
    await page.getByRole("link", { name: "Edit branch", exact: true }).click();
    await expect(marker).toHaveCount(0);
    await page
        .getByLabel("Branch status", { exact: true })
        .selectOption("active");
    await page.getByLabel(/Edit coordinates manually/).check();
    await latitude.fill("14.5995000");
    await longitude.fill("120.9842000");
    await radius.fill("100");
    await page.getByRole("button", { name: "Fit Geofence to View" }).click();
    await expect(marker).toHaveCount(1);
    await page.getByLabel("Branch name", { exact: true }).fill("");
    await page.locator("form.card").evaluate((form) => {
        form.noValidate = true;
    });
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await expect(
        page
            .getByText("The branch name field is required.", { exact: true })
            .last(),
    ).toBeVisible();
    await expect(latitude).toHaveValue("14.5995000");
    await expect(radius).toHaveValue("100");
    await expect(marker).toHaveCount(1);
    await page
        .getByLabel("Branch name", { exact: true })
        .fill("Synthetic Branch Map QA");
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await expect(page).toHaveURL(savedUrl);
    await expect(marker).toHaveCount(1);
    await expect(circle).toHaveCount(1);
    await expect(
        page.getByRole("button", { name: "Reset Location" }),
    ).toHaveCount(0);
    await map.scrollIntoViewIfNeeded();
    const readOnlyMarker = await marker.getAttribute("style");
    await map.click({ position: { x: 100, y: 100 } });
    expect(await marker.getAttribute("style")).toEqual(readOnlyMarker);
    await page.getByRole("link", { name: "Edit branch", exact: true }).click();
    await expect(latitude).toHaveValue("14.5995000");
    await expect(longitude).toHaveValue("120.9842000");
    await expect(radius).toHaveValue("100");
    await radius.fill("200");
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await page.getByRole("link", { name: "Edit branch", exact: true }).click();
    await expect(radius).toHaveValue("200");
    await radius.fill("500");
    await map.click({ position: { x: 100, y: 100 } });
    await page.getByRole("link", { name: "Cancel", exact: true }).click();
    await page.getByRole("link", { name: "Edit branch", exact: true }).click();
    await expect(radius).toHaveValue("200");
    await expect(latitude).toHaveValue("14.5995000");
    for (const width of [320, 390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        expect((await map.boundingBox()).height).toBeGreaterThanOrEqual(320);
    }
    await page.screenshot({
        path: "test-results/screenshots/branch-map-desktop.png",
        fullPage: true,
    });
    expect(errors).toEqual([]);
});

test("desktop details layout, direct wheel zoom, outside scrolling and sidebar resizing", async ({
    page,
}) => {
    await page.setViewportSize({ width: 1600, height: 1000 });
    await login(page);
    await page.goto("/hr/branches/create");
    const map = page.getByRole("region", {
        name: "Branch location map",
        exact: true,
    });
    const panel = page.getByRole("complementary", {
        name: "Location Details",
        exact: true,
    });
    const circle = page.locator(".leaflet-overlay-pane path");
    await expect(map).toHaveClass(/leaflet-container/);
    const mapBox = await map.boundingBox();
    const panelBox = await panel.boundingBox();
    expect(panelBox.x).toBeGreaterThan(mapBox.x + mapBox.width);
    expect(mapBox.width / (mapBox.width + panelBox.width)).toBeGreaterThan(0.6);
    expect(mapBox.width / (mapBox.width + panelBox.width)).toBeLessThanOrEqual(
        0.71,
    );
    expect(mapBox.height).toBeGreaterThanOrEqual(540);
    await expect(panel.getByLabel("Latitude", { exact: true })).toHaveCount(1);
    await expect(panel.getByLabel("Longitude", { exact: true })).toHaveCount(1);
    await expect(
        panel.getByRole("button", { name: "Reset Location", exact: true }),
    ).toHaveCount(1);
    const latBox = await panel
        .getByLabel("Latitude", { exact: true })
        .boundingBox();
    const lonBox = await panel
        .getByLabel("Longitude", { exact: true })
        .boundingBox();
    expect(lonBox.y).toBeGreaterThan(latBox.y + latBox.height);
    await panel.getByLabel(/Edit coordinates manually/).check();
    await panel.getByLabel("Latitude", { exact: true }).fill("14.5995");
    await panel.getByLabel("Longitude", { exact: true }).fill("120.9842");
    await panel
        .getByLabel("Geofencing Radius (Meters)", { exact: true })
        .fill("70");
    await panel.getByRole("button", { name: "Fit Geofence to View" }).click();
    await map.scrollIntoViewIfNeeded();
    await page.waitForTimeout(400);
    const originalWidth = (await circle.boundingBox()).width;
    const scrollBefore = await page.evaluate(() => scrollY);
    await map.hover();
    await page.mouse.wheel(0, -120);
    await expect
        .poll(async () => (await circle.boundingBox()).width)
        .toBeGreaterThan(originalWidth * 1.5);
    expect(await page.evaluate(() => scrollY)).toBe(scrollBefore);
    await expect(map).not.toHaveClass(/leaflet-zoom-anim/);
    await page.waitForTimeout(400);
    const zoomedWidth = (await circle.boundingBox()).width;
    await page.mouse.wheel(0, 120);
    await expect
        .poll(async () => (await circle.boundingBox()).width)
        .toBeLessThan(zoomedWidth * 0.75);
    await expect(panel.getByLabel("Latitude", { exact: true })).toHaveValue(
        "14.5995",
    );
    await expect(panel.getByLabel("Longitude", { exact: true })).toHaveValue(
        "120.9842",
    );
    await page.mouse.move(1590, 300);
    await page.mouse.wheel(0, 350);
    await expect
        .poll(() => page.evaluate(() => scrollY))
        .toBeGreaterThan(scrollBefore);
    const widthBeforeSidebar = (await map.boundingBox()).width;
    await page
        .getByRole("button", { name: "Toggle navigation", exact: true })
        .click();
    await expect
        .poll(async () => (await map.boundingBox()).width)
        .toBeGreaterThan(widthBeforeSidebar);
    await panel.getByRole("button", { name: "Fit Geofence to View" }).click();
    await expect(page.locator(".leaflet-marker-icon")).toHaveCount(1);
    await expect(page.locator(".leaflet-control-zoom-in")).toBeVisible();
    await map.scrollIntoViewIfNeeded();
    await page.screenshot({
        path: "test-results/screenshots/branch-map-two-column.png",
        fullPage: true,
    });
    for (const width of [1280, 1024, 768, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        const mapBounds = await map.boundingBox();
        const panelBounds = await panel.boundingBox();
        if (width >= 1280) {
            expect(panelBounds.x).toBeGreaterThan(
                mapBounds.x + mapBounds.width,
            );
        } else {
            expect(panelBounds.y).toBeGreaterThanOrEqual(
                mapBounds.y + mapBounds.height,
            );
            expect(mapBounds.height).toBeLessThanOrEqual(400);
        }
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
    }
});

test("tile failure is recoverable and mobile touch selection preserves page scrolling", async ({
    browser,
}) => {
    const context = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
    });
    const page = await context.newPage();
    await page.route("https://tile.openstreetmap.org/**", (route) =>
        route.abort(),
    );
    await login(page);
    await page.goto("/hr/branches/create");
    await expect(page.getByText(/Unable to load the map tiles/)).toBeVisible();
    const map = page.getByRole("region", {
        name: "Branch location map",
        exact: true,
    });
    await map.tap({ position: { x: 150, y: 170 } });
    await page
        .getByLabel("Geofencing Radius (Meters)", { exact: true })
        .fill("150");
    await expect(page.locator(".leaflet-marker-icon")).toHaveCount(1);
    await expect(page.locator(".leaflet-overlay-pane path")).toHaveCount(1);
    await page
        .getByRole("button", { name: "Enable map panning", exact: true })
        .click();
    await expect(
        page.getByRole("button", {
            name: "Enable page scrolling",
            exact: true,
        }),
    ).toHaveAttribute("aria-pressed", "true");
    await page
        .getByRole("button", { name: "Enable page scrolling", exact: true })
        .click();
    await expect(map).not.toHaveClass(/map-pan-enabled/);
    await page.unroute("https://tile.openstreetmap.org/**");
    await page.getByRole("button", { name: "Retry Map", exact: true }).click();
    await expect(page.getByText(/Unable to load the map tiles/)).toBeHidden();
    await expect(page.locator(".leaflet-tile-loaded").first()).toBeVisible();
    await expect(page.locator(".leaflet-marker-icon")).toHaveCount(1);
    await page.screenshot({
        path: "test-results/screenshots/branch-map-mobile.png",
        fullPage: true,
    });
    await context.close();
});

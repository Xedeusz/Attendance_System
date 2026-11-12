import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { mkdirSync } from "node:fs";

const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";
let hr, employee;
test.use({ screenshot: "off", trace: "off" });
test.beforeAll(() => {
    hr = JSON.parse(
        execFileSync(php, ["tests/Browser/fixture.php", "create"], {
            encoding: "utf8",
        }),
    );
    employee = JSON.parse(
        execFileSync(
            php,
            ["tests/Browser/fixture.php", "attendance", String(hr.id)],
            { encoding: "utf8" },
        ),
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

async function login(page, user) {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(user.email);
    await page.getByLabel("Password", { exact: true }).fill(user.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

// Synthetic browser-only streams exercise controls; they are not physical camera tests.
async function installMedia(page) {
    await page.addInitScript(() => {
        window.qaTracks = [];
        window.qaCameraDenied = false;
        window.qaLocationDenied = false;
        window.qaOutside = false;
        window.qaEnumerationDenied = false;
        navigator.geolocation.getCurrentPosition = (success, failure) => {
            if (window.qaLocationDenied) return failure({ code: 1 });
            success({
                coords: {
                    latitude: window.qaOutside ? 14.7 : 14.5995,
                    longitude: 120.9842,
                    accuracy: 5,
                },
                timestamp: Date.now(),
            });
        };
        navigator.mediaDevices.enumerateDevices = async () => {
            if (window.qaEnumerationDenied)
                throw new DOMException("Cannot enumerate", "NotAllowedError");
            return [
                { kind: "videoinput", deviceId: "qa-front" },
                { kind: "videoinput", deviceId: "qa-rear" },
            ];
        };
        navigator.mediaDevices.getUserMedia = async () => {
            if (window.qaCameraDenied)
                throw new DOMException("Denied for test", "NotAllowedError");
            const canvas = document.createElement("canvas");
            canvas.width = 640;
            canvas.height = 480;
            const context = canvas.getContext("2d");
            const draw = () => {
                context.fillStyle = `hsl(${Date.now() % 360}, 75%, 50%)`;
                context.fillRect(0, 0, 640, 480);
                context.fillStyle = "#111";
                context.font = "24px sans-serif";
                context.fillText("Synthetic QA camera " + Date.now(), 25, 120);
            };
            draw();
            const timer = setInterval(draw, 50);
            const stream = canvas.captureStream(20);
            stream.getTracks().forEach((track) => {
                const stop = track.stop.bind(track);
                track.stop = () => {
                    clearInterval(timer);
                    stop();
                };
                window.qaTracks.push(track);
            });
            return stream;
        };
    });
}

test("live controls, permissions, retake, switching, cleanup and real MariaDB attendance", async ({
    page,
    browser,
}) => {
    test.setTimeout(180000);
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await installMedia(page);
    await login(page, employee);
    await page.goto("/employee/attendance");
    expect(await page.locator('input[type="file"]').count()).toBe(0);
    expect(await page.evaluate(() => window.qaTracks.length)).toBe(0);
    for (const width of [320, 360, 375, 390, 430, 768, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    expect(
        (
            await page
                .getByRole("button", { name: "Time In", exact: true })
                .boundingBox()
        ).height,
    ).toBeGreaterThanOrEqual(64);
    await page.getByRole("button", { name: "Time In", exact: true }).click();
    await expect(
        page.getByText(
            "Reported location meets the configured geofence and accuracy checks.",
        ),
    ).toBeVisible();
    await page.evaluate(() => {
        window.qaCameraDenied = true;
    });
    await page.getByRole("button", { name: "Start live camera" }).click();
    await expect(page.getByText(/Camera permission denied/)).toBeVisible();
    await page.evaluate(() => {
        window.qaCameraDenied = false;
    });
    await page.getByRole("button", { name: "Start live camera" }).click();
    await expect(
        page.getByRole("button", { name: "Capture Photo", exact: true }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Switch Camera" }).click();
    await expect
        .poll(() => page.evaluate(() => window.qaTracks.length))
        .toBe(2);
    expect(await page.evaluate(() => window.qaTracks[0].readyState)).toBe(
        "ended",
    );
    await page
        .getByRole("button", { name: "Capture Photo", exact: true })
        .click();
    await expect(
        page.getByAltText("Captured attendance photo preview"),
    ).toBeVisible();
    await page.evaluate(() => {
        window.qaEnumerationDenied = true;
    });
    await page.getByRole("button", { name: "Retake Photo" }).click();
    await expect(
        page.getByRole("button", { name: "Capture Photo", exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText(/Camera switching is unavailable/),
    ).toBeVisible();
    await page.evaluate(() => {
        window.qaEnumerationDenied = false;
    });
    await page
        .getByRole("button", { name: "Capture Photo", exact: true })
        .click();
    expect(
        await page.evaluate(() =>
            window.qaTracks.every((track) => track.readyState === "ended"),
        ),
    ).toBe(true);
    await page.screenshot({
        path: "test-results/screenshots/attendance-mobile-synthetic.png",
        fullPage: true,
    });
    // Let Laravel commit, then drop the reply to exercise an uncertain outcome.
    await page.route(
        "**/employee/attendance/captures/*/submit",
        async (route) => {
            await route.fetch();
            await route.abort("failed");
        },
        { times: 1 },
    );
    await page.getByRole("button", { name: "Submit Attendance" }).click();
    await expect(page.getByText(/Server outcome is uncertain/)).toBeVisible();
    await page.getByRole("button", { name: "Check submission status" }).click();
    await expect(
        page.getByRole("heading", { name: "Attendance result" }),
    ).toBeVisible();
    await expect(
        page.getByText(/Attendance saved. Reported location passed/),
    ).toBeVisible();
    const recordUrl = await page
        .getByRole("link", { name: "View saved record" })
        .getAttribute("href");
    await page.getByRole("link", { name: "Refresh available actions" }).click();
    await expect(
        page.getByRole("button", { name: "Time In", exact: true }),
    ).toBeDisabled();
    await page.getByRole("button", { name: "Time Out", exact: true }).click();
    await page.getByRole("button", { name: "Start live camera" }).click();
    await page
        .getByRole("button", { name: "Capture Photo", exact: true })
        .click();
    await page.getByRole("button", { name: "Submit Attendance" }).click();
    await expect(
        page.getByText(/Attendance saved. Reported location passed/),
    ).toBeVisible();
    await page.goto(recordUrl);
    await expect(
        page.locator(".badge").filter({ hasText: /^Completed$/ }),
    ).toBeVisible();
    const photos = page.locator('img[alt^="Submitted"]');
    expect(await photos.count()).toBe(2);
    for (const photo of await photos.all()) {
        const response = await page.request.get(
            await photo.getAttribute("src"),
        );
        expect(response.status()).toBe(200);
        expect(response.headers()["content-type"]).toBe("image/jpeg");
    }
    await page.goto("/employee/attendance/history");
    await expect(
        page.locator(".badge").filter({ hasText: /^Completed$/ }),
    ).toBeVisible();
    await page.goto("/employee/attendance");
    await page.getByRole("button", { name: "Time In", exact: true }).click();
    await page.evaluate(() => {
        window.qaLocationDenied = true;
    });
    await page.getByRole("button", { name: "Retry Location" }).click();
    await expect(page.getByText(/Location permission denied/)).toBeVisible();
    await page.getByRole("button", { name: "Start live camera" }).click();
    await page.getByRole("button", { name: "Cancel", exact: true }).click();
    expect(
        await page.evaluate(() =>
            window.qaTracks.every((track) => track.readyState === "ended"),
        ),
    ).toBe(true);
    const hrContext = await browser.newContext();
    const hrPage = await hrContext.newPage();
    await login(hrPage, hr);
    await hrPage.goto("/hr/attendance?q=" + encodeURIComponent(hr.prefix));
    await expect(
        hrPage.locator(".badge").filter({ hasText: /^Completed$/ }),
    ).toBeVisible();
    await hrPage.goto(recordUrl);
    await expect(
        hrPage.getByText("Employee-reported, untrusted coordinates"),
    ).toHaveCount(2);
    await page.evaluate(() => {
        window.qaLocationDenied = false;
        window.qaOutside = true;
    });
    await page.getByRole("button", { name: "Time In", exact: true }).click();
    await expect(
        page.getByText(/Location not validated: outside geofence/),
    ).toBeVisible();
    await page.getByRole("checkbox", { name: /Request HR review/ }).check();
    await page
        .getByLabel(/Exception explanation/)
        .fill("Synthetic location exception for HR review testing.");
    await page.getByRole("button", { name: "Start live camera" }).click();
    await page
        .getByRole("button", { name: "Capture Photo", exact: true })
        .click();
    await page.getByRole("button", { name: "Submit Attendance" }).click();
    await expect(
        page.getByText(/Submission saved as Pending HR Review/),
    ).toBeVisible();
    const pendingUrl = await page
        .getByRole("link", { name: "View saved record" })
        .getAttribute("href");
    await hrPage.goto(pendingUrl);
    await hrPage.getByLabel("HR decision").selectOption("approved");
    await hrPage
        .getByLabel("Review remarks")
        .fill(
            "Reviewed synthetic exception; original location remains outside.",
        );
    await hrPage
        .getByRole("checkbox", { name: /I confirm this decision/ })
        .check();
    await hrPage.getByRole("button", { name: "Save review decision" }).click();
    await expect(
        hrPage.getByText("HR approved", { exact: false }),
    ).toBeVisible();
    await expect(
        hrPage.getByText("outside geofence", { exact: true }),
    ).toBeVisible();
    await hrPage.screenshot({
        path: "test-results/screenshots/attendance-hr-review-synthetic.png",
        fullPage: true,
    });
    await page.goto(pendingUrl);
    await expect(
        page.locator(".badge").filter({ hasText: /^Exception approved$/ }),
    ).toHaveCount(2);
    await hrContext.close();
    expect(errors).toEqual([]);
});

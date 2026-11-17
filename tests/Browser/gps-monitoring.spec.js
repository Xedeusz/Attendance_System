import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";
function fixture(...args) {
    const output = execFileSync(
        php,
        ["tests/Browser/fixture.php", ...args.map(String)],
        {
            encoding: "utf8",
        },
    );
    return output.trim() ? JSON.parse(output) : null;
}
test("browser GPS movement updates automatically without preview requests and clears the watcher on cancel", async ({
    browser,
}) => {
    test.setTimeout(90000);
    const hr = fixture("create");
    const employee = fixture("attendance", hr.id);
    const context = await browser.newContext({
        permissions: ["geolocation"],
        geolocation: { latitude: 14.5995, longitude: 120.9842, accuracy: 5 },
    });
    try {
        const page = await context.newPage();
        await page.addInitScript(() => {
            window.activeLocationWatches = new Set();
            const watch = navigator.geolocation.watchPosition.bind(
                navigator.geolocation,
            );
            const clear = navigator.geolocation.clearWatch.bind(
                navigator.geolocation,
            );
            navigator.geolocation.watchPosition = (...args) => {
                const id = watch(...args);
                window.activeLocationWatches.add(id);
                return id;
            };
            navigator.geolocation.clearWatch = (id) => {
                window.activeLocationWatches.delete(id);
                clear(id);
            };
        });
        let previews = 0,
            submissions = 0;
        page.on("request", (request) => {
            if (/\/captures\/[^/]+\/location$/.test(request.url())) previews++;
            if (request.url().endsWith("/submit")) submissions++;
        });
        await page.goto("/login");
        await page.getByLabel("Email address").fill(employee.email);
        await page
            .getByLabel("Password", { exact: true })
            .fill(employee.password);
        await page
            .getByRole("button", { name: "Sign in", exact: true })
            .click();
        await expect(page).toHaveURL(/dashboard$/);
        await page.goto("/employee/attendance");
        expect(
            await page.evaluate(() => window.activeLocationWatches.size),
        ).toBe(0);
        await page
            .getByRole("button", { name: "Time In", exact: true })
            .click();
        await expect(
            page.getByText("Inside authorized branch area", { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole("button", { name: "Restart location access" }),
        ).toBeHidden();
        expect(
            await page.evaluate(() => window.activeLocationWatches.size),
        ).toBe(1);
        await page.evaluate(() =>
            window.workspaceEcho?.connector.pusher.disconnect(),
        );
        await context.setGeolocation({
            latitude: 14.7,
            longitude: 120.9842,
            accuracy: 5,
        });
        await expect(
            page.getByText("Outside authorized branch area", { exact: true }),
        ).toBeVisible();
        await context.setGeolocation({
            latitude: 14.5995,
            longitude: 120.9842,
            accuracy: 150,
        });
        await expect(
            page.getByText(/Location accuracy is insufficient/),
        ).toBeVisible();
        await context.setGeolocation({
            latitude: 14.5995,
            longitude: 120.9842,
            accuracy: 5,
        });
        await expect(
            page.getByText("Inside authorized branch area", { exact: true }),
        ).toBeVisible();
        await page.getByRole("button", { name: "Cancel", exact: true }).click();
        expect(
            await page.evaluate(() => window.activeLocationWatches.size),
        ).toBe(0);
        await page
            .getByRole("button", { name: "Time In", exact: true })
            .click();
        await expect(
            page.getByText("Inside authorized branch area", { exact: true }),
        ).toBeVisible();
        expect(
            await page.evaluate(() => window.activeLocationWatches.size),
        ).toBe(1);
        await page.getByRole("button", { name: "Cancel", exact: true }).click();
        expect(
            await page.evaluate(() => window.activeLocationWatches.size),
        ).toBe(0);
        expect(previews).toBe(0);
        expect(submissions).toBe(0);
    } finally {
        await context.close();
        fixture("cleanup", hr.id);
    }
});

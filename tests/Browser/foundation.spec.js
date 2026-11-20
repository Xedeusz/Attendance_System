import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { mkdirSync } from "node:fs";

let account;
const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";
test.beforeAll(() => {
    account = JSON.parse(
        execFileSync(php, ["tests/Browser/fixture.php", "create"], {
            encoding: "utf8",
        }),
    );
    mkdirSync("test-results/screenshots", { recursive: true });
});
test.afterAll(() => {
    if (account)
        execFileSync(php, [
            "tests/Browser/fixture.php",
            "cleanup",
            String(account.id),
        ]);
});

async function signIn(page) {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(account.email);
    await page.getByLabel("Password", { exact: true }).fill(account.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}
async function noPageOverflow(page) {
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
}

test("login serves a compact logo and fits the viewport at each pixel density", async ({ browser }) => {
    for (const [density, width] of [[1, 1440], [2, 768], [3, 390]]) {
        const context = await browser.newContext({
            viewport: { width, height: 800 },
            deviceScaleFactor: density,
        });
        try {
            const page = await context.newPage();
            await page.goto("/login");
            const logo = page.getByAltText("LIMCOMA Multipurpose Cooperative logo");
            await logo.evaluate((image) => image.decode());
            await expect(
                page.locator('link[rel="icon"][sizes="32x32"]'),
            ).toHaveAttribute("href", /\/favicon-32x32\.png$/);
            await expect(
                page.locator('link[rel="apple-touch-icon"]'),
            ).toHaveAttribute("href", /\/apple-touch-icon\.png$/);
            const source = await logo.evaluate((image) => image.currentSrc);
            expect(new URL(source).pathname).toBe(`/limcoma-logo-${40 * density}.webp`);
            await noPageOverflow(page);
        } finally {
            await context.close();
        }
    }
});

test("Escape returns account disclosure focus to its toggle", async ({ page }) => {
    await signIn(page);
    const accountToggle = page.getByRole("button", { name: /Account menu for/ });
    await expect(accountToggle).toHaveAttribute("aria-expanded", "false");
    await accountToggle.press("Enter");
    await expect(accountToggle).toHaveAttribute("aria-expanded", "true");
    await expect(page.getByRole("link", { name: "Your profile" })).toBeFocused();
    await page.keyboard.press("Escape");
    await expect(accountToggle).toBeFocused();
    await expect(accountToggle).toHaveAttribute("aria-expanded", "false");
});

test("real login, session cookie flags, CSRF, navigation, disabled actions and logout", async ({
    page,
}) => {
    const errors = [];
    const notificationRequests = [];
    page.on("pageerror", (error) => errors.push(error.message));
    page.on("request", (request) => {
        if (/\/notifications-[^/]+\.(js|css)$/.test(new URL(request.url()).pathname)) {
            notificationRequests.push(request.resourceType());
        }
    });
    await page.goto("/dashboard");
    await expect(page).toHaveURL(/\/login$/);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.screenshot({
        path: "test-results/screenshots/login-desktop.png",
        fullPage: true,
    });
    await page
        .getByRole("button", { name: "Show Password", exact: true })
        .click();
    await expect(page.getByLabel("Password", { exact: true })).toHaveAttribute(
        "type",
        "text",
    );
    await page
        .getByRole("button", { name: "Hide Password", exact: true })
        .click();
    expect(notificationRequests).toEqual([]);
    const csrf = await page.request.post("/login", {
        form: { email: account.email, password: account.password },
    });
    expect([403, 419]).toContain(csrf.status());
    await page.getByLabel("Email address").fill(account.email);
    await page.getByLabel("Password", { exact: true }).fill("invalid-password");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page.locator(".form-errors").getByRole("alert")).toBeVisible();
    await expect(page.locator(".swal2-toast")).toContainText("Please check your information");
    await signIn(page);
    const cookies = await page.context().cookies();
    const session = cookies.find((cookie) => cookie.name === "ebas_session");
    expect(session.httpOnly).toBe(true);
    expect(session.sameSite).toBe("Lax");
    await expect(
        page.getByRole("heading", { name: "HR Dashboard", exact: true }),
    ).toBeVisible();
    await noPageOverflow(page);
    await expect(
        page.getByRole("region", { name: "Primary HR statistics" }).locator("[data-metric]"),
    ).toHaveCount(4);
    await expect(
        page.getByRole("region", { name: "Today's Attendance" }).locator("[data-metric]"),
    ).toHaveCount(3);
    await expect(
        page.getByRole("link", { name: "View Attendance Monitoring", exact: true }),
    ).toHaveAttribute("href", new RegExp("/hr/attendance[?]from="));
    await page.screenshot({
        path: "test-results/screenshots/dashboard-desktop.png",
        fullPage: true,
    });
    for (const path of [
        "employees",
        "attendance",
        "branches",
        "leave",
        "reports",
        "notifications",
        "settings",
        "profile",
    ]) {
        const response = await page.goto("/" + path);
        expect(response.status()).toBe(200);
        await noPageOverflow(page);
    }
    await page.goto("/employee/dashboard");
    await expect(
        page.getByRole("button", { name: "Time In", exact: true }),
    ).toBeDisabled();
    await expect(
        page.getByRole("button", { name: "Time Out", exact: true }),
    ).toBeDisabled();
    await page.getByRole("button", { name: "Sign out", exact: true }).click();
    await expect(page.getByRole("dialog")).toBeVisible();
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Yes, Log Out", exact: true })
        .click();
    await page.goto("/dashboard");
    await expect(page).toHaveURL(/\/login$/);
    expect(errors).toEqual([]);
});

test("login and workspace fit mobile/tablet widths and drawer supports Escape", async ({
    page,
}) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    const widths = [320, 360, 375, 390, 430, 768];
    await page.goto("/login");
    for (const width of widths) {
        await page.setViewportSize({ width, height: 900 });
        await noPageOverflow(page);
    }
    await signIn(page);
    for (const width of widths) {
        await page.setViewportSize({ width, height: 900 });
        await noPageOverflow(page);
        await page.screenshot({
            path: `test-results/screenshots/workforce-dashboard-${width}.png`,
            fullPage: true,
        });
        const toggle = page.getByRole("button", {
            name: "Toggle navigation",
            includeHidden: true,
        });
        await toggle.click();
        await expect(toggle).toHaveAttribute("aria-expanded", "true");
        const drawer = page.getByRole("dialog", { name: "Workspace navigation" });
        await expect(drawer).toBeVisible();
        await expect(drawer).toHaveAttribute("aria-modal", "true");
        await expect(page.locator(".main-shell")).toHaveJSProperty("inert", true);
        await expect(drawer.getByRole("button", { name: "Close navigation" })).toBeFocused();
        await page.keyboard.press("Escape");
        await expect(toggle).toHaveAttribute("aria-expanded", "false");
        await expect(toggle).toBeFocused();
    }
    for (const path of [
        "employees",
        "attendance",
        "branches",
        "leave",
        "reports",
        "notifications",
        "settings",
        "profile",
        "employee/dashboard",
    ]) {
        await page.goto("/" + path);
        for (const width of widths) {
            await page.setViewportSize({ width, height: 900 });
            await noPageOverflow(page);
        }
    }
    await page.setViewportSize({ width: 390, height: 900 });
    await page.screenshot({
        path: "test-results/screenshots/employee-mobile.png",
        fullPage: true,
    });
    expect(errors).toEqual([]);
});

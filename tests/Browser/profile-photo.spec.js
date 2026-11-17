import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";
function fixture(...args) {
    const output = execFileSync(
        php,
        ["tests/Browser/fixture.php", ...args.map(String)],
        { encoding: "utf8" },
    );
    return output.trim() ? JSON.parse(output) : null;
}
async function login(page, user) {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(user.email);
    await page.getByLabel("Password", { exact: true }).fill(user.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/dashboard$/);
}
test("profile preview, save, cross-session avatars, replacement, removal and responsive layout", async ({
    browser,
}) => {
    test.setTimeout(120000);
    const hr = fixture("create"),
        employee = fixture("attendance", hr.id);
    const a = await browser.newContext(),
        b = await browser.newContext();
    try {
        const page = await a.newPage(),
            dashboard = await b.newPage();
        const errors = [];
        page.on("pageerror", (e) => errors.push(e.message));
        await login(page, employee);
        await login(dashboard, employee);
        await dashboard.goto("/employee/dashboard");
        await expect(dashboard.locator("[data-sync-status]")).toHaveText(
            "Live",
            { timeout: 20000 },
        );
        await page.goto("/employee/profile");
        let navigations = 0;
        page.on("framenavigated", (frame) => {
            if (frame === page.mainFrame()) navigations++;
        });
        await page.evaluate(() =>
            window.workspaceEcho?.connector.pusher.disconnect(),
        );
        await page
            .locator("input[type=file]")
            .setInputFiles("resources/images/limcoma logo.jpg");
        await expect(
            page.getByAltText("Selected profile photo preview"),
        ).toBeVisible();
        await expect(
            page.locator(".topbar .user-trigger .avatar img"),
        ).toBeHidden();
        await page.getByRole("button", { name: "Cancel", exact: true }).click();
        await expect(
            page.getByAltText("Selected profile photo preview"),
        ).toBeHidden();
        await page
            .locator("input[type=file]")
            .setInputFiles("resources/images/limcoma logo.jpg");
        await page
            .getByRole("button", { name: "Save Photo", exact: true })
            .click();
        await expect(
            page.locator(".topbar .user-trigger .avatar img"),
        ).toBeVisible();
        await page.getByRole("button", { name: /Account menu for/ }).click();
        await expect(page.locator("#profile-menu .avatar img")).toBeVisible();
        await page.getByRole("button", { name: /Account menu for/ }).click();
        const first = await page
            .locator(".topbar .user-trigger .avatar img")
            .getAttribute("src");
        expect((await page.request.get(first)).status()).toBe(200);
        await expect(
            dashboard.locator(".topbar .user-trigger .avatar img"),
        ).toBeVisible({
            timeout: 20000,
        });
        await expect(dashboard.locator("main .avatar img")).toBeVisible({
            timeout: 20000,
        });
        await page
            .locator("input[type=file]")
            .setInputFiles("resources/images/limcoma logo.jpg");
        await page
            .getByRole("button", { name: "Save Photo", exact: true })
            .click();
        await expect(
            page.locator(".topbar .user-trigger .avatar img"),
        ).not.toHaveAttribute("src", first);
        for (const width of [320, 390, 768, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            await expect
                .poll(() =>
                    page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                )
                .toBe(true);
        }
        await page
            .getByRole("button", { name: "Remove Photo", exact: true })
            .click();
        await page.locator(".swal2-confirm").click();
        await expect(
            page.locator(".topbar .user-trigger .avatar img"),
        ).toBeHidden();
        await expect(
            dashboard.locator(".topbar .user-trigger .avatar img"),
        ).toBeHidden({
            timeout: 20000,
        });
        expect(navigations).toBe(0);
        expect(errors).toEqual([]);
    } finally {
        await a.close();
        await b.close();
        fixture("cleanup", hr.id);
    }
});

import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
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
test("HR and assigned supervisor profiles show employee avatars independently and remain responsive", async ({
    browser,
}) => {
    test.setTimeout(120000);
    const hr = fixture("create");
    const contexts = [];
    try {
        const employee = fixture("attendance", hr.id);
        const supervisor = fixture("phase5", hr.id);
        for (let i = 0; i < 3; i++) contexts.push(await browser.newContext());
        const [admin, owner, manager] = await Promise.all(
            contexts.map((c) => c.newPage()),
        );
        const errors = [];
        for (const page of [admin, owner, manager])
            page.setDefaultTimeout(15000);
        for (const page of [admin, owner, manager])
            page.on("pageerror", (e) => errors.push(e.message));
        await login(admin, hr);
        await admin.goto("/hr/supervisors/" + supervisor.id);
        if (admin.url().includes("confirm-password")) {
            await admin
                .getByLabel("Password", { exact: true })
                .fill(hr.password);
            await admin
                .getByRole("button", { name: "Confirm password", exact: true })
                .click();
        }
        await admin
            .getByLabel("Employee", { exact: true })
            .selectOption(String(supervisor.employee_id));
        await admin
            .getByRole("button", { name: "Save assignment", exact: true })
            .click();
        await admin.locator(".swal2-confirm").click();
        await expect(admin.locator("table")).toContainText(
            "Synthetic Employee",
        );
        await login(owner, employee);
        await owner.goto("/employee/profile");
        await owner
            .locator("input[type=file]")
            .setInputFiles("resources/images/limcoma logo.jpg");
        await owner
            .getByRole("button", { name: "Save Photo", exact: true })
            .click();
        await expect(
            owner.locator(".topbar .user-trigger .avatar img"),
        ).toBeVisible();
        await login(manager, supervisor);
        await manager
            .getByRole("link", { name: "View Profile", exact: true })
            .click();
        await expect(
            manager.getByText("Read-only Employee Profile", { exact: true }),
        ).toBeVisible();
        const photo = manager.locator("[data-sync-region] .avatar img");
        await expect(photo).toBeVisible();
        await expect
            .poll(() => photo.evaluate((e) => e.complete && e.naturalWidth > 0))
            .toBe(true);
        await expect(
            manager.locator(".topbar .user-trigger .avatar img"),
        ).toBeHidden();
        await expect(
            manager.getByText("Change Photo", { exact: true }),
        ).toHaveCount(0);
        await admin.goto("/hr/employees/" + supervisor.employee_id);
        await expect(
            admin.locator("[data-sync-region] .avatar img"),
        ).toBeVisible();
        await expect(
            admin.getByRole("link", { name: "Edit employee", exact: true }),
        ).toBeVisible();
        for (const width of [1440, 768, 390]) {
            await manager.setViewportSize({ width, height: 900 });
            await expect
                .poll(() =>
                    manager.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                )
                .toBe(true);
            await expect(photo).toBeVisible();
            await manager.screenshot({
                path: "test-results/employee-profile-" + width + ".png",
                fullPage: true,
            });
        }
        // Exercise fallback updates in the viewer without reloading the profile.
        await manager.evaluate(() =>
            window.workspaceEcho?.connector.pusher.disconnect(),
        );
        await owner
            .getByRole("button", { name: "Remove Photo", exact: true })
            .click();
        await owner.locator(".swal2-confirm").click();
        await expect(photo).toHaveCount(0, { timeout: 35000 });
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

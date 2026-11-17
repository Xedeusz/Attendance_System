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
    await expect(page).toHaveURL(/dashboard$|supervisor\/leave$/);
}
test("HR links a department without an employee profile and the supervisor workspace updates live", async ({
    browser,
}) => {
    test.setTimeout(120000);
    const hr = fixture("create");
    const employee = fixture("attendance", hr.id);
    const supervisor = fixture("phase5", hr.id);
    const a = await browser.newContext(),
        b = await browser.newContext();
    try {
        const admin = await a.newPage(),
            owner = await b.newPage();
        await login(admin, hr);
        await admin.goto("/hr/supervisors/" + supervisor.id + "/edit");
        if (admin.url().includes("confirm-password")) {
            await admin
                .getByLabel("Password", { exact: true })
                .fill(hr.password);
            await admin
                .getByRole("button", { name: "Confirm password", exact: true })
                .click();
        }
        const department = admin.getByLabel("Assigned Department", {
            exact: true,
        });
        const id = await department.inputValue();
        const name = await department.locator("option:checked").textContent();
        await department.selectOption("");
        await admin
            .getByRole("button", { name: "Save Supervisor", exact: true })
            .click();
        await expect(
            admin.getByText(/Current Department: Not assigned/),
        ).toBeVisible();
        await expect(
            admin.getByRole("button", { name: "Save assignment", exact: true }),
        ).toBeDisabled();
        await login(owner, supervisor);
        await expect(
            owner.getByText("Department: Not assigned", { exact: true }),
        ).toBeVisible();
        await expect(owner.locator("[data-sync-status]")).toHaveText("Live", {
            timeout: 20000,
        });
        await admin.getByLabel("Department", { exact: true }).selectOption(id);
        await admin
            .getByRole("button", {
                name: "Save Department Assignment",
                exact: true,
            })
            .click();
        await expect(
            admin.getByText("Current Department: " + name, { exact: true }),
        ).toBeVisible();
        await expect(
            owner.getByText("Department: " + name, { exact: true }),
        ).toBeVisible({ timeout: 20000 });
        await expect(
            admin
                .getByLabel("Employee", { exact: true })
                .locator('option[value="' + supervisor.employee_id + '"]'),
        ).toHaveCount(1);
        for (const width of [320, 390, 768]) {
            await admin.setViewportSize({ width, height: 1000 });
            await expect
                .poll(() =>
                    admin.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                )
                .toBe(true);
        }
        await admin.goto(
            "/hr/supervisors?q=" +
                encodeURIComponent("Synthetic Leave Supervisor"),
        );
        await expect(admin.locator("main")).toContainText(name);
        await expect(admin.locator("main")).not.toContainText(
            "No linked employee profile",
        );
    } finally {
        await a.close();
        await b.close();
        fixture("cleanup", hr.id);
    }
});

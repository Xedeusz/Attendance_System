import { expect, test } from "@playwright/test";
import { execFileSync } from "node:child_process";

const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";

test("HR can filter, inspect, and export attendance on desktop and mobile", async ({
    page,
}) => {
    const fixture = (command, ...args) =>
        execFileSync(php, ["tests/Browser/fixture.php", command, ...args], {
            encoding: "utf8",
        });
    const hr = JSON.parse(fixture("create"));

    try {
        fixture("attendance", String(hr.id));
        await page.goto("/login");
        await page.getByLabel("Email address").fill(hr.email);
        await page.getByLabel("Password", { exact: true }).fill(hr.password);
        await page
            .getByRole("button", { name: "Sign in", exact: true })
            .click();
        await expect(page).toHaveURL(/dashboard$/);
        await page.getByRole("link", { name: "Attendance Reports" }).click();
        await expect(
            page.getByRole("heading", { name: "Attendance Reports" }),
        ).toBeVisible();
        await page.getByLabel("Employee", { exact: true }).selectOption({
            label: "Synthetic Employee · " + hr.prefix + "-EMP",
        });
        await page.getByRole("button", { name: "Apply Filters" }).click();
        await expect(page).toHaveURL(/employee_id=/);

        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 950 });
            await expect(page.getByRole("table")).toBeVisible();
            await expect
                .poll(
                    () =>
                        page.evaluate(
                            () => document.documentElement.scrollWidth,
                        ),
                    { message: "Page width at a " + width + "px viewport" },
                )
                .toBeLessThanOrEqual(width);
        }

        await page
            .getByRole("link", { name: /View details for Synthetic Employee/ })
            .first()
            .click();
        await expect(
            page.getByRole("region", { name: "Daily attendance details" }),
        ).toContainText(hr.prefix + "-EMP");
        await page
            .getByRole("link", { name: "Back to Attendance Reports" })
            .click();
        await expect(page).toHaveURL(/employee_id=/);
        const downloadPromise = page.waitForEvent("download");
        await page.getByRole("link", { name: "Export CSV" }).click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toMatch(
            /^LIMCOMA_Attendance_Report_\d{4}-\d{2}-\d{2}_to_\d{4}-\d{2}-\d{2}\.csv$/,
        );
    } finally {
        fixture("cleanup", String(hr.id));
    }
});

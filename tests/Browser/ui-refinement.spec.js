import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { mkdirSync } from "node:fs";

const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";
const phase = process.env.UI_REVIEW_PHASE || "after";
const screenshotRoot = process.env.UI_SCREENSHOT_DIR || `.tools/ui-review/${phase}`;
let hr, employee;

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
    mkdirSync(screenshotRoot, { recursive: true });
});
test.afterAll(() => {
    if (hr)
        execFileSync(php, [
            "tests/Browser/fixture.php",
            "cleanup",
            String(hr.id),
        ]);
});

async function login(page, account) {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(account.email);
    await page.getByLabel("Password", { exact: true }).fill(account.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/dashboard$/);
}
async function fits(page) {
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBe(true);
}

test("representative HR and Employee screens retain accessible workflows across viewport sizes", async ({
    browser,
}) => {
    test.setTimeout(180000);
    const context = await browser.newContext();
    const page = await context.newPage();
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto("/login");
        await fits(page);
        await page.screenshot({
            path: `${screenshotRoot}/login-${width}.png`,
            fullPage: true,
        });
    }
    await login(page, hr);
    const hrPaths = [
        "hr/dashboard",
        "hr/employees",
        "hr/employees/create",
        "hr/branches",
        "hr/branches/create",
        "hr/departments",
        "hr/attendance",
        "notifications",
        "settings",
        "leave",
        "reports",
    ];
    for (const path of hrPaths) {
        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 1000 });
            const response = await page.goto(`/${path}`);
            expect(response.status()).toBe(200);
            await fits(page);
            await page.screenshot({
                path: `${screenshotRoot}/${path.replaceAll("/", "-")}-${width}.png`,
                fullPage: true,
            });
        }
    }
    for (const path of [
        "hr/employees",
        "hr/employees/create",
        "hr/attendance",
        "settings",
    ]) {
        await page.goto(`/${path}`);
        for (const width of [320, 360, 375, 390, 430, 768, 1024, 1280, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            await fits(page);
        }
    }
    if (phase === "after") {
        await page.goto("/hr/employees/create");
        const field = page.getByLabel("First name", { exact: true });
        await expect(field).toHaveJSProperty("required", true);
        await expect(
            field
                .locator("xpath=ancestor::div[contains(@class,'form-group')]")
                .locator(".field-required"),
        ).toHaveText("Required");
        await page.goto("/hr/attendance");
        await expect(
            page.locator("#workspace-navigation [aria-current='page']"),
        ).toHaveCount(1);
        await page.goto("/hr/employees");
        await page.getByText("More filters", { exact: true }).click();
        await page
            .getByLabel("Employment", { exact: true })
            .selectOption("active");
        await page
            .getByLabel("Search employees", { exact: true })
            .fill(hr.prefix);
        await page
            .getByRole("button", { name: "Apply filters", exact: true })
            .click();
        await expect(page).toHaveURL(/employment_status=active/);
        await expect(
            page.getByLabel("Employment", { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole("row").filter({ hasText: hr.prefix + "-EMP" }),
        ).toHaveCount(1);
        await page.setViewportSize({ width: 320, height: 1000 });
        await page.goto("/hr/employees?q=" + hr.prefix + "-no-results");
        await expect(
            page.getByText("No employees match this view", { exact: true }),
        ).toBeVisible();
        await fits(page);
        await expect(page.locator("#workspace-navigation")).toHaveJSProperty(
            "inert",
            true,
        );
        await page
            .getByRole("button", { name: "Toggle navigation", exact: true })
            .click();
        await expect(page.locator("#workspace-navigation")).toHaveJSProperty(
            "inert",
            false,
        );
        await page.keyboard.press("Escape");
    }
    await context.close();
    const employeeContext = await browser.newContext();
    const employeePage = await employeeContext.newPage();
    employeePage.on("pageerror", (error) => errors.push(error.message));
    await login(employeePage, employee);
    for (const path of [
        "employee/dashboard",
        "employee/attendance",
        "employee/attendance/history",
        "employee/profile",
        "settings",
        "notifications",
        "leave",
    ]) {
        for (const width of [1440, 390]) {
            await employeePage.setViewportSize({ width, height: 1000 });
            const response = await employeePage.goto(`/${path}`);
            expect(response.status()).toBe(200);
            await fits(employeePage);
            if (
                phase === "after" &&
                path === "employee/dashboard" &&
                width < 1024
            ) {
                const action = await employeePage
                    .getByRole("link", { name: "Open attendance", exact: true })
                    .boundingBox();
                const identity = await employeePage
                    .getByRole("region", { name: "Employee identification" })
                    .boundingBox();
                const attendance = await employeePage
                    .getByRole("region", { name: "My Attendance" })
                    .boundingBox();
                expect(identity.y).toBeGreaterThanOrEqual(
                    attendance.y + attendance.height,
                );
                expect(action.y).toBeGreaterThan(attendance.y);
                expect(action.y + action.height).toBeLessThan(identity.y);
            }
            await employeePage.screenshot({
                path: `${screenshotRoot}/${path.replaceAll("/", "-")}-employee-${width}.png`,
                fullPage: true,
            });
        }
    }
    for (const path of [
        "employee/dashboard",
        "employee/attendance",
        "employee/attendance/history",
    ]) {
        await employeePage.goto(`/${path}`);
        for (const width of [320, 360, 375, 430, 768, 1024, 1280, 1440]) {
            await employeePage.setViewportSize({ width, height: 1000 });
            await fits(employeePage);
        }
    }
    await employeeContext.close();
    expect(errors).toEqual([]);
});

test("attendance filters align and submit at desktop, tablet, and mobile widths", async ({
    page,
}) => {
    await login(page, hr);
    await page.goto("/hr/attendance");
    const search = page.getByLabel("Employee search");
    const dateTo = page.getByLabel("Work date to");
    const submit = page.getByRole("button", { name: "Apply filters" });
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await fits(page);
        const [field, button] = await Promise.all([
            dateTo.boundingBox(),
            submit.boundingBox(),
        ]);
        if (width === 1440) {
            expect(button.x).toBeGreaterThan(field.x + field.width);
            expect(button.y).toBeCloseTo(field.y, 0);
            expect(button.height).toBeCloseTo(field.height, 0);
        } else {
            expect(button.y).toBeGreaterThan(field.y);
            if (width === 390) expect(button.width).toBeCloseTo(field.width, 0);
        }
    }
    await page.getByText("More filters", { exact: true }).click();
    await page.getByLabel("Session status").selectOption("completed");
    await search.fill("Synthetic");
    await search.press("Enter");
    await expect(page).toHaveURL(/q=Synthetic/);
    await expect(page.getByLabel("Session status")).toHaveValue("completed");
});

test("work calendar opens date details with the keyboard and filters the compact month", async ({
    page,
}) => {
    await login(page, employee);
    await page.goto("/employee/attendance/calendar?month=2026-11");
    const selectedDay = page.getByRole("link", {
        name: /Thursday, November 19, 2026/,
    });
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await fits(page);
        await selectedDay.press("Enter");
        const dialog = page.getByRole("dialog", { name: "Date details" });
        await expect(
            dialog.getByRole("heading", {
                name: "Thursday, November 19, 2026",
            }),
        ).toBeVisible();
        await expect(
            dialog.getByText("No work schedule configured"),
        ).toBeVisible();
        await page.keyboard.press("Escape");
        await expect(dialog).toBeHidden();
    }

    await page.getByText("Filters", { exact: true }).click();
    await page.getByRole("checkbox", { name: "Work schedule" }).uncheck();
    await expect(selectedDay.getByText("No schedule")).toBeHidden();
    const refresh = page.waitForResponse((response) => response.request().headers()["x-workspace-sync"] === "1");
    await page.evaluate(() => window.dispatchEvent(new Event("online")));
    await (await refresh).finished();
    await expect(page.getByRole("checkbox", { name: "Work schedule" })).not.toBeChecked();
    await page.getByRole("button", { name: "Reset filters" }).click();
    await expect(
        page.getByRole("checkbox", { name: "Work schedule" }),
    ).toBeChecked();
    await page.getByRole("link", { name: "Next month" }).click();
    await expect(
        page.getByRole("heading", { name: "December 2026", exact: true }),
    ).toBeVisible();
});

test("work policy pages fit desktop and mobile and preview paid and unpaid breaks", async ({
    browser,
}) => {
    test.setTimeout(120000);
    const hrContext = await browser.newContext();
    const hrPage = await hrContext.newPage();
    await login(hrPage, hr);
    await hrPage.goto("/hr/attendance/schedules/create");
    await hrPage.getByLabel("Work start", { exact: true }).fill("08:00");
    await hrPage.getByLabel("Work end", { exact: true }).fill("16:00");
    await hrPage.getByLabel("Break start", { exact: true }).fill("12:00");
    await hrPage.getByLabel("Break end", { exact: true }).fill("13:00");
    const preview = hrPage.getByLabel("Schedule duration preview");
    await expect(preview.locator("dd")).toHaveText(["480", "60", "60", "420"]);
    await hrPage.getByLabel("Break classification").selectOption("1");
    await expect(preview.locator("dd")).toHaveText(["480", "60", "0", "480"]);

    const employeeContext = await browser.newContext();
    const employeePage = await employeeContext.newPage();
    await login(employeePage, employee);
    for (const width of [1440, 390]) {
        await hrPage.setViewportSize({ width, height: 1000 });
        await fits(hrPage);
        await employeePage.setViewportSize({ width, height: 1000 });
        for (const [page, path, heading] of [
            [hrPage, "/hr/attendance/schedules", "Schedules & Policies"],
            [
                hrPage,
                "/hr/attendance/holidays?view=month",
                "Holiday Management",
            ],
            [employeePage, "/employee/attendance/calendar", "My Work Calendar"],
        ]) {
            expect((await page.goto(path)).status()).toBe(200);
            await expect(
                page.getByRole("heading", { name: heading, exact: true }),
            ).toBeVisible();
            await fits(page);
        }
        await hrPage.goto("/hr/attendance/schedules/create");
    }

    await employeeContext.close();
    await hrContext.close();
});

import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { mkdirSync } from "node:fs";

const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";
const phase = process.env.UI_REVIEW_PHASE || "after";
const screenshotRoot = `.tools/ui-review/${phase}`;
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
    for (const width of [320, 360, 375, 390, 430, 768, 1024, 1280, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const path of [
            "hr/employees",
            "hr/employees/create",
            "hr/attendance",
            "settings",
        ]) {
            await page.goto(`/${path}`);
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
                const employmentHeading = await employeePage
                    .getByRole("heading", {
                        name: "My employment details",
                        exact: true,
                    })
                    .boundingBox();
                expect(action.y).toBeLessThan(employmentHeading.y);
            }
            await employeePage.screenshot({
                path: `${screenshotRoot}/${path.replaceAll("/", "-")}-employee-${width}.png`,
                fullPage: true,
            });
        }
    }
    for (const width of [320, 360, 375, 430, 768, 1024, 1280, 1440]) {
        await employeePage.setViewportSize({ width, height: 1000 });
        for (const path of [
            "employee/dashboard",
            "employee/attendance",
            "employee/attendance/history",
        ]) {
            await employeePage.goto(`/${path}`);
            await fits(employeePage);
        }
    }
    await employeeContext.close();
    expect(errors).toEqual([]);
});

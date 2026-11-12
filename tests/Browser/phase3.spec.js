import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { mkdirSync } from "node:fs";

test.use({ screenshot: "off", trace: "off" });
let hr;
const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";
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

test("HR registers real master data, invites employee, activation and session revocation work on MariaDB", async ({
    page,
    browser,
}) => {
    test.setTimeout(180_000);
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.goto("/login");
    await page.getByLabel("Email address").fill(hr.email);
    await page.getByLabel("Password", { exact: true }).fill(hr.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/\/hr\/dashboard$/);

    await page.goto("/hr/branches/create");
    for (const [label, value] of Object.entries({
        "Branch code": hr.prefix + "-BRANCH",
        "Branch name": hr.prefix + " Synthetic Office",
        "Street address": "Synthetic test street",
        City: "Test city",
        Province: "Test province",
    }))
        await page.getByLabel(label, { exact: true }).fill(value);
    await page.getByRole("button", { name: "Save branch" }).click();
    await expect(
        page.getByRole("heading", {
            name: hr.prefix + " Synthetic Office",
            exact: true,
        }),
    ).toBeVisible();
    await page.goto("/hr/departments/create");
    await page.getByLabel("Department code").fill(hr.prefix + "-DEPT");
    await page
        .getByLabel("Department name")
        .fill(hr.prefix + " Synthetic Department");
    await page.getByRole("button", { name: "Save department" }).click();
    await expect(page).toHaveURL(/\/hr\/departments$/);

    const email = hr.prefix.toLowerCase() + "-employee@example.test";
    await page.goto("/hr/employees/create");
    for (const [label, value] of Object.entries({
        "Employee number": hr.prefix + "-EMP",
        "Work email": email,
        "First name": "Synthetic",
        "Last name": "Browser Employee",
        "Position title": "QA fixture",
        "Date hired": "2026-01-01",
    }))
        await page.getByLabel(label, { exact: true }).fill(value);
    await page
        .getByLabel("Assigned primary branch")
        .selectOption({ label: hr.prefix + " Synthetic Office" });
    await page
        .getByLabel("Company-wide department")
        .selectOption({ label: hr.prefix + " Synthetic Department" });
    await page
        .getByRole("button", { name: "Save employee & issue invitation" })
        .click();
    await expect(
        page.getByRole("heading", {
            name: "Synthetic Browser Employee",
            exact: true,
        }),
    ).toBeVisible();
    const employeePath = new URL(page.url()).pathname;
    await expect(
        page.getByText("no email was delivered externally", { exact: false }),
    ).toBeVisible();

    await page.goto("/hr/local-mail");
    await expect(
        page.getByRole("heading", { name: "Confirm it's you" }),
    ).toBeVisible();
    await page.getByLabel("Password", { exact: true }).fill(hr.password);
    await page
        .getByRole("button", { name: "Confirm password", exact: true })
        .click();
    await expect(
        page.getByRole("heading", { name: "Local Mail Inbox", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("row")
        .filter({ hasText: email })
        .getByRole("link", { name: "Open preview" })
        .click();
    const link = await page
        .getByRole("link", { name: "Open activation link" })
        .getAttribute("href");
    const token = link.split("#")[1];
    const context = await browser.newContext({
        viewport: { width: 390, height: 900 },
    });
    const employeePage = await context.newPage();
    employeePage.on("pageerror", (error) => errors.push(error.message));
    await employeePage.addInitScript((code) => {
        if (location.pathname === "/activate-account") location.hash = code;
    }, token);
    await employeePage.goto("http://127.0.0.1:8000/activate-account");
    await expect(
        employeePage.getByRole("button", {
            name: "Activate account",
            exact: true,
        }),
    ).toBeEnabled();
    const employeePassword = "Browser!PrivatePassword123";
    await employeePage
        .getByLabel("New password", { exact: true })
        .fill(employeePassword);
    await employeePage
        .getByLabel("Confirm password", { exact: true })
        .fill(employeePassword);
    await employeePage
        .getByRole("button", { name: "Activate account", exact: true })
        .click();
    await expect(employeePage).toHaveURL(/\/login$/);
    await employeePage.getByLabel("Email address").fill(email);
    await employeePage
        .getByLabel("Password", { exact: true })
        .fill(employeePassword);
    await employeePage
        .getByRole("button", { name: "Sign in", exact: true })
        .click();
    await expect(employeePage).toHaveURL(/\/employee\/dashboard$/);
    const forbidden = await employeePage.request.get(
        "http://127.0.0.1:8000/hr/dashboard",
    );
    expect(forbidden.status()).toBe(403);
    await employeePage.goto("http://127.0.0.1:8000/employee/profile");
    await employeePage
        .getByLabel("Contact number", { exact: true })
        .fill("+639171112233");
    await employeePage
        .getByRole("button", { name: "Save contact number" })
        .click();
    await expect(
        employeePage.getByLabel("Contact number", { exact: true }),
    ).toHaveValue("+639171112233");
    await employeePage.screenshot({
        path: "test-results/screenshots/phase3-employee-mobile.png",
        fullPage: true,
    });

    await page.goto(employeePath);
    await page.getByRole("checkbox").check();
    await page
        .getByRole("button", { name: "Deactivate account", exact: true })
        .click();
    await expect(page.getByText("Deactivated", { exact: true })).toBeVisible();
    await employeePage.goto("http://127.0.0.1:8000/employee/dashboard");
    await expect(employeePage).toHaveURL(/\/login$/);
    await page.getByRole("checkbox").check();
    await page
        .getByRole("button", { name: "Reactivate account", exact: true })
        .click();
    await expect(
        page.getByText("Active", { exact: true }).first(),
    ).toBeVisible();
    await context.close();

    for (const width of [320, 360, 375, 390, 430, 768, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const path of [
            "/hr/dashboard",
            "/hr/employees",
            "/hr/employees/create",
            employeePath,
            "/hr/branches",
            "/hr/branches/create",
            "/hr/departments",
            "/employee/profile",
            "/settings",
        ]) {
            await page.goto(path);
            expect(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
                `Page overflow at ${width}: ${path}`,
            ).toBe(true);
        }
    }
    await page.goto("/hr/dashboard");
    await page.screenshot({
        path: "test-results/screenshots/phase3-hr-dashboard.png",
        fullPage: true,
    });
    expect(errors).toEqual([]);
});

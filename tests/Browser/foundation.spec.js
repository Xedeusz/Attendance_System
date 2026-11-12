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

test("real login, session cookie flags, CSRF, navigation, disabled actions and logout", async ({
    page,
}) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
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
    const csrf = await page.request.post("/login", {
        form: { email: account.email, password: account.password },
    });
    expect([403, 419]).toContain(csrf.status());
    await page.getByLabel("Email address").fill(account.email);
    await page.getByLabel("Password", { exact: true }).fill("invalid-password");
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page.getByRole("alert")).toBeVisible();
    await signIn(page);
    const cookies = await page.context().cookies();
    const session = cookies.find((cookie) => cookie.name === "ebas_session");
    expect(session.httpOnly).toBe(true);
    expect(session.sameSite).toBe("Lax");
    await expect(
        page.getByRole("heading", { name: "Company Overview", exact: true }),
    ).toBeVisible();
    await noPageOverflow(page);
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
        .getByRole("button", { name: "Sign out", exact: true })
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
    for (const width of [320, 360, 375, 390, 430, 768]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto("/login");
        await noPageOverflow(page);
    }
    await signIn(page);
    for (const width of [320, 360, 375, 390, 430, 768]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto("/dashboard");
        await noPageOverflow(page);
        const toggle = page.getByRole("button", {
            name: "Toggle navigation",
            includeHidden: true,
        });
        await toggle.click();
        await expect(toggle).toHaveAttribute("aria-expanded", "true");
        await expect(page.locator("#workspace-navigation")).toHaveClass(
            /is-open/,
        );
        await page.keyboard.press("Escape");
        await expect(toggle).toHaveAttribute("aria-expanded", "false");
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
            await noPageOverflow(page);
        }
    }
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto("/employee/dashboard");
    await page.screenshot({
        path: "test-results/screenshots/employee-mobile.png",
        fullPage: true,
    });
    expect(errors).toEqual([]);
});

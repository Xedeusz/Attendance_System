import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { mkdirSync } from "node:fs";

test("post-login notice remains readable and requires a CSRF-protected explicit acknowledgement", async ({
    page,
    context,
}) => {
    test.skip(
        process.env.PRIVACY_NOTICES_ENABLED !== "true",
        "Requires an isolated privacy fixture server.",
    );
    const php = process.env.PHP_BINARY || "C:/xampp/php/php.exe";
    const fixture = (...args) =>
        execFileSync(php, ["tests/Browser/fixture.php", ...args], {
            encoding: "utf8",
        });
    const account = JSON.parse(fixture("create"));
    try {
        const notice = JSON.parse(fixture("privacy", String(account.id)));
        await page.goto("/login");
        await page.getByLabel("Email address").fill(account.email);
        await page
            .getByLabel("Password", { exact: true })
            .fill(account.password);
        await page
            .getByRole("button", { name: "Sign in", exact: true })
            .click();
        await expect(page).toHaveURL(new RegExp("/privacy$"));

        const checkbox = page.getByRole("checkbox", { name: /^I acknowledge/ });
        await expect(checkbox).not.toBeChecked();
        await expect(
            page.getByRole("button", { name: "Sign out" }),
        ).toBeVisible();
        await page
            .getByRole("link", { name: "Read Full Privacy Notice" })
            .click();
        await expect(
            page.getByRole("heading", {
                name: "Information collected",
                exact: true,
            }),
        ).toBeVisible();
        await page
            .getByRole("link", { name: "Back to Privacy & Data Protection" })
            .click();

        const screenshotRoot = ".tools/privacy-review";
        mkdirSync(screenshotRoot, { recursive: true });
        for (const colorScheme of ["light", "dark"]) {
            await page.emulateMedia({ colorScheme });
            await expect(
                page.getByRole("link", { name: "Read Full Privacy Notice" }),
            ).toHaveCSS(
                "background-color",
                colorScheme === "dark"
                    ? "rgb(30, 41, 59)"
                    : "rgb(255, 255, 255)",
            );
            for (const width of [360, 390, 768, 1024, 1440]) {
                await page.setViewportSize({ width, height: 900 });
                expect(
                    await page.evaluate(
                        () => document.documentElement.scrollWidth,
                    ),
                ).toBeLessThanOrEqual(width);
                await expect(checkbox).toBeVisible();
                if (width === 390 || width === 1440) {
                    await page.screenshot({
                        path:
                            screenshotRoot +
                            "/" +
                            colorScheme +
                            "-" +
                            width +
                            ".png",
                        fullPage: true,
                        animations: "disabled",
                    });
                }
            }
        }

        const invalid = await context.request.post("/privacy/acknowledgement", {
            form: { notice_id: notice.id, acknowledged: "1" },
        });
        expect(invalid.status()).toBe(419);
        await checkbox.focus();
        await page.keyboard.press("Space");
        await expect(checkbox).toBeChecked();
        await page
            .getByRole("button", { name: "Acknowledge & Continue" })
            .click();
        await expect(page).toHaveURL(new RegExp("/hr/dashboard$"));
        await page.goto("/privacy");
        await expect(
            page.getByText("Acknowledged", { exact: false }).first(),
        ).toBeVisible();
        await expect(checkbox).toHaveCount(0);
    } finally {
        fixture("cleanup", String(account.id));
    }
});

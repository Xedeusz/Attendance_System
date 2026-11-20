import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";
let hr;
test.beforeAll(() => {
    hr = JSON.parse(
        execFileSync(php, ["tests/Browser/fixture.php", "create"], {
            encoding: "utf8",
        }),
    );
    execFileSync(php, [
        "tests/Browser/fixture.php",
        "attendance",
        String(hr.id),
    ]);
});
test.afterAll(() => {
    if (hr)
        execFileSync(php, [
            "tests/Browser/fixture.php",
            "cleanup",
            String(hr.id),
        ]);
});
async function login(page) {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(hr.email);
    await page.getByLabel("Password", { exact: true }).fill(hr.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/dashboard$/);
}
async function expectTopRight(page, popup) {
    await expect
        .poll(async () => {
            const bounds = await popup.boundingBox();
            return bounds ? Math.round(bounds.y) : null;
        })
        .toBe(12);
    await expect
        .poll(async () => {
            const bounds = await popup.boundingBox();
            return bounds
                ? Math.round(
                      page.viewportSize().width - bounds.x - bounds.width,
                  )
                : null;
        })
        .toBe(12);
}

test("ordinary messages fit mobile widths and expire without buttons or hover pauses", async ({
    page,
}) => {
    await login(page);
    await page.mouse.move(10, 500);
    const toast = page.locator(".limcoma-compact-toast");
    const colors = {
        success: "rgb(8, 124, 104)",
        error: "rgb(220, 38, 38)",
        warning: "rgb(217, 119, 6)",
        info: "rgb(47, 27, 114)",
    };
    for (const [type, color] of Object.entries(colors)) {
        const started = Date.now();
        await page.evaluate((icon) => {
            window.notify[icon]("Synthetic notification feedback");
            window.notify[icon]("Synthetic notification feedback");
        }, type);
        await expect(toast).toHaveCount(1);
        await expectTopRight(page, toast);
        await expect(toast.locator(".swal2-icon")).toHaveCSS("color", color);
        await expect(toast.locator(".swal2-title")).toHaveCSS(
            "font-size",
            "12.5px",
        );
        await expect(toast.locator(".swal2-title")).toHaveCSS(
            "color",
            "rgb(30, 41, 59)",
        );
        await expect(toast.locator(".swal2-timer-progress-bar")).toBeHidden();
        await expect(toast.getByRole("button")).toHaveCount(0);
        await expect(toast).toHaveAttribute("role", "status");
        await expect(toast.locator(".swal2-success-ring")).toHaveCount(0);
        expect((await toast.locator(".swal2-icon").boundingBox()).width).toBe(
            18,
        );
        expect((await toast.locator(".swal2-icon").boundingBox()).height).toBe(
            18,
        );
        for (const width of [320, 360, 375, 390, 430, 1440]) {
            await page.setViewportSize({ width, height: 844 });
            const bounds = await toast.boundingBox();
            expect(bounds.width).toBeLessThanOrEqual(
                Math.min(330, width - 24) + 1,
            );
            expect(bounds.height).toBeLessThanOrEqual(66);
            expect(bounds.x).toBeGreaterThanOrEqual(0);
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(width);
            const iconBox = await toast.locator(".swal2-icon").boundingBox();
            const titleBox = await toast.locator(".swal2-title").boundingBox();
            expect(
                Math.abs(
                    iconBox.y +
                        iconBox.height / 2 -
                        titleBox.y -
                        titleBox.height / 2,
                ),
            ).toBeLessThan(2);
        }
        await page.screenshot({
            path: `test-results/screenshots/compact-toast-${type}.png`,
        });
        await toast.hover();
        await expect(toast).toBeHidden({ timeout: 4000 });
        expect(Date.now() - started).toBeGreaterThanOrEqual(2950);
        expect(Date.now() - started).toBeLessThan(4400);
        await page.mouse.move(10, 500);
    }
    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.evaluate(() => {
        window.notify.toast("info", "Automatically dismiss this feedback");
    });
    await expect(toast).toBeVisible();
    await expect(toast).toHaveCSS("animation-name", "none");
    await expect(toast).toBeHidden({ timeout: 4500 });
});
test("plain text, deduplication, danger confirmation, loading and responsive keyboard dialogs", async ({
    page,
}) => {
    await login(page);
    await page.evaluate(() => {
        window.notify.error("Test Error", "<img src=x onerror=alert(1)>");
        window.notify.error("Test Error", "<img src=x onerror=alert(1)>");
    });
    const toast = page.locator(".swal2-toast");
    const dialog = page.getByRole("dialog");
    await expect(toast).toContainText("<img src=x onerror=alert(1)>");
    await expect(page.locator(".swal2-container")).toHaveClass(/swal2-top-end/);
    await expect(
        toast.locator(".swal2-html-container img, .swal2-title img"),
    ).toHaveCount(0);
    await expect(toast).toHaveCount(1);
    await expect(dialog).toHaveCount(0);
    await expectTopRight(page, toast);
    await expect(toast).toHaveCSS("border-radius", "12px");
    expect((await toast.boundingBox()).width).toBeLessThanOrEqual(330);
    expect((await toast.locator(".swal2-icon").boundingBox()).width).toBe(18);
    await expect(page.locator(".swal2-container")).toHaveCSS(
        "background-color",
        "rgba(0, 0, 0, 0)",
    );
    await expect(toast).toBeHidden({ timeout: 4000 });
    await page.evaluate(() => {
        window.notify.success("Saved test", "Confirmed test feedback.");
    });
    await expect(toast.locator(".swal2-icon")).toHaveCSS(
        "color",
        "rgb(8, 124, 104)",
    );
    await expect(toast.getByRole("button")).toHaveCount(0);
    await expect(toast).toBeHidden({ timeout: 4000 });
    for (const width of [320, 360, 375, 390, 430]) {
        await page.setViewportSize({ width, height: 844 });
        await page.evaluate(() => {
            window.outcome = null;
            window.notify
                .destructive({
                    title: "Delete This Record?",
                    text: "Test presentation only.",
                    confirmText: "Yes, Delete",
                })
                .then((value) => (window.outcome = value));
        });
        await expect(dialog).toBeVisible();
        await expect(dialog).toHaveCSS("animation-name", "none");
        if (width === 320) {
            await page.evaluate(() => {
                window.notify.info("Earlier feedback");
                window.notify.warning("Latest deferred feedback");
            });
            await page.waitForTimeout(3300);
            await expect(dialog).toBeVisible();
            expect(await page.evaluate(() => window.outcome)).toBeNull();
        }
        await expectTopRight(page, dialog);
        await expect(page.locator(".swal2-container")).toHaveClass(
            /swal2-top-end/,
        );
        await expect(
            page.getByRole("button", { name: "Cancel", exact: true }),
        ).toBeFocused();
        await page
            .getByRole("button", { name: "Yes, Delete", exact: true })
            .hover();
        await expect(
            page.getByRole("button", { name: "Yes, Delete", exact: true }),
        ).toHaveCSS("background-color", "rgb(139, 27, 40)");
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.keyboard.press("Escape");
        await expect
            .poll(() => page.evaluate(() => window.outcome))
            .toBe(false);
        if (width === 320) {
            await expect(toast).toContainText("Latest deferred feedback");
            await page.evaluate(() => window.notify.close());
        }
    }
    await page.evaluate(() => {
        window.notify.loading("Submitting test...");
    });
    await expect(dialog).toContainText("Submitting test...");
    await expectTopRight(page, dialog);
    await page.evaluate(() => window.notify.close());
    await expect(dialog).toBeHidden();
    await page.evaluate(() => {
        window.notify.toast("success", "Saved test feedback");
    });
    await expect(page.locator(".swal2-toast")).toContainText(
        "Saved test feedback",
    );
    await page.evaluate(() => window.notify.close());
});
test("cancel preserves an employee account; confirming submits exactly once; logout cancel preserves session", async ({
    page,
}) => {
    await login(page);
    await page.goto("/hr/employees?q=" + encodeURIComponent(hr.prefix));
    await page
        .getByRole("row")
        .filter({ hasText: hr.prefix + "-EMP" })
        .getByRole("link")
        .first()
        .click();
    let changes = 0;
    page.on("request", (request) => {
        if (/\/hr\/employees\/\d+\/status$/.test(request.url())) changes++;
    });
    await page.getByRole("checkbox").check();
    await page
        .getByRole("button", { name: "Deactivate account", exact: true })
        .click();
    await page.getByRole("button", { name: "Cancel", exact: true }).click();
    expect(changes).toBe(0);
    await expect(
        page.getByRole("button", { name: "Deactivate account", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Deactivate account", exact: true })
        .click();
    await page
        .getByRole("button", { name: "Yes, Deactivate", exact: true })
        .click();
    await expect(page.getByText("Deactivated", { exact: true })).toBeVisible();
    expect(changes).toBe(1);
    await expect(page.locator(".swal2-toast")).toContainText(
        "Account status updated.",
    );
    await page.getByRole("button", { name: "Sign out", exact: true }).click();
    await page.getByRole("button", { name: "Cancel", exact: true }).click();
    await page.goto("/hr/dashboard");
    await expect(page).toHaveURL(/\/hr\/dashboard$/);
});

test("duplicate branch code preserves field errors and successful save flashes only once", async ({
    page,
}) => {
    await login(page);
    await page.goto("/hr/branches/create");
    for (const [label, value] of Object.entries({
        "Branch code": hr.prefix + "-BRANCH",
        "Branch name": "Synthetic Notification Branch",
        "Street address": "Synthetic test street",
        City: "Test city",
        Province: "Test province",
    }))
        await page.getByLabel(label, { exact: true }).fill(value);
    await page
        .getByLabel("Geofencing Radius (Meters)", { exact: true })
        .fill("100");
    const map = page.getByRole("region", { name: "Branch location map", exact: true });
    await expect(map).toHaveClass(/leaflet-container/);
    await map.click({ position: { x: 150, y: 170 } });
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await expect(page.locator(".swal2-toast")).toContainText(
        "Branch Code Already Exists",
    );
    await expect(page.locator(".form-errors")).toHaveCount(0);
    await expect(page.locator(".swal2-toast").getByRole("button")).toHaveCount(
        0,
    );
    await expect(page.locator("#branch_code-error")).toHaveText(
        "The branch code has already been taken.",
    );
    await expect(page.getByLabel("Branch name", { exact: true })).toHaveValue(
        "Synthetic Notification Branch",
    );
    await page
        .getByLabel("Branch code", { exact: true })
        .fill(hr.prefix + "-MAP");
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await expect(page.locator(".swal2-toast")).toContainText("Branch created.");
    await expect(
        page.getByText("Branch created.", { exact: true }),
    ).toHaveCount(1);
    await page.reload();
    await expect(page.locator(".swal2-toast")).toHaveCount(0);
    await page.getByRole("link", { name: "Edit branch", exact: true }).click();
    await expect(page.getByLabel("Branch status", { exact: true })).toHaveValue("active");
    await page
        .getByLabel("Branch status", { exact: true })
        .selectOption("inactive");
    await page
        .getByRole("checkbox", { name: /I confirm deactivation/ })
        .check();
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await expect(page.getByRole("dialog")).toContainText(
        "Deactivate This Record?",
    );
    await page
        .getByRole("button", { name: "Yes, Deactivate", exact: true })
        .click();
    await expect(page).toHaveURL(/\/hr\/branches\/\d+$/);
    await page.getByRole("link", { name: "Edit branch", exact: true }).click();
    await page
        .getByLabel("Branch name", { exact: true })
        .fill("Updated inactive branch");
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await expect(page).toHaveURL(/\/hr\/branches\/\d+$/);
    await expect(page.getByRole("dialog")).toHaveCount(0);
    await expect(
        page.getByRole("heading", {
            name: "Updated inactive branch",
            exact: true,
        }),
    ).toBeVisible();
});

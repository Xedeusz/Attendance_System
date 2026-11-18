import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { mkdirSync, writeFileSync } from "node:fs";
import { enrollSignature } from "./leave-signature.js";
const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";
let hr, employee, supervisor;
function fixture(...args) {
    return JSON.parse(
        execFileSync(php, ["tests/Browser/fixture.php", ...args.map(String)], {
            encoding: "utf8",
        }),
    );
}
test.beforeAll(() => {
    hr = fixture("create");
    employee = fixture("attendance", hr.id);
    supervisor = fixture("phase5", hr.id, "generic-other");
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
async function login(page, account) {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(account.email);
    await page.getByLabel("Password", { exact: true }).fill(account.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(
        account === supervisor ? /supervisor\/leave$/ : /dashboard$/,
    );
}
async function fits(page) {
    if (page.viewportSize().width < 1024) {
        await expect
            .poll(async () =>
                page
                    .locator(".sidebar")
                    .evaluate(
                        (element) => element.getBoundingClientRect().right,
                    ),
            )
            .toBeLessThanOrEqual(0);
    }
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBe(true);
}
async function confirmPassword(page) {
    if (page.url().includes("confirm-password")) {
        await page.getByLabel("Password", { exact: true }).fill(hr.password);
        await page
            .getByRole("button", { name: "Confirm password", exact: true })
            .click();
    }
}
test("employee submission, final Supervisor decision, private document and HR correction work on MariaDB", async ({
    browser,
}) => {
    test.setTimeout(180000);
    const contexts = await Promise.all([
        browser.newContext(),
        browser.newContext(),
        browser.newContext(),
    ]);
    const [hrPage, employeePage, supPage] = await Promise.all(
        contexts.map((context) => context.newPage()),
    );
    const errors = [];
    for (const page of [hrPage, employeePage, supPage]) {
        page.setDefaultTimeout(15000);
        page.on("pageerror", (error) => errors.push(error.message));
    }
    await login(hrPage, hr);
    await hrPage.goto("/hr/supervisors/" + supervisor.id);
    await confirmPassword(hrPage);
    await hrPage
        .getByLabel("Employee", { exact: true })
        .selectOption(String(supervisor.employee_id));
    await hrPage
        .getByRole("button", { name: "Save assignment", exact: true })
        .click();
    await expect(hrPage.locator(".swal2-popup")).toBeVisible();
    await hrPage.locator(".swal2-confirm").click();
    await expect(hrPage.locator("table")).toContainText("Synthetic Employee");
    await login(employeePage, employee);
    await employeePage.goto("/employee/leave/create");
    for (const width of [320, 360, 375, 390, 430, 768, 1024, 1280]) {
        await employeePage.setViewportSize({ width, height: 950 });
        await fits(employeePage);
    }
    await employeePage
        .getByRole("radio", { name: "Others (pls specify)" })
        .check();
    if (await employeePage.locator("#leave-type-choice").isVisible()) {
        await employeePage
            .getByLabel("Specify leave type")
            .selectOption(String(supervisor.type_id));
    }
    await employeePage
        .getByLabel("Start Date", { exact: true })
        .fill(supervisor.from);
    await employeePage
        .getByLabel("End Date", { exact: true })
        .fill(supervisor.until);
    await expect(employeePage.locator("#leave-day-count")).toHaveText("2");
    await employeePage
        .getByLabel("Specify other leave", { exact: true })
        .fill("Personal appointment");
    await employeePage
        .getByLabel("Reason", { exact: true })
        .fill("Synthetic browser leave request for a personal matter.");
    await employeePage
        .getByLabel("Supporting Document", { exact: true })
        .setInputFiles({
            name: "synthetic.pdf",
            mimeType: "application/pdf",
            buffer: Buffer.from(
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
        });
    await employeePage
        .getByRole("button", { name: "Submit Application", exact: true })
        .click();
    await expect(employeePage).toHaveURL(/employee\/leave\/\d+$/);
    const leaveId = employeePage.url().split("/").at(-1);
    const unsignedForm = await employeePage.request.get(
        `/employee/leave/${leaveId}/form`,
    );
    expect(await unsignedForm.text()).toContain("Personal appointment");
    await expect(employeePage.locator("main")).toContainText(
        "Pending Supervisor approval",
    );
    await login(supPage, supervisor);
    await expect(supPage.locator(".sidebar")).not.toContainText("Attendance");
    await enrollSignature(supPage, supervisor);
    await supPage.goto("/supervisor/leave/" + leaveId);
    for (const width of [320, 360, 375, 390, 430, 768, 1024, 1280]) {
        await supPage.setViewportSize({ width, height: 950 });
        await fits(supPage);
    }
    await supPage.screenshot({
        path: "test-results/screenshots/phase5-supervisor-review.png",
        fullPage: true,
    });
    await expect(
        supPage
            .frameLocator('iframe[title="Printable leave application preview"]')
            .getByRole("heading", {
                name: "LEAVE APPLICATION FORM",
                exact: true,
            }),
    ).toBeVisible();
    const preview = supPage.frameLocator(
        'iframe[title="Printable leave application preview"]',
    );
    const watermark = preview.locator(".form-watermark");
    await expect(watermark).toHaveCSS("opacity", "0.1");
    await expect(preview.getByAltText("Supervisor signature")).toHaveCount(0);
    await expect
        .poll(() => watermark.evaluate((image) => image.naturalWidth))
        .toBeGreaterThan(0);
    const logo = await watermark.evaluate((image) => {
        const bounds = image.getBoundingClientRect();
        const frame = image.parentElement.getBoundingClientRect();
        return {
            width: image.naturalWidth,
            height: image.naturalHeight,
            offsetX:
                bounds.left + bounds.width / 2 - (frame.left + frame.width / 2),
            offsetY:
                bounds.top + bounds.height / 2 - (frame.top + frame.height / 2),
        };
    });
    expect(Math.abs(logo.offsetX)).toBeLessThan(1);
    expect(Math.abs(logo.offsetY)).toBeLessThan(1);
    await supPage.emulateMedia({ media: "print" });
    await expect(watermark).toBeVisible();
    await expect(watermark).toHaveCSS("print-color-adjust", "exact");
    await supPage.emulateMedia({ media: null });
    await supPage
        .getByLabel("Confirmed chargeable days", { exact: true })
        .fill("2");
    await supPage
        .getByLabel("Confirm your current password", { exact: true })
        .fill(supervisor.password);
    await supPage
        .getByRole("button", { name: "Approve & Sign", exact: true })
        .click();
    await expect(supPage.locator("#swal2-title")).toHaveText(
        "Approve and Sign Leave?",
    );
    await supPage.locator(".swal2-cancel").click();
    await expect(
        supPage.getByRole("heading", { name: "Final Supervisor decision" }),
    ).toBeVisible();
    await supPage
        .getByRole("button", { name: "Approve & Sign", exact: true })
        .click();
    await supPage.locator(".swal2-confirm").click();
    await expect(supPage.locator(".limcoma-compact-toast")).toContainText(
        "Leave application approved and signed.",
    );
    await expect(
        supPage.locator(".limcoma-compact-toast .swal2-confirm"),
    ).not.toBeVisible();
    await expect(
        supPage.getByRole("heading", { name: "Final Supervisor decision" }),
    ).toHaveCount(0);
    await expect(employeePage.locator("main")).toContainText("Approved", {
        timeout: 20000,
    });
    const approvedLink = employeePage.getByRole("link", {
        name: "View Approved Form",
    });
    await expect(approvedLink).toBeVisible();
    const approved = await employeePage.request.get(
        await approvedLink.getAttribute("href"),
    );
    expect(approved.ok()).toBe(true);
    expect(approved.headers()["content-type"]).toContain("application/pdf");
    const bytes = await approved.body();
    const pdf = bytes.toString("latin1");
    expect(pdf.startsWith("%PDF-")).toBe(true);
    expect(pdf).toMatch(/\/MediaBox\s*\[0 0 84\d(?:\.\d+)? 59\d(?:\.\d+)?\]/);
    expect(pdf).toMatch(/\/Count 1\b/);
    expect(pdf).toMatch(
        new RegExp(
            String.raw`/Subtype\s*/Image\s*/Width\s+${logo.width}\s*/Height\s+${logo.height}\b`,
        ),
    );
    expect(pdf).toMatch(
        /\/Subtype\s*\/Image\s*\/Width\s+200\s*\/Height\s+60\b/,
    );
    writeFileSync("test-results/screenshots/phase5-approved-form.pdf", bytes);
    const download = await employeePage.request.get(
        await employeePage
            .getByRole("link", { name: "Download Approved PDF" })
            .getAttribute("href"),
    );
    expect(download.headers()["content-disposition"]).toMatch(/^attachment/);
    expect(await download.body()).toEqual(bytes);
    await employeePage.goto("/employee/leave");
    await expect(employeePage.locator("main")).toContainText(
        "3.00 days available",
    );
    const denied = await supPage.request.get("/hr/attendance/corrections");
    expect(denied.status()).toBe(403);
    await hrPage.goto("/hr/leave/" + leaveId);
    await expect(hrPage.locator('[name="decision"]')).toHaveCount(0);
    await employeePage.goto("/employee/attendance/corrections/create");
    await hrPage.goto("/hr/attendance/corrections");
    await employeePage
        .getByLabel("Correction type", { exact: true })
        .selectOption("missing_record");
    await employeePage
        .getByLabel("Attendance date", { exact: true })
        .fill(supervisor.correction_date);
    await employeePage
        .getByLabel("Proposed Time In", { exact: true })
        .fill(supervisor.correction_date + "T08:00");
    await employeePage
        .getByLabel("Proposed Time Out", { exact: true })
        .fill(supervisor.correction_date + "T17:00");
    await employeePage
        .getByLabel("Reason for correction", { exact: false })
        .fill("Synthetic missing record after an unexpected device problem.");
    await employeePage
        .getByRole("button", { name: "Submit for HR review", exact: true })
        .click();
    await expect(employeePage).toHaveURL(/corrections\/\d+$/);
    const correctionId = employeePage.url().split("/").at(-1);
    await expect(
        hrPage.locator('a[href$="/corrections/' + correctionId + '"]'),
    ).toBeVisible({ timeout: 20000 });
    await hrPage.goto("/hr/attendance/corrections/" + correctionId);
    await hrPage
        .getByLabel("Decision", { exact: true })
        .selectOption("approved");
    await hrPage
        .getByLabel("HR remarks", { exact: false })
        .fill("Confirmed the manual times with the employee and records.");
    await hrPage
        .getByRole("button", { name: "Save decision", exact: true })
        .click();
    await hrPage.locator(".swal2-confirm").click();
    await expect(hrPage.locator("main")).toContainText("Approved");
    await expect(employeePage.locator("main")).toContainText("Approved", {
        timeout: 20000,
    });
    await employeePage.goto("/employee/attendance/history");
    await expect(employeePage.locator("main")).toContainText(
        "Approved manual entries",
    );
    for (const [page, name] of [
        [hrPage, "hr"],
        [employeePage, "employee"],
    ]) {
        await page.setViewportSize({ width: 390, height: 950 });
        await fits(page);
        await page.screenshot({
            path: "test-results/screenshots/phase5-" + name + ".png",
            fullPage: true,
        });
    }
    expect(errors).toEqual([]);
    for (const context of contexts) await context.close();
});

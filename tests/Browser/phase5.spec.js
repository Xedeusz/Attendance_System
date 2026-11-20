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
test.beforeEach(() => {
    hr = fixture("create");
    employee = fixture("attendance", hr.id);
    supervisor = fixture("phase5", hr.id, "generic-other");
    mkdirSync("test-results/screenshots", { recursive: true });
});
test.afterEach(() => {
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
    test.setTimeout(240000);
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
        page.setDefaultTimeout(30000);
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
    const calendarPage = await contexts[0].newPage();
    calendarPage.on("pageerror", (error) => errors.push(error.message));
    await calendarPage.goto(
        "/hr/leave/calendar?view=month&date=" + supervisor.from,
    );
    const schedules = calendarPage.getByRole("region", {
        name: "Leave schedules",
    });
    const period = schedules.getByRole("heading", { level: 2 });
    const initialPeriod = await period.innerText();
    await calendarPage.getByRole("button", { name: "Next period" }).click();
    await expect(period).not.toHaveText(initialPeriod);
    await calendarPage.getByRole("button", { name: "Previous period" }).click();
    await expect(period).toHaveText(initialPeriod);
    await calendarPage
        .getByRole("button", { name: "List", exact: true })
        .click();
    const statusFilter = calendarPage
        .locator('[aria-label="Calendar filters"] select')
        .last();
    await statusFilter.selectOption("approved");
    await calendarPage.getByRole("button", { name: "Clear filters" }).click();
    await expect(statusFilter).toHaveValue("");
    await expect(calendarPage.locator("[data-sync-status]")).toHaveText("Live", {
        timeout: 20000,
    });
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
    await expect(
        schedules.getByRole("button", {
            name: /Synthetic Employee.*Pending Supervisor approval/,
        }),
    ).toBeVisible({ timeout: 15000 });
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
    await supPage.goto(
        "/supervisor/leave/calendar?view=week&date=" + supervisor.from,
    );
    const weekGrid = supPage.getByLabel("Calendar days");
    await weekGrid
        .getByRole("button", {
            name: /Synthetic Employee.*Pending Supervisor approval/,
        })
        .click();
    const calendarDetails = supPage.getByRole("dialog", {
        name: "Leave application details",
    });
    await expect(calendarDetails).toBeVisible();
    await calendarDetails
        .getByRole("link", { name: "Review Application" })
        .click();
    await expect(supPage).toHaveURL(
        new RegExp("/supervisor/leave/" + leaveId + "$"),
    );
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
    const approvalPassword = supPage.getByLabel(
        "Confirm your current password",
        { exact: true },
    );
    const approvalButton = supPage.getByRole("button", {
        name: "Approve & Sign",
        exact: true,
    });
    await expect(approvalPassword).toHaveCount(1);
    await approvalPassword.fill(supervisor.password);
    await supPage.evaluate(
        () =>
            new Promise((resolve) => {
                window.addEventListener(
                    "workspace:notifications",
                    () => resolve(),
                    { once: true },
                );
                window.dispatchEvent(new Event("online"));
            }),
    );
    await expect(approvalPassword).toHaveCount(1);
    await expect(approvalPassword).toHaveValue(supervisor.password);
    await expect(approvalButton).toHaveCount(1);
    await approvalButton.click();
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
    await expect(
        schedules.getByRole("button", { name: /Synthetic Employee.*Approved/ }),
    ).toBeVisible({ timeout: 15000 });
    await calendarPage.close();
    const personalCalendar = await contexts[1].newPage();
    personalCalendar.on("pageerror", (error) => errors.push(error.message));
    await personalCalendar.setViewportSize({ width: 320, height: 800 });
    await personalCalendar.goto(
        "/employee/leave/calendar?date=" + supervisor.from,
    );
    await expect(
        personalCalendar.getByRole("button", { name: "List", exact: true }),
    ).toHaveAttribute("aria-pressed", "true");
    await fits(personalCalendar);
    await personalCalendar
        .getByRole("button", { name: /Synthetic Employee.*Approved/ })
        .click();
    await expect(
        personalCalendar
            .getByRole("dialog", { name: "Leave application details" })
            .getByRole("link", { name: "View Approved Form" }),
    ).toBeVisible({ timeout: 20000 });
    await personalCalendar.keyboard.press("Escape");
    await expect(
        personalCalendar.getByRole("dialog", {
            name: "Leave application details",
        }),
    ).toBeHidden();
    await personalCalendar.close();

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
        /\/Subtype\s*\/Image\s*\/Width\s+640\s*\/Height\s+640\b/,
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
    const qrUrl = pdf.match(
        /\/URI \(([^)]+\/verify\/leave#[a-f0-9]{64})\)/,
    )?.[1];
    expect(qrUrl).toBeTruthy();
    const scanContext = await browser.newContext({
        viewport: { width: 375, height: 800 },
    });
    const scanPage = await scanContext.newPage();
    try {
        await scanPage.goto(qrUrl, { waitUntil: "commit" });
        await expect(scanPage).toHaveURL(/\/login$/);
        await scanPage.getByLabel("Email address").fill(hr.email);
        await scanPage
            .getByLabel("Password", { exact: true })
            .fill(hr.password);
        await scanPage
            .getByRole("button", { name: "Sign in", exact: true })
            .click();
        await expect(
            scanPage.getByRole("heading", { name: "Valid Approved Record" }),
        ).toBeVisible();
        await expect(
            scanPage.getByRole("link", { name: "View Official PDF" }),
        ).toBeVisible();
        expect(
            await scanPage.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
    } finally {
        await scanContext.close();
    }
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

test("approved leave details form two aligned rows on desktop and stack on smaller screens", async ({
    page: hrPage,
}) => {
    const leave = fixture("leave-layout", hr.id);
    await login(hrPage, hr);
    await hrPage.goto("/hr/leave/" + leave.id);
    await expect(hrPage.getByRole("link", { name: "Verify Document", exact: true })).toHaveCount(0);
    for (const link of [
        "View Approved Form",
        "Download Approved PDF",
        "Download document",
    ]) {
        await expect(
            hrPage.getByRole("link", { name: link, exact: true }),
        ).toHaveAttribute("href", /.+/);
    }
    await expect(
        hrPage.getByText("Approved for the requested dates."),
    ).toBeVisible();
    const cardHeadings = [
        "Application details",
        "LIMCOMA Leave Application Form",
        "Supporting documents",
        "Review history",
    ];
    const cards = cardHeadings.map((name) =>
        hrPage
            .getByRole("heading", { name, exact: true })
            .locator("xpath=ancestor::section[1]"),
    );
    for (const width of [1440, 1024, 768, 390]) {
        await hrPage.setViewportSize({ width, height: 950 });
        await fits(hrPage);
        const [application, form, documents, history] = await Promise.all(
            cards.map((card) => card.boundingBox()),
        );
        if (width >= 1024) {
            expect(application.y).toBeCloseTo(form.y, 0);
            expect(documents.y).toBeCloseTo(history.y, 0);
            expect(application.x).toBeCloseTo(documents.x, 0);
            expect(form.x).toBeCloseTo(history.x, 0);
            expect(application.width).toBeCloseTo(form.width, 0);
            expect(form.x).toBeGreaterThan(application.x);
        } else {
            expect(form.x).toBeCloseTo(application.x, 0);
            expect(form.y).toBeGreaterThan(application.y);
            expect(documents.y).toBeGreaterThan(form.y);
            expect(history.y).toBeGreaterThan(documents.y);
        }
    }
    await hrPage.setViewportSize({ width: 1440, height: 950 });
});

import { test, expect } from "@playwright/test";
import { enrollSignature } from "./leave-signature.js";
import { execFileSync } from "node:child_process";
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
    supervisor = fixture("phase5", hr.id);
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
async function live(page) {
    await expect(page.locator("[data-sync-status]")).toHaveText("Live", {
        timeout: 20000,
    });
}

test("private WebSocket updates preserve filters and drafts, reject stale writes, and recover through polling", async ({
    browser,
}) => {
    test.setTimeout(150000);
    const a = await browser.newContext(),
        b = await browser.newContext();
    const viewer = await a.newPage(),
        editor = await b.newPage();
    const errors = [];
    for (const page of [viewer, editor])
        page.on("pageerror", (error) => errors.push(error.message));
    await login(viewer, hr);
    await login(editor, hr);
    await viewer.goto("/hr/branches?q=" + hr.prefix);
    await live(viewer);
    const branchUrl = await viewer
        .getByRole("link", { name: employee.branch, exact: true })
        .getAttribute("href");
    const editUrl = branchUrl + "/edit";
    let navigations = 0;
    viewer.on("framenavigated", (frame) => {
        if (frame === viewer.mainFrame()) navigations++;
    });
    await viewer.getByLabel("Search branches").fill("Unsaved filter draft");
    await editor.goto(editUrl);
    await editor
        .getByLabel("Branch name", { exact: true })
        .fill(hr.prefix + " Live Office");
    await editor.getByRole("button", { name: "Save branch" }).click();
    await expect(
        viewer.getByRole("link", {
            name: hr.prefix + " Live Office",
            exact: true,
        }),
    ).toBeVisible({ timeout: 20000 });
    await expect(viewer.getByLabel("Search branches")).toHaveValue(
        "Unsaved filter draft",
    );
    expect(navigations).toBe(0);
    await viewer.goto(branchUrl);
    await live(viewer);
    await editor.goto(editUrl);
    await editor
        .getByLabel("Geofencing Radius (Meters)", { exact: true })
        .fill("180");
    await editor.getByRole("button", { name: "Save branch" }).click();
    await expect
        .poll(
            () =>
                viewer
                    .locator("[data-map-config]")
                    .evaluate(
                        (element) =>
                            JSON.parse(element.dataset.mapConfig).radius,
                    ),
            { timeout: 20000 },
        )
        .toBe(180);
    await viewer.goto(editUrl);
    await live(viewer);
    await viewer
        .getByLabel("Branch name", { exact: true })
        .fill("My unsaved draft");
    await editor.goto(editUrl);
    await editor
        .getByLabel("Branch name", { exact: true })
        .fill(hr.prefix + " Newer Office");
    await editor.getByRole("button", { name: "Save branch" }).click();
    await expect(viewer.locator("#workspace-update-notice")).toBeVisible({
        timeout: 20000,
    });
    await expect(viewer.getByLabel("Branch name", { exact: true })).toHaveValue(
        "My unsaved draft",
    );
    await viewer.getByRole("button", { name: "Save branch" }).click();
    await expect(
        viewer.getByText(/This record changed after the form was opened/),
    ).toBeVisible();
    await expect(viewer.getByLabel("Branch name", { exact: true })).toHaveValue(
        "My unsaved draft",
    );
    await viewer.goto("/hr/branches?q=" + hr.prefix);
    await live(viewer);
    await viewer.evaluate(() =>
        window.workspaceEcho.connector.pusher.disconnect(),
    );
    await editor.goto(editUrl);
    await editor
        .getByLabel("Branch name", { exact: true })
        .fill(hr.prefix + " Fallback Office");
    await editor.getByRole("button", { name: "Save branch" }).click();
    await expect(
        viewer.getByRole("link", {
            name: hr.prefix + " Fallback Office",
            exact: true,
        }),
    ).toBeVisible({ timeout: 25000 });
    await expect(viewer.locator("[data-sync-status]")).toContainText("Polling");
    await viewer.evaluate(() =>
        window.workspaceEcho.connector.pusher.connect(),
    );
    await live(viewer);
    expect(errors).toEqual([]);
    await a.close();
    await b.close();
});

test("leave submission and final supervisor approval synchronize authorized queues, history, and badges", async ({
    browser,
}) => {
    test.setTimeout(150000);
    const contexts = await Promise.all([
        browser.newContext(),
        browser.newContext(),
        browser.newContext(),
    ]);
    const [admin, owner, reviewer] = await Promise.all(
        contexts.map((context) => context.newPage()),
    );
    await login(admin, hr);
    await admin.goto("/hr/supervisors/" + supervisor.id);
    if (admin.url().includes("confirm-password")) {
        await admin.getByLabel("Password", { exact: true }).fill(hr.password);
        await admin
            .getByRole("button", { name: "Confirm password", exact: true })
            .click();
    }
    await admin
        .getByLabel("Employee", { exact: true })
        .selectOption(String(supervisor.employee_id));
    await admin.getByRole("button", { name: "Save assignment" }).click();
    await admin.locator(".swal2-confirm").click();
    await expect(admin.locator("table")).toContainText("Synthetic Employee");
    await login(reviewer, supervisor);
    await enrollSignature(reviewer, supervisor);
    await reviewer.goto("/supervisor/leave/pending");
    await live(reviewer);
    await login(owner, employee);
    await owner.goto("/employee/leave/create");
    await owner.getByRole("radio", { name: "Others (pls specify)" }).check();
    if (await owner.locator("#leave-type-choice").isVisible()) {
        await owner
            .getByLabel("Specify leave type")
            .selectOption(String(supervisor.type_id));
    }
    await owner.getByLabel("Start Date", { exact: true }).fill(supervisor.from);
    await owner.getByLabel("End Date", { exact: true }).fill(supervisor.until);
    await expect(owner.locator("#leave-day-count")).toHaveText("2");
    await owner
        .getByLabel("Reason", { exact: true })
        .fill("Synthetic request to verify secure real-time decisions.");
    await owner
        .getByLabel("Supporting Document", { exact: true })
        .setInputFiles({
            name: "synthetic.pdf",
            mimeType: "application/pdf",
            buffer: Buffer.from(
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
        });
    await owner
        .getByRole("button", { name: "Submit Application", exact: true })
        .click();
    await expect(owner).toHaveURL(/employee\/leave\/\d+$/);
    const id = owner.url().split("/").at(-1);
    await expect(
        reviewer.locator('a[href$="/supervisor/leave/' + id + '"]'),
    ).toBeVisible({ timeout: 20000 });
    await expect(reviewer.locator("[data-sync-unread]")).toBeVisible();
    await owner.goto("/employee/leave");
    await live(owner);
    let navigations = 0;
    owner.on("framenavigated", (frame) => {
        if (frame === owner.mainFrame()) navigations++;
    });
    await reviewer.goto("/supervisor/leave/" + id);
    await reviewer
        .getByLabel("Confirmed chargeable days", { exact: true })
        .fill("2");
    await reviewer
        .getByLabel("Confirm your current password", { exact: true })
        .fill(supervisor.password);
    await reviewer
        .getByRole("button", { name: "Approve & Sign", exact: true })
        .click();
    await reviewer.locator(".swal2-confirm").click();
    await expect(owner.locator("main")).toContainText("Approved", {
        timeout: 20000,
    });
    await expect(owner.locator("main")).toContainText("3.00 days available");
    expect(navigations).toBe(0);
    expect(
        (await reviewer.request.get("/hr/attendance/corrections")).status(),
    ).toBe(403);
    await Promise.all(contexts.map((context) => context.close()));
});

test("department edits and employee creation update another HR dashboard from database counts", async ({
    browser,
}) => {
    test.setTimeout(120000);
    const contexts = await Promise.all([
        browser.newContext(),
        browser.newContext(),
    ]);
    const [viewer, editor] = await Promise.all(
        contexts.map((context) => context.newPage()),
    );
    await login(viewer, hr);
    await login(editor, hr);
    await viewer.goto("/hr/departments?q=" + hr.prefix);
    await live(viewer);
    const editUrl = await viewer
        .locator("main")
        .getByRole("link", { name: "Edit", exact: true })
        .getAttribute("href");
    const departmentId = new URL(editUrl).pathname.split("/").at(-2);
    await editor.goto(editUrl);
    await editor
        .getByLabel("Department name", { exact: true })
        .fill(hr.prefix + " Live Department");
    await editor.getByRole("button", { name: "Save department" }).click();
    await expect(viewer.locator("main")).toContainText(
        hr.prefix + " Live Department",
        { timeout: 20000 },
    );
    await viewer.goto("/hr/dashboard");
    await live(viewer);
    const count = viewer.locator('[data-metric="Total employees"] strong');
    const before = Number((await count.textContent()).replaceAll(",", ""));
    let navigations = 0;
    viewer.on("framenavigated", (frame) => {
        if (frame === viewer.mainFrame()) navigations++;
    });
    await editor.goto("/hr/employees/create");
    for (const [label, value] of Object.entries({
        "Employee number": hr.prefix + "-LIVE",
        "Work email": hr.prefix.toLowerCase() + "-realtime@example.test",
        "First name": "Synthetic",
        "Last name": "Realtime",
        "Position title": "QA fixture",
        "Date hired": "2026-01-01",
    }))
        await editor.getByLabel(label, { exact: true }).fill(value);
    const branchId = await editor
        .getByLabel("Assigned primary branch")
        .locator("option")
        .filter({ hasText: hr.prefix })
        .getAttribute("value");
    await editor.getByLabel("Assigned primary branch").selectOption(branchId);
    await editor
        .getByLabel("Company-wide department")
        .selectOption(departmentId);
    await editor
        .getByRole("button", { name: "Save employee & issue invitation" })
        .click();
    await expect(
        editor.getByRole("heading", {
            name: "Synthetic Realtime",
            exact: true,
        }),
    ).toBeVisible();
    await expect(count).toHaveText(String(before + 1), { timeout: 20000 });
    await expect(viewer.locator("main")).toContainText("Synthetic Realtime");
    expect(navigations).toBe(0);
    await Promise.all(contexts.map((context) => context.close()));
});

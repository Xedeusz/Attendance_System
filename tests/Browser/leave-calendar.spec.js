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
});

test.afterAll(() => {
    if (hr)
        execFileSync(php, [
            "tests/Browser/fixture.php",
            "cleanup",
            String(hr.id),
        ]);
});

function leave(id, title, start, end, status = "approved") {
    return { id, title, start, end, status, half_day: null };
}

async function openCalendar(page, events, date, view = "month") {
    await page.route("**/hr/leave/calendar/events?*", async (route) => {
        const query = new URL(route.request().url()).searchParams;
        const visible = events.filter(
            (entry) =>
                entry.start <= query.get("to") &&
                entry.end >= query.get("from") &&
                (query.get("status")
                    ? entry.status === query.get("status")
                    : !["cancelled", "rejected"].includes(entry.status)),
        );
        await route.fulfill({
            json: { today: date, events: visible, next_cursor: null },
        });
    });
    await page.goto("/login");
    await page.getByLabel("Email address").fill(hr.email);
    await page.getByLabel("Password", { exact: true }).fill(hr.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/\/hr\/dashboard$/);
    await page.goto("/hr/leave/calendar?date=" + date + "&view=" + view);
}

test("month bars span inclusive dates, stack and open hidden leave by date", async ({
    page,
}) => {
    const events = [
        leave(1, "Avery", "2026-11-18", "2026-11-20"),
        leave(
            2,
            "Bailey",
            "2026-11-19",
            "2026-11-20",
            "pending_supervisor_approval",
        ),
        leave(3, "Casey", "2026-11-19", "2026-11-20"),
        leave(4, "Drew", "2026-11-19", "2026-11-20"),
        leave(5, "Emery", "2026-11-18", "2026-11-18"),
        leave(6, "Finley", "2026-11-18", "2026-11-18", "cancelled"),
        leave(7, "Gray", "2026-11-18", "2026-11-18", "rejected"),
    ];
    await page.route("**/hr/leave/calendar/events/4", (route) =>
        route.fulfill({
            json: { ...events[3], duration: 2, url: "/hr/leave/4" },
        }),
    );
    await openCalendar(page, events, "2026-11-18");
    const grid = page.getByLabel("Calendar days");
    const avery = grid.getByRole("button", { name: /^Avery · Approved/ });
    const bailey = grid.getByRole("button", {
        name: /^Bailey · Pending Supervisor approval/,
    });
    const emery = grid.getByRole("button", { name: /^Emery · Approved/ });
    await expect(avery).toHaveCount(1);
    await expect(avery).toHaveCSS("grid-column-start", "3");
    await expect(avery).toHaveCSS("grid-column-end", "span 3");
    await expect(bailey).toHaveCSS("grid-column-start", "4");
    await expect(bailey).toHaveCSS("grid-column-end", "span 2");
    await expect(bailey).toHaveCSS("background-color", "rgb(255, 244, 229)");
    await expect(avery).toHaveCSS("background-color", "rgb(231, 247, 242)");
    await expect(emery).toHaveCSS("grid-column-end", "span 1");
    const day = await grid
        .getByRole("button", { name: /^Nov 18, 2026,/ })
        .boundingBox();
    const longBar = await avery.boundingBox();
    const stacked = await bailey.boundingBox();
    expect(longBar.width).toBeGreaterThan(day.width * 2.8);
    expect(stacked.y).toBeGreaterThan(longBar.y + longBar.height);

    await expect(grid.getByRole("button", { name: /^Drew/ })).toHaveCount(0);
    await grid
        .getByRole("button", {
            name: "Show 1 more for Nov 19, 2026",
        })
        .click();
    const overflow = page.getByRole("region", { name: "Selected day leave" });
    await expect(overflow).toBeFocused();
    await expect(overflow.getByRole("button")).toHaveCount(1);
    await overflow.getByRole("button", { name: /^Drew/ }).press("Enter");
    const dialog = page.getByRole("dialog", {
        name: "Leave application details",
    });
    await expect(
        dialog.getByRole("link", { name: "View Leave Application" }),
    ).toHaveAttribute("href", "/hr/leave/4");
    await page.keyboard.press("Escape");
    await expect(dialog).toBeHidden();

    const status = page.getByRole("combobox", { name: "Status", exact: true });
    await status.selectOption("cancelled");
    await expect(
        grid.getByRole("button", { name: /^Finley · Cancelled/ }),
    ).toBeVisible();
    await expect(avery).toHaveCount(0);
    await status.selectOption("rejected");
    await expect(
        grid.getByRole("button", { name: /^Gray · Rejected/ }),
    ).toBeVisible();
});

test("one leave continues across weeks, months and years without duplicating list entries", async ({
    page,
}) => {
    const events = [
        leave(11, "Cross-month leave", "2026-11-29", "2026-12-03"),
        leave(
            12,
            "Year-end leave",
            "2026-12-30",
            "2027-01-03",
            "pending_supervisor_approval",
        ),
    ];
    await page.route("**/hr/leave/calendar/events/11", (route) =>
        route.fulfill({
            json: { ...events[0], duration: 5, url: "/hr/leave/11" },
        }),
    );
    await page.setViewportSize({ width: 390, height: 844 });
    await openCalendar(page, events, "2026-11-29");
    const grid = page.getByLabel("Calendar days");
    const monthSegments = grid.getByRole("button", {
        name: /^Cross-month leave/,
    });
    await expect(monthSegments).toHaveCount(2);
    await expect(monthSegments.first()).toHaveCSS("grid-column-end", "span 1");
    await expect(monthSegments.last()).toHaveCSS("grid-column-end", "span 4");
    const dialog = page.getByRole("dialog", {
        name: "Leave application details",
    });
    for (const segment of await monthSegments.all()) {
        await segment.click();
        await expect(
            dialog.getByRole("link", { name: "View Leave Application" }),
        ).toHaveAttribute("href", "/hr/leave/11");
        await page.keyboard.press("Escape");
    }
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);

    await grid.getByRole("button", { name: /^Dec 1, 2026,/ }).click();
    await page.getByRole("button", { name: "Week", exact: true }).click();
    await expect(monthSegments).toHaveCount(1);
    await expect(monthSegments).toHaveCSS("grid-column-start", "1");
    await expect(monthSegments).toHaveCSS("grid-column-end", "span 4");
    await page.getByRole("button", { name: "List", exact: true }).click();
    const list = page.getByLabel("Leave schedule list");
    await expect(
        list.getByRole("button", { name: /^Cross-month leave/ }),
    ).toHaveCount(1);
    await expect(
        list.getByRole("button", {
            name: /^Year-end leave .*Pending Supervisor approval/,
        }),
    ).toHaveCount(1);

    events[1].status = "approved";
    await page.evaluate(() =>
        window.dispatchEvent(new CustomEvent("workspace:leave-calendar")),
    );
    await expect(
        list.getByRole("button", { name: /^Year-end leave .*Approved/ }),
    ).toHaveCount(1);
    await expect(
        list.getByRole("button", { name: /^Year-end leave/ }),
    ).toHaveCount(1);

    await page.getByRole("button", { name: "Next period" }).click();
    await expect(
        list.getByRole("button", { name: /^Cross-month leave/ }),
    ).toHaveCount(0);
    await expect(
        list.getByRole("button", { name: /^Year-end leave .*Approved/ }),
    ).toHaveCount(1);
    await page.getByRole("button", { name: "Month", exact: true }).click();
    const yearEnd = grid.getByRole("button", { name: /^Year-end leave/ });
    await expect(yearEnd).toHaveCount(1);
    await expect(yearEnd).toHaveCSS("grid-column-end", "span 5");

    events[1].status = "cancelled";
    await page.evaluate(() =>
        window.dispatchEvent(new CustomEvent("workspace:leave-calendar")),
    );
    await expect(yearEnd).toHaveCount(0);
});

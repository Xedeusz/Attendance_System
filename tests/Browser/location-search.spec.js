import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";

const php = process.env.PHP_BINARY || "C:\\xampp\\php\\php.exe";
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
});
test.afterAll(() => {
    if (hr)
        execFileSync(php, [
            "tests/Browser/fixture.php",
            "cleanup",
            String(hr.id),
        ]);
});

async function login(page, user) {
    await page.goto("/login");
    await expect(page.locator(".brand-name")).toContainText("LIMCOMA");
    await expect(
        page.getByRole("button", { name: "Sign in", exact: true }),
    ).toHaveCSS("background-color", "rgb(47, 27, 114)");
    await page.getByLabel("Email address").fill(user.email);
    await page.getByLabel("Password", { exact: true }).fill(user.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

test("LIMCOMA theme and key-free Photon search preserve HR and employee workflows", async ({
    page,
    browser,
}) => {
    await login(page, hr);
    await expect(page.locator(".sidebar")).toHaveCSS(
        "background-color",
        "rgb(47, 27, 114)",
    );
    await expect(page.locator(".nav-link.active")).toHaveCSS(
        "background-color",
        "rgb(16, 171, 140)",
    );
    await page.goto("/hr/branches/create");
    const searchInput = page.getByLabel("Search Location", { exact: true });
    await expect(searchInput).toBeEnabled();
    await searchInput.fill("Lipa City, Batangas");
    await expect(searchInput).toHaveValue("Lipa City, Batangas");
    await expect(searchInput).toHaveCSS("color", "rgb(30, 41, 59)");
    await expect(page).toHaveURL(/\/hr\/branches\/create$/);
    await expect(
        page.getByRole("button", { name: "Search", exact: true }),
    ).toBeEnabled();
    await expect(
        page.getByText(
            "Location search is not configured. Contact the system administrator.",
            { exact: true },
        ),
    ).toBeHidden();
    await page
        .getByRole("region", { name: "Branch location map", exact: true })
        .click({ position: { x: 150, y: 170 } });
    await expect(page.locator(".leaflet-marker-icon")).toHaveCount(1);
    const context = await browser.newContext();
    const employeePage = await context.newPage();
    await login(employeePage, employee);
    await expect(employeePage.locator(".sidebar")).toHaveCSS(
        "background-color",
        "rgb(47, 27, 114)",
    );
    expect(
        (
            await employeePage.request.post("/hr/branch-location-search", {
                data: { query: "Lipa City" },
                headers: {
                    "X-CSRF-TOKEN": await employeePage
                        .locator('meta[name="csrf-token"]')
                        .getAttribute("content"),
                    Accept: "application/json",
                },
            })
        ).status(),
    ).toBe(403);
    await context.close();
});

test("search result selection, errors, radius preservation, save and edit work with deterministic provider responses", async ({
    page,
}) => {
    await login(page, hr);
    let calls = 0;
    await page.route("**/hr/branch-location-search", async (route) => {
        calls++;
        const query = route.request().postDataJSON().query;
        if (query === "No results")
            return route.fulfill({ json: { results: [] } });
        if (query === "Provider failure")
            return route.fulfill({
                status: 503,
                json: {
                    message:
                        "Location search is temporarily unavailable. You can still select a location directly on the map.",
                },
            });
        await route.fulfill({
            json: {
                results: [
                    {
                        label: "Lipa City, Batangas, Philippines <img src=x onerror=alert(1)>",
                        latitude: 13.9411,
                        longitude: 121.1622,
                    },
                    {
                        label: "Gen. Luna Street, Lipa City",
                        latitude: 13.9484,
                        longitude: 121.1652,
                    },
                ],
            },
        });
    });
    await page.goto("/hr/branches/create");
    const query = page.getByLabel("Search Location", { exact: true });
    const search = page.getByRole("button", { name: "Search", exact: true });
    const radius = page.getByLabel("Geofencing Radius (Meters)", {
        exact: true,
    });
    await radius.fill("70");
    await search.click();
    await expect(
        page.getByText("Enter a valid location to search.", {
            exact: true,
        }),
    ).toBeVisible();
    expect(calls).toBe(0);
    await query.fill("Lipa City, Batangas");
    expect(calls).toBe(0);
    await query.press("Enter");
    const results = page.getByRole("list", { name: "Location search results" });
    await expect(results.getByRole("button")).toHaveCount(2);
    await expect(page.locator(".leaflet-marker-icon")).toHaveCount(0);
    await expect(results.locator("img")).toHaveCount(0);
    await results.getByRole("button").first().click();
    await expect(page.getByLabel("Latitude", { exact: true })).toHaveValue(
        "13.9411000",
    );
    await expect(page.getByLabel("Longitude", { exact: true })).toHaveValue(
        "121.1622000",
    );
    await expect(radius).toHaveValue("70");
    await expect(page.locator(".leaflet-marker-icon")).toHaveCount(1);
    await expect(page.locator(".leaflet-overlay-pane path")).toHaveAttribute(
        "stroke",
        "#10ab8c",
    );
    await expect(
        page.getByLabel("Street address", { exact: true }),
    ).toHaveValue("");
    await query.fill("No results");
    await search.click();
    await expect(page.getByText(/No matching locations found/)).toBeVisible();
    await query.fill("Provider failure");
    await search.click();
    await expect(
        page.getByText(/Location search is temporarily unavailable/),
    ).toBeVisible();
    await expect(page.locator(".swal2-toast")).toBeHidden({ timeout: 4500 });
    await expect(page.getByLabel("Latitude", { exact: true })).toHaveValue(
        "13.9411000",
    );
    for (const [label, value] of Object.entries({
        "Branch code": hr.prefix + "-MAP",
        "Branch name": "Synthetic Search Branch",
        "Street address": "Do not overwrite this address",
        City: "Lipa City",
        Province: "Batangas",
    }))
        await page.getByLabel(label, { exact: true }).fill(value);
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await expect(page).toHaveURL(/\/hr\/branches\/\d+$/);
    await page.getByRole("link", { name: "Edit branch", exact: true }).click();
    await expect(radius).toHaveValue("70");
    await query.fill("Gen. Luna Street, Lipa City");
    await search.click();
    await results.getByRole("button").nth(1).click();
    await expect(page.getByLabel("Latitude", { exact: true })).toHaveValue(
        "13.9484000",
    );
    await expect(radius).toHaveValue("70");
    await expect(
        page.getByLabel("Street address", { exact: true }),
    ).toHaveValue("Do not overwrite this address");
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await page.getByRole("link", { name: "Edit branch", exact: true }).click();
    await expect(page.getByLabel("Latitude", { exact: true })).toHaveValue(
        "13.9484000",
    );
    await page.setViewportSize({ width: 390, height: 844 });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    ).toBe(true);
    await page.screenshot({
        path: "test-results/screenshots/limcoma-search-mobile.png",
        fullPage: true,
    });
});

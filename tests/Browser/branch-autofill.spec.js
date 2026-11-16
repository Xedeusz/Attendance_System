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

test("map-first form autofills suggestions, protects existing values and saves only on submission", async ({
    page,
}) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto("/login");
    await page.getByLabel("Email address").fill(hr.email);
    await page.getByLabel("Password", { exact: true }).fill(hr.password);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.route("**/hr/branch-location-search", (route) =>
        route.fulfill({
            json: {
                results: [
                    {
                        label: "Lipa City",
                        latitude: 13.94,
                        longitude: 121.16,
                        details: { city: "Lipa City", province: "Batangas" },
                    },
                    {
                        label: "Synthetic Cooperative Office",
                        latitude: 13.9411,
                        longitude: 121.1622,
                        details: {
                            branch_name: "Synthetic Cooperative Office",
                            address: "12 Gen. Luna Street, Barangay 1",
                            city: "Lipa City",
                            province: "Batangas",
                            postal_code: "4217",
                            branch_code: "MALICIOUS",
                            status: "inactive",
                        },
                    },
                    {
                        label: "Replacement Office",
                        latitude: 13.95,
                        longitude: 121.17,
                        details: {
                            branch_name: "Replacement Office",
                            address: "34 Test Road",
                            city: "San Jose",
                            province: "Batangas",
                        },
                    },
                ],
            },
        }),
    );
    await page.goto("/hr/branches/create");
    const map = page.getByRole("region", {
        name: "Branch location map",
        exact: true,
    });
    const info = page.getByRole("region", {
        name: "Branch Information",
        exact: true,
    });
    const query = page.getByLabel("Search Location", { exact: true });
    const search = page.getByRole("button", { name: "Search", exact: true });
    const field = (label) => page.getByLabel(label, { exact: true });
    const code = hr.prefix + "-MAP";
    expect((await info.boundingBox()).y).toBeGreaterThan(
        (await map.boundingBox()).y,
    );
    const qbox = await query.boundingBox(),
        sbox = await search.boundingBox();
    expect(Math.abs(qbox.y - sbox.y)).toBeLessThan(2);
    expect(Math.abs(qbox.height - sbox.height)).toBeLessThan(2);
    expect(sbox.x).toBeGreaterThanOrEqual(qbox.x + qbox.width);
    await field("Branch code").fill(code);
    await field("Geofencing Radius (Meters)").fill("40");
    async function choose(label) {
        await query.fill("Test location");
        await search.click();
        await page
            .getByRole("list", { name: "Location search results" })
            .getByRole("button", { name: label, exact: true })
            .click();
    }
    await choose("Lipa City");
    await expect(field("Branch name")).toHaveValue("");
    await expect(field("Street address")).toHaveValue("");
    await expect(field("City")).toHaveValue("Lipa City");
    await choose("Synthetic Cooperative Office");
    await expect(field("Branch name")).toHaveValue(
        "Synthetic Cooperative Office",
    );
    await expect(field("Street address")).toHaveValue(
        "12 Gen. Luna Street, Barangay 1",
    );
    await expect(field("Postal code (optional)")).toHaveValue("4217");
    await expect(field("Branch code")).toHaveValue(code);
    await expect(field("Branch status")).toHaveValue("active");
    await expect(field("Geofencing Radius (Meters)")).toHaveValue("40");
    await field("Street address").fill("Manual address to preserve");
    await choose("Replacement Office");
    await expect(
        page.getByRole("group", { name: "Apply location details" }),
    ).toBeVisible();
    await expect(field("Latitude")).toHaveValue("13.9500000");
    await expect(field("Street address")).toHaveValue(
        "Manual address to preserve",
    );
    await page
        .getByRole("button", { name: "Keep Existing Details", exact: true })
        .click();
    await expect(field("Street address")).toHaveValue(
        "Manual address to preserve",
    );
    await choose("Replacement Office");
    await expect(
        page.getByRole("button", { name: "Apply Details", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Reset Location", exact: true })
        .click();
    await expect(
        page.getByRole("group", { name: "Apply location details" }),
    ).toBeHidden();
    await expect(field("Street address")).toHaveValue(
        "Manual address to preserve",
    );
    await choose("Replacement Office");
    await page
        .getByRole("button", { name: "Apply Details", exact: true })
        .click();
    await expect(field("Street address")).toHaveValue("34 Test Road");
    await expect(field("Postal code (optional)")).toHaveValue("4217");
    await expect(field("City")).toHaveValue("San Jose");
    await expect(field("Branch code")).toHaveValue(code);
    await expect(field("Branch status")).toHaveValue("active");
    const directory = await page.request.get(
        "/hr/branches?q=" + encodeURIComponent(code),
    );
    expect(await directory.text()).toContain("No branches match this view");
    await page
        .getByRole("button", { name: "Save branch", exact: true })
        .click();
    await expect(page).toHaveURL(/\/hr\/branches\/\d+$/);
    await page.getByRole("link", { name: "Edit branch", exact: true }).click();
    await expect(field("Branch name")).toHaveValue("Replacement Office");
    await expect(field("Street address")).toHaveValue("34 Test Road");
    await choose("Lipa City");
    await expect(field("City")).toHaveValue("San Jose");
    await page
        .getByRole("button", { name: "Apply Details", exact: true })
        .click();
    await expect(field("City")).toHaveValue("Lipa City");
    await expect(field("Branch name")).toHaveValue("Replacement Office");
    await expect(field("Street address")).toHaveValue("34 Test Road");
    await page.getByRole("link", { name: "Cancel", exact: true }).click();
    await page.getByRole("link", { name: "Edit branch", exact: true }).click();
    await expect(field("City")).toHaveValue("San Jose");
    for (const width of [390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        const inputBox = await query.boundingBox(),
            buttonBox = await search.boundingBox();
        expect(Math.abs(inputBox.y - buttonBox.y)).toBeLessThan(2);
        expect(buttonBox.x + buttonBox.width).toBeLessThanOrEqual(width);
        expect((await info.boundingBox()).y).toBeGreaterThan(
            (await map.boundingBox()).y,
        );
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
    }
    await page.screenshot({
        path: "test-results/screenshots/branch-autofill-mobile.png",
        fullPage: true,
    });
});

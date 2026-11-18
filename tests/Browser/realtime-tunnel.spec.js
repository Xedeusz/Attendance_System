import { test, expect } from "@playwright/test";
import { readFileSync } from "node:fs";

for (const { origin, host, connects } of [
    {
        origin: "https://attendance.example.test",
        host: "127.0.0.1",
        connects: false,
    },
    { origin: "http://127.0.0.1:8000", host: "127.0.0.1", connects: true },
    {
        origin: "https://attendance.example.test",
        host: "reverb.example.test",
        connects: true,
    },
]) {
    test(`workspace at ${origin} ${connects ? "connects to" : "polls instead of connecting to"} ${host}`, async ({
        page,
    }) => {
        const build = new URL("../../public/build/", import.meta.url);
        const manifest = JSON.parse(
            readFileSync(new URL("manifest.json", build), "utf8"),
        );
        const entry = manifest["resources/js/app.js"].file;
        const channel = "workspace.synthetic";
        const config = {
            channel,
            stateUrl: origin + "/realtime/state",
            key: "synthetic-key",
            host,
            poll_seconds: 15,
            port: host === "127.0.0.1" ? 8080 : 443,
            scheme: host === "127.0.0.1" ? "http" : "https",
        };
        const html = `<!doctype html><meta name="csrf-token" content="synthetic">
            <div data-sync-status></div><main data-sync-region="page"></main>
            <script id="workspace-sync-config" type="application/json">${JSON.stringify(config)}</script>
            <script type="module" src="/build/${entry}"></script>`;

        const sockets = [];
        await page.routeWebSocket("**/app/**", (socket) => {
            sockets.push(new URL(socket.url()).hostname);
            socket.close();
        });
        await page.route(origin + "/**", async (route) => {
            const { pathname } = new URL(route.request().url());
            if (pathname === "/realtime/state") {
                await route.fulfill({
                    json: { channel, revisions: {}, unread: 0 },
                });
            } else if (pathname.startsWith("/build/")) {
                await route.fulfill({
                    body: readFileSync(new URL(pathname.slice(7), build)),
                    contentType: "text/javascript",
                });
            } else {
                await route.fulfill({ body: html, contentType: "text/html" });
            }
        });

        const state = page.waitForResponse(origin + "/realtime/state");
        await page.goto(origin + "/employee/dashboard");
        expect((await state).ok()).toBe(true);
        if (connects) {
            await expect.poll(() => sockets).toContain(host);
        } else {
            await expect(page.locator("[data-sync-status]")).toHaveText(
                "Polling · delayed updates",
            );
            expect(sockets).toEqual([]);
        }
    });
}

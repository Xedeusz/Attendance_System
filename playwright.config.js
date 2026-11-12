import { defineConfig } from "@playwright/test";

export default defineConfig({
    testDir: "./tests/Browser",
    testMatch: "*.spec.js",
    workers: 1,
    retries: 0,
    timeout: 90_000,
    reporter: "list",
    use: {
        baseURL: "http://127.0.0.1:8000",
        channel: "msedge",
        headless: true,
        trace: "off",
        screenshot: "only-on-failure",
    },
});

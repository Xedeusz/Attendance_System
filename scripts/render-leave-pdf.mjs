import { existsSync } from "node:fs";
import { chromium } from "playwright";

const edge = "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe";
const bundledChromium = chromium.executablePath();
const executablePath =
    process.env.LEAVE_PDF_BROWSER_BINARY ||
    (existsSync(bundledChromium) ? bundledChromium : undefined) ||
    (process.platform === "win32" && existsSync(edge) ? edge : undefined);

const chunks = [];
for await (const chunk of process.stdin) {
    chunks.push(chunk);
}

const browser = await chromium.launch({ executablePath, timeout: 15000 });
try {
    const page = await browser.newPage({ javaScriptEnabled: false });
    await page.route(/^https?:/i, (route) => route.abort());
    await page.setContent(Buffer.concat(chunks).toString("utf8"), {
        waitUntil: "load",
        timeout: 5000,
    });
    const pdf = await page.pdf({ preferCSSPageSize: true });
    await new Promise((resolve, reject) => {
        process.stdout.write(pdf, (error) =>
            error ? reject(error) : resolve(),
        );
    });
} finally {
    await browser.close();
}

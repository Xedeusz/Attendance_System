import { expect } from "@playwright/test";

export async function enrollSignature(page, supervisor) {
    await page.goto("/supervisor/account/electronic-signature", {
        waitUntil: "domcontentloaded",
    });
    const png = await page.evaluate(() => {
        const canvas = document.createElement("canvas");
        canvas.width = 200;
        canvas.height = 60;
        const ctx = canvas.getContext("2d");
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(16, 48);
        ctx.bezierCurveTo(100, -25, 31, 83, 107, 33);
        ctx.lineTo(173, 18);
        ctx.lineTo(128, 44);
        ctx.stroke();
        return canvas.toDataURL("image/png").split(",")[1];
    });
    await page.getByLabel("Signature image", { exact: true }).setInputFiles({
        name: "qa-signature.png",
        mimeType: "image/png",
        buffer: Buffer.from(png, "base64"),
    });
    await expect(page.getByAltText("Selected signature preview")).toBeVisible();
    await page
        .getByLabel("Confirm your current password", { exact: true })
        .fill(supervisor.password);
    await page
        .getByRole("button", { name: "Save signature", exact: true })
        .click();
    await expect(page.getByAltText("Your registered signature")).toBeVisible();
}

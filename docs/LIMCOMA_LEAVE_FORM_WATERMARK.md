# LIMCOMA leave form watermark

The **LMPC Leave Application Form (HRD No. 23/Rev.2)** uses the original [LIMCOMA.png](../public/LIMCOMA.png). This asset is byte-identical to the copy in `resources/images/LIMCOMA.png` and includes the corporate symbol, wordmark, original colors and “Since 1970” text.

The shared Blade template centers the image behind the form at **10% opacity**. The image box takes up 44% of the form width, with proportional scaling. Form text, borders, checkbox markings and the Supervisor signature are on the foreground layer. The employee signature line is blank.

Authorized HTML previews load the original PNG from the same origin. The existing Playwright/Chromium PDF renderer receives the exact PNG bytes embedded as a data URL, allowing Windows/XAMPP to render it without an asset request. It is a positioned image, so normal print output retains it independently of the browser’s background graphics preference.

## Document verification and privacy

Approval reuses the existing unique document reference in the footer, with format `LMPC-LA-{request id}-01`. The application stores the SHA-256 digest of the finalized PDF and checks that digest when serving the decrypted bytes. The reference and digest support verification of the stored copy. The electronic approval record links the Supervisor, signature version and approval timestamp to the document. The watermark serves as a visual deterrent.

PDFs are generated once at approval and encrypted on the private leave disk. Their bytes stay fixed after changes to the watermark asset. The owner, HR and the currently assigned Supervisor use the existing authorized view and download routes. Access and approval events remain auditable.

## Verification

All 62 focused PHPUnit tests and the Playwright leave workflow passed. The browser test checked centering, opacity and print media in the unsigned preview. It verified the logo and Supervisor signature in the downloaded one-page A4 landscape PDF. The synthetic PDF was rendered and inspected in color and simulated grayscale. Physical printer output was outside this verification.

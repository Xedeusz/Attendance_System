# LMPC Leave Application Form — HRD No. 23/Rev.2

The Blade template follows the supplied photograph. It uses a black-and-white A4 landscape page with submission details, three leave-type boxes, a reason area, and two Supervisor decision blocks. The employee signature line is blank. Employee signature implementation is deferred.

| Printed field | Saved source |
| --- | --- |
| Name and Sect/Dept/Div. | Encrypted submission snapshot of the employee name and department |
| Date Filed | Server submission timestamp in the configured attendance time zone |
| Inclusive Dates | Saved start and end dates |
| No. of Days | Validated requested duration, including AM or PM for a half-day |
| Vacation Leave and Sick Leave | Configured leave type name or code, mapped at submission |
| Others (pls specify) | Specific configured type name, or the employee's description for a generic Other type |
| Reason | Encrypted request reason |
| Authorized Leave, Approved by, signature | Final Supervisor approval, approver name at signing and registered signature version |
| Unauthorized Leave, Noted by | Blank pending LIMCOMA policy |

Branch is retained for review outside the printed form. Older applications without a snapshot use available profile data and their saved leave type name. Approving an older pending application preserves that available data from then on. Existing completed decisions retain their electronic history.

Users with access can view the saved application before approval. After signing, they can open or download the final PDF. The footer shows the document reference, approval timestamp and chargeable days when applicable. The template can be updated independently of the schema. Each approved PDF preserves the bytes generated at signing.

The original LIMCOMA logo is centered behind the form in previews and new approved PDFs. See [watermark implementation and print verification](LIMCOMA_LEAVE_FORM_WATERMARK.md).

Approved versions include a QR and reference in the footer. [QR verification and versioning](LEAVE_QR_VERIFICATION.md)

## Renderer setup

The renderer uses Node, the existing Playwright package, and headless Chromium or Microsoft Edge. Install the existing JavaScript dependencies, including development dependencies, on the host serving approval requests. The XAMPP setup resolves the project-local Node binary and standard Edge path when available. Set `LEAVE_PDF_NODE_BINARY` or `LEAVE_PDF_BROWSER_BINARY` to absolute paths when using other locations. PHP must be able to launch the browser and write to the private leave disk. Rendering passes HTML and PDF bytes over process pipes. The browser page uses escaped content with JavaScript and network requests disabled.

Before production use, authorized LIMCOMA HR personnel must approve the digital template and signing procedure, including the blank employee line and treatment of rejected requests.

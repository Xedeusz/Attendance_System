# Supervisor electronic signature

1. Sign in as a Supervisor and open **My account → Electronic Signature**.
2. Select your PNG or JPEG signature image. Each side must be 20–2400 pixels, with a maximum file size of 2 MB.
3. Review the preview, confirm your current password and select **Save signature** or **Replace signature**.
4. On an assigned pending request, review the application, enter your current password, select **Approve & Sign**, and confirm the dialog.

Registration creates a version. Replacement revokes the previous version for future approvals. The server checks the displayed version at signing, so requests opened before a replacement must be reloaded. Previously approved PDFs retain their original embedded signature and version metadata.

The private leave disk holds the encrypted, normalized PNG. The image endpoint only serves the current signature to its authenticated Supervisor owner. Final approved PDFs have separate leave-request authorization. Audit entries capture enrollment, replacement, signing confirmation, PDF generation and retrieval without images or passwords. Protect the private disk, application encryption key and backups together.

This workflow applies an electronic signature image. Certificate-based signing is outside its scope. Authorized LIMCOMA HR personnel must approve the signing procedure before production use.

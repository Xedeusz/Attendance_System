<?php

return [
    'document_max_kilobytes' => (int) env('LEAVE_DOCUMENT_MAX_KILOBYTES', 5120),
    'document_retention_days' => env('LEAVE_DOCUMENT_RETENTION_DAYS') === null ? null : (int) env('LEAVE_DOCUMENT_RETENTION_DAYS'),
    'max_request_calendar_days' => 366,
    'pdf_node_binary' => env('LEAVE_PDF_NODE_BINARY'),
    'pdf_browser_binary' => env('LEAVE_PDF_BROWSER_BINARY'),
    'verification_url' => env('LEAVE_VERIFICATION_URL'),
    'cancellation' => 'pending_only',
];

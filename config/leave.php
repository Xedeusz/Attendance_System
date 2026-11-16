<?php

return [
    'document_max_kilobytes' => (int) env('LEAVE_DOCUMENT_MAX_KILOBYTES', 5120),
    'document_retention_days' => env('LEAVE_DOCUMENT_RETENTION_DAYS') === null ? null : (int) env('LEAVE_DOCUMENT_RETENTION_DAYS'),
    'max_request_calendar_days' => 366,
    'cancellation' => 'pending_only',
];

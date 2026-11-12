<?php

return [
    'timezone' => 'Asia/Manila',
    'capture_seconds' => (int) env('ATTENDANCE_CAPTURE_SECONDS', 180),
    'location_max_age_seconds' => (int) env('ATTENDANCE_LOCATION_MAX_AGE_SECONDS', 45),
    'max_accuracy_meters' => (float) env('ATTENDANCE_MAX_ACCURACY_METERS', 50),
    'allow_review_requests' => (bool) env('ATTENDANCE_ALLOW_REVIEW_REQUESTS', true),
    'photo_retention_days' => (int) env('ATTENDANCE_PHOTO_RETENTION_DAYS', 30),
    'pending_review_hold_days' => (int) env('ATTENDANCE_REVIEW_HOLD_DAYS', 90),
    'open_warning_hours' => 24,
    'policy_version' => 'photo-gps-v1',
];

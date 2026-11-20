<?php

return [
    'enabled' => (bool) env('PRIVACY_NOTICES_ENABLED', false),
    'publisher_ids' => array_values(array_map('intval', array_filter(array_map('trim', explode(',', (string) env('PRIVACY_NOTICE_PUBLISHER_IDS', ''))), 'ctype_digit'))),
];

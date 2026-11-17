<?php

return [
    'import' => [
        'disk' => 'local',
        'max_kilobytes' => (int) env('MEMBERSHIP_IMPORT_MAX_KILOBYTES', 10240),
        'max_rows' => (int) env('MEMBERSHIP_IMPORT_MAX_ROWS', 10000),
        'chunk_size' => (int) env('MEMBERSHIP_IMPORT_CHUNK_SIZE', 500),
    ],
];

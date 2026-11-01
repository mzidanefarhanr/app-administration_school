<?php

return [
    'disk' => env('LETTERS_DISK', 'local'),
    'attachments_path' => 'attachments/letters',
    'max_file_kb' => (int) env('LETTERS_MAX_FILE_KB', 10240),
    'max_files_per_letter' => (int) env('LETTERS_MAX_FILES_PER_LETTER', 5),
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
    'allowed_mimetypes' => ['application/pdf', 'image/jpeg', 'image/png'],
    'categories' => [
        'Government',
        'Internal',
        'Invitation',
        'Notification',
        'Request',
        'Partnership',
        'Other',
    ],
    'statuses' => ['active', 'void'],
    'search_max_length' => 100,
    'per_page_options' => [10, 20, 50, 100],
    'default_per_page' => 20,
    'events_limit' => 100,
];

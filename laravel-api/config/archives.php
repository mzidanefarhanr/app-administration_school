<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Archive Categories
    |--------------------------------------------------------------------------
    | Fixed taxonomy for Phase 09a; no master-data CRUD is exposed.
    */
    'categories' => [
        'student_record' => 'Student Record',
        'employee_record' => 'Employee Record',
        'letter' => 'Letter',
        'leave' => 'Leave',
        'school_document' => 'School Document',
        'other' => 'Other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Subject Links
    |--------------------------------------------------------------------------
    | Only these domain subjects may be linked. The domain ability is required
    | in addition to archives.view/archives.manage.
    */
    'subject_types' => [
        'student' => ['label' => 'Student', 'ability' => 'students.view'],
        'employee' => ['label' => 'Employee', 'ability' => 'employees.view'],
        'letter' => ['label' => 'Letter', 'ability' => 'letters.view'],
        'leave' => ['label' => 'Leave', 'ability' => 'leaves.view'],
    ],

    'validity_statuses' => [
        'none' => 'No Expiry',
        'upcoming' => 'Upcoming',
        'valid' => 'Valid',
        'expiring_soon' => 'Expiring Soon',
        'expired' => 'Expired',
    ],

    'expiring_soon_days' => 30,

    'title' => ['min' => 3, 'max' => 200],
    'document_number' => ['max' => 100],
    'description' => ['max' => 1000],
    'version_note' => ['max' => 500],

    'attachment' => [
        'disk' => 'local',
        'path' => 'attachments/archives',
        'max_file_kb' => 10240,
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
        'allowed_mimetypes' => ['application/pdf', 'image/jpeg', 'image/png'],
    ],

    'per_page_options' => [10, 20, 50, 100],
    'default_per_page' => 20,
    'search_max_length' => 100,
    'subject_picker_limit' => 20,
];

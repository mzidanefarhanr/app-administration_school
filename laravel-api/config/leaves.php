<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Leave types
    |--------------------------------------------------------------------------
    |
    | Stable codes per subject type. Labels are English (DEC-14). There is no
    | master CRUD for leave types; changing this config never rewrites history.
    |
    */
    'types' => [
        'student' => [
            'sick' => ['label' => 'Sick Leave', 'max_days' => 30, 'attachment_required' => false],
            'permission' => ['label' => 'Permission', 'max_days' => 30, 'attachment_required' => false],
            'other' => ['label' => 'Other', 'max_days' => 30, 'attachment_required' => false],
        ],
        'employee' => [
            'annual' => ['label' => 'Annual Leave', 'max_days' => 30, 'attachment_required' => false],
            'sick' => ['label' => 'Sick Leave', 'max_days' => 30, 'attachment_required' => false],
            'maternity' => ['label' => 'Maternity/Paternity', 'max_days' => 90, 'attachment_required' => false],
            'official_duty' => ['label' => 'Official Duty', 'max_days' => 30, 'attachment_required' => false],
            'other' => ['label' => 'Other', 'max_days' => 30, 'attachment_required' => false],
        ],
    ],

    'subject_types' => [
        'student' => 'Student',
        'employee' => 'Employee',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ],

    'max_range_days' => 90,

    'attachment' => [
        'disk' => env('LEAVES_DISK', 'local'),
        'path' => 'attachments/leaves',
        'max_file_kb' => 5120,
        'max_files_per_request' => 3,
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
        'allowed_mimetypes' => ['application/pdf', 'image/jpeg', 'image/png'],
    ],

    'reason' => ['min' => 5, 'max' => 1000],
    'decision_reason' => ['min' => 10, 'max' => 500],
    'cancel_reason' => ['min' => 10, 'max' => 255],

    'per_page_options' => [10, 20, 50, 100],
    'default_per_page' => 10,
    'search_max_length' => 100,
    'subject_picker_limit' => 50,
];

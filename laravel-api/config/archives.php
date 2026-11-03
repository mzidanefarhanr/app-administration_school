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

    /*
    |--------------------------------------------------------------------------
    | Lifecycle (Phase 09b, DEC-38 D5)
    |--------------------------------------------------------------------------
    | No soft delete: the state is derived from archived_at. Archived
    | documents stay readable but reject metadata edits and new versions.
    */
    'statuses' => [
        'active' => 'Active',
        'archived' => 'Archived',
    ],

    // 'all' adalah alias filter daftar, bukan status yang tersimpan.
    'status_filter_options' => ['active', 'archived', 'all'],

    'archive_reason' => ['min' => 10, 'max' => 255],

    /*
    |--------------------------------------------------------------------------
    | Access Audit (Phase 09b, DEC-38 D3)
    |--------------------------------------------------------------------------
    | Append-only log of authorized download/preview events. Reading requires
    | audit.view in addition to archives.view and the subject domain ability.
    */
    'access_log' => [
        'actions' => [
            'download' => 'Download',
            'preview' => 'Preview',
        ],
        'ability' => 'audit.view',
        'user_agent_max' => 255,
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiry Task (Phase 09b, DEC-38 D4)
    |--------------------------------------------------------------------------
    | Computed on read from valid_until; no table, job, or notification.
    */
    'expiring_task' => [
        'default_limit' => 10,
        'max_limit' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Policy (Phase 09b, DEC-38 D6 Option A)
    |--------------------------------------------------------------------------
    | Proposal used by the read-only report and the dry-run console command.
    | A null period means permanent. Purge execution is NOT authorized.
    */
    'retention' => [
        'anchor' => 'document_date',
        'unit' => 'years',
        'periods' => [
            'student_record' => ['years' => null, 'label' => 'Permanent'],
            'employee_record' => ['years' => 5, 'label' => '5 years after the document date'],
            'letter' => ['years' => 5, 'label' => '5 years after the document date'],
            'leave' => ['years' => 2, 'label' => '2 years after the document date'],
            'school_document' => ['years' => 10, 'label' => '10 years after the document date'],
            'other' => ['years' => 5, 'label' => '5 years after the document date'],
        ],
        'access_log_years' => 5,
    ],

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

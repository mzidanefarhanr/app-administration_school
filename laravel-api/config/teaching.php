<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active Days of Week (DEC-39 D3)
    |--------------------------------------------------------------------------
    | ISO-8601 numeric representation of days (1 = Monday .. 6 = Saturday).
    */
    'days_of_week' => [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ],

    /*
    |--------------------------------------------------------------------------
    | Standard Teaching Period Presets (DEC-39 D3 / DEC-10)
    |--------------------------------------------------------------------------
    | Default 45-minute school periods (1..12) with standard breaks.
    | Operators can select a start/end period to prefill start_time/end_time
    | or customize HH:MM per slot (e.g. Friday schedule).
    */
    'period_presets' => [
        1 => ['period' => 1, 'label' => 'Period 1', 'start_time' => '07:00', 'end_time' => '07:45'],
        2 => ['period' => 2, 'label' => 'Period 2', 'start_time' => '07:45', 'end_time' => '08:30'],
        3 => ['period' => 3, 'label' => 'Period 3', 'start_time' => '08:30', 'end_time' => '09:15'],
        4 => ['period' => 4, 'label' => 'Period 4', 'start_time' => '09:15', 'end_time' => '10:00'],
        // 10:00 - 10:15 First Break
        5 => ['period' => 5, 'label' => 'Period 5', 'start_time' => '10:15', 'end_time' => '11:00'],
        6 => ['period' => 6, 'label' => 'Period 6', 'start_time' => '11:00', 'end_time' => '11:45'],
        7 => ['period' => 7, 'label' => 'Period 7', 'start_time' => '11:45', 'end_time' => '12:30'],
        // 12:30 - 13:00 Midday Break
        8 => ['period' => 8, 'label' => 'Period 8', 'start_time' => '13:00', 'end_time' => '13:45'],
        9 => ['period' => 9, 'label' => 'Period 9', 'start_time' => '13:45', 'end_time' => '14:30'],
        10 => ['period' => 10, 'label' => 'Period 10', 'start_time' => '14:30', 'end_time' => '15:15'],
        11 => ['period' => 11, 'label' => 'Period 11', 'start_time' => '15:15', 'end_time' => '16:00'],
        12 => ['period' => 12, 'label' => 'Period 12', 'start_time' => '16:00', 'end_time' => '16:45'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Allowed Period & Weekly Hours
    |--------------------------------------------------------------------------
    */
    'max_period' => 12,
    'max_hours_per_week' => 24,

    /*
    |--------------------------------------------------------------------------
    | Schedule Exception Types (DEC-39 D5 / DEC-10)
    |--------------------------------------------------------------------------
    */
    'exception_types' => [
        'holiday' => 'Holiday',
        'cancelled' => 'Cancelled',
        'rescheduled' => 'Rescheduled',
    ],

    /*
    |--------------------------------------------------------------------------
    | Teaching Journal Grace Window in Calendar Days (DEC-39 D8 / DEC-10)
    |--------------------------------------------------------------------------
    | Used by Subphase 10b/10c: assigned teachers can fill/update journals
    | from session date D up to D + journal_grace_days (Asia/Jakarta) while
    | the school year is unlocked.
    */
    'journal_grace_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Teaching Journal Delivery Methods (DEC-40 D3 / D7)
    |--------------------------------------------------------------------------
    */
    'journal_methods' => [
        'lecture' => 'Lecture',
        'discussion' => 'Discussion',
        'practicum' => 'Practicum',
        'demonstration' => 'Demonstration',
        'project' => 'Project',
        'assignment' => 'Assignment',
        'assessment' => 'Assessment',
        'other' => 'Other',
    ],
];

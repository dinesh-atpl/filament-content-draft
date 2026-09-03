<?php

use App\Models\User;

return [
    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    | The database table name used to store content drafts.
    */
    'table_name' => env('CONTENT_DRAFT_TABLE_NAME', 'content_drafts'),

    /*
    |--------------------------------------------------------------------------
    | Poll Interval
    |--------------------------------------------------------------------------
    | How often (in seconds) the form auto-saves a draft via wire:poll.
    */
    'poll_interval' => env('CONTENT_DRAFT_POLL_INTERVAL', 5),

    /*
    |--------------------------------------------------------------------------
    | Prune After Days
    |--------------------------------------------------------------------------
    | Drafts older than this many days are deleted by `drafts:prune`.
    */
    'prune_after_days' => env('CONTENT_DRAFT_PRUNE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    | The fully-qualified class name of your User model.
    | Used for the foreign key in the migration and the relationship.
    */
    'user_model' => User::class,

    /*
    |--------------------------------------------------------------------------
    | Indicator Position
    |--------------------------------------------------------------------------
    | Where the "Draft saved at HH:MM:SS" indicator badge should be displayed.
    |
    | Supported options:
    |   - 'under-form' (default, inline directly beneath the form fields)
    |   - 'bottom-right' (floating fixed pill at bottom-right of screen)
    |   - 'bottom-left' (floating fixed pill at bottom-left of screen)
    |   - 'top-right' (floating fixed pill at top-right of screen)
    |   - 'top-left' (floating fixed pill at top-left of screen)
    */
    'position' => env('CONTENT_DRAFT_POSITION', 'under-form'),

    /*
    |--------------------------------------------------------------------------
    | Lock Form While Draft Restore Pending
    |--------------------------------------------------------------------------
    | Whether the form should be disabled/locked until the user clicks
    | "Restore" or "Discard" from the unsaved draft banner.
    | Defaults to false (form remains editable).
    */
    'lock_form_while_draft_pending' => env('CONTENT_DRAFT_LOCK_FORM', false),

    /*
    |--------------------------------------------------------------------------
    | Excluded / Sensitive Fields
    |--------------------------------------------------------------------------
    | Form fields that should never be stored in content drafts (e.g. passwords).
    | Matching is case-insensitive and strips nested keys recursively.
    */
    'except_fields' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
    ],
];

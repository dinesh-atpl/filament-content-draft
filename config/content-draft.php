<?php

use Filament\View\PanelsRenderHook;

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
    'user_model' => env('CONTENT_DRAFT_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Render Hook
    |--------------------------------------------------------------------------
    | Which Filament render hook to attach the poller to.
    | Default: immediately after form / before footer widgets.
    */
    'render_hook' => PanelsRenderHook::PAGE_FOOTER_WIDGETS_BEFORE,

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
];

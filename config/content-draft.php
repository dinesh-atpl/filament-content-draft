<?php

return [
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
    | Default: after the page footer widgets (unobtrusive).
    */
    'render_hook' => \Filament\View\PanelsRenderHook::PAGE_FOOTER_WIDGETS_AFTER,
];

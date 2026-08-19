<?php

namespace Konectar\FilamentContentDraft\Commands;

use Illuminate\Console\Command;
use Konectar\FilamentContentDraft\Models\ContentDraft;

class PruneContentDraftsCommand extends Command
{
    protected $signature = 'drafts:prune {--days= : Override the config prune_after_days value}';

    protected $description = 'Delete content drafts older than N days (default: from config content-draft.prune_after_days)';

    public function handle(): void
    {
        $days = (int) ($this->option('days') ?? config('content-draft.prune_after_days', 7));

        $deleted = ContentDraft::where('updated_at', '<', now()->subDays($days))->delete();

        $this->info("Pruned {$deleted} stale draft(s) older than {$days} day(s).");
    }
}

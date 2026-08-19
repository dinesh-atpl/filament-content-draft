<?php

namespace Konectar\FilamentContentDraft;

use Illuminate\Console\Scheduling\Schedule;
use Konectar\FilamentContentDraft\Commands\PruneContentDraftsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ContentDraftServiceProvider extends PackageServiceProvider
{
    public static string $name = 'content-draft';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_content_drafts_table')
            ->runsMigrations()
            ->hasCommand(PruneContentDraftsCommand::class);
    }

    public function packageBooted(): void
    {
        // Register the daily prune schedule
        $this->callAfterResolving(
            Schedule::class,
            function (Schedule $schedule) {
                $schedule->command('drafts:prune')->daily();
            }
        );
    }
}

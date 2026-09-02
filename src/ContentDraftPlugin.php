<?php

namespace Konectar\FilamentContentDraft;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;

class ContentDraftPlugin implements Plugin
{
    protected ?string $tableName = null;

    protected ?int $pollInterval = null;

    protected ?int $pruneAfterDays = null;

    protected ?string $userModel = null;

    protected ?string $renderHook = null;

    protected ?string $position = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'content-draft';
    }

    public function tableName(string $tableName): static
    {
        $this->tableName = $tableName;

        return $this;
    }

    public function pollInterval(int $seconds): static
    {
        $this->pollInterval = $seconds;

        return $this;
    }

    public function pruneAfterDays(int $days): static
    {
        $this->pruneAfterDays = $days;

        return $this;
    }

    public function userModel(string $model): static
    {
        $this->userModel = $model;

        return $this;
    }

    public function renderHook(string $hook): static
    {
        $this->renderHook = $hook;

        return $this;
    }

    public function position(string $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getTableName(): string
    {
        return $this->tableName ?? (string) config('content-draft.table_name', 'content_drafts');
    }

    public function getPollInterval(): int
    {
        return $this->pollInterval ?? (int) config('content-draft.poll_interval', 5);
    }

    public function getPruneAfterDays(): int
    {
        return $this->pruneAfterDays ?? (int) config('content-draft.prune_after_days', 7);
    }

    public function getUserModel(): string
    {
        return $this->userModel ?? (string) config('content-draft.user_model', 'App\\Models\\User');
    }

    public function getRenderHook(): string
    {
        return $this->renderHook ?? (string) config('content-draft.render_hook', PanelsRenderHook::PAGE_FOOTER_WIDGETS_BEFORE);
    }

    public function getPosition(): string
    {
        return $this->position ?? (string) config('content-draft.position', 'under-form');
    }

    public function register(Panel $panel): void
    {
        // No resources/pages to register — this plugin works via traits
    }

    public function boot(Panel $panel): void
    {
        // Panel-level boot
    }
}

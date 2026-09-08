<?php

namespace Konectar\FilamentContentDraft\Tests\Feature;

use Konectar\FilamentContentDraft\ContentDraftPlugin;
use Konectar\FilamentContentDraft\Tests\Fixtures\User;
use Konectar\FilamentContentDraft\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PluginTest extends TestCase
{
    #[DataProvider('settings')]
    public function test_builder_overrides_config_without_mutating_global_configuration(string $configKey, string $setter, string $getter, mixed $configured, mixed $override): void
    {
        config()->set('content-draft.'.$configKey, $configured);
        $plugin = ContentDraftPlugin::make();
        $this->assertSame($configured, $plugin->{$getter}());
        $this->assertSame($plugin, $plugin->{$setter}($override));
        $this->assertSame($override, $plugin->{$getter}());
        $this->assertSame($configured, config('content-draft.'.$configKey));
    }

    public static function settings(): array
    {
        return [
            ['table_name', 'tableName', 'getTableName', 'drafts', 'custom_drafts'],
            ['poll_interval', 'pollInterval', 'getPollInterval', 5, 10],
            ['prune_after_days', 'pruneAfterDays', 'getPruneAfterDays', 7, 30],
            ['user_model', 'userModel', 'getUserModel', 'App\\Models\\User', User::class],
            ['render_hook', 'renderHook', 'getRenderHook', 'hook.before', 'hook.after'],
            ['position', 'position', 'getPosition', 'under-form', 'bottom-right'],
            ['lock_form_while_draft_pending', 'lockFormWhileDraftPending', 'shouldLockFormWhileDraftPending', true, false],
        ];
    }

    public function test_plugin_can_be_resolved_from_current_panel(): void
    {
        $plugin = ContentDraftPlugin::make()->lockFormWhileDraftPending();
        \Filament\Facades\Filament::getCurrentPanel()->plugin($plugin);
        $this->assertSame('content-draft', $plugin->getId());
        $this->assertTrue($plugin->shouldLockFormWhileDraftPending());
        $this->assertSame($plugin, ContentDraftPlugin::get());
    }
}

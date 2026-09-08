<?php

namespace Konectar\FilamentContentDraft\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Konectar\FilamentContentDraft\Tests\TestCase;

class StorageAndPruningTest extends TestCase
{
    public function test_payload_round_trips_and_user_relationship_resolves(): void
    {
        $payload = ['nested' => [['title' => 'Draft', 'enabled' => false, 'count' => 0, 'optional' => null]]];
        $draft = $this->draft(payload: $payload)->fresh();
        $this->assertSame($payload, $draft->payload);
        $this->assertTrue($draft->user->is(auth()->user()));
    }

    public function test_database_rejects_duplicate_user_and_key(): void
    {
        $this->draft();
        $this->expectException(QueryException::class);
        $this->draft();
    }

    public function test_deleting_user_cascades_to_drafts(): void
    {
        $this->draft();
        auth()->user()->delete();
        $this->assertDatabaseCount('content_drafts', 0);
    }

    public function test_custom_table_migration_and_model_agree_and_rollback_works(): void
    {
        config()->set('content-draft.table_name', 'custom_drafts');
        $migration = require __DIR__.'/../../database/migrations/create_content_drafts_table.php.stub';
        $migration->up();
        $draft = $this->draft();
        $this->assertSame('custom_drafts', $draft->getTable());
        $this->assertDatabaseCount('custom_drafts', 1);
        $migration->down();
        $this->assertFalse(Schema::hasTable('custom_drafts'));
        $this->assertTrue(Schema::hasTable('content_drafts'));
    }

    public function test_prune_uses_updated_time_and_keeps_exact_cutoff(): void
    {
        config()->set('content-draft.prune_after_days', 3);
        $stale = $this->draft('stale');
        $stale->forceFill(['updated_at' => now()->subDays(3)->subSecond()])->save();
        $boundary = $this->draft('boundary');
        $boundary->forceFill(['updated_at' => now()->subDays(3)])->save();
        $recent = $this->draft('recent');
        $recent->forceFill(['created_at' => now()->subDays(30)])->save();
        $this->artisan('drafts:prune')->expectsOutput('Pruned 1 stale draft(s) older than 3 day(s).')->assertSuccessful();
        $this->assertModelMissing($stale);
        $this->assertModelExists($boundary);
        $this->assertModelExists($recent);
    }

    public function test_days_option_overrides_configuration_and_empty_run_succeeds(): void
    {
        config()->set('content-draft.prune_after_days', 30);
        $draft = $this->draft();
        $draft->forceFill(['updated_at' => now()->subDays(4)])->save();
        $this->artisan('drafts:prune', ['--days' => 2])->expectsOutput('Pruned 1 stale draft(s) older than 2 day(s).')->assertSuccessful();
        $this->artisan('drafts:prune')->expectsOutput('Pruned 0 stale draft(s) older than 30 day(s).')->assertSuccessful();
    }

    public function test_provider_registers_views_config_and_daily_pruning(): void
    {
        $this->assertSame(5, config('content-draft.poll_interval'));
        $this->assertTrue(view()->exists('content-draft::content-draft-banner'));
        $this->assertTrue(view()->exists('content-draft::content-draft-poller'));
        $events = array_values(array_filter(app(Schedule::class)->events(), fn ($event) => str_contains($event->command ?? '', 'drafts:prune')));
        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events[0]->expression);
    }
}

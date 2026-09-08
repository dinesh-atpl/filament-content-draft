<?php

namespace Konectar\FilamentContentDraft\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Konectar\FilamentContentDraft\ContentDraftServiceProvider;
use Konectar\FilamentContentDraft\Models\ContentDraft;
use Konectar\FilamentContentDraft\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [\Livewire\LivewireServiceProvider::class, \Filament\Support\SupportServiceProvider::class, \Filament\FilamentServiceProvider::class, ContentDraftServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        $app['config']->set('content-draft.user_model', User::class);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Panel::make()->id('testing')->authGuard('web'));
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        $migration = require __DIR__.'/../database/migrations/create_content_drafts_table.php.stub';
        $migration->up();
        $this->actingAs(User::create(['name' => 'Author']));
        $this->freezeTime();
    }

    protected function draft(string $key = 'posts-create', array $payload = ['title' => 'Saved draft'], ?int $userId = null): ContentDraft
    {
        return ContentDraft::create(['user_id' => $userId ?? auth()->id(), 'key' => $key, 'payload' => $payload]);
    }
}

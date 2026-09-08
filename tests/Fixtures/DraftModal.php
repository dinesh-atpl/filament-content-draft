<?php

namespace Konectar\FilamentContentDraft\Tests\Fixtures;

use Konectar\FilamentContentDraft\Concerns\RecoversModalContentDraft;

class DraftModal
{
    use RecoversModalContentDraft;

    public array $mountedActions = [];
    public array $mountedTableActions = [];
    public array $events = [];

    public static function getSlug(): string { return 'posts'; }
    public function modalContentDraftExcept(): array { return ['api_token']; }
    public function dispatch(string $event, ...$params): void { $this->events[$event] = $params; }
}

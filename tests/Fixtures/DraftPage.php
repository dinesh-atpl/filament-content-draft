<?php

namespace Konectar\FilamentContentDraft\Tests\Fixtures;

use Konectar\FilamentContentDraft\Concerns\RecoversContentDraft;

// A small host for exercising the trait with real Laravel persistence.
class DraftPage
{
    use RecoversContentDraft;

    public array $data = [];
    public array $events = [];
    public object $form;

    public function __construct()
    {
        $this->form = new class {
            public array $state = [];
            public function fill(array $state): void { $this->state = $state; }
        };
    }

    public static function getSlug(): string { return 'posts'; }
    public function contentDraftExcept(): array { return ['api_token']; }
    public function dispatch(string $event, ...$params): void { $this->events[$event] = $params; }
}

<?php

namespace Konectar\FilamentContentDraft\Tests\Feature;

use Konectar\FilamentContentDraft\Models\ContentDraft;
use Konectar\FilamentContentDraft\Tests\Fixtures\DraftModal;
use Konectar\FilamentContentDraft\Tests\Fixtures\User;
use Konectar\FilamentContentDraft\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ModalDraftTest extends TestCase
{
    #[DataProvider('actions')]
    public function test_saves_supported_actions_with_record_scoped_keys(array $action, string $key, string $property): void
    {
        $modal = new DraftModal;
        $modal->{$property} = [$action + ['data' => ['title' => 'First', 'nested' => ['PASSWORD' => 'secret', 'api_token' => 'secret', 'count' => 0]]]];
        $modal->saveDraft();
        $modal->{$property}[0]['data']['title'] = 'Updated';
        $modal->saveDraft();
        $this->assertDatabaseCount('content_drafts', 1);
        $draft = ContentDraft::first();
        $this->assertSame($key, $draft->key);
        $this->assertSame(['title' => 'Updated', 'nested' => ['count' => 0]], $draft->payload);
        $this->assertArrayHasKey('content-draft-saved', $modal->events);
    }

    public static function actions(): array
    {
        return [
            'create' => [['name' => 'create'], 'posts-create', 'mountedActions'],
            'table edit context' => [['name' => 'edit', 'context' => ['recordKey' => 42]], 'posts-edit-42', 'mountedActions'],
            'edit arguments' => [['name' => 'edit', 'arguments' => ['record' => 'uuid-record']], 'posts-edit-uuid-record', 'mountedActions'],
            'legacy table action' => [['name' => 'create'], 'posts-create', 'mountedTableActions'],
        ];
    }

    public function test_closed_unsupported_and_empty_modals_do_not_save(): void
    {
        $modal = new DraftModal;
        $modal->saveDraft();
        $modal->mountedActions = [['name' => 'delete', 'data' => ['title' => 'Ignored']]];
        $modal->saveDraft();
        $modal->mountedActions = [['name' => 'create', 'data' => ['nested' => [null, '', []]]]];
        $modal->saveDraft();
        $this->assertDatabaseCount('content_drafts', 0);
    }

    public function test_pending_restore_protects_payload_and_restore_keeps_backup(): void
    {
        $draft = $this->draft();
        $modal = new DraftModal;
        $modal->mountedActions = [['name' => 'create', 'data' => ['title' => 'Default']]];
        $modal->renderingRecoversModalContentDraft();
        $this->assertTrue($modal->modalDraftRestorePending);
        $modal->saveDraft();
        $this->assertSame($draft->payload, $draft->fresh()->payload);
        $modal->restoreCreateDraft();
        $modal->renderingRecoversModalContentDraft();
        $this->assertFalse($modal->modalDraftRestorePending);
        $this->assertTrue($modal->modalDraftRestoreDecisionMade);
        $this->assertSame($draft->payload, $modal->mountedActions[0]['data']);
        $this->assertModelExists($draft);
    }

    public function test_edit_restore_resolves_record_from_action_context(): void
    {
        $draft = $this->draft('posts-edit-42');
        $modal = new DraftModal;
        $modal->mountedActions = [['name' => 'edit', 'context' => ['recordKey' => 42], 'data' => []]];
        $modal->restoreEditDraft();
        $this->assertSame($draft->payload, $modal->mountedActions[0]['data']);
        $this->assertModelExists($draft);
    }

    public function test_discard_and_successful_save_preserve_other_users_and_forms(): void
    {
        $this->draft();
        $other = User::create(['name' => 'Other']);
        $otherDraft = $this->draft(userId: $other->id);
        $editDraft = $this->draft('posts-edit-42');
        $modal = new DraftModal;
        $modal->mountedActions = [['name' => 'create', 'data' => []]];
        $modal->renderingRecoversModalContentDraft();
        $modal->discardModalContentDraft();
        $this->assertModelExists($otherDraft);
        $this->assertModelExists($editDraft);
        $this->assertDatabaseCount('content_drafts', 2);
        $modal->mountedActions = [['name' => 'edit', 'context' => ['recordKey' => 42], 'data' => []]];
        $modal->renderingRecoversModalContentDraft();
        $modal->clearModalContentDraftAfterSave();
        $this->assertModelMissing($editDraft);
        $this->assertModelExists($otherDraft);
        $this->assertNull($modal->modalDraftActiveKey);
        $this->assertFalse($modal->modalDraftRestorePending);
    }

    public function test_closing_modal_resets_session_and_reopening_offers_draft_again(): void
    {
        $this->draft();
        $modal = new DraftModal;
        $modal->mountedActions = [['name' => 'create', 'data' => []]];
        $modal->renderingRecoversModalContentDraft();
        $modal->restoreCreateDraft();
        $modal->mountedActions = [];
        $modal->renderingRecoversModalContentDraft();
        $this->assertNull($modal->modalDraftActiveKey);
        $this->assertNull($modal->modalContentDraftLastSavedAt);
        $this->assertFalse($modal->modalDraftRestoreDecisionMade);
        $modal->mountedActions = [['name' => 'create', 'data' => []]];
        $modal->renderingRecoversModalContentDraft();
        $this->assertTrue($modal->modalDraftRestorePending);
    }
}

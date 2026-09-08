<?php

namespace Konectar\FilamentContentDraft\Tests\Feature;

use Konectar\FilamentContentDraft\Models\ContentDraft;
use Konectar\FilamentContentDraft\Tests\Fixtures\DraftPage;
use Konectar\FilamentContentDraft\Tests\Fixtures\User;
use Konectar\FilamentContentDraft\Tests\TestCase;

class PageDraftTest extends TestCase
{
    public function test_autosave_updates_one_row_and_strips_nested_sensitive_fields(): void
    {
        $page = new DraftPage;
        $page->mountRecoversContentDraft();
        $page->data = ['title' => 'First', 'Password' => 'secret', 'items' => [['API_TOKEN' => 'secret', 'body' => 'Keep']]];
        $page->saveDraft();
        $page->data['title'] = 'Second';
        $page->saveDraft();

        $this->assertDatabaseCount('content_drafts', 1);
        $this->assertSame(['title' => 'Second', 'items' => [['body' => 'Keep']]], ContentDraft::first()->payload);
        $this->assertSame(now()->format('h:i:s A'), $page->contentDraftLastSavedAt);
        $this->assertArrayHasKey('content-draft-saved', $page->events);
    }

    public function test_pending_restore_protects_draft_even_when_form_matches_reference(): void
    {
        $draft = $this->draft();
        $page = new DraftPage;
        $page->data = ['title' => 'Database title'];
        $page->mountRecoversContentDraft();
        $page->saveDraft();

        $this->assertTrue($page->contentDraftRestorePending);
        $this->assertSame(['title' => 'Saved draft'], $draft->fresh()->payload);
        $page->restoreContentDraft();
        $this->assertFalse($page->contentDraftRestorePending);
        $this->assertSame($draft->payload, $page->form->state);
        $this->assertDatabaseCount('content_drafts', 1);
    }

    public function test_discard_is_scoped_to_current_user_and_form(): void
    {
        $this->draft();
        $other = User::create(['name' => 'Other']);
        $otherDraft = $this->draft(userId: $other->id);
        $differentForm = $this->draft('posts-edit-42');
        $page = new DraftPage;
        $page->mountRecoversContentDraft();
        $page->discardContentDraft();

        $this->assertFalse($page->contentDraftRestorePending);
        $this->assertNull($page->contentDraftLastSavedAt);
        $this->assertDatabaseCount('content_drafts', 2);
        $this->assertModelExists($otherDraft);
        $this->assertModelExists($differentForm);
    }

    public function test_undo_and_successful_save_clear_drafts_and_reset_reference(): void
    {
        $page = new DraftPage;
        $page->data = ['title' => 'Original'];
        $page->mountRecoversContentDraft();
        $page->data = ['title' => 'Changed'];
        $page->saveDraft();
        $page->data = ['title' => 'Original'];
        $page->saveDraft();
        $this->assertDatabaseCount('content_drafts', 0);

        $page->data = ['title' => 'Final'];
        $page->saveDraft();
        $page->clearContentDraftAfterSave();
        $page->saveDraft();
        $this->assertDatabaseCount('content_drafts', 0);
        $this->assertSame($page->data, $page->contentDraftReferenceState);
    }

    public function test_empty_nested_forms_are_skipped_but_zero_is_saved(): void
    {
        $page = new DraftPage;
        $page->data = ['nested' => [null, '', '  ', []], 'password' => 'secret'];
        $page->saveDraft();
        $this->assertDatabaseCount('content_drafts', 0);
        $page->data = ['count' => 0];
        $page->saveDraft();
        $this->assertSame(['count' => 0], ContentDraft::first()->payload);
    }

    public function test_missing_restore_does_not_fill_form_or_create_draft(): void
    {
        $page = new DraftPage;
        $page->contentDraftRestorePending = true;
        $page->restoreContentDraft();
        $this->assertFalse($page->contentDraftRestorePending);
        $this->assertSame([], $page->form->state);
        $this->assertDatabaseCount('content_drafts', 0);
    }
}

<?php

namespace Konectar\FilamentContentDraft\Concerns;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Konectar\FilamentContentDraft\ContentDraftPlugin;
use Konectar\FilamentContentDraft\Models\ContentDraft;
use Livewire\Attributes\On;

trait RecoversContentDraft
{
    /*
    |--------------------------------------------------------------------------
    | Draft Key Resolution & Customization
    |--------------------------------------------------------------------------
    |
    | By default, the key is derived from the resource slug and operation:
    |   Create: '{slug}-create' (e.g. 'users-create')
    |   Edit:   '{slug}-edit-{id}' (e.g. 'users-edit-1')
    |
    | Override contentDraftKey() in your Page class to use a custom key.
    |
    */
    protected function contentDraftKey(): string
    {
        if (method_exists($this, 'getResource')) {
            $slug = str(static::getResource()::getSlug())->replace('/', '-')->toString();
        } elseif (method_exists($this, 'getSlug')) {
            $slug = str(static::getSlug())->replace('/', '-')->toString();
        } else {
            $slug = str(static::class)->classBasename()->kebab()->toString();
        }

        if ($this instanceof EditRecord || (method_exists($this, 'getRecord') && $this->getRecord() instanceof Model)) {
            $record = method_exists($this, 'getRecord') ? $this->getRecord() : ($this->record ?? null);
            if ($record instanceof Model && $record->getKey()) {
                return $slug.'-edit-'.$record->getKey();
            }
        }

        return $slug.'-create';
    }

    /*
    |--------------------------------------------------------------------------
    | Livewire Trait Lifecycle — auto-called on mount
    |--------------------------------------------------------------------------
    */

    public ?string $contentDraftLastSavedAt = null;

    /**
     * Snapshot initial form state, then check for an existing draft.
     * Livewire calls this automatically because of the naming convention
     * mount{TraitName}().
     */
    public function mountRecoversContentDraft(): void
    {
        // Snapshot the form state at load time.
        // On edit pages this is the DB-loaded data; on create pages it's empty/defaults.
        $this->contentDraftReferenceState = $this->data ?? [];

        $draft = ContentDraft::query()
            ->where('user_id', Auth::id())
            ->where('key', $this->contentDraftKey())
            ->first();

        if ($draft) {
            $this->contentDraftLastSavedAt = null;

            // Freeze auto-save until the user resolves the prompt via the inline banner.
            $this->contentDraftRestorePending = true;
        }

        $this->lockFormIfDraftRestorePending();
    }

    public function hydrateRecoversContentDraft(): void
    {
        $this->lockFormIfDraftRestorePending();
    }

    public function renderingRecoversContentDraft(): void
    {
        $this->lockFormIfDraftRestorePending();
    }

    protected function lockFormIfDraftRestorePending(): void
    {
        if (method_exists($this, 'getSchema') && $this->getSchema('form')) {
            $this->getSchema('form')->disabled(fn ($livewire) => (bool) ($livewire->contentDraftRestorePending ?? false));
        }
    }

    /**
     * Register the wire:poll Blade partial via a Filament render hook,
     * scoped to only this page class.
     * Livewire calls this automatically because of the naming convention
     * boot{TraitName}().
     */
    public function bootRecoversContentDraft(): void
    {
        try {
            $hook = ContentDraftPlugin::get()->getRenderHook();
        } catch (\Throwable $e) {
            $hook = config(
                'content-draft.render_hook',
                PanelsRenderHook::PAGE_FOOTER_WIDGETS_BEFORE
            );
        }

        FilamentView::registerRenderHook(
            $hook,
            fn (array $data = []) => view('content-draft::content-draft-poller', $data),
            scopes: static::class,
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_HEADER_WIDGETS_BEFORE,
            fn (array $data = []) => view('content-draft::content-draft-banner', $data),
            scopes: static::class,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Livewire State
    |--------------------------------------------------------------------------
    */

    /**
     * Holds the form data as it was at mount time.
     * Used to detect whether the form has actually changed before saving a draft.
     */
    public array $contentDraftReferenceState = [];

    /**
     * When true, a draft was found on page load and the user has NOT yet
     * responded to the "Restore or Discard?" notification.
     *
     * saveDraft() MUST return early while this is true. Without this guard:
     *  - On CREATE pages: an empty form passes isContentDraftEmpty() but any
     *    pre-filled default values could slip through and overwrite the draft.
     *  - On EDIT pages: the form is pre-filled with DB values, so
     *    matchesContentDraftReferenceState() is immediately true and
     *    clearContentDraft() deletes the draft before the user ever sees
     *    the restore prompt.
     */
    public bool $contentDraftRestorePending = false;

    /*
    |--------------------------------------------------------------------------
    | Auto-save (triggered by wire:poll in the Blade partial)
    |--------------------------------------------------------------------------
    */

    /**
     * Field-agnostic: serialises the entire $this->data array as the draft payload.
     * Called automatically every N seconds by wire:poll.
     */
    public function saveDraft(): void
    {
        $data = $this->data ?? [];

        // Skip saving if the form is completely empty (e.g. user just landed on page)
        if ($this->isContentDraftEmpty($data)) {
            return;
        }

        // ── Guard: Restore decision is pending ───────────────────────────────
        // A draft was found on page load. Do NOT touch the saved draft until
        // the user explicitly chooses "Restore" or "Discard".
        // Without this guard, the matchesContentDraftReferenceState() branch
        // below would call clearContentDraft() on the very first poll tick
        // for edit pages (where the form equals the DB-loaded reference state),
        // silently deleting the draft before the user ever sees the prompt.
        if ($this->contentDraftRestorePending) {
            return;
        }

        // If the current data is identical to the reference (loaded) state,
        // clear any existing draft and bail — nothing has changed.
        if ($this->matchesContentDraftReferenceState($data)) {
            $this->clearContentDraft();

            return;
        }

        // Overwrite the same row on every poll — the unique index guarantees
        // at most one draft per user per key.
        ContentDraft::query()->updateOrCreate(
            ['user_id' => Auth::id(), 'key' => $this->contentDraftKey()],
            ['payload' => $data],
        );

        $this->contentDraftLastSavedAt = now()->format('h:i:s A');

        // Broadcast to Alpine.js so the UI shows "Draft saved at HH:MM:SS"
        $this->dispatch('content-draft-saved', time: $this->contentDraftLastSavedAt);
    }

    /*
    |--------------------------------------------------------------------------
    | Restore / Discard handlers
    |--------------------------------------------------------------------------
    */

    /**
     * Fill the form with the draft payload.
     * We intentionally do NOT delete the draft here — it stays in the DB
     * until the user actually saves the form (clearContentDraftAfterSave).
     * This protects against the gap between restore and the next wire:poll tick:
     * if the user closes the tab right after restoring, the draft is still recoverable.
     */
    #[On('restoreContentDraft')]
    public function restoreContentDraft(): void
    {
        // Unfreeze auto-save now that the user has made a decision.
        $this->contentDraftRestorePending = false;

        $draft = ContentDraft::query()
            ->where('user_id', Auth::id())
            ->where('key', $this->contentDraftKey())
            ->first();

        if ($draft) {
            $this->contentDraftLastSavedAt = $draft->updated_at?->format('h:i:s A');
            $this->form->fill($draft->payload);
            $this->dispatch('content-draft-saved', time: $this->contentDraftLastSavedAt);

            Notification::make()
                ->success()
                ->title('Draft restored successfully')
                ->send();
        }
    }

    /**
     * Clear the draft without restoring.
     */
    #[On('discardContentDraft')]
    public function discardContentDraft(): void
    {
        // Unfreeze auto-save now that the user has made a decision.
        $this->contentDraftRestorePending = false;
        $this->contentDraftLastSavedAt = null;
        $this->clearContentDraft();
    }

    /*
    |--------------------------------------------------------------------------
    | Public API — call this inside your save() / afterCreate() / afterSave()
    |--------------------------------------------------------------------------
    */

    /**
     * Wipe the draft after the real save succeeds, and update the reference
     * state so subsequent polls won't re-create the draft.
     *
     * Usage:
     *   public function save(): void { ...; $this->clearContentDraftAfterSave(); }
     *   protected function afterCreate(): void { $this->clearContentDraftAfterSave(); }
     *   protected function afterSave(): void { $this->clearContentDraftAfterSave(); }
     */
    public function clearContentDraftAfterSave(): void
    {
        $this->clearContentDraft();
        $this->contentDraftReferenceState = $this->data ?? [];
    }

    /**
     * Automatically clear draft after record creation.
     */
    protected function afterCreate(): void
    {
        $this->clearContentDraftAfterSave();
    }

    /**
     * Automatically clear draft after record save/update.
     */
    protected function afterSave(): void
    {
        $this->clearContentDraftAfterSave();
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Delete the draft row for this user + key.
     */
    protected function clearContentDraft(): void
    {
        ContentDraft::query()
            ->where('user_id', Auth::id())
            ->where('key', $this->contentDraftKey())
            ->delete();

        $this->contentDraftLastSavedAt = null;
    }

    /**
     * Field-agnostic emptiness check.
     * Returns true if every scalar value in the array (recursively) is blank.
     * blank() treats null, '', [], and '0' as blank — adjust if needed.
     */
    protected function isContentDraftEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if (is_array($value)) {
                if (! $this->isContentDraftEmpty($value)) {
                    return false;
                }
            } elseif (! blank($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deep equality check via JSON encoding.
     * Field-agnostic: works on any form structure without knowing field names.
     */
    protected function matchesContentDraftReferenceState(array $data): bool
    {
        return json_encode($data) === json_encode($this->contentDraftReferenceState);
    }
}

<?php

namespace Konectar\FilamentContentDraft\Concerns;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\View\ActionsRenderHook;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Auth;
use Konectar\FilamentContentDraft\ContentDraftPlugin;
use Konectar\FilamentContentDraft\Models\ContentDraft;
use Livewire\Attributes\On;

/**
 * Auto-save draft support for Filament Pages that use inline modal
 * CreateAction / EditAction (i.e. NOT dedicated CreateRecord/EditRecord pages).
 *
 * The trait reads and writes to $this->mountedActions[0]['data'] which is
 * where Filament stores the live form state for a mounted modal action.
 *
 * Usage in your Page class:
 *
 *   use RecoversModalContentDraft;
 *
 *   protected function createDraftKey(): string { return 'country-create'; }
 *   protected function editDraftKey(?int $recordId): string { return 'country-edit-' . $recordId; }
 *
 * Then wire the CreateAction / EditAction lifecycle callbacks with:
 *   ->afterFormFilled(fn () => $this->onModalFormFilled())    // show restore notification
 *   ->after(fn () => $this->clearModalContentDraftAfterSave()) // clear draft on save
 *
 * For edit actions, call setModalEditRecordId($record->id) inside ->using() or
 * ->before() so the trait knows which draft key to use.
 */
trait RecoversModalContentDraft
{
    /*
    |--------------------------------------------------------------------------
    | Contract — implement in your Page
    |--------------------------------------------------------------------------
    */

    /** Unique draft key for the create modal. */
    abstract protected function createDraftKey(): string;

    /** Unique draft key for the edit modal, scoped to the record being edited. */
    abstract protected function editDraftKey(?int $recordId): string;

    /**
     * Register the wire:poll Blade partial via a Filament render hook,
     * scoped to only this page class.
     * Livewire calls this automatically because of the naming convention
     * boot{TraitName}().
     */
    public function bootRecoversModalContentDraft(): void
    {
        try {
            $hook = ContentDraftPlugin::get()->getRenderHook();
        } catch (\Throwable $e) {
            $hook = config(
                'content-draft.render_hook',
                PanelsRenderHook::PAGE_FOOTER_WIDGETS_BEFORE
            );
        }

        // Register under page content / widgets
        FilamentView::registerRenderHook(
            $hook,
            fn () => view('content-draft::content-draft-poller'),
            scopes: static::class,
        );

        // Register directly under the modal form schema inside action modals
        FilamentView::registerRenderHook(
            ActionsRenderHook::MODAL_SCHEMA_AFTER,
            fn (array $data = []) => view('content-draft::content-draft-poller', $data),
            scopes: [
                static::class,
                Action::class,
                CreateAction::class,
                EditAction::class,
            ],
        );

        // Register directly above the modal form schema for the restore banner
        FilamentView::registerRenderHook(
            ActionsRenderHook::MODAL_SCHEMA_BEFORE,
            fn (array $data = []) => view('content-draft::content-draft-banner', $data),
            scopes: [
                static::class,
                Action::class,
                CreateAction::class,
                EditAction::class,
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Internal State
    |--------------------------------------------------------------------------
    */

    /** Tracks the record ID currently open in the edit modal (null = create). */
    public ?int $modalEditRecordId = null;

    /** Tracks the timestamp when the modal draft was last saved. */
    public ?string $modalContentDraftLastSavedAt = null;

    /**
     * When true, a draft was found on modal open and the user has NOT yet
     * responded to the "Restore or Discard?" notification.
     *
     * saveDraft() MUST return early while this is true, otherwise the first
     * wire:poll tick would overwrite the saved draft with the current
     * empty/default form values.
     */
    public bool $modalDraftRestorePending = false;

    /*
    |--------------------------------------------------------------------------
    | Lifecycle hooks to wire into CreateAction / EditAction
    |--------------------------------------------------------------------------
    */

    /**
     * Call this inside ->before() or ->using() on the EditAction so the trait
     * knows which record ID is currently being edited.
     */
    public function setModalEditRecordId(?int $id): void
    {
        $this->modalEditRecordId = $id;
    }

    /**
     * Called from ->afterFormFilled() on the CreateAction.
     * Shows the restore notification if a draft exists.
     */
    public function onCreateModalFormFilled(): void
    {
        $this->modalEditRecordId = null;
        $this->checkAndOfferDraftRestore($this->createDraftKey(), 'restoreCreateDraft');
    }

    /**
     * Called from ->afterFormFilled() on the EditAction.
     * Shows the restore notification if a draft exists for this record.
     */
    public function onEditModalFormFilled(int $recordId): void
    {
        $this->modalEditRecordId = $recordId;
        $this->checkAndOfferDraftRestore($this->editDraftKey($recordId), 'restoreEditDraft');
    }

    /**
     * Clear the draft and reset state after a successful save.
     * Call this inside ->after() on CreateAction or EditAction.
     */
    public function clearModalContentDraftAfterSave(): void
    {
        $key = $this->modalEditRecordId === null
            ? $this->createDraftKey()
            : $this->editDraftKey($this->modalEditRecordId);

        $this->clearModalContentDraft($key);
        $this->modalEditRecordId = null;
        $this->modalDraftRestorePending = false;
    }

    /*
    |--------------------------------------------------------------------------
    | Auto-save — triggered by wire:poll in the Blade partial
    |--------------------------------------------------------------------------
    */

    /**
     * Helper to retrieve active mounted action array.
     */
    protected function getActiveMountedAction(): ?array
    {
        if (! empty($this->mountedActions) && is_array($this->mountedActions[0] ?? null)) {
            return $this->mountedActions[0];
        }

        if (property_exists($this, 'mountedTableActions') && ! empty($this->mountedTableActions) && is_array($this->mountedTableActions[0] ?? null)) {
            return $this->mountedTableActions[0];
        }

        return null;
    }

    /**
     * Serialises the mounted modal form data as the draft payload.
     * Called automatically every N seconds by wire:poll (from the poller partial).
     * Only runs when a modal with a form is actually open.
     *
     * IMPORTANT: saveDraft() bails immediately when $modalDraftRestorePending is
     * true. This prevents overwriting an existing draft with the form's initial
     * empty/default state during the window between the restore notification
     * appearing and the user clicking "Restore" or "Discard".
     */
    public function saveDraft(): void
    {
        // ── Guard 1: No modal open ────────────────────────────────────────────
        $mountedAction = $this->getActiveMountedAction();

        if ($mountedAction === null) {
            return;
        }

        // ── Guard 2: Restore decision is pending ─────────────────────────────
        // A draft was found when the modal opened. Do NOT touch the saved draft
        // until the user explicitly chooses "Restore" or "Discard".
        if ($this->modalDraftRestorePending) {
            $this->lockModalActionSchemaIfPending();

            return;
        }

        $data = $mountedAction['data'] ?? [];

        // ── Guard 3: Form is still in its initial empty/null state ────────────
        // Uses the deep emptiness check (same as the standard trait) rather than
        // PHP's empty(), which returns false for ['name' => null] arrays.
        if ($this->isModalDraftFormEmpty($data)) {
            return;
        }

        $key = $this->resolveActiveDraftKey();

        if ($key === null) {
            return;
        }

        ContentDraft::query()->updateOrCreate(
            ['user_id' => Auth::id(), 'key' => $key],
            ['payload' => $data],
        );

        $this->modalContentDraftLastSavedAt = now()->format('h:i:s A');

        $this->dispatch('content-draft-saved', time: $this->modalContentDraftLastSavedAt);
    }

    /*
    |--------------------------------------------------------------------------
    | Restore handlers
    |--------------------------------------------------------------------------
    */

    #[On('restoreCreateDraft')]
    public function restoreCreateDraft(): void
    {
        $this->modalDraftRestorePending = false;
        $this->restoreDraftIntoModal($this->createDraftKey());
    }

    #[On('restoreEditDraft')]
    public function restoreEditDraft(): void
    {
        $this->modalDraftRestorePending = false;
        $recordId = $this->modalEditRecordId;
        if ($recordId === null) {
            $mountedAction = $this->getActiveMountedAction();
            $recordId = isset($mountedAction['context']['recordKey']) ? (int) $mountedAction['context']['recordKey'] : null;
        }
        $this->restoreDraftIntoModal($this->editDraftKey($recordId));
    }

    #[On('discardModalContentDraft')]
    public function discardModalContentDraft(): void
    {
        $this->modalDraftRestorePending = false;
        $this->modalContentDraftLastSavedAt = null;

        $key = $this->resolveActiveDraftKey();

        if ($key !== null) {
            $this->clearModalContentDraft($key);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Query for an existing draft. If one is found:
     *  - Set $modalDraftRestorePending = true to freeze auto-save.
     *  - Send the persistent "Restore or Discard?" notification.
     */
    protected function checkAndOfferDraftRestore(string $key, string $restoreEvent): void
    {
        $draft = ContentDraft::query()
            ->where('user_id', Auth::id())
            ->where('key', $key)
            ->first();

        if ($draft) {
            $this->modalContentDraftLastSavedAt = null;

            // Freeze auto-save until the user resolves the prompt via the inline banner.
            $this->modalDraftRestorePending = true;

            $this->lockModalActionSchemaIfPending();
        } else {
            $this->modalContentDraftLastSavedAt = null;
        }
    }

    public function renderingRecoversModalContentDraft(): void
    {
        if ($this->modalDraftRestorePending) {
            $this->lockModalActionSchemaIfPending();
        }
    }

    protected function lockModalActionSchemaIfPending(): void
    {
        if (property_exists($this, 'table') && ! isset($this->table)) {
            return;
        }

        try {
            $action = $this->getMountedAction();
            if ($action) {
                $action->disabledSchema(fn ($livewire) => (bool) ($livewire->modalDraftRestorePending ?? false));
            }
        } catch (\Throwable) {
        }

        if (method_exists($this, 'getSchema')) {
            for ($i = 0; $i < 5; $i++) {
                try {
                    $schema = $this->getSchema("mountedActionSchema{$i}");
                    $schema?->disabled(fn ($livewire) => (bool) ($livewire->modalDraftRestorePending ?? false));
                } catch (\Throwable) {
                }
            }
        }
    }

    protected function restoreDraftIntoModal(string $key): void
    {
        $draft = ContentDraft::query()
            ->where('user_id', Auth::id())
            ->where('key', $key)
            ->first();

        if (! $draft) {
            return;
        }

        $this->modalContentDraftLastSavedAt = $draft->updated_at?->format('h:i:s A');

        if (isset($this->mountedActions[0]['data'])) {
            $this->mountedActions[0]['data'] = $draft->payload;
        } elseif (property_exists($this, 'mountedTableActions') && isset($this->mountedTableActions[0]['data'])) {
            $this->mountedTableActions[0]['data'] = $draft->payload;
        }

        if (method_exists($this, 'getMountedActionSchema')) {
            try {
                $this->getMountedActionSchema()?->fill($draft->payload);
            } catch (\Throwable) {
            }
        } elseif (method_exists($this, 'getMountedActionForm')) {
            try {
                $this->getMountedActionForm()?->fill($draft->payload);
            } catch (\Throwable) {
            }
        }

        Notification::make()
            ->success()
            ->title('Draft restored successfully')
            ->send();

        $this->dispatch('content-draft-saved', time: $this->modalContentDraftLastSavedAt);
    }

    protected function clearModalContentDraft(string $key): void
    {
        ContentDraft::query()
            ->where('user_id', Auth::id())
            ->where('key', $key)
            ->delete();

        $this->modalContentDraftLastSavedAt = null;
    }

    protected function resolveActiveDraftKey(): ?string
    {
        $mountedAction = $this->getActiveMountedAction();

        if ($mountedAction === null) {
            return null;
        }

        $actionName = $mountedAction['name'] ?? null;

        if ($actionName === null) {
            return null;
        }

        // Table record actions are create or edit; match by name heuristic.
        if ($actionName === 'create') {
            return $this->createDraftKey();
        }

        if ($actionName === 'edit') {
            $recordId = $this->modalEditRecordId ?? ($mountedAction['context']['recordKey'] ?? null);

            return $this->editDraftKey($recordId ? (int) $recordId : null);
        }

        return null;
    }

    /**
     * Deep emptiness check — returns true only when every scalar value
     * (recursively) is blank. Uses Laravel's blank() which treats null, '',
     * [], false, and '0' as blank.
     *
     * This is safer than PHP's empty() which returns false for non-empty
     * arrays like ['name' => null, 'Capital' => null], allowing those to
     * slip through and overwrite a real saved draft with null values.
     */
    protected function isModalDraftFormEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if (is_array($value)) {
                if (! $this->isModalDraftFormEmpty($value)) {
                    return false;
                }
            } elseif (! blank($value)) {
                return false;
            }
        }

        return true;
    }
}

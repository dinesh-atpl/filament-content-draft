<?php

namespace Konectar\FilamentContentDraft\Concerns;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Events\ActionCalled;
use Filament\Actions\View\ActionsRenderHook;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
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
 * Override createDraftKey() or editDraftKey() only if you need custom draft keys.
 */
trait RecoversModalContentDraft
{
    /*
    |--------------------------------------------------------------------------
    | Draft Key Resolution & Customization
    |--------------------------------------------------------------------------
    |
    | By default, the key is derived from the resource slug and operation:
    |   Create: '{slug}-create' (e.g. 'cartoons-create')
    |   Edit:   '{slug}-edit-{id}' (e.g. 'cartoons-edit-1')
    |
    | Override createDraftKey() or editDraftKey() in your Page class for custom keys.
    |
    */

    /** Unique draft key for the create modal. */
    protected function createDraftKey(): string
    {
        $slug = null;
        try {
            if (method_exists($this, 'getResource') && ! empty(static::getResource())) {
                $slug = str(static::getResource()::getSlug())->replace('/', '-')->toString();
            }
        } catch (\Throwable $e) {
            $slug = null;
        }

        if (empty($slug)) {
            if (method_exists($this, 'getSlug')) {
                $rawSlug = preg_replace('/\{[^}]+\}/', '', static::getSlug());
                $slug = str($rawSlug)->replace('/', '-')->trim('-')->toString();
            } else {
                $slug = str(static::class)->classBasename()->kebab()->toString();
            }
        }

        if (str_ends_with($slug, '-create')) {
            return $slug;
        }

        return $slug.'-create';
    }

    /** Unique draft key for the edit modal, scoped to the record being edited. */
    protected function editDraftKey(int|string|null $recordId): string
    {
        $slug = null;
        try {
            if (method_exists($this, 'getResource') && ! empty(static::getResource())) {
                $slug = str(static::getResource()::getSlug())->replace('/', '-')->toString();
            }
        } catch (\Throwable $e) {
            $slug = null;
        }

        if (empty($slug)) {
            if (method_exists($this, 'getSlug')) {
                $rawSlug = static::getSlug();
                if ($recordId !== null && $recordId !== '') {
                    $rawSlug = preg_replace('/\{[^}]+\}/', (string) $recordId, $rawSlug);
                } else {
                    $rawSlug = preg_replace('/\{[^}]+\}/', '', $rawSlug);
                }
                $slug = str($rawSlug)->replace('/', '-')->trim('-')->toString();
            } else {
                $slug = str(static::class)->classBasename()->kebab()->toString();
            }
        }

        if ($recordId !== null && $recordId !== '') {
            if (str_contains($slug, (string) $recordId)) {
                return $slug;
            }

            return $slug.'-edit-'.$recordId;
        }

        return $slug.'-edit';
    }

    /**
     * Register the wire:poll Blade partial via a Filament render hook,
     * scoped to only this page class.
     * Livewire calls this automatically because of the naming convention
     * boot{TraitName}().
     */
    public function bootRecoversModalContentDraft(): void
    {
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

        // Auto-clear draft when a CreateAction or EditAction completes successfully
        Action::configureUsing(function (Action $action): void {
            if ($action instanceof CreateAction || $action instanceof EditAction || in_array($action->getName(), ['create', 'edit'], true)) {
                $action->after(function ($livewire) use ($action) {
                    if ($livewire && method_exists($livewire, 'clearModalContentDraftAfterSave')) {
                        $livewire->clearModalContentDraftAfterSave($action);
                    }
                });
            }
        });

        // Listen for ActionCalled event — guarantees cleanup even if ->after() was overwritten on the action
        Event::listen(ActionCalled::class, function ($event): void {
            $action = $event instanceof ActionCalled ? $event->getAction() : $event;

            if (! $action instanceof Action) {
                return;
            }

            $livewire = $action->getLivewire();

            if ($livewire && method_exists($livewire, 'clearModalContentDraftAfterSave')) {
                if ($action instanceof CreateAction || $action instanceof EditAction || in_array($action->getName(), ['create', 'edit'], true) || ($livewire->modalDraftActiveKey ?? null) !== null) {
                    $livewire->clearModalContentDraftAfterSave($action);
                }
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Internal State
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    */

    /** Tracks the record ID currently open in the edit modal (null = create). */
    public int|string|null $modalEditRecordId = null;

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

    /** Tracks the active modal draft key for the currently open modal session. */
    public ?string $modalDraftActiveKey = null;

    /** Tracks whether the user has already made a decision (Restore or Discard) for the active modal session. */
    public bool $modalDraftRestoreDecisionMade = false;

    /*
    |--------------------------------------------------------------------------
    | Helper hooks for CreateAction / EditAction
    |--------------------------------------------------------------------------
    */

    /**
     * Call this inside ->before() or ->using() on the EditAction so the trait
     * knows which record ID is currently being edited.
     */
    public function setModalEditRecordId(int|string|null $id): void
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
        $this->modalDraftActiveKey = $this->createDraftKey();
        $this->modalDraftRestoreDecisionMade = false;
        $this->checkAndOfferDraftRestore($this->createDraftKey(), 'restoreCreateDraft');
    }

    /**
     * Called from ->afterFormFilled() on the EditAction.
     * Shows the restore notification if a draft exists for this record.
     */
    public function onEditModalFormFilled(int|string|null $recordId): void
    {
        $this->modalEditRecordId = $recordId;
        $this->modalDraftActiveKey = $this->editDraftKey($recordId);
        $this->modalDraftRestoreDecisionMade = false;
        $this->checkAndOfferDraftRestore($this->editDraftKey($recordId), 'restoreEditDraft');
    }

    /**
     * Clear the draft and reset state after a successful save.
     */
    public function clearModalContentDraftAfterSave(?Action $action = null): void
    {
        $candidateKeys = [];

        if ($this->modalDraftActiveKey !== null) {
            $candidateKeys[] = $this->modalDraftActiveKey;
        }

        if ($action instanceof EditAction || $action?->getName() === 'edit') {
            $recordId = $action->getRecord()?->getKey() ?? $this->modalEditRecordId;
            if ($recordId !== null && $recordId !== '') {
                $candidateKeys[] = $this->editDraftKey($recordId);
            }
        } elseif ($action instanceof CreateAction || $action?->getName() === 'create') {
            $candidateKeys[] = $this->createDraftKey();
        }

        if ($this->modalEditRecordId !== null && $this->modalEditRecordId !== '') {
            $candidateKeys[] = $this->editDraftKey($this->modalEditRecordId);
        }

        if ($activeKey = $this->resolveActiveDraftKey()) {
            $candidateKeys[] = $activeKey;
        }

        $candidateKeys = array_unique(array_filter($candidateKeys));

        if (! empty($candidateKeys)) {
            $this->clearModalContentDraft($candidateKeys);
        }

        $this->modalEditRecordId = null;
        $this->modalDraftActiveKey = null;
        $this->modalDraftRestorePending = false;
        $this->modalDraftRestoreDecisionMade = false;
        $this->modalContentDraftLastSavedAt = null;

        $this->dispatchDraftEvent('content-draft-cleared');
    }

    /**
     * Safely dispatch Livewire browser events if dispatch method is available.
     */
    protected function dispatchDraftEvent(string $event, ...$params): void
    {
        if (method_exists($this, 'dispatch')) {
            $this->dispatch($event, ...$params);
        }
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

        $data = $this->filterContentDraftPayload($mountedAction['data'] ?? []);

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

        $userId = Filament::auth()->id() ?? Auth::id();

        ContentDraft::query()->updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['payload' => $data],
        );

        $this->modalContentDraftLastSavedAt = now()->format('h:i:s A');

        $this->dispatchDraftEvent('content-draft-saved', time: $this->modalContentDraftLastSavedAt);
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
        $this->modalDraftRestoreDecisionMade = true;
        $this->restoreDraftIntoModal($this->createDraftKey());
    }

    #[On('restoreEditDraft')]
    public function restoreEditDraft(): void
    {
        $this->modalDraftRestorePending = false;
        $this->modalDraftRestoreDecisionMade = true;
        $recordId = $this->modalEditRecordId;
        if ($recordId === null) {
            $mountedAction = $this->getActiveMountedAction();
            $recordId = $mountedAction['context']['recordKey'] ?? ($mountedAction['arguments']['record'] ?? null);
        }
        $this->restoreDraftIntoModal($this->editDraftKey($recordId));
    }

    #[On('discardModalContentDraft')]
    public function discardModalContentDraft(): void
    {
        $this->modalDraftRestorePending = false;
        $this->modalDraftRestoreDecisionMade = true;
        $this->modalContentDraftLastSavedAt = null;

        $candidateKeys = array_unique(array_filter([
            $this->modalDraftActiveKey,
            $this->resolveActiveDraftKey(),
        ]));

        if (! empty($candidateKeys)) {
            $this->clearModalContentDraft($candidateKeys);
        }

        $this->modalDraftActiveKey = null;

        $this->dispatchDraftEvent('content-draft-cleared');
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Query for an existing draft. If one is found:
     *  - Set $modalDraftRestorePending = true to freeze auto-save.
     */
    protected function checkAndOfferDraftRestore(string $key, string $restoreEvent): void
    {
        $userId = Filament::auth()->id() ?? Auth::id();

        $draft = ContentDraft::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();

        if ($draft) {
            $this->modalContentDraftLastSavedAt = null;
            $this->modalDraftRestorePending = true;
            $this->lockModalActionSchemaIfPending();
        } else {
            $this->modalContentDraftLastSavedAt = null;
        }
    }

    public function hydrateRecoversModalContentDraft(): void
    {
        if ($this->modalDraftRestorePending) {
            $this->lockModalActionSchemaIfPending();
        }
    }

    public function renderingRecoversModalContentDraft(): void
    {
        $currentKey = $this->resolveActiveDraftKey();

        if ($currentKey !== null) {
            // Only check DB when modal is first opened (session key transition)
            if ($this->modalDraftActiveKey !== $currentKey) {
                $this->modalDraftActiveKey = $currentKey;
                $this->modalDraftRestoreDecisionMade = false;

                $userId = Filament::auth()->id() ?? Auth::id();

                $draft = ContentDraft::query()
                    ->where('user_id', $userId)
                    ->where('key', $currentKey)
                    ->first();

                $this->modalDraftRestorePending = (bool) $draft;
            }
        } else {
            // Modal is closed — reset session tracking
            $this->modalDraftActiveKey = null;
            $this->modalDraftRestorePending = false;
            $this->modalDraftRestoreDecisionMade = false;
            $this->modalEditRecordId = null;
            $this->modalContentDraftLastSavedAt = null;
        }

        if ($this->modalDraftRestorePending) {
            $this->lockModalActionSchemaIfPending();
        }
    }

    protected function shouldLockFormWhileDraftPending(): bool
    {
        try {
            return ContentDraftPlugin::get()->shouldLockFormWhileDraftPending();
        } catch (\Throwable $e) {
            return (bool) config('content-draft.lock_form_while_draft_pending', false);
        }
    }

    protected function lockModalActionSchemaIfPending(): void
    {
        if (! $this->shouldLockFormWhileDraftPending()) {
            return;
        }

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
        $userId = Filament::auth()->id() ?? Auth::id();

        $draft = ContentDraft::query()
            ->where('user_id', $userId)
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

        $this->dispatchDraftEvent('content-draft-saved', time: $this->modalContentDraftLastSavedAt);
    }

    protected function clearModalContentDraft(string|array $keys): void
    {
        $keys = (array) $keys;

        $userId = Filament::auth()->id() ?? Auth::id();

        ContentDraft::query()
            ->where('user_id', $userId)
            ->whereIn('key', array_filter($keys))
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

        $mountedActionObject = method_exists($this, 'getMountedAction') ? $this->getMountedAction() : null;

        // Table record actions are create or edit; match by type or name heuristic.
        if ($mountedActionObject instanceof CreateAction || $actionName === 'create' || str_ends_with(strtolower((string) $actionName), 'create')) {
            return $this->createDraftKey();
        }

        if ($mountedActionObject instanceof EditAction || $actionName === 'edit' || str_ends_with(strtolower((string) $actionName), 'edit')) {
            $recordId = $mountedActionObject?->getRecord()?->getKey()
                ?? $this->modalEditRecordId
                ?? ($mountedAction['context']['recordKey'] ?? ($mountedAction['arguments']['record'] ?? null));

            return $this->editDraftKey($recordId);
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

    /**
     * Filter out sensitive fields (like password) recursively from the payload.
     */
    protected function filterContentDraftPayload(array $data): array
    {
        $excludedFields = array_map('strtolower', (array) config('content-draft.except_fields', [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'new_password_confirmation',
        ]));

        if (method_exists($this, 'modalContentDraftExcept')) {
            $excludedFields = array_merge($excludedFields, array_map('strtolower', (array) $this->modalContentDraftExcept()));
        } elseif (method_exists($this, 'contentDraftExcept')) {
            $excludedFields = array_merge($excludedFields, array_map('strtolower', (array) $this->contentDraftExcept()));
        }

        return $this->stripExcludedFieldsRecursively($data, $excludedFields);
    }

    /**
     * Recursively strip excluded keys from data array.
     */
    protected function stripExcludedFieldsRecursively(array $data, array $excludedFields): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, $excludedFields, true)) {
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->stripExcludedFieldsRecursively($value, $excludedFields);
            }
        }

        return $data;
    }
}

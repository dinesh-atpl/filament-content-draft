@php
    use Filament\Support\Facades\FilamentColor;

    /** @var \Livewire\Component|null $livewire */
    $livewire = (isset($this) && is_object($this))
        ? $this
        : (isset($action) && method_exists($action, 'getLivewire') ? $action->getLivewire() : null);

    $isModal = $livewire && property_exists($livewire, 'modalDraftRestorePending');
    $isPending = (bool) ($livewire ? ($isModal ? $livewire->modalDraftRestorePending : ($livewire->contentDraftRestorePending ?? false)) : false);
    $modalEditRecordId = $livewire->modalEditRecordId ?? null;

    $restoreAction = $isModal
        ? ($modalEditRecordId !== null ? 'restoreEditDraft' : 'restoreCreateDraft')
        : 'restoreContentDraft';

    $discardAction = $isModal
        ? 'discardModalContentDraft'
        : 'discardContentDraft';

    $primary = FilamentColor::getColors()['primary'] ?? [];
    $p50 = $primary[50] ?? '#f0fdf4';
    $p100 = $primary[100] ?? '#dcfce7';
    $p200 = $primary[200] ?? '#bbf7d0';
    $p500 = $primary[500] ?? '#22c55e';
    $p600 = $primary[600] ?? '#16a34a';
    $p700 = $primary[700] ?? '#15803d';
    $p800 = $primary[800] ?? '#166534';
    $p900 = $primary[900] ?? '#14532d';
@endphp

@if ($isPending)
    <div
        class="fi-content-draft-banner my-2.5 w-full"
        style="
            background: linear-gradient(135deg, var(--primary-50, {{ $p50 }}) 0%, rgba(255, 255, 255, 0.95) 100%);
            border: 1px solid var(--primary-200, {{ $p200 }});
            border-radius: 8px;
            padding: 7px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
        "
    >
        <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
            <span style="display: inline-flex; width: 6px; height: 6px; border-radius: 9999px; background: var(--primary-600, {{ $p600 }}); flex-shrink: 0;"></span>
            <span style="font-size: 13px; font-weight: 500; color: var(--primary-900, {{ $p900 }}); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                Unsaved draft available
            </span>
        </div>

        <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
            <button
                type="button"
                wire:click="{{ $restoreAction }}"
                class="fi-draft-restore-btn"
                style="
                    font-size: 12px;
                    font-weight: 600;
                    padding: 4px 11px;
                    border-radius: 6px;
                    background: var(--primary-600, {{ $p600 }});
                    color: #ffffff;
                    border: none;
                    cursor: pointer;
                    line-height: 1.25;
                    transition: all 0.15s ease;
                "
            >
                Restore
            </button>

            <button
                type="button"
                wire:click="{{ $discardAction }}"
                class="fi-draft-discard-btn"
                style="
                    font-size: 12px;
                    font-weight: 500;
                    padding: 4px 8px;
                    border-radius: 6px;
                    background: transparent;
                    color: #64748b;
                    border: none;
                    cursor: pointer;
                    line-height: 1.25;
                    transition: all 0.15s ease;
                "
            >
                Discard
            </button>
        </div>
    </div>

    <style>
        .fi-draft-restore-btn:hover {
            filter: brightness(0.92);
            transform: translateY(-0.5px);
        }
        .fi-draft-discard-btn:hover {
            color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.08) !important;
        }
        :is(.dark, [data-theme="dark"], .dark *) .fi-content-draft-banner {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.85) 0%, rgba(15, 23, 42, 0.95) 100%) !important;
            border-color: var(--primary-800, {{ $p800 }}) !important;
        }
        :is(.dark, [data-theme="dark"], .dark *) .fi-content-draft-banner span {
            color: #f1f5f9 !important;
        }
        :is(.dark, [data-theme="dark"], .dark *) .fi-draft-discard-btn {
            color: #94a3b8 !important;
        }
    </style>
@endif

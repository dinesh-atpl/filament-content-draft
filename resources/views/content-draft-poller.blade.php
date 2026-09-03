@php
    try {
        $interval = \Konectar\FilamentContentDraft\ContentDraftPlugin::get()->getPollInterval();
    } catch (\Throwable $e) {
        $interval = config('content-draft.poll_interval', 5);
    }

    try {
        $position = \Konectar\FilamentContentDraft\ContentDraftPlugin::get()->getPosition();
    } catch (\Throwable $e) {
        $position = config('content-draft.position', 'under-form');
    }

    $isFloating = in_array($position, [
        'bottom-right', 'bottom_right',
        'bottom-left', 'bottom_left',
        'top-right', 'top_right',
        'top-left', 'top_left',
    ]);

    $floatingContainerStyle = match ($position) {
        'bottom-left', 'bottom_left' => 'position: fixed; bottom: 24px; left: 24px; z-index: 99999; pointer-events: none;',
        'top-right', 'top_right' => 'position: fixed; top: 24px; right: 24px; z-index: 99999; pointer-events: none;',
        'top-left', 'top_left' => 'position: fixed; top: 24px; left: 24px; z-index: 99999; pointer-events: none;',
        'bottom-right', 'bottom_right' => 'position: fixed; bottom: 24px; right: 24px; z-index: 99999; pointer-events: none;',
        default => '',
    };

    /** @var \Livewire\Component|null $livewire */
    $livewire = (isset($this) && is_object($this))
        ? $this
        : (isset($action) && method_exists($action, 'getLivewire') ? $action->getLivewire() : null);

    $isModal = $livewire && property_exists($livewire, 'modalDraftRestorePending');
    $isModalOpen = $isModal ? ($livewire->getActiveMountedAction() !== null) : true;
    $isPending = (bool) ($livewire ? ($isModal ? $livewire->modalDraftRestorePending : ($livewire->contentDraftRestorePending ?? false)) : false);

    $serverLastSaved = ($isPending || ($isModal && ! $isModalOpen)) ? null : ($livewire->contentDraftLastSavedAt ?? $livewire->modalContentDraftLastSavedAt ?? null);
@endphp

<div
    wire:poll.{{ $interval }}s="saveDraft"
    x-data="{
        lastSaved: @js($serverLastSaved),
        show: {{ ($serverLastSaved && (! $isModal || $isModalOpen)) ? 'true' : 'false' }},
        timeout: null,
        isFloating: {{ $isFloating ? 'true' : 'false' }},
        isModal: {{ $isModal ? 'true' : 'false' }},
        isModalOpen: {{ $isModalOpen ? 'true' : 'false' }},
        init() {
            if (this.lastSaved && ! @js($isPending) && (! this.isModal || this.isModalOpen)) {
                this.show = true;
            }
        },
        onSaved(detail) {
            if (this.isModal && ! this.isModalOpen) {
                this.show = false;
                return;
            }

            let time = null;
            if (typeof detail === 'string') {
                time = detail;
            } else if (Array.isArray(detail) && detail.length > 0) {
                time = typeof detail[0] === 'string' ? detail[0] : (detail[0]?.time || null);
            } else if (detail && typeof detail === 'object') {
                time = detail.time || null;
            }
            this.lastSaved = time || @js($serverLastSaved) || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.show = true;

            if (this.isFloating) {
                clearTimeout(this.timeout);
                this.timeout = setTimeout(() => {
                    this.show = false;
                }, 4000);
            }
        },
        onCleared() {
            this.lastSaved = null;
            this.show = false;
            if (this.timeout) {
                clearTimeout(this.timeout);
            }
        }
    }"
    x-on:content-draft-saved.window="onSaved($event.detail)"
    x-on:content-draft-cleared.window="onCleared()"
    @if (! $isFloating)
        class="w-full mt-2 mb-1"
    @endif
>
    <div
        x-cloak
        x-show="show && ! @js($isPending)"
        @if ($isFloating)
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 translate-y-3 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-3 scale-95"
            style="{{ $floatingContainerStyle }}"
        @else
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="flex items-center justify-end px-1"
        @endif
    >
        <div
            class="fi-content-draft-capsule {{ $isFloating ? 'is-floating' : '' }}"
            style="
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 12px;
                border-radius: 9999px;
                font-size: 12px;
                font-weight: 500;
                line-height: 1.4;
            "
        >
            {{-- Pulsing green dot --}}
            <span style="position: relative; display: inline-flex; width: 7px; height: 7px; flex-shrink: 0;">
                <span
                    style="
                        position: absolute;
                        inset: 0;
                        border-radius: 9999px;
                        background: #10b981;
                        opacity: 0.75;
                        animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
                    "
                ></span>
                <span
                    style="
                        position: relative;
                        display: inline-flex;
                        border-radius: 9999px;
                        width: 7px;
                        height: 7px;
                        background: #059669;
                    "
                ></span>
            </span>

            {{-- Checkmark icon --}}
            <svg
                width="14"
                height="14"
                style="width: 14px; height: 14px; flex-shrink: 0; color: currentColor;"
                viewBox="0 0 20 20"
                fill="currentColor"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
            </svg>

            <span>Draft saved at <strong x-text="lastSaved || '{{ $serverLastSaved }}'" style="font-weight: 600;">{{ $serverLastSaved }}</strong></span>
        </div>
    </div>

    <style>
        .fi-content-draft-capsule {
            background-color: #ecfdf5 !important;
            color: #065f46 !important;
            border: 1px solid #6ee7b7 !important;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        :is(.dark, [data-theme="dark"], .dark *) .fi-content-draft-capsule {
            background-color: #064e3b !important;
            color: #6ee7b7 !important;
            border: 1px solid #059669 !important;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
        }

        .fi-content-draft-capsule.is-floating {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }

        @keyframes ping {
            75%, 100% {
                transform: scale(2);
                opacity: 0;
            }
        }
    </style>
</div>

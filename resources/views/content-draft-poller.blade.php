@php
    try {
        $interval = \Konectar\FilamentContentDraft\ContentDraftPlugin::get()->getPollInterval();
    } catch (\Throwable $e) {
        $interval = config('content-draft.poll_interval', 5);
    }
@endphp

<div
    wire:poll.{{ $interval }}s="saveDraft"
    x-data="{
        lastSaved: null,
        show: false,
        onSaved() {
            this.lastSaved = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            this.show = true;
        }
    }"
    x-on:content-draft-saved.window="onSaved()"
>
    <template x-if="show">
        <div
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="flex justify-end mt-2 px-1"
        >
            <span
                style="
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 3px 10px 3px 7px;
                    border-radius: 9999px;
                    font-size: 11px;
                    font-weight: 500;
                    letter-spacing: 0.01em;
                    background: rgba(16, 185, 129, 0.10);
                    border: 1px solid rgba(16, 185, 129, 0.25);
                    color: #059669;
                "
            >
                {{-- Pulsing dot --}}
                <span style="position:relative; display:inline-flex; width:8px; height:8px; flex-shrink:0;">
                    <span style="
                        position:absolute; inset:0;
                        border-radius:9999px;
                        background:#10b981;
                        opacity:0.4;
                        animation: ping 1.5s cubic-bezier(0,0,0.2,1) 1;
                    "></span>
                    <span style="
                        position:relative;
                        display:inline-flex;
                        border-radius:9999px;
                        width:8px; height:8px;
                        background:#10b981;
                    "></span>
                </span>

                {{-- Tick icon --}}
                <svg
                    width="12" height="12"
                    style="width:12px;height:12px;flex-shrink:0;color:#10b981;"
                    viewBox="0 0 20 20" fill="currentColor"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                </svg>

                <span>Draft saved at&nbsp;<span x-text="lastSaved" style="font-weight:600;"></span></span>
            </span>
        </div>
    </template>

    <style>
        @keyframes ping {
            75%, 100% { transform: scale(2); opacity: 0; }
        }
    </style>
</div>

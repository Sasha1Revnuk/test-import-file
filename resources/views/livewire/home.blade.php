<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('import.leads') }}</h1>
        <div class="flex flex-wrap items-center gap-4 sm:ms-auto sm:ps-4">
            <div class="shrink-0">
                {{ $this->clearLeadsAction }}
            </div>
            <div class="shrink-0">
                {{ $this->createImportAction }}
            </div>
        </div>
    </div>

    @if ($importResult !== null)
        <div
            @class([
                'flex items-start justify-between gap-4 rounded-xl border px-4 py-3 text-sm',
                'border-red-200 bg-red-50 text-red-900' => $importResult['is_failed'],
                'border-emerald-200 bg-emerald-50 text-emerald-900' => ! $importResult['is_failed'],
            ])
            role="status"
        >
            <div class="min-w-0 flex-1">
                <p>{{ $importResult['message'] }}</p>
                <a
                    href="{{ $importResult['report_url'] }}"
                    class="mt-1 inline-flex font-medium underline underline-offset-2 hover:no-underline"
                >
                    {{ __('import.view_report') }}
                </a>
            </div>
            <button
                type="button"
                wire:click="dismissImportResult"
                class="shrink-0 text-current/70 transition hover:text-current"
            >
                ×
            </button>
        </div>
    @endif

    <div class="w-full overflow-x-auto">
        {{ $this->table }}
    </div>
</div>

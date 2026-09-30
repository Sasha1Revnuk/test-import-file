<div class="space-y-6" @if ($isRunning) wire:poll.2s @endif>
    <div class="mb-2">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('import.show_title', ['id' => $import->id]) }}</h1>
    </div>

    @if (session('status'))
        <div class="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <dl class="grid gap-4 rounded border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <dt class="text-xs uppercase text-gray-500">{{ __('import.filename') }}</dt>
            <dd class="font-medium">{{ $import->original_filename }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase text-gray-500">{{ __('import.status') }}</dt>
            <dd class="font-medium">{{ $statusLabel }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase text-gray-500">{{ __('import.processed') }}</dt>
            <dd class="font-medium">{{ $import->processed_count }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase text-gray-500">{{ __('import.imported') }} / {{ __('import.failed') }}</dt>
            <dd class="font-medium">{{ $import->imported_count }} / {{ $import->failed_count }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase text-gray-500">{{ __('import.started_at') }}</dt>
            <dd class="font-medium">{{ $import->started_at?->format('Y-m-d H:i:s') }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase text-gray-500">{{ __('import.finished_at') }}</dt>
            <dd class="font-medium">{{ $import->finished_at?->format('Y-m-d H:i:s') }}</dd>
        </div>
    </dl>

    @if ($import->fatal_error)
        <div class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <strong>{{ __('import.fatal_error') }}:</strong> {{ $import->fatal_error }}
        </div>
    @endif

    <div class="space-y-3 w-full overflow-x-auto">
        <h2 class="text-lg font-semibold">{{ __('import.failures') }}</h2>
        {{ $this->table }}
    </div>
</div>

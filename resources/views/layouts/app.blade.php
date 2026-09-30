<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">

        <meta name="application-name" content="{{ config('app.name') }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>

        @fonts
        @filamentStyles
        @vite('resources/css/app.css')
    </head>

    <body class="min-h-screen bg-gray-50 antialiased text-gray-950">
        <header class="border-b border-gray-200 bg-white">
            <div class="flex w-full flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6 sm:py-5 lg:px-8">
                <a
                    href="{{ route('home') }}"
                    class="inline-flex items-center rounded-xl px-3 py-2 text-lg font-semibold tracking-tight text-gray-950 transition hover:bg-gray-50"
                >
                    {{ config('app.name') }}
                </a>

                <nav class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <a
                        href="{{ route('home') }}"
                        @class([
                            'inline-flex items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-medium transition',
                            'border-gray-900 bg-gray-900 text-white shadow-sm' => request()->routeIs('home'),
                            'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-950' => ! request()->routeIs('home'),
                        ])
                    >
                        {{ __('import.leads') }}
                    </a>
                    <a
                        href="{{ route('imports.index') }}"
                        @class([
                            'inline-flex items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-medium transition',
                            'border-gray-900 bg-gray-900 text-white shadow-sm' => request()->routeIs('imports.*'),
                            'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-950' => ! request()->routeIs('imports.*'),
                        ])
                    >
                        {{ __('import.title') }}
                    </a>
                </nav>
            </div>
        </header>

        <main class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            {{ $slot }}
        </main>

        @filamentScripts
        @vite('resources/js/app.js')
    </body>
</html>

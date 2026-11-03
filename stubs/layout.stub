<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), config('blueprint.rtl_locales', ['ar', 'fa', 'he', 'ur']), true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
</head>
<body>
    @if (count(config('blueprint.locales', ['en'])) > 1)
        <nav aria-label="{{ __('Language') }}">
            @foreach (config('blueprint.locales') as $locale)
                <a href="{{ route('blueprint.locale', $locale) }}" lang="{{ $locale }}" @if ($locale === app()->getLocale()) aria-current="true" @endif>{{ strtoupper($locale) }}</a>
            @endforeach
        </nav>
    @endif

    <main>
        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif

        @yield('content')
    </main>
</body>
</html>

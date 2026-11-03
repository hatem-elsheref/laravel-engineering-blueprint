<?php

namespace Best\Blueprint\Http\Middleware;

use Best\Blueprint\Flags\Definitions;
use Best\Blueprint\Flags\FlagContext;
use Best\Blueprint\Settings\SettingManager;
use Best\Blueprint\Support\Profile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Picks the request's language from blueprint.locales: API → Accept-Language; web → the visitor's
 * choice (session); then the app.language setting; otherwise the app default stays.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = array_values(array_filter((array) config('blueprint.locales', ['en']), 'is_string'));
        $api = $request->expectsJson() || $request->is('api/*');

        $locale = $api
            ? $this->fromHeader((string) $request->header('Accept-Language'), $locales)
            : $this->fromSession($request, $locales);

        // Always set it (also the default), so a long-running worker never keeps the last request's language.
        app()->setLocale($locale ?? $this->fromSetting($locales) ?? (string) app('blueprint.default_locale'));

        return $next($request);
    }

    /**
     * @param  list<string>  $locales
     */
    private function fromHeader(string $header, array $locales): ?string
    {
        $wanted = [];

        foreach (array_filter(array_map('trim', explode(',', $header))) as $i => $part) {
            [$tag, $q] = array_pad(explode(';q=', str_replace(' ', '', $part)), 2, '1');
            $wanted[] = [strtolower(str_replace('_', '-', $tag)), (float) $q, $i];
        }

        usort($wanted, fn ($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);

        foreach ($wanted as [$tag]) {
            foreach ($locales as $locale) {
                $normalized = strtolower(str_replace('_', '-', $locale));

                if ($tag === $normalized || explode('-', $tag)[0] === $normalized) {
                    return $locale;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $locales
     */
    private function fromSession(Request $request, array $locales): ?string
    {
        $choice = $request->hasSession() ? $request->session()->get('locale') : null;

        return is_string($choice) && in_array($choice, $locales, true) ? $choice : null;
    }

    /**
     * @param  list<string>  $locales
     */
    private function fromSetting(array $locales): ?string
    {
        try {
            $profile = Profile::current();
            $class = Definitions::settings();

            if ($profile === null || ! $profile->usesSettings() || ! class_exists($class)
                || ! array_key_exists('app.language', $class::definitions()) || app(FlagContext::class)->key() === null) {
                return null;
            }

            $language = app(SettingManager::class)->get('app.language');
        } catch (Throwable) {
            return null; // settings not set up yet (e.g. tables not migrated)
        }

        return is_string($language) && in_array($language, $locales, true) ? $language : null;
    }
}

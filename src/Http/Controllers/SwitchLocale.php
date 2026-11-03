<?php

namespace Best\Blueprint\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** GET /locale/{locale}: remembers the visitor's language (one of blueprint.locales) and goes back. */
final class SwitchLocale
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, (array) config('blueprint.locales', ['en']), true), 404);

        $request->session()->put('locale', $locale);

        return redirect()->back();
    }
}

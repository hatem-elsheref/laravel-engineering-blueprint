<?php

use Best\Blueprint\Http\Controllers\SwitchLocale;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->get('locale/{locale}', SwitchLocale::class)->name('blueprint.locale');

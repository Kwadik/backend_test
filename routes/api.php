<?php

use App\Http\Controllers\ReferralController;
use App\Http\Middleware\ResolveCurrentMaster;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
|
| Текущий мастер приходит в заголовке X-Master-Id и уже разложен
| в атрибуты запроса middleware'ом ResolveCurrentMaster:
|
|     $master = $request->attributes->get('current_master');
|
| Здесь нужно написать три роута — см. README.md.
|
*/

Route::get('/ping', fn () => ['ok' => true]);

Route::middleware([ResolveCurrentMaster::class])->group(function () {
    Route::post('/referrals/attach', [ReferralController::class, 'attach']);
    Route::get('/referrals/my', [ReferralController::class, 'my']);
    Route::get('/referrals/earnings', [ReferralController::class, 'earnings']);
});
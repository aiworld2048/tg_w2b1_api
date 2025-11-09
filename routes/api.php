<?php

use App\Http\Controllers\Api\Player\GameLogController;
use App\Http\Controllers\Api\Player\TransactionController;
use App\Http\Controllers\Api\ThreeDController;
use App\Http\Controllers\Api\V1\Auth\AuthController;

use App\Http\Controllers\Api\V1\Bank\BankController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\ContactController;

use App\Http\Controllers\Api\V1\DepositRequestController;

use App\Http\Controllers\Api\V1\Game\GameController;
use App\Http\Controllers\Api\V1\Game\GSCPlusProviderController;
use App\Http\Controllers\Api\V1\Game\LaunchGameController;
use App\Http\Controllers\Api\V1\Game\ProviderLaunchGameController;

use App\Http\Controllers\Api\V1\Shan\ShanLaunchGameController;

use App\Http\Controllers\Api\V1\gplus\Webhook\DepositController;
use App\Http\Controllers\Api\V1\gplus\Webhook\GameListController;
use App\Http\Controllers\Api\V1\gplus\Webhook\GetBalanceController;
use App\Http\Controllers\Api\V1\gplus\Webhook\ProductListController;
use App\Http\Controllers\Api\V1\gplus\Webhook\PushBetDataController;
use App\Http\Controllers\Api\V1\gplus\Webhook\WithdrawController;

use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\Shan\ShanGetBalanceController;

use App\Http\Controllers\Api\V1\TwoDigit\TwoDigitBetController;
use App\Http\Controllers\Api\V1\WithDrawRequestController;
use App\Http\Controllers\Api\V1\Shan\ShanTransactionController;
use App\Http\Controllers\Api\V1\Shan\BalanceUpdateCallbackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PoneWine\GameMatchController;
use App\Http\Controllers\Api\PoneWine\PoneWineClientBalanceUpdateController;
use App\Http\Controllers\Api\PoneWine\PoneWineLaunchGameController;
use App\Http\Controllers\Api\V1\Game\BuffaloGameController;



/*
|--------------------------------------------------------------------------
| API Routes
|-----------------------------------------------------------
*/

// admin login

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/player-change-password', [AuthController::class, 'playerChangePassword']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::get('product-list', [ProductListController::class, 'index']);
Route::get('operators/provider-games', [GameListController::class, 'index']);

Route::prefix('v1/api/seamless')->group(function () {
    Route::post('balance', [GetBalanceController::class, 'getBalance']);
    Route::post('withdraw', [WithdrawController::class, 'withdraw']);
    Route::post('deposit', [DepositController::class, 'deposit']);
    Route::post('pushbetdata', [PushBetDataController::class, 'pushBetData']);
});


Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/seamless/launch-game', [LaunchGameController::class, 'launchGame']);

   
    // user api
    Route::get('user', [AuthController::class, 'getUser']);
    Route::get('/banks', [GSCPlusProviderController::class, 'banks']);
    Route::get('contact', [ContactController::class, 'get']);
    Route::get('promotion', [PromotionController::class, 'index']);

    // fanicial api
    Route::get('agentfinicialPaymentType', [BankController::class, 'all']);
    Route::post('depositfinicial', [DepositRequestController::class, 'FinicialDeposit']);
    Route::get('depositlogfinicial', [DepositRequestController::class, 'log']);
    Route::get('paymentTypefinicial', [GSCPlusProviderController::class, 'paymentType']);
    Route::post('withdrawfinicial', [WithDrawRequestController::class, 'FinicalWithdraw']);
    Route::get('withdrawlogfinicial', [WithDrawRequestController::class, 'log']);

    // Player game logs
    Route::get('/player/game-logs', [GameLogController::class, 'index']);
    Route::get('user', [AuthController::class, 'getUser']);

     

});

Route::get('winnerText', [BannerController::class, 'winnerText']);
Route::get('banner_Text', [BannerController::class, 'bannerText']);
Route::get('popup-ads-banner', [BannerController::class, 'AdsBannerIndex']);
Route::get('banner', [BannerController::class, 'index']);
Route::get('videoads', [BannerController::class, 'ApiVideoads']);
Route::get('toptenwithdraw', [BannerController::class, 'TopTen']);

// games
Route::get('/game_types', [GSCPlusProviderController::class, 'gameTypes']);
Route::get('/providers/{type}', [GSCPlusProviderController::class, 'providers']);
Route::get('/game_lists/{type}/{provider}', [GSCPlusProviderController::class, 'gameLists']);

Route::get('/game_lists/{type}/{productcode}', [GSCPlusProviderController::class, 'NewgameLists']);
Route::get('/hot_game_lists', [GSCPlusProviderController::class, 'hotGameLists']);



Route::middleware(['auth:sanctum'])->group(function () {
   
    Route::post('/change-password', [AuthController::class, 'changePassword']); 
});




// Buffalo Game API routes
Route::prefix('buffalo')->group(function () {
    // Public webhook endpoints (no authentication required)
    Route::post('/get-user-balance', [BuffaloGameController::class, 'getUserBalance']);
    Route::post('/change-balance', [BuffaloGameController::class, 'changeBalance']);
    
    // Protected endpoints for frontend integration
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/game-auth', [BuffaloGameController::class, 'generateGameAuth']);
        Route::post('/game-url', [BuffaloGameController::class, 'generateGameUrl']);
        Route::post('/launch-game', [BuffaloGameController::class, 'launchGame']);
    });
});


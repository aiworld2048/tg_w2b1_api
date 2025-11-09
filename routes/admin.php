<?php

use App\Http\Controllers\Admin\AdsVedioController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\AgentPlayerController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\BannerAdsController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BannerTextController;
use App\Http\Controllers\Admin\BuffaloGame\BuffaloReportController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\DepositRequestController;
use App\Http\Controllers\Admin\GameListController;
use App\Http\Controllers\Admin\PaymentTypeController;
use App\Http\Controllers\Admin\PlayerController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\TransferLogController;
use App\Http\Controllers\Admin\UserGuideController;
use App\Http\Controllers\Admin\WinnerTextController;
use App\Http\Controllers\Admin\WithDrawRequestController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'admin',
    'as' => 'admin.',
    'middleware' => ['auth', 'checkBanned'],
], function () {

    Route::post('balance-up', [HomeController::class, 'balanceUp'])->name('balanceUp');

    Route::get('logs/{id}', [HomeController::class, 'logs'])->name('logs');

    // to do
    Route::get('/changePassword/{user}', [HomeController::class, 'changePassword'])->name('changePassword');
    Route::post('/updatePassword/{user}', [HomeController::class, 'updatePassword'])->name('updatePassword');

    Route::get('/changeplayersite/{user}', [HomeController::class, 'changePlayerSite'])->name('changeSiteName');

    Route::post('/updatePlayersite/{user}', [HomeController::class, 'updatePlayerSiteLink'])->name('updateSiteLink');

    Route::get('/player-list', [HomeController::class, 'playerList'])->name('playerList');

    // banner etc start

    Route::resource('video-upload', AdsVedioController::class);
    Route::resource('winner_text', WinnerTextController::class);
    Route::resource('banners', BannerController::class);
    Route::resource('adsbanners', BannerAdsController::class);
    Route::resource('text', BannerTextController::class);
    Route::resource('/promotions', PromotionController::class);
    Route::resource('contact', ContactController::class);
    Route::resource('paymentTypes', PaymentTypeController::class);
    Route::resource('bank', BankController::class);

    // agent start
    Route::controller(AgentController::class)
        ->prefix('agent')
        ->name('agent.')
        ->group(function () {
            Route::get('/', 'index')->middleware('permission:agent_index')->name('index');
            Route::get('/create', 'create')->middleware('permission:agent_create')->name('create');
            Route::post('/', 'store')->middleware('permission:agent_create')->name('store');
            Route::get('/{agent}/edit', 'edit')->middleware('permission:agent_edit')->name('edit');
            Route::put('/{agent}', 'update')->middleware('permission:agent_edit')->name('update');

            Route::put('/{agent}/ban', 'banAgent')->middleware('permission:agent_edit')->name('ban');

            Route::get('/{agent}/change-password', 'getChangePassword')
                ->middleware('permission:agent_change_password_access')
                ->name('getChangePassword');
            Route::post('/{agent}/change-password', 'makeChangePassword')
                ->middleware('permission:agent_change_password_access')
                ->name('makeChangePassword');

            Route::get('/{agent}/cash-in', 'getCashIn')
                ->middleware('permission:make_transfer')
                ->name('getCashIn');
            Route::post('/{agent}/cash-in', 'makeCashIn')
                ->middleware('permission:make_transfer')
                ->name('makeCashIn');
            Route::get('/{agent}/cash-out', 'getCashOut')
                ->middleware('permission:make_transfer')
                ->name('getCashOut');
            Route::post('/{agent}/cash-out', 'makeCashOut')
                ->middleware('permission:make_transfer')
                ->name('makeCashOut');

            Route::get('/{agent}/report', 'agentReportIndex')
                ->middleware('permission:transfer_log')
                ->name('report');
            Route::get('/{agent}/player-report', 'getPlayerReports')
                ->middleware('permission:transfer_log')
                ->name('getPlayerReports');
            Route::get('/{agent}/profile', 'agentProfile')
                ->middleware('permission:agent_access')
                ->name('profile');
        });
    // agent end

    Route::middleware(['permission:player_view'])->group(function () {
        Route::get('players', [PlayerController::class, 'groupedIndex'])->name('players.grouped');
        Route::get('players/{agent}', [PlayerController::class, 'groupedShow'])->name('players.grouped.show');
        Route::get('agent/players', [AgentPlayerController::class, 'index'])->name('agent.players.index');
        Route::get('agent/players/{player}/logs', [AgentPlayerController::class, 'loginLogs'])->name('agent.players.logs');
        Route::get('agent/players/{player}/report', [AgentPlayerController::class, 'report'])->name('agent.players.report');
    });

    Route::middleware(['permission:create_player'])->group(function () {
        Route::get('agent/players/create', [AgentPlayerController::class, 'create'])->name('agent.players.create');
        Route::post('agent/players', [AgentPlayerController::class, 'store'])->name('agent.players.store');
    });

    Route::middleware(['permission:edit_player'])->group(function () {
        Route::get('agent/players/{player}/edit', [AgentPlayerController::class, 'edit'])->name('agent.players.edit');
        Route::put('agent/players/{player}', [AgentPlayerController::class, 'update'])->name('agent.players.update');
        Route::delete('agent/players/{player}', [AgentPlayerController::class, 'destroy'])->name('agent.players.destroy');
    });

    Route::middleware(['permission:ban_player'])->group(function () {
        Route::put('agent/players/{player}/ban', [AgentPlayerController::class, 'banUser'])->name('agent.players.ban');
    });

    Route::middleware(['permission:change_player_password'])->group(function () {
        Route::get('agent/players/{player}/change-password', [AgentPlayerController::class, 'getChangePassword'])->name('agent.players.getChangePassword');
        Route::post('agent/players/{player}/change-password', [AgentPlayerController::class, 'makeChangePassword'])->name('agent.players.makeChangePassword');
    });

    Route::middleware(['permission:deposit_withdraw'])->group(function () {
        Route::get('agent/players/{player}/cash-in', [AgentPlayerController::class, 'getCashIn'])->name('agent.players.getCashIn');
        Route::post('agent/players/{player}/cash-in', [AgentPlayerController::class, 'makeCashIn'])->name('agent.players.makeCashIn');
        Route::get('agent/players/{player}/cash-out', [AgentPlayerController::class, 'getCashOut'])->name('agent.players.getCashOut');
        Route::post('agent/players/{player}/cash-out', [AgentPlayerController::class, 'makeCashOut'])->name('agent.players.makeCashOut');
    });

    Route::get('user-guide', UserGuideController::class)->name('user-guide');

    // Withdraw routes (for process_withdraw permission)
    Route::middleware(['permission:process_withdraw'])->group(function () {
        Route::get('finicialwithdraw', [WithDrawRequestController::class, 'index'])->name('agent.withdraw');
        Route::post('finicialwithdraw/{withdraw}', [WithDrawRequestController::class, 'statusChangeIndex'])->name('agent.withdrawStatusUpdate');
        Route::post('finicialwithdraw/reject/{withdraw}', [WithDrawRequestController::class, 'statusChangeReject'])->name('agent.withdrawStatusreject');
        Route::get('finicialwithdraw/{withdraw}', [WithDrawRequestController::class, 'WithdrawShowLog'])->name('agent.withdrawLog');
    });

    // Deposit routes (for both parent agents and sub-agents)
    Route::middleware(['permission:process_deposit|view_deposit_requests'])->group(function () {
        Route::get('finicialdeposit', [DepositRequestController::class, 'index'])->name('agent.deposit');
        Route::get('finicialdeposit/{deposit}', [DepositRequestController::class, 'view'])->name('agent.depositView');
        Route::post('finicialdeposit/{deposit}', [DepositRequestController::class, 'statusChangeIndex'])->name('agent.depositStatusUpdate');
        Route::post('finicialdeposit/reject/{deposit}', [DepositRequestController::class, 'statusChangeReject'])->name('agent.depositStatusreject');
        Route::get('finicialdeposit/{deposit}/log', [DepositRequestController::class, 'DepositShowLog'])->name('agent.depositLog');
    });

    // Player-related routes handled by AgentPlayerController above

    // master, agent sub-agent end
    Route::get('/transfer-logs', [TransferLogController::class, 'index'])->name('transfer-logs.index');

    // Route::get('transer-log', [TransferLogController::class, 'index'])->name('transferLog');
    Route::get('playertransferlog/{id}', [TransferLogController::class, 'PlayertransferLog'])->name('PlayertransferLogDetail');

    // provider start
    Route::get('gametypes', [ProductController::class, 'index'])->name('gametypes.index');
    Route::post('/game-types/{productId}/toggle-status', [ProductController::class, 'toggleStatus'])->name('gametypes.toggle-status');
    Route::get('gametypes/{game_type_id}/product/{product_id}', [ProductController::class, 'edit'])->name('gametypes.edit');
    Route::post('gametypes/{game_type_id}/product/{product_id}', [ProductController::class, 'update'])->name('gametypes.update');
    Route::post('admin/gametypes/{gameTypeId}/{productId}/update', [ProductController::class, 'update'])
        ->name('gametypesproduct.update');

    // game list start
    Route::get('all-game-lists', [GameListController::class, 'GetGameList'])->name('gameLists.index');
    Route::get('all-game-lists/{id}', [GameListController::class, 'edit'])->name('gameLists.edit');
    Route::post('all-game-lists/{id}', [GameListController::class, 'update'])->name('gameLists.update');

    Route::patch('gameLists/{id}/toggleStatus', [GameListController::class, 'toggleStatus'])->name('gameLists.toggleStatus');

    Route::patch('hotgameLists/{id}/toggleStatus', [GameListController::class, 'HotGameStatus'])->name('HotGame.toggleStatus');

    // pp hot

    Route::patch('pphotgameLists/{id}/toggleStatus', [GameListController::class, 'PPHotGameStatus'])->name('PPHotGame.toggleStatus');
    Route::get('game-list/{gameList}/edit', [GameListController::class, 'edit'])->name('game_list.edit');
    Route::post('/game-list/{id}/update-image-url', [GameListController::class, 'updateImageUrl'])->name('game_list.update_image_url');
    Route::get('game-list-order/{gameList}/edit', [GameListController::class, 'GameListOrderedit'])->name('game_list_order.edit');
    Route::post('/game-lists/{id}/update-order', [GameListController::class, 'updateOrder'])->name('GameListOrderUpdate');

    // Buffalo Game reports
    Route::group(['prefix' => 'buffalo-game'], function () {
        Route::get('/report', [BuffaloReportController::class, 'index'])->name('buffalo-report.index');
        Route::get('/report/{id}', [BuffaloReportController::class, 'show'])->name('buffalo-report.show');
    });
});

<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Integration\InboundController;
use App\Http\Controllers\Integration\UpdatesController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');

    // All resource endpoints and the sync endpoints require a valid
    // Passport (auth:api) token. See GitHub issue #284 — previously these
    // routes were fully unauthenticated, exposing full CRUD + db dump.
    Route::middleware('auth:api')->group(function () {
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('expenses', ExpenseController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('users', UserController::class);
        Route::apiResource('warehouses', WarehouseController::class);

        Route::get('sync/pull', [SyncController::class, 'pull'])->name('sync.pull');
        Route::post('sync/push', [SyncController::class, 'push'])->name('sync.push');
    });
});

/*
| Integration with the client's existing inventory system (docs/integration-api.md).
| Authenticated with the shared INTEGRATION_API_TOKEN, not user tokens.
*/
Route::prefix('integration/v1')
    ->name('integration.')
    ->middleware(['integration', 'throttle:240,1'])
    ->group(function () {
        Route::get('ping', [InboundController::class, 'ping'])->name('ping');

        // The existing system sends its records here (create or update by its own id).
        Route::put('branches', [InboundController::class, 'branches'])->name('branches');
        Route::put('users', [InboundController::class, 'users'])->name('users');
        Route::put('products', [InboundController::class, 'products'])->name('products');
        Route::put('services', [InboundController::class, 'services'])->name('services');
        Route::put('stock-levels', [InboundController::class, 'stockLevels'])->name('stock-levels');
        Route::put('purchase-orders', [InboundController::class, 'purchaseOrders'])->name('purchase-orders');
        Route::put('requisitions', [InboundController::class, 'requisitions'])->name('requisitions');

        // ...and collects what happened in the module from here.
        Route::get('updates', UpdatesController::class)->name('updates');
    });

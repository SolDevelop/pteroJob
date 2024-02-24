<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Client;
use Pterodactyl\Http\Controllers\MainQueueController;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Activity\TrackAPIKey;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\Http\Middleware\EnsureStatefulRequests;


Route::group([
    'prefix' => '/servers/{server}',
    'middleware' => [
        ServerSubject::class,
        AuthenticateServerAccess::class,
        ResourceBelongsToServer::class,
    ],
], function () {
    
Route::post('/queue', [MainQueueController::class, 'index'])->name('api:client:server.queue')->withoutMiddleware([TrackAPIKey::class])->withoutMiddleware([EnsureStatefulRequests::class])->withoutMiddleware([EnsureStatefulRequests::class]);
Route::post('/checker', [Client\Servers\PowerController::class, 'checker'])->name('api:client:server.checker');
});

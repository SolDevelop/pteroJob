<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Client;
use Pterodactyl\Http\Controllers\MainQueueController;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Activity\TrackAPIKey;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\Http\Middleware\EnsureStatefulRequests;

Route::post('/servers/{server}/queue', [MainQueueController::class, 'index'])
    ->name('server.queue')
    ->middleware([
        ServerSubject::class,
        AuthenticateServerAccess::class,
        ResourceBelongsToServer::class,
        TrackAPIKey::class, // Added TrackAPIKey middleware
        EnsureStatefulRequests::class // Added EnsureStatefulRequests middleware
    ]);

Route::post('/servers/{server}/checker', [Client\Servers\PowerController::class, 'checker'])
    ->name('server.checker')
    ->middleware([
        ServerSubject::class,
        AuthenticateServerAccess::class,
        ResourceBelongsToServer::class,
        EnsureStatefulRequests::class // Added EnsureStatefulRequests middleware
    ]);
//2035dddf
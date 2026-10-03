<?php

use App\Http\Actions\FederationDirectory;
use App\Http\Actions\FederationFriendAccept;
use App\Http\Actions\FederationFriendReject;
use App\Http\Actions\FederationFriendRemove;
use App\Http\Actions\FederationFriendRequest;
use App\Http\Actions\HandlePaymentWebhook;
use App\Http\Actions\JoinWaitlist;
use Illuminate\Support\Facades\Route;

Route::post('/waitlist', JoinWaitlist::class);
Route::post('/webhooks/ko-fi', HandlePaymentWebhook::class);

// Federation between platform instances (Wasmer <-> Vercel). Public
// directory + HMAC-signed cross-instance friend requests. Silently
// disabled (404) unless FEDERATION_PEER_URL and FEDERATION_SECRET are set.
Route::prefix('federation')->group(function () {
    Route::get('/players', FederationDirectory::class)->middleware('throttle:60,1');
    Route::post('/friend-request', FederationFriendRequest::class)->middleware('throttle:30,1');
    Route::post('/friend-accept', FederationFriendAccept::class)->middleware('throttle:30,1');
    Route::post('/friend-reject', FederationFriendReject::class)->middleware('throttle:30,1');
    Route::post('/friend-remove', FederationFriendRemove::class)->middleware('throttle:30,1');
});

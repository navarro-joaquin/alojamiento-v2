<?php

use App\Models\Room;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('adds the request id and the client ip to the context and the response', function () {
    Route::get('/_contexto', fn () => Context::only(['request_id', 'ip_address']));

    $response = $this->getJson('/_contexto');

    $requestId = $response->headers->get('X-Request-Id');
    expect($requestId)->toBeUuid();
    $response->assertExactJson(['request_id' => $requestId, 'ip_address' => '127.0.0.1']);
});

it('ignores a request id sent by the client', function () {
    Route::get('/_contexto', fn () => Context::only(['request_id']));

    $response = $this->getJson('/_contexto', ['X-Request-Id' => 'cliente']);

    expect($response->json('request_id'))->not->toBe('cliente')->toBeUuid();
});

it('records the request context in the audit trail', function () {
    $room = Room::factory()->create();
    Route::post('/_auditar', function () use ($room) {
        app(AuditService::class)->registrar('reception', 'room.audited', $room, User::factory()->create(), $room->branch_id);
    });

    $response = $this->post('/_auditar');

    expect(Activity::query()->sole()->properties->only(['request_id', 'ip_address'])->all())
        ->toEqual(['request_id' => $response->headers->get('X-Request-Id'), 'ip_address' => '127.0.0.1']);
});

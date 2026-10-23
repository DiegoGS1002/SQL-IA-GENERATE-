<?php

use App\Models\FirebirdConnection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('stores a Firebird password encrypted and omits it from the response', function () {
    $response = $this->postJson('/api/v1/connections', [
        'label' => 'ERP',
        'host' => '127.0.0.1',
        'port' => 3050,
        'database_path' => '/dados/erp.fdb',
        'username' => 'leitor',
        'password' => 'segredo',
        'charset' => 'UTF8',
    ]);

    $response->assertCreated()->assertJsonMissing(['password' => 'segredo']);
    $connection = FirebirdConnection::firstOrFail();
    expect($connection->password)->toBe('segredo');
    expect($connection->getRawOriginal('password'))->not->toBe('segredo');
});

it('explains that the Firebird driver is missing without exposing the password', function () {
    $connection = FirebirdConnection::create([
        'label' => 'ERP', 'host' => '127.0.0.1', 'port' => 3050,
        'database_path' => '/dados/erp.fdb', 'username' => 'leitor',
        'password' => 'segredo', 'charset' => 'UTF8',
    ]);

    $this->postJson("/api/v1/connections/{$connection->id}/test")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O driver pdo_firebird não está instalado neste PHP.');

    expect($connection->fresh()->status)->toBe('error');
});

it('rejects control characters in the Firebird database address', function () {
    $this->postJson('/api/v1/connections', [
        'label' => 'ERP', 'host' => '127.0.0.1', 'port' => 3050,
        'database_path' => "/dados/erp.fdb\nmalicioso", 'username' => 'leitor',
        'password' => 'segredo', 'charset' => 'UTF8',
    ])->assertUnprocessable()->assertJsonValidationErrors('database_path');

    expect(FirebirdConnection::count())->toBe(0);
});

it('refuses API requests from outside the desktop computer', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.25'])
        ->getJson('/api/v1/connections')
        ->assertForbidden();
});

it('returns only the schema associated with the requested Firebird source', function () {
    $first = FirebirdConnection::create(['label' => 'A', 'host' => 'localhost', 'port' => 3050, 'database_path' => '/a.fdb', 'username' => 'reader', 'password' => 'secret', 'charset' => 'UTF8']);
    $second = FirebirdConnection::create(['label' => 'B', 'host' => 'localhost', 'port' => 3050, 'database_path' => '/b.fdb', 'username' => 'reader', 'password' => 'secret', 'charset' => 'UTF8']);
    $first->snapshots()->create(['fingerprint' => str_repeat('a', 64), 'catalog' => ['tables' => [['name' => 'CLIENTES', 'columns' => []]], 'relationships' => []]]);
    $second->snapshots()->create(['fingerprint' => str_repeat('b', 64), 'catalog' => ['tables' => [['name' => 'SEGREDOS', 'columns' => []]], 'relationships' => []]]);

    $this->getJson("/api/v1/connections/{$first->id}/schema")
        ->assertOk()
        ->assertJsonPath('catalog.tables.0.name', 'CLIENTES')
        ->assertDontSee('SEGREDOS');
});

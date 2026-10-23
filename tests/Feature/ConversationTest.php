<?php

use App\Models\FirebirdConnection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('keeps a conversation fixed to its original Firebird source', function () {
    $first = FirebirdConnection::create(['label' => 'Base A', 'host' => 'localhost', 'port' => 3050, 'database_path' => '/a.fdb', 'username' => 'reader', 'password' => 'secret', 'charset' => 'UTF8']);
    $second = FirebirdConnection::create(['label' => 'Base B', 'host' => 'localhost', 'port' => 3050, 'database_path' => '/b.fdb', 'username' => 'reader', 'password' => 'secret', 'charset' => 'UTF8']);

    $created = $this->postJson('/api/v1/conversations', ['firebird_connection_id' => $first->id, 'title' => 'Relatório'])->assertCreated();
    $id = $created->json('id');
    $this->patchJson("/api/v1/conversations/{$id}", ['title' => 'Relatório atualizado', 'firebird_connection_id' => $second->id])->assertOk();

    $this->getJson("/api/v1/conversations/{$id}")
        ->assertOk()
        ->assertJsonPath('firebird_connection_id', $first->id)
        ->assertJsonPath('title', 'Relatório atualizado');
});

it('deletes conversation history when the user removes a chat', function () {
    $connection = FirebirdConnection::create(['label' => 'Base', 'host' => 'localhost', 'port' => 3050, 'database_path' => '/a.fdb', 'username' => 'reader', 'password' => 'secret', 'charset' => 'UTF8']);
    $conversation = $connection->conversations()->create(['title' => 'Relatório']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Pedido']);

    $this->deleteJson("/api/v1/conversations/{$conversation->id}")->assertOk()->assertJsonPath('deleted', true);

    $this->assertDatabaseCount('chat_messages', 0);
});

<?php

use App\Models\Conversation;
use App\Models\FirebirdConnection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

function makeConversationForSqlTest(bool $withSchema = true): Conversation
{
    $connection = FirebirdConnection::create(['label' => 'ERP', 'host' => 'localhost', 'port' => 3050, 'database_path' => '/erp.fdb', 'username' => 'reader', 'password' => 'secret', 'charset' => 'UTF8']);
    if ($withSchema) {
        $connection->snapshots()->create([
            'fingerprint' => str_repeat('a', 64),
            'catalog' => ['tables' => [['name' => 'CLIENTES', 'kind' => 'table', 'columns' => [['name' => 'NOME', 'type_code' => 37, 'nullable' => false]], 'primary_key' => []]], 'relationships' => []],
        ]);
    }

    return $connection->conversations()->create(['title' => 'Clientes']);
}

it('asks to synchronize the schema before generating SQL', function () {
    $conversation = makeConversationForSqlTest(false);
    Http::preventStrayRequests();

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['content' => 'Liste os clientes'])
        ->assertCreated()
        ->assertJsonPath('content', 'Sincronize o esquema da conexão antes de gerar SQL.');

    $this->assertDatabaseCount('sql_generations', 0);
});

it('returns a review-only draft for a screened SELECT', function () {
    $conversation = makeConversationForSqlTest();
    config()->set('services.openai.key', 'test-key');
    Http::preventStrayRequests();
    Http::fake(['api.openai.com/v1/responses' => Http::response([
        'output' => [['content' => [['type' => 'output_text', 'text' => json_encode([
            'sql' => 'SELECT C.NOME FROM CLIENTES C',
            'explanation' => 'Lista o nome dos clientes.',
            'parameters' => [], 'warnings' => [],
        ])]]]],
    ])]);

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['content' => 'Liste os clientes'])
        ->assertCreated()
        ->assertJsonPath('generation.validation_status', 'draft')
        ->assertJsonPath('generation.sql', 'SELECT C.NOME FROM CLIENTES C');

    $this->assertDatabaseCount('sql_generations', 1);
    Http::assertSentCount(1);
});

it('removes generated SQL that references an unknown table', function () {
    $conversation = makeConversationForSqlTest();
    config()->set('services.openai.key', 'test-key');
    Http::preventStrayRequests();
    Http::fake(['api.openai.com/v1/responses' => Http::response([
        'output' => [['content' => [['type' => 'output_text', 'text' => json_encode([
            'sql' => 'SELECT X.SENHA FROM SEGREDOS X',
            'explanation' => 'Consulta gerada.',
            'parameters' => [], 'warnings' => [],
        ])]]]],
    ])]);

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['content' => 'Liste os clientes'])
        ->assertCreated()
        ->assertJsonPath('generation.validation_status', 'failed')
        ->assertJsonPath('generation.sql', null);
});

it('blocks multiple statements proposed by the model', function () {
    $conversation = makeConversationForSqlTest();
    config()->set('services.openai.key', 'test-key');
    Http::preventStrayRequests();
    Http::fake(['api.openai.com/v1/responses' => Http::response([
        'output' => [['content' => [['type' => 'output_text', 'text' => json_encode([
            'sql' => 'SELECT NOME FROM CLIENTES; DELETE FROM CLIENTES',
            'explanation' => 'Consulta gerada.',
            'parameters' => [], 'warnings' => [],
        ])]]]],
    ])]);

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['content' => 'Liste os clientes'])
        ->assertCreated()
        ->assertJsonPath('generation.sql', null)
        ->assertJsonPath('generation.validation_status', 'failed');
});

it('preserves the requested generator format and warns that it is not executable SQL', function () {
    $conversation = makeConversationForSqlTest();
    config()->set('services.openai.key', 'test-key');
    Http::preventStrayRequests();
    Http::fake(['api.openai.com/v1/responses' => Http::response([
        'output' => [['content' => [['type' => 'output_text', 'text' => json_encode([
            'sql' => '--SELECT C.NOME FROM CLIENTES C',
            'explanation' => 'Lista os clientes no formato do gerador.',
            'parameters' => [], 'warnings' => [],
        ])]]]],
    ])]);

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['content' => 'Use o formato --SELECT do gerador'])
        ->assertCreated()
        ->assertJsonPath('generation.sql', '--SELECT C.NOME FROM CLIENTES C')
        ->assertJsonPath('generation.validation_status', 'draft')
        ->assertJsonPath('generation.warnings.0', 'Formato do gerador de relatórios: a linha --SELECT não é executável diretamente no Firebird.');
});

<?php

namespace App\Http\Controllers;

use App\Models\FirebirdConnection;
use App\Services\Firebird\ConnectionService;
use App\Services\Firebird\SchemaInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class FirebirdConnectionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FirebirdConnection::latest('id')->get()->map(fn (FirebirdConnection $connection): array => [
            'id' => $connection->id,
            'label' => $connection->label,
            'host' => $connection->host,
            'port' => $connection->port,
            'database_path' => $connection->database_path,
            'username' => $connection->username,
            'charset' => $connection->charset,
            'status' => $connection->status,
            'server_version' => $connection->server_version,
            'fingerprint' => $connection->snapshots()->latest('id')->value('fingerprint'),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.-]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database_path' => ['required', 'string', 'max:1024', 'not_regex:/[;\\r\\n]/'],
            'username' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string'],
            'charset' => ['required', 'in:UTF8,WIN1252,ISO8859_1,NONE'],
        ]);

        return response()->json(FirebirdConnection::create($data)->makeHidden('password'), 201);
    }

    public function schema(Request $request, FirebirdConnection $connection): JsonResponse
    {
        $data = $request->validate(['query' => ['nullable', 'string', 'max:120']]);
        $snapshot = $connection->snapshots()->latest('id')->first();
        if ($snapshot === null) {
            return response()->json(['message' => 'O esquema desta conexão ainda não foi sincronizado.'], 404);
        }

        $catalog = $snapshot->catalog;
        if (! empty($data['query'])) {
            $term = mb_strtolower($data['query']);
            $catalog['tables'] = array_values(array_filter($catalog['tables'], function (array $table) use ($term): bool {
                if (str_contains(mb_strtolower($table['name']), $term)) {
                    return true;
                }

                foreach ($table['columns'] as $column) {
                    if (str_contains(mb_strtolower($column['name']), $term)) {
                        return true;
                    }
                }

                return false;
            }));
            $names = array_column($catalog['tables'], 'name');
            $catalog['relationships'] = array_values(array_filter($catalog['relationships'], fn (array $relationship): bool => in_array($relationship['from_table'], $names, true) && in_array($relationship['to_table'], $names, true)));
        }

        return response()->json(['fingerprint' => $snapshot->fingerprint, 'catalog' => $catalog]);
    }

    public function test(FirebirdConnection $connection, ConnectionService $service): JsonResponse
    {
        try {
            $version = $service->test($connection);
            if (! str_starts_with($version, '3.')) {
                throw new RuntimeException("A fonte usa Firebird {$version}. Esta versão do gerador requer Firebird 3.x.");
            }
            $connection->update(['status' => 'connected', 'server_version' => $version, 'last_connected_at' => now()]);

            return response()->json(['status' => 'connected', 'version' => $version]);
        } catch (Throwable $exception) {
            $connection->update(['status' => 'error']);

            return response()->json(['message' => $exception instanceof RuntimeException ? $exception->getMessage() : 'Falha ao conectar ao servidor Firebird. Confira servidor, arquivo e credenciais.'], 422);
        }
    }

    public function synchronize(FirebirdConnection $connection, SchemaInspector $inspector): JsonResponse
    {
        try {
            $snapshot = $inspector->synchronize($connection);

            return response()->json(['fingerprint' => $snapshot->fingerprint, 'tables' => count($snapshot->catalog['tables'])]);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception instanceof RuntimeException ? $exception->getMessage() : 'Não foi possível sincronizar o esquema Firebird.'], 422);
        }
    }
}

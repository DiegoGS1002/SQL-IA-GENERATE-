<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\FirebirdConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Conversation::with('firebirdConnection:id,label,status')->latest('updated_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'firebird_connection_id' => ['required', 'integer', 'exists:firebird_connections,id'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $connection = FirebirdConnection::findOrFail($data['firebird_connection_id']);
        $conversation = $connection->conversations()->create(['title' => $data['title'] ?? 'Nova conversa']);

        return response()->json($conversation, 201);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        return response()->json($conversation->load(['firebirdConnection:id,label,status', 'messages.generation']));
    }

    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:120']]);
        $conversation->update($data);

        return response()->json($conversation);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $conversation->delete();

        return response()->json(['deleted' => true]);
    }
}

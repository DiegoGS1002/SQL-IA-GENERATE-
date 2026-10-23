<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\Reports\ReportGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation, ReportGenerationService $generator): JsonResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:4000']]);
        $conversation->messages()->create(['role' => 'user', 'content' => $data['content']]);

        try {
            $result = $generator->generate($conversation, $data['content']);
            $content = $result['explanation'];
            if ($result['sql'] === null && $result['warnings'] !== []) {
                $content .= "\n\n".implode("\n", $result['warnings']);
            }
            $assistant = $conversation->messages()->create(['role' => 'assistant', 'content' => $content]);
            $assistant->generation()->create([
                'conversation_id' => $conversation->id,
                'schema_fingerprint' => $result['schema_fingerprint'],
                'sql' => $result['sql'],
                'explanation' => $result['explanation'],
                'parameters' => $result['parameters'],
                'warnings' => $result['warnings'],
                'validation_status' => $result['validation_status'],
            ]);
            $conversation->touch();

            return response()->json($assistant->load('generation'), 201);
        } catch (RuntimeException $exception) {
            $assistant = $conversation->messages()->create(['role' => 'assistant', 'content' => $exception->getMessage()]);
            $conversation->touch();

            return response()->json($assistant, 201);
        } catch (Throwable) {
            $assistant = $conversation->messages()->create(['role' => 'assistant', 'content' => 'A geração está indisponível no momento. Tente novamente.']);
            $conversation->touch();

            return response()->json($assistant, 201);
        }
    }
}

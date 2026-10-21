<?php

namespace App\Http\Controllers\Api;

use App\Assistant\Dialogue;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssistantController extends Controller
{
    public function __invoke(Request $request, Dialogue $dialogue): JsonResponse
    {
        $data = $request->validate([
            'token' => ['nullable', 'uuid'],
            'message' => ['required', 'string', 'max:1000'],
            'value' => ['nullable', 'string', 'max:100'],
        ]);

        $conversation = isset($data['token'])
            ? Conversation::firstOrCreate(['token' => $data['token']])
            : Conversation::create(['token' => (string) Str::uuid()]);

        $reply = $dialogue->handle($conversation, trim($data['message']), $data['value'] ?? null);

        return response()->json(['token' => $conversation->token, ...$reply->toArray()]);
    }
}

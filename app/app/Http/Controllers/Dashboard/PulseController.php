<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AssistantRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Polled every 15 s by the dashboard to refresh the sidebar badges. */
class PulseController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['badges' => [
            'dash.requests' => AssistantRequest::where('status', 'open')->count(),
            'dash.messages' => \App\Models\InternalMessage::where('recipient_id', $request->user()->id)->whereNull('read_at')->count(),
            'dash.documents' => \App\Models\Document::where('type', 'invoice')->where('status', 'validated')->whereColumn('paid_cents', '<', 'total_cents')->count(),
        ]]);
    }
}

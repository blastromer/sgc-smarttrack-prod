<?php

namespace App\Http\Controllers;

use App\Support\SgcAi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SgcAiController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'path' => ['sometimes', 'nullable', 'string', 'max:200'],
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2000'],
        ]);

        return response()->json([
            'reply' => SgcAi::chat(
                $request->user(),
                $data['message'],
                $data['history'] ?? [],
                $data['path'] ?? $request->headers->get('referer'),
            ),
        ]);
    }
}

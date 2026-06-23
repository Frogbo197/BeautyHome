<?php

namespace App\Http\Controllers;

use App\Actions\AnalyzeHealthAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    public function __construct(private readonly AnalyzeHealthAction $analyzeHealth)
    {
    }

    public function analyze(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                // TODO: Khi API co auth that, thay user_id request bang auth()->id()
                // de tranh truy cap cheo du lieu suc khoe.
                'user_id' => 'required|integer|exists:taikhoan,ID',
                'prompt' => 'required|string|max:1000',
            ]);

            $result = $this->analyzeHealth->execute($validated);

            return response()->json($result->toResponseArray());
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('AI analyze error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

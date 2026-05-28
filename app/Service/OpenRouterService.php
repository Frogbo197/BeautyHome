<?php

namespace App\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    public function ask(array $messages): string
    {
        try {
            $baseUrl = rtrim((string) env('AI_CHAT_BASE_URL', 'http://127.0.0.1:11434'), '/');
            $model = (string) env('AI_CHAT_MODEL', 'qwen2.5:3b');
            $timeout = max(3, (int) env('AI_CHAT_TIMEOUT', 15));
            $numPredict = max(64, (int) env('AI_CHAT_NUM_PREDICT', 160));

            Log::info('Start AI chat request', [
                'base_url' => $baseUrl,
                'model' => $model,
            ]);

            $response = Http::timeout($timeout)->post(
                "{$baseUrl}/api/chat",
                [
                    'model' => $model,
                    'messages' => $messages,
                    'stream' => false,
                    'keep_alive' => '30m',
                    'options' => [
                        'temperature' => 0.55,
                        'top_p' => 0.9,
                        'repeat_penalty' => 1.12,
                        'num_predict' => $numPredict,
                    ],
                ]
            );

            if ($response->failed()) {
                Log::error('Ollama API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Dịch vụ AI chưa phản hồi thành công.');
            }

            $data = $response->json();
            Log::info('End AI chat request');

            $reply = trim($data['message']['content'] ?? 'Không có gì để phản hồi.');
            $reply = preg_replace('/USER:/i', '', $reply) ?? $reply;
            $reply = preg_replace('/ASSISTANT:/i', '', $reply) ?? $reply;
            $reply = preg_replace('/SYSTEM:/i', '', $reply) ?? $reply;

            return trim($reply);
        } catch (\Exception $e) {
            Log::error('Ollama service lỗi', [
                'message' => $e->getMessage(),
            ]);

            throw new \Exception('Dịch vụ AI lỗi: ' . $e->getMessage());
        }
    }
}

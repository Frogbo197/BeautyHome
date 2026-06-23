<?php
namespace App\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    public function ask(array $messages): string
    {
        try {

            Log::info('Start Ollama Request');

            $response = Http::timeout(6)->post(
                'http://127.0.0.1:11434/api/chat',
                [
                    'model' => 'qwen2.5:3b',
                    'messages' => $messages,
                    'stream' => false,
                    'keep_alive' => '30m',
                    'options' => [
                        'temperature' => 0.85,
                        'top_p' => 0.9,
                        'repeat_penalty' => 1.1,
                        'num_predict' => 70,
                    ]
                ]
            );

            if ($response->failed()) {

                Log::error('Ollama API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                throw new \Exception('Ollama lỗi rồi nha =)))');
            }

            $data = $response->json();

            Log::info('End Ollama Request');

            $reply = trim(
                $data['message']['content']
                ?? 'Không có gì để phản hồi =)))'
            );
            
            $reply = preg_replace('/USER:/i', '', $reply); 
            $reply = preg_replace('/ASSISTANT:/i', '', $reply);
             return trim($reply);

        } catch (\Exception $e) {

            Log::error('Ollama service tạch rồi =))', [
                'message' => $e->getMessage()
            ]);

            throw new \Exception(
                'AI local lỗi: ' .
                $e->getMessage()
            );
        }
    }
}

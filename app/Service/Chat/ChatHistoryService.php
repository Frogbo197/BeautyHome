<?php

namespace App\Service\Chat;

use App\Models\ChatHistory;
use Illuminate\Support\Collection;

class ChatHistoryService
{
    public function history(int $userId, string $sessionId = 'default'): Collection
    {
        return ChatHistory::where('NguoiDungID', $userId)
            ->where('SessionID', $sessionId)
            ->latest('ID')
            ->limit(40)
            ->get()
            ->reverse()
            ->values()
            ->map(function (ChatHistory $row) {
                $row->BotReply = $this->repairMojibake((string) $row->BotReply);

                return $row;
            });
    }

    public function clear(int $userId, string $sessionId = 'default'): void
    {
        ChatHistory::where('NguoiDungID', $userId)
            ->where('SessionID', $sessionId)
            ->delete();
    }

    private function repairMojibake(string $value): string
    {
        $best = $value;

        for ($i = 0; $i < 3; $i++) {
            if (preg_match('/Ã|Â|â|Æ|Ä|Å|ð/u', $best) !== 1) {
                break;
            }

            $converted = @iconv('UTF-8', 'Windows-1252//IGNORE', $best);
            if (!is_string($converted) || $converted === '' || $converted === $best) {
                break;
            }

            if (!mb_check_encoding($converted, 'UTF-8')) {
                break;
            }

            $best = $converted;
        }

        return trim(strtr($best, [
            'â€™' => "'",
            'â€œ' => '"',
            'â€' => '"',
            'â€“' => '-',
            'â€”' => '-',
            'â€¦' => '...',
            'Â·' => '·',
            'Â ' => ' ',
        ]));
    }
}

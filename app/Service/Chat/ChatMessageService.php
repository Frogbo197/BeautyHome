<?php

namespace App\Service\Chat;

use App\Models\ChatHistory;
use App\Service\HealthContextService;
use App\Service\OpenRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ChatMessageService
{
    public const AI_MODEL_VERSION = 'qwen2.5:3b';
    public const MAX_HISTORY = 10;
    public const FOOD_PREF_TABLE = 'SoThichThucPhamNguoiDung';

    private const MODE_NORMAL = 'normal';
    private const MODE_NUTRITION = 'nutrition';
    private const MODE_WORKOUT = 'workout';
    private const MODE_EMOTIONAL = 'emotional';
    private const MODE_MOTIVATE = 'motivate';
    private const MODE_SUMMARY = 'summary';
    private const MODE_CASUAL = 'casual';

    public function __construct(
        protected OpenRouterService $ai,
        protected HealthContextService $healthContext
    ) {
    }

    public function send(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                // TODO: thay bằng auth()->id() khi API có auth/session thật.
                'user_id' => 'required|integer',
                'message' => 'required|string|max:1200',
                'session_id' => 'nullable|string|max:64',
            ]);

            $userId = (int) $validated['user_id'];
            $rawMessage = (string) $validated['message'];
            $message = trim($rawMessage);
            $sessionId = $validated['session_id'] ?? 'default';

            $this->learnFoodPreference($userId, $message);

            $ctx = $this->normalizeContext($this->healthContext->build($userId), $userId);
            $ctx['food_preferences'] = $this->foodPreferenceContext($userId);

            if ($this->containsUnsafeContent($message)) {
                return $this->storeAndReturn(
                    $userId,
                    $sessionId,
                    $rawMessage,
                    'Mình không thể hỗ trợ nội dung đó nha. Nếu bạn đang có triệu chứng nguy hiểm hoặc thấy không an toàn, hãy liên hệ người thân, bác sĩ hoặc cấp cứu địa phương ngay.',
                    self::MODE_EMOTIONAL,
                    null,
                    $ctx,
                    'rule-safety'
                );
            }

            $blockedMention = $this->blockedFoodMentioned($message, $ctx);
            if ($blockedMention !== null && $this->isFoodIntent($message)) {
                return $this->storeAndReturn(
                    $userId,
                    $sessionId,
                    $rawMessage,
                    $this->blockedFoodReply($ctx, $blockedMention),
                    self::MODE_NUTRITION,
                    'nutrition',
                    $ctx,
                    'rule-food-safety'
                );
            }

            $command = $this->detectCommand($message);
            $mode = $this->detectMode($message, $command);

            if ($mode === self::MODE_CASUAL) {
                return $this->storeAndReturn(
                    $userId,
                    $sessionId,
                    $rawMessage,
                    $this->buildCasualReply($message, $ctx),
                    $mode,
                    $command,
                    $ctx,
                    'rule-casual'
                );
            }

            if (in_array($mode, [self::MODE_SUMMARY, self::MODE_NUTRITION, self::MODE_WORKOUT, self::MODE_MOTIVATE], true)) {
                $reply = match ($mode) {
                    self::MODE_SUMMARY => $this->buildSummaryReply($ctx),
                    self::MODE_NUTRITION => $this->buildNutritionReply($message, $ctx),
                    self::MODE_WORKOUT => $this->buildWorkoutReply($message, $ctx),
                    self::MODE_MOTIVATE => $this->buildMotivationReply($ctx),
                    default => $this->fallbackReply($ctx),
                };

                return $this->storeAndReturn(
                    $userId,
                    $sessionId,
                    $rawMessage,
                    $reply,
                    $mode,
                    $command,
                    $ctx,
                    'rule-fast'
                );
            }

            $reply = $this->askAi($userId, $sessionId, $message, $ctx, $mode);
            if ($reply === '') {
                $reply = $this->fallbackReply($ctx);
            }

            return $this->storeAndReturn(
                $userId,
                $sessionId,
                $rawMessage,
                $reply,
                $mode,
                $command,
                $ctx,
                self::AI_MODEL_VERSION
            );
        } catch (\Throwable $e) {
            Log::error('Chat service error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'session_id' => 'nullable|string|max:64',
        ]);

        $history = ChatHistory::where('NguoiDungID', $validated['user_id'])
            ->where('SessionID', $validated['session_id'] ?? 'default')
            ->latest('ID')
            ->limit(40)
            ->get()
            ->reverse()
            ->values();

        return response()->json(['success' => true, 'history' => $history]);
    }

    public function clear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'session_id' => 'nullable|string|max:64',
        ]);

        ChatHistory::where('NguoiDungID', $validated['user_id'])
            ->where('SessionID', $validated['session_id'] ?? 'default')
            ->delete();

        return response()->json(['success' => true]);
    }

    private function storeAndReturn(
        int $userId,
        string $sessionId,
        string $rawMessage,
        string $reply,
        string $mode,
        ?string $command,
        array $ctx,
        string $model
    ): JsonResponse {
        $reply = $this->cleanReply($reply);

        ChatHistory::create([
            'NguoiDungID' => $userId,
            'SessionID' => $sessionId,
            'UserMessage' => $rawMessage,
            'BotReply' => $reply,
            'Model' => $model,
            'ThoiGian' => now(),
        ]);

        return response()->json([
            'success' => true,
            'reply' => $reply,
            'mode' => $mode,
            'level' => 2,
            'command' => $command,
            'health_context' => $this->responseContext($ctx),
            'model' => $model,
        ]);
    }

    private function askAi(int $userId, string $sessionId, string $message, array $ctx, string $mode): string
    {
        $history = ChatHistory::where('NguoiDungID', $userId)
            ->where('SessionID', $sessionId)
            ->latest('ID')
            ->limit(self::MAX_HISTORY)
            ->get()
            ->reverse()
            ->values();

        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($ctx, $mode)],
        ];

        foreach ($history as $chat) {
            $messages[] = ['role' => 'user', 'content' => (string) $chat->UserMessage];
            $messages[] = ['role' => 'assistant', 'content' => $this->cleanReply((string) $chat->BotReply)];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            return $this->cleanReply($this->ai->ask($messages));
        } catch (\Throwable $e) {
            Log::warning('Chat AI fallback used', [
                'message' => $e->getMessage(),
                'mode' => $mode,
            ]);

            return '';
        }
    }

    private function buildSystemPrompt(array $ctx, string $mode): string
    {
        return trim("
Bạn là SaludAI, trợ lý sức khỏe tiếng Việt. Trả lời ngắn, rõ, thân thiện, có duyên nhẹ. Dùng dữ liệu thật bên dưới, không tự bịa số liệu và không chẩn đoán bệnh.

Phong cách:
- Xưng 'mình' và gọi người dùng bằng tên khi phù hợp.
- Nếu người dùng chào hỏi, tâm sự nhẹ, nói buồn/vui/chán, hoặc hỏi vu vơ, hãy phản hồi ấm áp, dí dỏm vừa phải, không lên lớp.
- Nếu câu hỏi không thuộc sức khỏe, vẫn trả lời tự nhiên; chỉ kết nối nhẹ về sức khỏe nếu hợp ngữ cảnh.
- Không bịa thời tiết, tin tức, giá cả hay dữ liệu thời gian thực. Nếu không có dữ liệu live, nói rằng mình chưa xem được và gợi ý cách kiểm tra.
- Trả lời 1-4 câu, thân thiện như bạn đồng hành.

Tên: {$ctx['ten']}
BMI: {$ctx['bmi']} ({$ctx['bmi_label']})
Calo hôm nay: nạp {$ctx['calo_nap']} kcal, đốt {$ctx['calo_dot']} kcal
Macro: protein {$ctx['protein']}g, carb {$ctx['carb']}g, fat {$ctx['fat']}g
Bữa ăn hôm nay: {$ctx['meal_summary']}
Hoạt động hôm nay: {$ctx['activity_summary']}
Bệnh nền/thể trạng nếu có: {$ctx['benh_nen']} {$ctx['the_trang']}
Sở thích/dị ứng thức ăn: {$this->foodPreferenceText($ctx)}

Nếu người dùng hỏi bài tập hoặc bữa ăn cụ thể, hãy đưa lựa chọn cụ thể, an toàn, không nói chung chung. Nếu có dấu hiệu khẩn cấp như đau ngực, khó thở, đột quỵ, tự hại, hãy hướng dẫn tìm trợ giúp y tế ngay.
Mode hiện tại: {$mode}
");
    }

    private function buildSummaryReply(array $ctx): string
    {
        return "{$ctx['ten']} ơi, hôm nay bạn đã nạp khoảng {$ctx['calo_nap']} kcal và đốt khoảng {$ctx['calo_dot']} kcal. BMI hiện là {$ctx['bmi']} ({$ctx['bmi_label']}), protein khoảng {$ctx['protein']}g; nếu có thể, hãy ghi thêm bữa ăn, vận động và nước để mình gợi ý chính xác hơn nha.";
    }

    private function buildCasualReply(string $message, array $ctx): string
    {
        $name = trim((string) ($ctx['ten'] ?? 'bạn'));
        $plain = $this->plainText($message);

        if ($this->containsAny($plain, ['thoi tiet', 'troi hom nay', 'hom nay mua', 'hom nay nang'])) {
            return "{$name} ơi, mình chưa xem được thời tiết trực tiếp trong máy bạn đâu. Nhưng bạn mở app thời tiết xem nhanh nhé; nếu trời mưa thì nhớ mang áo mưa, còn trời nắng thì nước lọc là “vật phẩm tăng lực” hôm nay đó.";
        }

        if ($this->containsAny($plain, ['xin chao', 'chao', 'hello', 'hi', 'helo', 'hey'])) {
            return "Chào {$name} nha. SaludAI đã online, tinh thần là vừa chăm sức khỏe vừa tám chuyện nhẹ nhàng, hôm nay bạn muốn mình giúp gì nè?";
        }

        if ($this->containsAny($plain, ['toi buon', 'minh buon', 'em buon', 'dang buon', 'rat buon'])) {
            return "{$name} ơi, buồn thì mình ngồi cạnh bạn một chút nha. Hít một hơi chậm, uống vài ngụm nước, rồi kể mình nghe chuyện gì làm mood tụt như pin 5% vậy?";
        }

        if ($this->containsAny($plain, ['toi chan', 'minh chan', 'em chan', 'chan qua', 'nham chan'])) {
            return "Chán hả {$name}? Vậy mình đề xuất nhiệm vụ mini: đứng dậy đi 2 phút, uống nước, rồi quay lại than tiếp cũng được. Ít nhất cơ thể mình sẽ tưởng hôm nay rất có kế hoạch.";
        }

        if ($this->containsAny($plain, ['toi vui', 'minh vui', 'em vui', 'vui qua', 'rat vui'])) {
            return "Ô vui là tốt quá {$name} ơi. Giữ mood này lại nha, kiểu bỏ vào hộp cơm tinh thần để chiều mở ra vẫn còn thơm.";
        }

        if ($this->containsAny($plain, ['met', 'u oai', 'uể oải', 'het nang luong', 'het pin'])) {
            return "{$name} nghe có vẻ hơi hết pin rồi. Mình vote nghỉ mắt 3 phút, uống nước, rồi làm một việc nhỏ thôi; hôm nay không cần hóa siêu nhân, làm người bình thường ổn áp là được.";
        }

        if ($this->containsAny($plain, ['cam on', 'thank', 'thanks', 'tks'])) {
            return "Không có gì nha {$name}. Mình ở đây để nhắc nhẹ, đỡ quên, và thỉnh thoảng pha trò cho app bớt giống bảng Excel biết nói.";
        }

        return "{$name} ơi, câu này ngoài vùng sức khỏe một chút nhưng mình vẫn nghe nè. Bạn kể thêm một câu nữa đi, mình sẽ trả lời kiểu thân thiện hơn bản hướng dẫn sử dụng máy giặt.";
    }

    private function buildNutritionReply(string $message, array $ctx): string
    {
        $slot = $this->detectMealSlot($message);
        $label = $this->mealSlotLabel($slot);
        $foods = $this->mealSuggestions($slot, $ctx);
        $foodText = implode('; ', array_slice($foods, 0, 3));
        $proteinNote = ((float) $ctx['protein'] < 50)
            ? ' Protein hôm nay còn thấp, nên ưu tiên món có cá, thịt nạc, trứng, đậu hoặc sữa chua ít đường.'
            : '';

        return "{$ctx['ten']} ơi, {$label} bạn có thể chọn: {$foodText}.{$proteinNote} Mình đã tránh các món bạn dị ứng hoặc không thích nếu có ghi trong hồ sơ.";
    }

    private function buildWorkoutReply(string $message, array $ctx): string
    {
        $slot = $this->detectMealSlot($message);
        $label = $this->workoutSlotLabel($slot);
        $wantsGentle = $this->containsAny($message, ['nhẹ', 'nhe', 'nhẹ nhàng', 'nhe nhang', 'giãn cơ', 'gian co']);
        $messageHighActivity = $this->containsAny($message, [
            'vận động cao',
            'van dong cao',
            'hoạt động cao',
            'hoat dong cao',
            'tập nhiều',
            'tap nhieu',
        ]);
        $highActivity = $this->isHighActivityProfile($ctx) || $messageHighActivity;
        $workouts = $this->workoutSuggestions($slot, $ctx, $wantsGentle || $highActivity);
        $workoutText = implode('; ', array_slice($workouts, 0, 3));
        $tone = $highActivity
            ? 'Vì hồ sơ vận động của bạn đang cao, mình ưu tiên bài phục hồi nhẹ để tránh quá tải.'
            : 'Nếu hôm nay còn ít vận động, các bài này đủ nhẹ để bắt đầu.';

        return "{$ctx['ten']} ơi, {$label} bạn nên thử: {$workoutText}. {$tone} Nếu đau ngực, khó thở, chóng mặt hoặc đau bất thường thì dừng lại và hỏi chuyên gia y tế nhé.";
    }

    private function buildMotivationReply(array $ctx): string
    {
        return "Cố lên {$ctx['ten']} nha. Hôm nay chỉ cần làm đều 3 việc nhỏ: uống nước, ghi một bữa ăn thật, và vận động nhẹ 10-20 phút. Nhỏ nhưng tích lũy rất tốt.";
    }

    private function fallbackReply(array $ctx): string
    {
        return "{$ctx['ten']} ơi, mình sẵn sàng hỗ trợ. Bạn có thể hỏi kiểu: 'bữa sáng nên ăn gì', 'buổi trưa nên tập gì', hoặc 'hôm nay sức khỏe của tôi thế nào'.";
    }

    private function mealSuggestions(string $slot, array $ctx): array
    {
        $risk = $this->isNutritionRiskProfile($ctx);
        $sets = [
            'morning' => [
                'cháo yến mạch thịt bằm với rau củ',
                'bánh mì nguyên cám kẹp cá ngừ và dưa leo',
                'sữa chua ít đường với chuối và hạt',
                'phở bò nạc ít nước béo, thêm rau',
            ],
            'noon' => [
                'cơm cá kho ít dầu với rau luộc',
                'bún thịt nạc nhiều rau, ít nước béo',
                'cơm gạo lứt với cá hoặc tôm và canh rau',
                'gỏi cuốn tôm thịt, nước chấm vừa phải',
            ],
            'evening' => [
                'miến gà xé nhiều rau',
                'cá hấp gừng với rau xào ít dầu',
                'canh bí đỏ thịt bằm với cơm vừa phải',
                'đậu hũ sốt cà chua ít dầu kèm rau luộc',
            ],
            'snack' => [
                'sữa chua không đường với trái cây',
                'chuối nhỏ kèm một ít hạt',
                'sữa đậu nành không đường',
                'khoai lang nhỏ',
            ],
        ];

        $foods = $sets[$slot] ?? $sets[$this->slotByCurrentTime()];
        if ($risk) {
            $foods = array_values(array_filter($foods, fn ($food) => !$this->containsAny($food, ['xôi', 'nước béo', 'chiên', 'rán', 'ngọt'])));
            $foods[] = 'cá hấp hoặc gà luộc kèm nhiều rau';
        }

        if ((float) $ctx['protein'] < 50) {
            $foods[] = 'tôm hấp hoặc cá hấp với rau';
            $foods[] = 'đậu hũ hoặc đậu lăng hầm rau củ';
        }

        return $this->filterBlockedFoods(array_values(array_unique($foods)), $ctx);
    }

    private function workoutSuggestions(string $slot, array $ctx, bool $gentle): array
    {
        if ($gentle) {
            return match ($slot) {
                'morning' => ['giãn cơ toàn thân 8-10 phút', 'đi bộ nhẹ 15 phút', 'yoga chào ngày mới 10 phút'],
                'noon' => ['đi bộ sau ăn 10-15 phút', 'giãn cổ vai gáy 8 phút', 'leo cầu thang nhẹ 5-7 phút nếu không đau gối'],
                'evening' => ['yoga thư giãn 12 phút', 'đi bộ chậm 15-20 phút', 'giãn cơ lưng và chân 10 phút'],
                default => ['đi bộ nhẹ 10 phút', 'giãn cơ 8 phút', 'hít thở sâu kết hợp xoay khớp 5 phút'],
            };
        }

        return match ($slot) {
            'morning' => ['đi bộ nhanh 20 phút', 'squat nhẹ 3 hiệp x 10 lần', 'giãn cơ động 8 phút'],
            'noon' => ['đi bộ nhanh 15 phút', 'leo cầu thang nhẹ 8 phút', 'plank ngắn 3 hiệp x 20 giây'],
            'evening' => ['đạp xe nhẹ 20 phút', 'yoga 15 phút', 'bodyweight nhẹ 12 phút'],
            default => ['đi bộ nhanh 15-20 phút', 'giãn cơ 10 phút', 'cardio nhẹ 10 phút'],
        };
    }

    private function detectCommand(string $message): ?string
    {
        $plain = $this->plainText($message);

        if (str_starts_with(trim($message), '/summary') || str_contains($plain, 'tong ket') || str_contains($plain, 'phan tich hom nay')) {
            return 'summary';
        }
        if (str_starts_with(trim($message), '/nutrition') || $this->isFoodIntent($message)) {
            return 'nutrition';
        }
        if (str_starts_with(trim($message), '/workout') || $this->isWorkoutIntent($message)) {
            return 'workout';
        }
        if (str_starts_with(trim($message), '/motivate') || str_contains($plain, 'dong luc') || str_contains($plain, 'co len')) {
            return 'motivate';
        }

        return null;
    }

    private function detectMode(string $message, ?string $command): string
    {
        return match ($command) {
            'summary' => self::MODE_SUMMARY,
            'nutrition' => self::MODE_NUTRITION,
            'workout' => self::MODE_WORKOUT,
            'motivate' => self::MODE_MOTIVATE,
            default => $this->detectFreeformMode($message),
        };
    }

    private function detectFreeformMode(string $message): string
    {
        if ($this->isFoodIntent($message)) {
            return self::MODE_NUTRITION;
        }
        if ($this->isWorkoutIntent($message)) {
            return self::MODE_WORKOUT;
        }
        if ($this->isCasualIntent($message)) {
            return self::MODE_CASUAL;
        }
        if ($this->containsAny($message, ['stress', 'buồn', 'mệt', 'căng thẳng', 'không ổn'])) {
            return self::MODE_EMOTIONAL;
        }

        return self::MODE_NORMAL;
    }

    private function isCasualIntent(string $message): bool
    {
        return $this->containsAny($message, [
            'xin chao', 'chao', 'hello', 'hi', 'hey', 'cam on', 'thank', 'thanks',
            'thoi tiet', 'troi hom nay', 'hom nay mua', 'hom nay nang',
            'toi buon', 'minh buon', 'em buon', 'dang buon', 'toi vui', 'minh vui',
            'em vui', 'toi chan', 'minh chan', 'em chan', 'chan qua',
            'met', 'u oai', 'het pin', 'het nang luong', 'vu vo', 'tam chuyen',
        ]);
    }

    private function detectMealSlot(string $message): string
    {
        $plain = $this->plainText($message);

        if (str_contains($plain, 'sang')) {
            return 'morning';
        }
        if (str_contains($plain, 'trua')) {
            return 'noon';
        }
        if (str_contains($plain, 'toi') || str_contains($plain, 'chieu')) {
            return 'evening';
        }
        if (str_contains($plain, 'snack') || str_contains($plain, 'phu') || str_contains($plain, 'xe')) {
            return 'snack';
        }

        return $this->slotByCurrentTime();
    }

    private function slotByCurrentTime(): string
    {
        $hour = now('Asia/Ho_Chi_Minh')->hour;

        return match (true) {
            $hour < 10 => 'morning',
            $hour < 15 => 'noon',
            $hour < 21 => 'evening',
            default => 'snack',
        };
    }

    private function mealSlotLabel(string $slot): string
    {
        return match ($slot) {
            'morning' => 'bữa sáng',
            'noon' => 'bữa trưa',
            'evening' => 'bữa tối',
            'snack' => 'bữa phụ/snack',
            default => 'bữa này',
        };
    }

    private function workoutSlotLabel(string $slot): string
    {
        return match ($slot) {
            'morning' => 'buổi sáng',
            'noon' => 'buổi trưa',
            'evening' => 'buổi tối',
            'snack' => 'lúc nghỉ giữa ngày',
            default => 'lúc này',
        };
    }

    private function isFoodIntent(string $message): bool
    {
        return $this->containsAny($message, [
            'ăn gì', 'ăn gi', 'nên ăn', 'nen an', 'bữa', 'bua', 'món', 'mon',
            'dinh dưỡng', 'dinh duong', 'calo', 'protein', 'snack', 'bữa sáng',
            'bữa trưa', 'bữa tối',
        ]);
    }

    private function isWorkoutIntent(string $message): bool
    {
        return $this->containsAny($message, [
            'tập gì', 'tap gi', 'bài tập', 'bai tap', 'nên tập', 'nen tap',
            'vận động', 'van dong', 'workout', 'cardio', 'giãn cơ', 'gian co',
            'yoga', 'đi bộ', 'di bo',
        ]);
    }

    private function isHighActivityProfile(array $ctx): bool
    {
        return $this->containsAny((string) ($ctx['muc_do_van_dong'] ?? ''), ['cao', 'nhiều', 'nhieu', 'active', 'vận động cao', 'van dong cao']);
    }

    private function isNutritionRiskProfile(array $ctx): bool
    {
        $text = $this->plainText(($ctx['the_trang'] ?? '') . ' ' . ($ctx['benh_nen'] ?? '') . ' ' . ($ctx['muc_tieu'] ?? ''));
        $bmi = (float) ($ctx['bmi'] ?? 0);

        return $bmi >= 30 || $this->containsAny($text, ['beo', 'tieu duong', 'mo mau', 'huyet ap', 'giam can']);
    }

    private function containsAny(string $value, array $needles): bool
    {
        $plain = $this->plainText($value);
        foreach ($needles as $needle) {
            if (str_contains($plain, $this->plainText($needle))) {
                return true;
            }
        }

        return false;
    }

    private function foodPreferenceContext(int $userId): array
    {
        $prefs = ['likes' => [], 'dislikes' => [], 'allergies' => [], 'blocked' => []];
        if (!Schema::hasTable(self::FOOD_PREF_TABLE)) {
            return $prefs;
        }

        $rows = DB::table(self::FOOD_PREF_TABLE)
            ->where('NguoiDungID', $userId)
            ->get(['FoodName', 'PreferenceType']);

        foreach ($rows as $row) {
            $food = trim((string) ($row->FoodName ?? ''));
            if ($food === '') {
                continue;
            }

            $type = $this->plainText((string) ($row->PreferenceType ?? ''));
            if (str_contains($type, 'allergy') || str_contains($type, 'di ung')) {
                $prefs['allergies'][] = $food;
            } elseif (str_contains($type, 'dislike') || str_contains($type, 'ghet') || str_contains($type, 'khong an')) {
                $prefs['dislikes'][] = $food;
            } elseif (str_contains($type, 'like') || str_contains($type, 'thich')) {
                $prefs['likes'][] = $food;
            }
        }

        $prefs['likes'] = array_values(array_unique($prefs['likes']));
        $prefs['dislikes'] = array_values(array_unique($prefs['dislikes']));
        $prefs['allergies'] = array_values(array_unique($prefs['allergies']));
        $prefs['blocked'] = array_values(array_unique(array_merge($prefs['allergies'], $prefs['dislikes'])));

        return $prefs;
    }

    private function filterBlockedFoods(array $foods, array $ctx): array
    {
        $blocked = $this->expandedBlockedTerms($ctx['food_preferences']['blocked'] ?? []);
        if (empty($blocked)) {
            return array_values($foods);
        }

        return array_values(array_filter($foods, function (string $food) use ($blocked) {
            return !$this->foodMatchesAnyTerm($food, $blocked);
        }));
    }

    private function expandedBlockedTerms(array $terms): array
    {
        $expanded = [];
        foreach ($terms as $term) {
            $term = trim((string) $term);
            if ($term === '') {
                continue;
            }

            $expanded[] = $term;
            $plain = $this->plainText($term);
            if (str_contains($plain, 'hai san')) {
                $expanded = array_merge($expanded, ['tôm', 'cua', 'mực', 'nghêu', 'sò', 'ốc', 'hàu']);
            }
            if ($plain === 'sua' || str_contains($plain, 'sua bo')) {
                $expanded = array_merge($expanded, ['sữa chua', 'phô mai', 'whey']);
            }
        }

        return array_values(array_unique($expanded));
    }

    private function foodMatchesAnyTerm(string $food, array $terms): bool
    {
        $foodPlain = $this->plainText($food);
        foreach ($terms as $term) {
            $termPlain = $this->plainText((string) $term);
            if ($termPlain !== '' && (str_contains($foodPlain, $termPlain) || str_contains($termPlain, $foodPlain))) {
                return true;
            }
        }

        return false;
    }

    private function blockedFoodMentioned(string $message, array $ctx): ?string
    {
        foreach ($this->expandedBlockedTerms($ctx['food_preferences']['blocked'] ?? []) as $term) {
            if ($this->foodMatchesAnyTerm($message, [$term])) {
                return (string) $term;
            }
        }

        return null;
    }

    private function blockedFoodReply(array $ctx, string $term): string
    {
        $alternatives = array_slice($this->mealSuggestions($this->slotByCurrentTime(), $ctx), 0, 2);
        $alternativeText = empty($alternatives)
            ? 'cá hấp gừng với rau hoặc bún thịt nạc nhiều rau'
            : implode(' hoặc ', $alternatives);

        return "{$ctx['ten']} ơi, mình đã ghi nhớ bạn dị ứng hoặc không ăn {$term}, nên mình sẽ không gợi ý món có {$term}. Thay vào đó, bạn chọn {$alternativeText} sẽ an toàn hơn nhé.";
    }

    private function learnFoodPreference(int $userId, string $message): void
    {
        if (!Schema::hasTable(self::FOOD_PREF_TABLE)) {
            return;
        }

        $plain = $this->plainText($message);
        $patterns = [
            'allergy' => ['/di ung(?: voi)?\s+([^.,;!?]+)/u', '/khong an duoc\s+([^.,;!?]+)/u', '/khong an\s+([^.,;!?]+)/u'],
            'dislike' => ['/(?:ghet|khong thich|ngan)\s+([^.,;!?]+)/u'],
            'like' => ['/(?:thich|hay an)\s+([^.,;!?]+)/u'],
        ];

        foreach ($patterns as $type => $typePatterns) {
            foreach ($typePatterns as $pattern) {
                if (!preg_match($pattern, $plain, $matches)) {
                    continue;
                }

                $food = trim(mb_substr($matches[1] ?? '', 0, 80));
                if ($food === '') {
                    continue;
                }

                DB::table(self::FOOD_PREF_TABLE)->updateOrInsert(
                    ['NguoiDungID' => $userId, 'FoodName' => $food],
                    ['PreferenceType' => $type, 'NgayTao' => now(), 'NgayCapNhat' => now()]
                );

                return;
            }
        }
    }

    private function containsUnsafeContent(string $message): bool
    {
        return $this->containsAny($message, [
            'tự tử', 'tu tu', 'tự hại', 'tu hai', 'uống thuốc quá liều',
            'overdose', 'kill myself', 'đau ngực', 'dau nguc', 'khó thở',
            'kho tho', 'đột quỵ', 'dot quy',
        ]);
    }

    private function responseContext(array $ctx): array
    {
        return [
            'health_score' => $ctx['health_score'],
            'bmi' => $ctx['bmi'],
            'bmi_label' => $ctx['bmi_label'],
            'calories_in' => $ctx['calo_nap'],
            'calories_out' => $ctx['calo_dot'],
            'protein' => $ctx['protein'],
            'carb' => $ctx['carb'],
            'fat' => $ctx['fat'],
        ];
    }

    private function foodPreferenceText(array $ctx): string
    {
        $prefs = $ctx['food_preferences'] ?? [];
        $parts = [];
        if (!empty($prefs['allergies'])) {
            $parts[] = 'dị ứng: ' . implode(', ', $prefs['allergies']);
        }
        if (!empty($prefs['dislikes'])) {
            $parts[] = 'không thích/không ăn: ' . implode(', ', $prefs['dislikes']);
        }
        if (!empty($prefs['likes'])) {
            $parts[] = 'thích: ' . implode(', ', $prefs['likes']);
        }

        return empty($parts) ? 'chưa ghi nhận' : implode('; ', $parts);
    }

    private function normalizeContext(array $ctx, int $userId): array
    {
        $defaults = [
            'user_id' => $userId,
            'ten' => 'bạn',
            'tuoi' => '',
            'gioi_tinh' => '',
            'bmi' => 0,
            'bmi_label' => 'chưa rõ',
            'bmi_advice' => '',
            'health_score' => 70,
            'calorie_context' => '',
            'calo_nap' => 0,
            'calo_dot' => 0,
            'protein' => 0,
            'carb' => 0,
            'fat' => 0,
            'meal_summary' => '',
            'activity_summary' => '',
            'nutrition_advice' => '',
            'benh_nen' => '',
            'the_trang' => '',
            'muc_do_van_dong' => '',
            'che_do_an' => '',
            'muc_tieu' => '',
        ];

        $ctx = array_merge($defaults, $ctx);
        foreach ($ctx as $key => $value) {
            if (is_string($value)) {
                $ctx[$key] = $this->repairMojibake($value);
            }
        }

        return $ctx;
    }

    private function cleanReply(?string $reply): string
    {
        $reply = $this->repairMojibake($reply ?? '');
        $reply = preg_replace('/\*?\*?user:\*?\*?/i', '', $reply) ?? $reply;
        $reply = preg_replace('/\*?\*?assistant:\*?\*?/i', '', $reply) ?? $reply;
        $reply = preg_replace('/\*?\*?system:\*?\*?/i', '', $reply) ?? $reply;

        return trim($reply);
    }

    private function plainText(string $value): string
    {
        $value = mb_strtolower(trim($this->repairMojibake($value)), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'đ' => 'd',
            'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        ]);
        $value = preg_replace('/[^a-z0-9\s\/_-]+/i', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function repairMojibake(string $value): string
    {
        $best = $value;
        for ($i = 0; $i < 3; $i++) {
            if (preg_match('/Ã|Â|â|Æ|Ä|Å|ð/u', $best) !== 1) {
                break;
            }

            $converted = @iconv('UTF-8', 'Windows-1252//IGNORE', $best);
            if (!is_string($converted) || $converted === '' || $converted === $best || !mb_check_encoding($converted, 'UTF-8')) {
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
            'Â ' => ' ',
        ]));
    }
}

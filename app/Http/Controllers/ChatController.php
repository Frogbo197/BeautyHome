<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Service\OpenRouterService;
use App\Service\HealthContextService;
use App\Models\ChatHistory;

class ChatController extends Controller
{
const AI_MODEL_VERSION = 'qwen2.5:3b';
const MAX_HISTORY = 10;
const FOOD_PREF_TABLE = 'SoThichThucPhamNguoiDung';

const RESPONSE_LEVEL_SHORT = 1;
const RESPONSE_LEVEL_NORMAL = 2;
const RESPONSE_LEVEL_DETAIL = 3;
const RESPONSE_LEVEL_COACH = 4;

const MODE_NORMAL = 'normal';
const MODE_NUTRITION = 'nutrition';
const MODE_WORKOUT = 'workout';
const MODE_EMOTIONAL = 'emotional';
const MODE_MOTIVATE = 'motivate';
const MODE_STRICT = 'strict';

public function __construct(
protected OpenRouterService $ai,
protected HealthContextService $healthContext
) {
}

/**
* Xử lý gửi tin nhắn, build prompt, gọi AI và lưu history.
*/
public function send(Request $request)
{
try {
$validated = $request->validate([
'user_id' => 'required|integer',
'message' => 'required|string|max:1200',
'session_id' => 'nullable|string|max:64',
]);

$userId = (int) $validated['user_id'];
$rawMessage = (string) $validated['message'];
$message = trim($rawMessage);
$sessionId = $validated['session_id'] ?? 'default';

// Học sở thích ăn uống từ message (không throw)
$this->learnFoodPreference($userId, $message);

// Kiểm tra nội dung unsafe
if ($this->containsUnsafeContent($message)) {
return response()->json([
'success' => true,
'reply' => "⚠️ Mình không thể hỗ trợ nội dung đó nha. Nếu bạn đang không ổn, hãy nói chuyện với người thân hoặc chuyên gia nhé.",
]);
}

// Lấy context sức khỏe (service trả về mảng)
$ctx = $this->healthContext->build($userId);
// Bảo đảm các key tối thiểu có mặt
$ctx = $this->normalizeContext($ctx, $userId);
$ctx['food_preferences'] = $this->foodPreferenceContext($userId);

// Xây recommendation (dựa trên ctx)
$recommendation = $this->buildRecommendation($ctx);

// Detect command/mode/level
$command = $this->detectCommand($message);
$mode = $this->detectMode($message, $command);
$level = $this->detectResponseLevel($message);

$blockedMention = $this->blockedFoodMentioned($message, $ctx);
if ($blockedMention !== null && $this->isFoodIntent($message)) {
$reply = $this->blockedFoodReply($ctx, $blockedMention);
ChatHistory::create([
'NguoiDungID' => $userId,
'SessionID' => $sessionId,
'UserMessage' => $rawMessage,
'BotReply' => $reply,
'Model' => 'rule-food-safety',
'ThoiGian' => now(),
]);

return response()->json([
'success' => true,
'reply' => $reply,
'mode' => $mode,
'level' => $level,
'command' => $command,
'health_context' => [
'health_score' => $ctx['health_score'],
'bmi' => $ctx['bmi'],
'bmi_label' => $ctx['bmi_label'],
'calories_in' => $ctx['calo_nap'],
'calories_out' => $ctx['calo_dot'],
'protein' => $ctx['protein'],
'carb' => $ctx['carb'],
'fat' => $ctx['fat'],
],
'model' => 'rule-food-safety',
]);
}

if ($command && in_array($command, ['summary', 'nutrition', 'workout', 'motivate'], true)) {
$reply = $this->buildFastCommandReply($command, $ctx);
ChatHistory::create([
'NguoiDungID' => $userId,
'SessionID' => $sessionId,
'UserMessage' => $rawMessage,
'BotReply' => $reply,
'Model' => 'rule-fast',
'ThoiGian' => now(),
]);

return response()->json([
'success' => true,
'reply' => $reply,
'mode' => $mode,
'level' => $level,
'command' => $command,
'health_context' => [
'health_score' => $ctx['health_score'],
'bmi' => $ctx['bmi'],
'bmi_label' => $ctx['bmi_label'],
'calories_in' => $ctx['calo_nap'],
'calories_out' => $ctx['calo_dot'],
'protein' => $ctx['protein'],
'carb' => $ctx['carb'],
'fat' => $ctx['fat'],
],
'model' => 'rule-fast',
]);
}

// Load recent chat history
$history = ChatHistory::where('NguoiDungID', $userId)
->where('SessionID', $sessionId)
->latest('ID')
->limit(self::MAX_HISTORY)
->get()
->reverse()
->values();

// Build messages for AI
$systemPrompt = $this->buildSystemPrompt($ctx, $mode, $level, $recommendation);
$fewShot = $this->buildFewShot();

$messages = [];
$messages[] = ['role' => 'system', 'content' => $systemPrompt];
$messages[] = ['role' => 'user', 'content' => $fewShot];

foreach ($history as $chat) {
$messages[] = ['role' => 'user', 'content' => $chat->UserMessage];
$messages[] = ['role' => 'assistant', 'content' => $chat->BotReply];
}

// Nếu có command, override final user message
$finalUserMessage = $command ? $this->buildCommandPrompt($command, $ctx) : $message;
$messages[] = ['role' => 'user', 'content' => $finalUserMessage];

// Gọi service AI
$reply = '';
try {
$reply = $this->ai->ask($messages);
$reply = $this->cleanReply($reply);
} catch (\Throwable $e) {
Log::warning('Chat AI fallback used', [
'message' => $e->getMessage(),
'mode' => $mode,
'command' => $command,
]);
}

// Làm sạch reply

if (empty(trim($reply))) {
$reply = $this->fallbackReply($mode, $ctx);
}

// Lưu history
ChatHistory::create([
'NguoiDungID' => $userId,
'SessionID' => $sessionId,
'UserMessage' => $rawMessage,
'BotReply' => $reply,
'Model' => self::AI_MODEL_VERSION,
'ThoiGian' => now(),
]);

return response()->json([
'success' => true,
'reply' => $reply,
'mode' => $mode,
'level' => $level,
'command' => $command,
'health_context' => [
'health_score' => $ctx['health_score'],
'bmi' => $ctx['bmi'],
'bmi_label' => $ctx['bmi_label'],
'calories_in' => $ctx['calo_nap'],
'calories_out' => $ctx['calo_dot'],
'protein' => $ctx['protein'],
'carb' => $ctx['carb'],
'fat' => $ctx['fat'],
],
'model' => self::AI_MODEL_VERSION,
]);
} catch (\Throwable $e) {
Log::error('ChatController error', [
'message' => $e->getMessage(),
'line' => $e->getLine(),
]);
return response()->json([
'success' => false,
'message' => $e->getMessage(),
], 500);
}
}

/**
* Trả về lịch sử chat (cũ → mới).
*/
public function history(Request $request)
{
$validated = $request->validate([
'user_id' => 'required|integer',
'session_id' => 'nullable|string|max:64',
]);

$history = ChatHistory::where('NguoiDungID', $validated['user_id'])
->where('SessionID', $validated['session_id'] ?? 'default')
->latest('ID')
->limit(40)
->get();
$history = $history->reverse()->values();

return response()->json(['success' => true, 'history' => $history]);
}

/**
* Xoá lịch sử theo session.
*/
public function clear(Request $request)
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

/**
* Build system prompt dựa trên context, mode, level, recommendation.
*/
private function buildSystemPrompt(array $ctx, string $mode, int $level, array $recommendation): string
{
$personality = $this->buildPersonality($mode);
$levelInstruction = $this->buildLevelInstruction($level);
$mealVariety = $this->buildMealVarietyInstruction($ctx, $recommendation);
$foodSafetyBlock = $this->foodPreferencePromptBlock($ctx);
$foodSafetyRules = $this->foodSafetyRules($ctx);
$proteinSourcesText = $this->proteinSourcesText($ctx);
$nutritionSafety = $this->isNutritionRiskProfile($ctx)
? "Người dùng có BMI cao/bệnh nền liên quan chuyển hóa. Khi gợi ý dinh dưỡng: KHÔNG gợi ý đồ chiên/rán, nước ngọt, bánh kẹo, fast food hoặc món nhiều dầu. Ưu tiên món ít dầu, có rau, đạm nạc/cá/đậu/ sữa chua không đường/ngũ cốc nguyên hạt và khẩu phần vừa. Không lặp đi lặp lại một bộ món cố định."
: "Ưu tiên món ít dầu, đủ đạm, có rau và dễ làm.";

$dataBlock = "
=== DỮ LIỆU SỨC KHỎE HÔM NAY CỦA {$ctx['ten']} (KHÔNG ĐƯỢC THAY ĐỔI) ===

Tên: {$ctx['ten']} | Tuổi: {$ctx['tuoi']} | Giới tính: {$ctx['gioi_tinh']}
Onboarding: thể trạng {$ctx['the_trang']}; bệnh nền {$ctx['benh_nen']}; vận động {$ctx['muc_do_van_dong']}; chế độ ăn {$ctx['che_do_an']}; mục tiêu {$ctx['muc_tieu']}
BMI: {$ctx['bmi']} ({$ctx['bmi_label']}) — {$ctx['bmi_advice']}
Điểm sức khỏe: {$ctx['health_score']}/100

CALORIES HÔM NAY:
{$ctx['calorie_context']}

MACRO:

Protein: {$ctx['protein']}g

Carb: {$ctx['carb']}g

Fat: {$ctx['fat']}g

BỮA ĂN HÔM NAY:
{$ctx['meal_summary']}

HOẠT ĐỘNG HÔM NAY:
{$ctx['activity_summary']}

ĐÁNH GIÁ DINH DƯỠNG:
{$ctx['nutrition_advice']}
=======================================================================
";

return "
{$personality}

{$dataBlock}

Dá»Š á»¨NG / KHÃ”NG Ä‚N:
{$foodSafetyBlock}

{$levelInstruction}

QUY TẮC BẮT BUỘC:

Trả lời bằng tiếng Việt.

Gọi người dùng là '{$ctx['ten']}' — KHÔNG dùng 'anh/chị/bạn' chung chung.

PHẢI dùng đúng số liệu trong khối DỮ LIỆU ở trên — KHÔNG được tự bịa số.

KHÔNG nói chung chung kiểu 'ăn đủ chất' — phải nêu món cụ thể.

{$nutritionSafety}

{$foodSafetyRules}

{$mealVariety}

Nếu protein < 50g → gợi ý thêm nguồn đạm, nhưng PHẢI xoay vòng nhiều lựa chọn trong danh sách đã lọc dị ứng/không ăn: {$proteinSourcesText}; không chỉ trứng/ức gà/đậu hũ.

Không được trả lời cùng một mô-típ món ăn trong nhiều lần liên tiếp. Mỗi lần nên đổi món, đổi cách chế biến hoặc đổi combo bữa ăn.

Nếu calo dư > 300 kcal → gợi ý bài tập cụ thể để đốt bù.

Nếu chưa có bữa ăn → nhắc log và gợi ý món.

Nếu chưa có hoạt động → gợi ý tối thiểu 20 phút đi bộ.

Có emoji tự nhiên 😭🔥🥗🍜💪

Món ăn phải ngon miệng và realistic cho người trẻ Việt Nam.

KHÔNG chẩn đoán bệnh.

KHÔNG markdown phức tạp, KHÔNG code block.

TUYỆT ĐỐI không được ghi USER:, ASSISTANT:, SYSTEM:

Chỉ trả lời nội dung cuối cùng cho người dùng.

Không lặp lại câu hỏi của người dùng.
";
} private function buildPersonality(string $mode): string
{
return match ($mode) {
self::MODE_NUTRITION => "Bạn là chuyên gia dinh dưỡng AI. Tập trung phân tích calo, protein, carb, fat và gợi ý món ăn cụ thể.",
self::MODE_WORKOUT => "Bạn là fitness coach AI. Gợi ý bài tập cụ thể phù hợp với thời gian và calo đã đốt của người dùng.",
self::MODE_EMOTIONAL => "Bạn là AI hỗ trợ tinh thần — dịu dàng, không phán xét, không toxic positivity.",
self::MODE_MOTIVATE => "Bạn là AI động lực — tích cực, năng lượng, có emoji.",
self::MODE_STRICT => "Bạn là PT nghiêm khắc — thẳng thắn, kỷ luật, không nịnh.",
default => "Bạn là trợ lý sức khỏe AI thân thiện — cá nhân hóa, cụ thể, không nói chung chung.",
};
} private function buildLevelInstruction(int $level): string
{
return match ($level) {
self::RESPONSE_LEVEL_SHORT => "Trả lời TỐI ĐA 2 câu. Không bullet. Không markdown.",
self::RESPONSE_LEVEL_DETAIL => "Giải thích rõ ràng, có bullet points, nêu lý do cụ thể. Tối đa 150 từ.",
self::RESPONSE_LEVEL_COACH => "Nói như PT thật. Có kế hoạch nhỏ, có bước cụ thể, có động lực.",
default => "Trả lời tự nhiên, ngắn gọn, dễ hiểu. Tối đa 80 từ.",
};
} private function buildFewShot(): string
{
return "
VÍ DỤ TRẢ LỜI ĐÚNG (học theo format này):

User: Tôi nên ăn gì?
Assistant:
Minh ơi, hôm nay protein mới đạt 32g — hơi thấp so với mục tiêu đó 😅
Bữa tiếp theo thử cơm cá kho ít dầu với rau luộc, hoặc bún bò nạc nhiều rau nhé; đổi món như vậy dễ ăn hơn mà vẫn tăng đạm tốt 🔥

User: Tôi có nên tập không?
Assistant:
Tuấn đã đốt 0 kcal hôm nay và đang dư 420 kcal rồi 😬
20 phút đi bộ nhanh là đủ để cân bằng lại — không cần gym, ra ngoài đi là được!

User: Tôi mệt quá
Assistant:
An mệt thì nghỉ ngơi là đúng rồi 🌿
Uống đủ nước và ăn một chút tinh bột nhẹ như chuối hoặc bánh mì là cơ thể sẽ lại sức thôi.
";
}

private function buildCommandPrompt(string $command, array $ctx): string
{
$name = $ctx['ten'] ?? 'Bạn';
return match ($command) {
'summary' => "Tóm tắt sức khỏe hôm nay của {$name} dựa trên dữ liệu thực tế phía trên.",
'nutrition' => "Đánh giá dinh dưỡng hôm nay của {$name} và gợi ý món ăn cụ thể cho bữa còn lại.",
'workout' => "Gợi ý bài tập phù hợp với lượng calo dư/thiếu hôm nay của {$name}.",
'motivate' => "Động viên {$name} dựa trên điểm sức khỏe và dữ liệu thực tế hôm nay.",
'strict' => "Đánh giá nghiêm khắc tình trạng sức khỏe của {$name} — không nịnh.",
default => "Hỗ trợ sức khỏe cho {$name}.",
};
}

private function buildFastCommandReply(string $command, array $ctx): string
{
$name = $ctx['ten'] ?? 'bạn';
$score = $ctx['health_score'] ?? 0;
$bmi = $ctx['bmi'] ?? 0;
$bmiLabel = $ctx['bmi_label'] ?? '';
$conditions = trim((string) ($ctx['benh_nen'] ?? ''));
$bodyStatus = trim((string) ($ctx['the_trang'] ?? ''));
$activityLevel = trim((string) ($ctx['muc_do_van_dong'] ?? ''));
$diet = trim((string) ($ctx['che_do_an'] ?? ''));
$goals = trim((string) ($ctx['muc_tieu'] ?? ''));
$caloIn = $ctx['calo_nap'] ?? 0;
$caloOut = $ctx['calo_dot'] ?? 0;
$protein = $ctx['protein'] ?? 0;
$mealOptions = $this->mealOptionsForNow($ctx);
$mealText = implode('; ', array_slice($mealOptions, 0, 3));
$nutritionHint = $this->isNutritionRiskProfile($ctx)
? " Với BMI/thể trạng/bệnh nền hiện tại, ưu tiên món ít dầu, nhiều rau và khẩu phần vừa. Gợi ý hôm nay: {$mealText}."
: " Nếu protein còn thấp, chọn một combo khác nhau mỗi ngày như: {$mealText}.";

return match ($command) {
'summary' =>
"{$name} ơi, hôm nay điểm sức khỏe của bạn là {$score}/100, BMI {$bmi} ({$bmiLabel})."
. ($bodyStatus !== '' ? " Thể trạng: {$bodyStatus}." : '')
. ($conditions !== '' ? " Bệnh nền đã ghi: {$conditions}." : '')
. ($goals !== '' ? " Mục tiêu: {$goals}." : '')
. " Nhớ uống nước đều trong ngày nha.",

'nutrition' =>
"Dinh dưỡng hôm nay: bạn đã nạp khoảng {$caloIn} kcal, protein {$protein}g."
. ($diet !== '' ? " Chế độ ăn onboarding: {$diet}." : '')
. $nutritionHint,

'workout' =>
"Bài tập hôm nay: bạn đã đốt khoảng {$caloOut} kcal."
. ($activityLevel !== '' ? " Mức vận động bạn chọn: {$activityLevel}." : '')
. " Nếu ít vận động, bắt đầu bằng 20 phút đi bộ nhanh hoặc 10 phút giãn cơ nha.",

'motivate' =>
"Cố lên {$name} nha. Bạn đã có hồ sơ sức khỏe rõ hơn rồi, cứ cập nhật từng chút: nước, bữa ăn, vận động. Nhỏ thôi nhưng đều là điểm cộng thật đó.",

default => "Mình đã sẵn sàng hỗ trợ sức khỏe cho {$name}.",
};
}

private function detectCommand(string $message): ?string
{
$msg = trim($message);
$plain = $this->plainText($msg);
return match (true) {
str_starts_with($msg, '/summary') => 'summary',
str_starts_with($msg, '/nutrition') => 'nutrition',
str_starts_with($msg, '/workout') => 'workout',
str_starts_with($msg, '/motivate') => 'motivate',
str_starts_with($msg, '/strict') => 'strict',
$plain === 'summary' => 'summary',
$plain === 'nutrition' => 'nutrition',
$plain === 'workout' => 'workout',
$plain === 'motivate' => 'motivate',
str_contains($plain, 'phan tich hom nay') || str_contains($plain, 'tong ket hom nay') => 'summary',
str_contains($plain, 'dinh duong')
|| str_contains($plain, 'an gi')
|| str_contains($plain, 'nen an')
|| str_contains($plain, 'bua sang')
|| str_contains($plain, 'bua trua')
|| str_contains($plain, 'bua toi')
|| str_contains($plain, 'calo')
|| str_contains($plain, 'protein') => 'nutrition',
str_contains($plain, 'bai tap')
|| str_contains($plain, 'tap gi')
|| str_contains($plain, 'nen tap')
|| str_contains($plain, 'van dong')
|| str_contains($plain, 'workout') => 'workout',
str_contains($plain, 'dong luc') || str_contains($plain, 'co len') => 'motivate',
default => null,
};
}

private function plainText(string $value): string
{
$value = mb_strtolower(trim($value), 'UTF-8');
return $this->normalizePlainText(strtr($value, [
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
]));
}

private function normalizePlainText(string $value): string
{
$ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
$value = $ascii !== false ? $ascii : $value;
$value = preg_replace('/[^a-z0-9\s\/_-]+/i', ' ', $value) ?? $value;
return trim(preg_replace('/\s+/', ' ', mb_strtolower($value, 'UTF-8')) ?? $value);
}

private function detectMode(string $message, ?string $command): string
{
if ($command) {
return $command;
}

$msg = mb_strtolower($message);
$plain = $this->plainText($message);

if (str_contains($plain, 'an gi') || str_contains($plain, 'nen an') ||
str_contains($plain, 'bua sang') || str_contains($plain, 'bua trua') ||
str_contains($plain, 'bua toi') || str_contains($plain, 'dinh duong') ||
str_contains($plain, 'calo') || str_contains($plain, 'protein')) {
return self::MODE_NUTRITION;
}

if (str_contains($plain, 'tap gi') || str_contains($plain, 'nen tap') ||
str_contains($plain, 'van dong') || str_contains($plain, 'bai tap') ||
str_contains($plain, 'workout')) {
return self::MODE_WORKOUT;
}

if (str_contains($msg, 'ăn') || str_contains($msg, 'calo') ||
str_contains($msg, 'protein') || str_contains($msg, 'carb') || str_contains($msg, 'fat')) {
return self::MODE_NUTRITION;
}
if (str_contains($msg, 'tập') || str_contains($msg, 'gym') ||
str_contains($msg, 'cardio') || str_contains($msg, 'workout')) {
return self::MODE_WORKOUT;
}
if (str_contains($msg, 'stress') || str_contains($msg, 'buồn') ||
str_contains($msg, 'mệt') || str_contains($msg, 'căng thẳng')) {
return self::MODE_EMOTIONAL;
}

return self::MODE_NORMAL;
}

private function detectResponseLevel(string $message): int
{
$msg = mb_strtolower($message);
if (str_contains($msg, 'chi tiết') || str_contains($msg, 'phân tích') || str_contains($msg, 'giải thích')) {
return self::RESPONSE_LEVEL_DETAIL;
}
if (str_contains($msg, 'coach') || str_contains($msg, 'kế hoạch') || str_contains($msg, 'lịch tập')) {
return self::RESPONSE_LEVEL_COACH;
}
if (str_contains($msg, 'ngắn') || str_contains($msg, 'tóm tắt')) {
return self::RESPONSE_LEVEL_SHORT;
}
return self::RESPONSE_LEVEL_NORMAL;
}

private function fallbackReply(string $mode, array $ctx): string
{
$name = $ctx['ten'] ?? 'Bạn';
$mealOptions = $this->mealOptionsForNow($ctx);
$first = $mealOptions[0] ?? 'cơm cá với rau';
$second = $mealOptions[1] ?? 'bún thịt nạc nhiều rau';
return match ($mode) {
self::MODE_NUTRITION => "🥗 {$name} ơi, hôm nay protein đang {$ctx['protein']}g. Bữa tới thử {$first}; nếu muốn đổi vị thì chọn {$second} nhé.",
self::MODE_WORKOUT => "🏋️ {$name} hôm nay chưa tập — thử cardio nhẹ 20 phút để đốt bớt {$ctx['calo_nap']} kcal đã nạp nhé!",
self::MODE_EMOTIONAL => "🌱 {$name} mệt cũng không sao đâu — nghỉ ngơi đúng cách cũng là chăm sóc bản thân đó.",
default => "💙 {$name} ơi, mình luôn ở đây hỗ trợ bạn nhé!",
};
}

private function buildMealVarietyInstruction(array $ctx, array $recommendation): string
{
$mealOptions = array_values(array_unique(array_merge(
$this->mealOptionsForNow($ctx),
$recommendation['foods'] ?? []
)));
$mealOptions = $this->filterBlockedFoods($mealOptions, $ctx);
$mealOptions = array_slice($mealOptions, 0, 8);
$list = implode('; ', $mealOptions);

return "KHO MÓN GỢI Ý ĐỂ XOAY VÒNG HÔM NAY: {$list}. Khi người dùng hỏi ăn gì cho sáng/trưa/tối, hãy chọn 2-3 món khác nhau từ kho này, chia theo từng bữa nếu cần. Ưu tiên món Việt Nam đời thường, tự nhiên, không lặp lại đúng combo cũ.";
}

private function mealOptionsForNow(array $ctx): array
{
$hour = now()->hour;
$riskProfile = $this->isNutritionRiskProfile($ctx);
$protein = (float) ($ctx['protein'] ?? 0);

if ($hour < 10) {
$options = [
'cháo yến mạch thịt bằm với rau củ',
'bánh mì nguyên cám kẹp cá ngừ và dưa leo',
'sữa chua Hy Lạp/ít đường với chuối và hạt',
'phở bò nạc ít nước béo, thêm rau',
'xôi đậu xanh ít dầu kèm sữa đậu nành không đường',
];
} elseif ($hour < 15) {
$options = [
'cơm cá kho ít dầu với rau luộc',
'bún thịt nạc nhiều rau, ít nước béo',
'cơm bò xào bông cải ít dầu',
'gỏi cuốn tôm thịt kèm nước chấm vừa phải',
'cơm gạo lứt với cá thu/cá basa và canh rau',
];
} else {
$options = [
'canh bí đỏ thịt bằm với cơm vừa phải',
'miến gà xé nhiều rau',
'đậu lăng/đậu xanh hầm rau củ kèm sữa chua ít đường',
'cá hấp gừng với rau xào ít dầu',
'salad tôm hoặc cá ngừ với khoai lang nhỏ',
];
}

if ($protein < 50) {
$options[] = 'sữa đậu nành không đường kèm chuối';
$options[] = 'tôm hấp hoặc cá hấp với rau';
$options[] = 'bò nạc xào rau củ ít dầu';
}

if ($riskProfile) {
$blocked = ['xôi', 'nước béo'];
$options = array_values(array_filter($options, function ($food) use ($blocked) {
$plain = mb_strtolower($food);
foreach ($blocked as $word) {
if (str_contains($plain, $word)) {
return false;
}
}
return true;
}));
$options[] = 'cháo yến mạch rau củ với thịt nạc';
$options[] = 'cá hấp hoặc cá nướng giấy bạc kèm rau';
}

$offset = ((int) now()->format('z')) % max(count($options), 1);
$options = array_values(array_merge(array_slice($options, $offset), array_slice($options, 0, $offset)));
return $this->filterBlockedFoods($options, $ctx);
}

private function isNutritionRiskProfile(array $ctx): bool
{
$bmi = (float) ($ctx['bmi'] ?? 0);
$bodyStatus = mb_strtolower((string) ($ctx['the_trang'] ?? ''));
$conditions = mb_strtolower((string) ($ctx['benh_nen'] ?? ''));
$goal = mb_strtolower((string) ($ctx['muc_tieu'] ?? ''));

return $bmi >= 30
|| str_contains($bodyStatus, 'béo')
|| str_contains($bodyStatus, 'beo')
|| str_contains($conditions, 'tiểu đường')
|| str_contains($conditions, 'tieu duong')
|| str_contains($conditions, 'mỡ máu')
|| str_contains($conditions, 'mo mau')
|| str_contains($conditions, 'huyết áp')
|| str_contains($conditions, 'huyet ap')
|| str_contains($goal, 'giảm cân')
|| str_contains($goal, 'giam can');
}

private function buildRecommendation(array $ctx): array
{
$hour = now()->hour;
$bmi = $ctx['bmi'] ?? 22;
$protein = $ctx['protein'] ?? 0;
$score = $ctx['health_score'] ?? 100;
$riskProfile = $this->isNutritionRiskProfile($ctx);

$foods = [];
$workouts = [];

// Khung giờ
if ($riskProfile) {
$foods = ['cá hấp gừng với rau', 'cháo yến mạch thịt bằm rau củ', 'gỏi cuốn tôm thịt ít nước chấm', 'sữa chua ít đường với chuối và hạt'];
} elseif ($hour < 10) {
$foods = ['cháo yến mạch thịt bằm', 'bánh mì cá ngừ dưa leo 🥪', 'sữa chua chuối hạt 🍌'];
} elseif ($hour < 15) {
$foods = ['cơm cá kho ít dầu với rau', 'bún thịt nạc nhiều rau', 'gỏi cuốn tôm thịt 🥗'];
} else {
$foods = ['miến gà xé nhiều rau', 'canh bí đỏ thịt bằm', 'cá hấp gừng với rau'];
}

// Theo BMI
if ($bmi < 18.5) {
$foods = [
'cơm bò sốt tiêu đen 🥩',
'mì quảng bò trứng lòng đào 🍜',
'cơm gà áp chảo 🍗',
];
$workouts = [
'squat 15 phút 🏋️',
'tập chân nhẹ',
];
} elseif ($bmi <= 24.9) {
if ($protein < 50) {
$foods = [
'poke bowl cá hồi 🥗',
'cơm cá kho ít dầu 🍱',
'bánh mì cá ngừ dưa leo 🥪',
'bún bò nạc nhiều rau',
];
} else {
$foods = [
'sushi 🍣',
'mì trộn ít béo 🍜',
'cơm bò nướng 🔥',
];
}
$workouts = [
'đi bộ 20 phút 🚶',
'cardio nhẹ 🏃',
];
} else {
$foods = [
'cá hấp gừng với rau',
'cơm gạo lứt với cá hoặc tôm',
'yến mạch trái cây ít đường',
];
$workouts = [
'HIIT 20 phút 🔥',
'đạp xe 🚴',
];
}

if ($score < 50) {
$foods[] = 'trà trái cây ít đường 🍹';
$workouts[] = 'stretching nhẹ 🌿';
}

// Lấy likes/dislikes từ DB
$foodPrefs = $ctx['food_preferences'] ?? $this->foodPreferenceContext((int) $ctx['user_id']);
$foods = $this->filterBlockedFoods($foods, $ctx);

if (!empty($foodPrefs['likes'])) {
$likedFoods = $this->filterBlockedFoods($foodPrefs['likes'], $ctx);
$foods = array_values(array_unique(array_merge($likedFoods, $foods)));
}

if ($riskProfile) {
$blocked = ['rán', 'ran', 'chiên', 'chien', 'nước ngọt', 'nuoc ngot', 'bánh kẹo', 'banh keo', 'burger', 'pizza', 'fast food'];
$foods = array_values(array_filter($foods, function ($food) use ($blocked) {
$plain = mb_strtolower($food);
foreach ($blocked as $word) {
if (str_contains($plain, $word)) {
return false;
}
}
return true;
}));
}

return [
'foods' => $foods,
'workouts' => $workouts,
];
}

private function foodPreferenceContext(int $userId): array
{
$prefs = [
'likes' => [],
'dislikes' => [],
'allergies' => [],
'blocked' => [],
];

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
$prefs = $ctx['food_preferences'] ?? [];
$blocked = $this->expandedBlockedTerms($prefs['blocked'] ?? []);

if (empty($blocked)) {
return array_values($foods);
}

return array_values(array_filter($foods, function ($food) use ($blocked) {
return !$this->foodMatchesAnyTerm((string) $food, $blocked);
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
$expanded = array_merge($expanded, ['tom', 'cua', 'muc', 'ngheu', 'so', 'oc', 'hau']);
}

if ($plain === 'sua' || str_contains($plain, 'sua bo')) {
$expanded = array_merge($expanded, ['sua chua', 'pho mai', 'whey']);
}
}

return array_values(array_unique($expanded));
}

private function foodMatchesAnyTerm(string $food, array $terms): bool
{
$foodPlain = $this->plainText($food);
if ($foodPlain === '') {
return false;
}

foreach ($terms as $term) {
$termPlain = $this->plainText((string) $term);
if ($termPlain === '') {
continue;
}

if (str_contains($foodPlain, $termPlain) || str_contains($termPlain, $foodPlain)) {
return true;
}
}

return false;
}

private function foodPreferencePromptBlock(array $ctx): string
{
$prefs = $ctx['food_preferences'] ?? [];
$allergies = $prefs['allergies'] ?? [];
$dislikes = $prefs['dislikes'] ?? [];
$likes = $prefs['likes'] ?? [];

if (empty($allergies) && empty($dislikes) && empty($likes)) {
return 'Chua ghi nhan.';
}

$lines = [];
if (!empty($allergies)) {
$lines[] = 'Di ung: ' . implode(', ', $allergies);
}
if (!empty($dislikes)) {
$lines[] = 'Khong an / khong thich: ' . implode(', ', $dislikes);
}
if (!empty($likes)) {
$lines[] = 'Thich: ' . implode(', ', $likes);
}

return implode("\n", $lines);
}

private function foodSafetyRules(array $ctx): string
{
$prefs = $ctx['food_preferences'] ?? [];
$blocked = $this->expandedBlockedTerms($prefs['blocked'] ?? []);

if (empty($blocked)) {
return '';
}

$list = implode(', ', array_values(array_unique($blocked)));
return "TUYET DOI khong duoc goi y mon co chua cac thuc pham nguoi dung di ung/khong an: {$list}. Neu nguoi dung hoi ve mon do, phai tu choi nhe nhang va de xuat mon thay the an toan.";
}

private function proteinSourcesText(array $ctx): string
{
$sources = [
'ca',
'thit nac',
'sua chua Hy Lap it duong',
'sua dau nanh',
'dau lang',
'dau xanh',
'bo nac',
'pho/bun co thit nac',
'tom hap',
];

$sources = $this->filterBlockedFoods($sources, $ctx);
return implode(', ', $sources);
}

private function isFoodIntent(string $message): bool
{
$plain = $this->plainText($message);
$keywords = ['an', 'mon', 'bua', 'dinh duong', 'calo', 'protein', 'goi y', 'nen', 'duoc khong', 'di ung', 'khong an'];

foreach ($keywords as $keyword) {
if (str_contains($plain, $keyword)) {
return true;
}
}

return false;
}

private function blockedFoodMentioned(string $message, array $ctx): ?string
{
$prefs = $ctx['food_preferences'] ?? [];
$blocked = $this->expandedBlockedTerms($prefs['blocked'] ?? []);

foreach ($blocked as $term) {
if ($this->foodMatchesAnyTerm($message, [$term])) {
return (string) $term;
}
}

return null;
}

private function blockedFoodReply(array $ctx, string $term): string
{
$name = $ctx['ten'] ?? 'bạn';
$displayTerm = $this->displayFoodTerm($term);
$alternatives = array_slice($this->mealOptionsForNow($ctx), 0, 2);
$alternativeText = !empty($alternatives)
? implode(' hoặc ', $alternatives)
: 'cá hấp gừng với rau hoặc bún thịt nạc nhiều rau';

return "{$name} ơi, mình đã ghi nhớ bạn dị ứng/không ăn {$displayTerm}, nên mình sẽ không gợi ý món có {$displayTerm}. Thay vào đó, bạn chọn {$alternativeText} sẽ an toàn hơn nhé.";
}

private function displayFoodTerm(string $term): string
{
$map = [
'tom' => 'tôm',
'cua' => 'cua',
'muc' => 'mực',
'ngheu' => 'nghêu',
'so' => 'sò',
'oc' => 'ốc',
'hau' => 'hàu',
'hai san' => 'hải sản',
'sua' => 'sữa',
'sua chua' => 'sữa chua',
'pho mai' => 'phô mai',
];

$plain = $this->plainText($term);
return $map[$plain] ?? $term;
}

private function learnFoodPreference(int $userId, string $message): void
{
if (!Schema::hasTable(self::FOOD_PREF_TABLE)) {
return;
}

$plain = $this->plainText($message);
$patterns = [
'allergy' => [
'/di ung(?: voi)?\s+([^.,;!?]+)/u',
'/khong an duoc\s+([^.,;!?]+)/u',
'/khong dung duoc\s+([^.,;!?]+)/u',
'/(?:toi|minh|t|em)\s+khong an\s+([^.,;!?]+)/u',
'/([^.,;!?]+?)\s+lam (?:toi|minh|t|em).*(?:noi man|ngua|dau bung|kho chiu)/u',
],
'dislike' => [
'/(?:ghet|khong thich|ngan)\s+([^.,;!?]+)/u',
'/khong muon an\s+([^.,;!?]+)/u',
],
'like' => [
'/(?:thich|me|hay an)\s+([^.,;!?]+)/u',
],
];

foreach ($patterns as $type => $typePatterns) {
foreach ($typePatterns as $pattern) {
if (!preg_match($pattern, $plain, $matches)) {
continue;
}

$food = $this->cleanPreferenceFood($matches[1] ?? '');
if ($food === '') {
continue;
}

DB::table(self::FOOD_PREF_TABLE)->updateOrInsert(
['NguoiDungID' => $userId, 'FoodName' => $food],
[
'PreferenceType' => $type,
'NgayTao' => now(),
'NgayCapNhat' => now(),
]
);
return;
}
}
}

private function cleanPreferenceFood(string $food): string
{
$food = $this->plainText($food);
$food = preg_replace('/\b(thi|nen|vi|la|lam|minh|toi|t|em|bi|noi man|ngua|dau bung|kho chiu)\b.*$/u', '', $food) ?? $food;
$food = trim(preg_replace('/\s+/', ' ', $food) ?? $food);

return mb_substr($food, 0, 80);
}

private function containsUnsafeContent(string $message): bool
{
$unsafe = [
'tự tử',
'overdose',
'nhịn ăn',
'tự hại',
'uống thuốc quá liều',
'kill myself',
];
$msg = mb_strtolower($message);
foreach ($unsafe as $word) {
if (str_contains($msg, $word)) {
return true;
}
}
return false;
}

private function cleanReply(?string $reply): string
{
$reply = $reply ?? '';
$reply = preg_replace('/\*?\*?user:\*?\*?/i', '', $reply);
$reply = preg_replace('/\*?\*?assistant:\*?\*?/i', '', $reply);
$reply = preg_replace('/USER:/i', '', $reply);
$reply = preg_replace('/ASSISTANT:/i', '', $reply);
return trim($reply);
}

/**
* Đảm bảo context có đủ key để không bị undefined index.
*/
private function normalizeContext(array $ctx, int $userId): array
{
$defaults = [
'user_id' => $userId,
'ten' => $ctx['ten'] ?? 'Bạn',
'tuoi' => $ctx['tuoi'] ?? '—',
'gioi_tinh' => $ctx['gioi_tinh'] ?? '—',
'bmi' => $ctx['bmi'] ?? 0,
'bmi_label' => $ctx['bmi_label'] ?? '—',
'bmi_advice' => $ctx['bmi_advice'] ?? '',
'health_score' => $ctx['health_score'] ?? 100,
'calorie_context' => $ctx['calorie_context'] ?? '',
'calo_nap' => $ctx['calo_nap'] ?? 0,
'calo_dot' => $ctx['calo_dot'] ?? 0,
'protein' => $ctx['protein'] ?? 0,
'carb' => $ctx['carb'] ?? 0,
'fat' => $ctx['fat'] ?? 0,
'meal_summary' => $ctx['meal_summary'] ?? '',
'activity_summary' => $ctx['activity_summary'] ?? '',
'nutrition_advice' => $ctx['nutrition_advice'] ?? '',
'nhom_mau' => $ctx['nhom_mau'] ?? '',
'benh_nen' => $ctx['benh_nen'] ?? '',
'the_trang' => $ctx['the_trang'] ?? '',
'muc_do_van_dong' => $ctx['muc_do_van_dong'] ?? '',
'che_do_an' => $ctx['che_do_an'] ?? '',
'muc_tieu' => $ctx['muc_tieu'] ?? '',
];

return array_merge($defaults, $ctx);
}
}

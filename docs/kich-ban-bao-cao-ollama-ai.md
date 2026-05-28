# Kich ban bao cao Ollama va AI trong du an SaludAI

## 1. Muc tieu khi bao cao

Sau phan nay, nguoi nghe can nam duoc 5 y:

- Ollama la gi va vi sao dung Ollama thay vi goi cloud AI truc tiep.
- Cach cai, tai model, chay model va goi API Ollama.
- Du an dang tich hop Ollama o dau trong backend Laravel.
- Model nao dang dung, vai tro cua tung model, va vi sao chon model nho.
- Cach AI duoc ap dung vao bai toan suc khoe: chatbot, tom tat ngu canh, goi y an uong/luyen tap, va health score.

## 2. Loi mo dau 30-45 giay

"Trong do an cua nhom em, phan AI khong phai la mot man hinh rieng de cho dep, ma duoc gan vao bai toan cham soc suc khoe ca nhan. Nguoi dung ghi nhan can nang, BMI, bua an, van dong, nuoc uong va thuoc. He thong gom cac du lieu do lai, tinh health score, va dung tro ly AI de tra loi bang tieng Viet theo ngu canh cua tung nguoi.

De chay AI, nhom em dung Ollama. Ollama co the hieu don gian la mot runtime giup tai va chay cac mo hinh ngon ngu lon tren may local hoac server rieng. Vi vay backend Laravel cua nhom co the goi model qua HTTP API, giong cach goi mot service noi bo."

## 3. Ollama la gi?

Noi ngan:

"Ollama la cong cu giup chay LLM local. Thay vi minh tu cai moi thu nhu model weights, runtime, tokenizer va API server, Ollama dong goi cac phan do lai. Minh chi can pull model, run model, sau do goi API o cong mac dinh `11434`."

Noi sau hon neu thay hoi:

- LLM la large language model, nhan prompt dau vao va sinh text dau ra.
- Ollama khong phai la model. Ollama la lop chay model.
- Model moi la `qwen2.5:3b`, `gemma:2b`, `gemma3`, `llama`, `mistral`, ...
- Ollama cung cap CLI nhu `ollama pull`, `ollama run`, `ollama ls`, `ollama serve`.
- Ollama cung cap HTTP API, trong du an minh goi endpoint `/api/chat`.

Vi du de noi:

```bash
ollama pull qwen2.5:3b
ollama run qwen2.5:3b
ollama serve
```

## 4. Su dung Ollama nhu the nao?

Quy trinh co ban:

1. Cai Ollama tren may.
2. Tai model bang lenh `ollama pull <ten-model>`.
3. Chay thu bang `ollama run <ten-model>`.
4. Backend goi API Ollama qua `http://127.0.0.1:11434/api/chat`.

Vi du request dang dung trong du an:

```json
{
  "model": "qwen2.5:3b",
  "messages": [
    {
      "role": "system",
      "content": "Ban la SaludAI, tro ly suc khoe tieng Viet..."
    },
    {
      "role": "user",
      "content": "Hom nay toi nen an gi?"
    }
  ],
  "stream": false,
  "keep_alive": "30m",
  "options": {
    "temperature": 0.55,
    "top_p": 0.9,
    "repeat_penalty": 1.12,
    "num_predict": 160
  }
}
```

Giai thich cac truong quan trong:

- `model`: ten model Ollama se dung.
- `messages`: lich su hoi dap gom system, user, assistant.
- `system`: dat vai tro cho AI, trong du an la tro ly suc khoe tieng Viet.
- `stream: false`: nhan ket qua mot lan, de Laravel tra JSON don gian cho app.
- `keep_alive: 30m`: giu model trong RAM mot thoi gian de lan sau tra loi nhanh hon.
- `temperature`: do sang tao. Du an de 0.55 de cau tra loi vua tu nhien vua it bay.
- `num_predict`: gioi han do dai cau tra loi, tranh model noi qua dai.

## 5. Tich hop vao Laravel ra sao?

Can noi dung luong nay:

"Ollama duoc tich hop nhu mot service ben ngoai. Flutter goi API Laravel. Laravel lay du lieu suc khoe trong database, dong goi thanh context, tao prompt, gui sang Ollama, nhan cau tra loi, luu lich su chat va tra ve cho app."

Luong chay:

```text
Flutter app
  -> POST /api/chat/send
  -> ChatController
  -> ChatMessageService
  -> HealthContextService lay BMI, calo, macro, bua an, hoat dong, benh nen, so thich
  -> OpenRouterService goi Ollama /api/chat
  -> ChatHistory luu UserMessage, BotReply, Model
  -> JSON tra ve app
```

File can nam:

- `routes/api.php`: khai bao route `/chat/send`, `/chat/history`, `/chat/clear`.
- `app/Http/Controllers/ChatController.php`: nhan request chat.
- `app/Service/Chat/ChatMessageService.php`: xu ly intent, tao system prompt, fallback rule, luu history.
- `app/Service/HealthContextService.php`: gom ngu canh suc khoe cua nguoi dung.
- `app/Service/OpenRouterService.php`: ten class la OpenRouterService nhung hien tai dang goi Ollama local qua `AI_CHAT_BASE_URL`.
- `.env.example`: co `AI_CHAT_BASE_URL=http://127.0.0.1:11434`, `AI_CHAT_MODEL=qwen2.5:3b`.

Doan noi mau:

"Trong code, service goi AI nam o `OpenRouterService`. Tuy ten class la OpenRouterService, hien tai no khong goi OpenRouter cloud ma doc bien moi truong `AI_CHAT_BASE_URL`, mac dinh la `http://127.0.0.1:11434`. Sau do Laravel post len `/api/chat` cua Ollama. Nhu vay Ollama dong vai tro nhu mot AI server local cho backend."

## 6. Dung voi model nao?

Trong du an hien tai co 2 ten model can phan biet:

- Chatbot: `qwen2.5:3b`, khai bao trong `ChatMessageService::AI_MODEL_VERSION` va `.env.example`.
- Phan tich tong quat: `gemma:2b`, khai bao trong `AnalyzeHealthAction::AI_MODEL_VERSION`.

Can noi that ro:

"Phan chat la phan goi model Ollama truc tiep. Model mac dinh la `qwen2.5:3b` vi nho, de chay local, toc do chap nhan duoc va phu hop tra loi ngan bang tieng Viet. Phan analyze-health hien tai chu yeu la rule-based/template: he thong lay BMI, calo, van dong, tinh score bang cong thuc, tao nhan xet va luu `gemma:2b` nhu model version. Day la diem nhom em can noi trung thuc: khong phai tat ca phan 'AI' deu do LLM sinh truc tiep."

Neu thay hoi "vi sao dung model nho?":

"Vi muc tieu demo la chay duoc on dinh tren may local/server nho. Model 2B-3B tra loi nhanh hon, ton it RAM hon. Doi lai, no khong manh bang model lon, nen nhom em bo sung rule-based logic va guardrail de dam bao cau tra loi khong qua nguy hiem."

## 7. Ap dung vao bai toan cua minh nhu the nao?

### 7.1 Chatbot suc khoe ca nhan

Nguoi dung hoi:

- "Hom nay toi nen an gi?"
- "Toi nen tap bai gi?"
- "Tom tat suc khoe hom nay cua toi"
- "Toi bi met, co nen tap tiep khong?"

He thong khong gui cau hoi thuan tuy sang model. Truoc khi goi model, Laravel them ngu canh:

- Ten, tuoi, gioi tinh.
- BMI va nhan dinh BMI.
- Calo nap, calo dot, protein, carb, fat.
- Bua an hom nay.
- Hoat dong hom nay.
- Benh nen, the trang.
- So thich, mon khong thich, di ung thuc pham.

Noi mau:

"Diem quan trong la prompt cua nhom co du lieu that tu database. Vi du nguoi dung hoi 'toi nen an gi', AI khong doan chung chung, ma co biet hom nay nguoi do da nap bao nhieu calo, protein thap hay cao, co di ung hay khong. Neu phat hien nguoi dung hoi mon bi di ung, code chan bang rule truoc khi goi model."

### 7.2 Health score

`HealthScoreService` tinh diem dua tren nhieu thanh phan:

- BMI.
- Huyet ap.
- Nhip tim.
- Van dong.
- Uong nuoc.
- Dinh duong.
- Tuan thu lich dung thuoc.
- Benh nen/the trang.

Noi mau:

"Health score cua nhom khong de model tu cham tuy y. Nhom dung rule-based scoring co trong so, vi diem suc khoe can on dinh va giai thich duoc. LLM phu hop hon o phan dien giai va hoi dap tu nhien, con diem so nen tinh bang logic ro rang."

### 7.3 Goi y dinh duong va luyen tap

Trong `AnalyzeHealthAction`, flow la:

```text
HealthContextBuilder build context
HealthScoreService tinh diem
NutritionRecommendationService goi y mon an
WorkoutRecommendationService goi y bai tap
HealthAnalysisPresenter tao cau nhan xet
HealthAnalysisPersistenceService luu vao database
```

Ket qua duoc luu vao:

- `diemsuckhoe`: diem va nhan xet.
- `goiydinhduong`: goi y mon an.
- `goiytapluyen`: goi y bai tap.
- `phantichsuckhoeai`: ket qua phan tich va prompt.
- `daily_health_summaries`: tong hop ngay.

## 8. Diem manh, han che, huong phat trien

Diem manh:

- Chay local, de demo, khong phu thuoc API key cloud.
- Du lieu suc khoe van di trong backend cua minh.
- Co rule de tranh tra loi nguy hiem hoac goi y thuc pham nguoi dung di ung.
- Co luu lich su chat va model da dung.

Han che:

- Model nho nen kha nang suy luan/giai thich chua bang model lon.
- Neu may yeu, phan hoi co the cham.
- Ten `OpenRouterService` chua dung voi vai tro hien tai, nen nen doi ten thanh `OllamaService` trong ban nang cap.
- `AnalyzeHealthAction` dang gan nhan AI/model version, nhung chua goi LLM truc tiep de sinh phan tich.

Huong phat trien:

- Doi ten service cho dung nghia: `OpenRouterService` -> `OllamaService`.
- Cho `AnalyzeHealthAction` goi Ollama de sinh phan giai thich ca nhan hoa, nhung van giu health score rule-based.
- Them structured output JSON de model tra ve dung format.
- Them evaluation test: cung mot ho so, AI khong duoc goi y mon nguoi dung di ung, khong chan doan benh, khong dua loi khuyen y te nguy hiem.

## 9. Kich ban noi 5-7 phut

### Phan 1: Gioi thieu bai toan

"Bai toan cua nhom em la ung dung theo doi va ho tro suc khoe ca nhan. Nguoi dung co the ghi nhan ho so, BMI, bua an, van dong, nuoc uong, thuoc va muc tieu. Tu cac du lieu do, he thong tinh health score, goi y dinh duong/luyen tap, va co chatbot de hoi dap theo ngu canh ca nhan."

### Phan 2: Ollama la gi

"Ollama la cong cu de chay cac mo hinh ngon ngu lon tren may local hoac server rieng. Ollama khong phai la model, ma la moi truong de tai, quan ly va chay model. Model co the la Qwen, Gemma, Llama, Mistral. Khi Ollama chay, no mo API local o cong `11434`, nen backend cua nhom co the goi model bang HTTP."

### Phan 3: Cach su dung

"Quy trinh su dung gom: cai Ollama, pull model, run model, sau do goi API. Vi du nhom dung model `qwen2.5:3b`, co the tai bang `ollama pull qwen2.5:3b`. Khi backend Laravel can AI tra loi, no gui request toi `/api/chat` voi model, danh sach messages, va cac option nhu temperature, top_p, num_predict."

### Phan 4: Tich hop trong code

"Trong du an, route chat la `/api/chat/send`. Request di vao `ChatController`, sau do `ChatMessageService` xu ly. Service nay lay context suc khoe tu `HealthContextService`: BMI, calo, macro, bua an, hoat dong, benh nen, so thich an uong. Sau do tao system prompt cho SaludAI va goi `OpenRouterService`. Ten class nay hoi gay nham lan, nhung hien tai no goi Ollama local qua `AI_CHAT_BASE_URL=http://127.0.0.1:11434`, endpoint `/api/chat`."

### Phan 5: Model va ly do chon

"Chatbot dang dung `qwen2.5:3b`. Nhom chon model nho vi phu hop demo local, toc do nhanh hon va yeu cau RAM thap hon model lon. Ngoai ra code co `gemma:2b` o phan analyze-health, nhung hien tai phan do chu yeu dung rule-based va template de tao nhan xet. Nhom em tach ro: rule-based dung cho diem so vi can on dinh, LLM dung cho hoi dap tu nhien."

### Phan 6: Ap dung vao suc khoe

"Khi nguoi dung hoi 'hom nay nen an gi', AI khong tra loi chung chung. Backend dua vao prompt cac du lieu that nhu protein hom nay, calo nap, calo dot, BMI va di ung thuc pham. Neu nguoi dung da ghi di ung hai san, code co rule chan truoc khi model tra loi. Voi bai tap cung vay, neu ho so van dong cao hoac nguoi dung muon bai nhe, he thong uu tien goi y bai phuc hoi nhe."

### Phan 7: Ket luan

"Tom lai, Ollama trong du an dong vai tro AI runtime local. Laravel la tang nghiep vu: lay du lieu, tinh toan, kiem tra an toan, tao prompt va luu ket qua. Model chi la mot phan trong pipeline. Diem nhom em can cai tien tiep theo la doi ten service cho dung, va cho phan analyze-health goi LLM theo structured output de giai thich ca nhan hoa hon."

## 10. Cau hoi thay co the hoi va cach tra loi

### Ollama co phai model khong?

"Khong. Ollama la runtime/cong cu chay model. Model la Qwen, Gemma, Llama, Mistral..."

### Backend goi Ollama bang cach nao?

"Bang HTTP POST toi `http://127.0.0.1:11434/api/chat`. Trong Laravel, code nam o `app/Service/OpenRouterService.php`, dung `Http::timeout(...)->post(...)`."

### Model dang dung la gi?

"Chatbot dang dung `qwen2.5:3b`. Phan analyze-health co khai bao `gemma:2b`, nhung hien tai output phan tich chu yeu duoc tao bang rule/template va luu model version."

### Vi sao khong de AI cham diem suc khoe?

"Vi diem suc khoe can on dinh, lap lai duoc va giai thich duoc. Neu de LLM cham diem, cung mot du lieu co the cho diem khac nhau. Nen nhom dung rule-based scoring, con LLM dung de dien giai va chat tu nhien."

### Neu Ollama loi thi sao?

"Trong `ChatMessageService`, neu goi AI loi thi catch exception va tra fallback reply. Mot so intent nhu summary, nutrition, workout co rule-fast nen khong nhat thiet luc nao cung phai goi model."

### Du lieu nao duoc dua vao prompt?

"Ten, BMI, calo nap/dot, protein/carb/fat, bua an, hoat dong, benh nen, the trang, so thich va di ung thuc pham."

### Co rui ro gi khi dung AI trong suc khoe?

"Co. AI co the noi sai hoac dua loi khuyen qua muc. Vi vay prompt quy dinh khong chan doan benh, gap dau hieu nguy hiem thi khuyen lien he y te. Code cung co rule chan mot so noi dung khan cap va thuc pham bi di ung."

### `stream: false` de lam gi?

"Ollama mac dinh co the stream tung phan output. Du an de `stream: false` de backend nhan tron cau tra loi mot lan roi tra JSON cho app, de xu ly don gian hon."

### `keep_alive` de lam gi?

"De giu model trong bo nho mot khoang thoi gian sau request. Lan goi tiep theo khong can load model lai tu dau, nen nhanh hon."

### `temperature` la gi?

"La tham so dieu khien do ngau nhien/sang tao. Du an de 0.55, muc trung binh, de cau tra loi tu nhien nhung khong qua bay."

## 11. Luyen tap noi cho troi chay

### Lan 1: Noi trong 60 giay

"Ollama la runtime de chay LLM local. Trong du an, Laravel goi Ollama qua `/api/chat`. Chatbot lay context suc khoe tu database, tao system prompt cho SaludAI, gui sang model `qwen2.5:3b`, nhan cau tra loi, luu lich su chat va tra ve app. Health score thi khong de AI cham tuy y, ma tinh bang rule-based de on dinh. AI chu yeu dung de hoi dap va dien giai ca nhan hoa."

### Lan 2: Noi trong 2 phut

Noi theo khung:

1. Bai toan: app theo doi suc khoe.
2. Ollama: runtime local cho LLM.
3. Model: chatbot dung `qwen2.5:3b`, analyze co `gemma:2b` nhung hien la rule/template.
4. Integration: Flutter -> Laravel -> HealthContextService -> Ollama -> ChatHistory.
5. Ly do thiet ke: rule-based cho diem, LLM cho ngon ngu tu nhien.

### Lan 3: Demo flow bang mieng

"Vi du nguoi dung hoi 'toi nen an gi toi nay?'. Request vao `/chat/send`. Backend lay user_id, doc bua an hom nay, macro, BMI, di ung. Neu cau hoi co mon bi di ung thi rule chan truoc. Neu khong, system prompt noi AI la SaludAI va kem du lieu that. Laravel goi Ollama model `qwen2.5:3b`, nhan reply, clean cac nhan USER/ASSISTANT neu co, luu vao `chat_history`, roi tra JSON cho app."

## 12. Can tranh noi sai

Dung noi:

- "Ollama la AI cua minh."
- "Tat ca health score do model tinh."
- "Gemma dang sinh toan bo ket qua analyze-health."
- "AI co the chan doan benh."

Nen noi:

- "Ollama la runtime chay model."
- "Chatbot goi Ollama that qua `/api/chat`."
- "Health score duoc tinh rule-based de on dinh."
- "LLM ho tro hoi dap/dien giai, khong thay the bac si."
- "Phan analyze-health hien tai co model version nhung logic chinh van la rule/template."

## 13. Nguon doc nhanh truoc khi bao cao

- Ollama documentation: https://docs.ollama.com/
- Ollama API `/api/chat`: https://docs.ollama.com/api/chat
- Ollama API pull model: https://docs.ollama.com/api/pull
- Ollama CLI reference: https://docs.ollama.com/cli

# Deploy backend Laravel len Render + Supabase

Tai lieu nay ap dung cho backend Laravel trong project hien tai.

## 1. Tao database tren Supabase

1. Vao Supabase Dashboard va tao project moi.
2. Mo **Connect** trong project database.
3. Copy **Session pooler** connection string dang:

```text
postgres://postgres.[PROJECT_REF]:[PASSWORD]@[REGION].pooler.supabase.com:5432/postgres
```

4. Dung connection string do lam bien `DB_URL` tren Render.

Nen dung pooler session mode vi Render service chay tren moi truong server dai han. Dat `DB_SSLMODE=require`.

## 2. Tao APP_KEY cho Laravel

Chay lenh nay tren may local:

```bash
php artisan key:generate --show
```

Copy gia tri tra ve, vi du `base64:...`, roi dan vao bien `APP_KEY` tren Render.

## 3. Deploy len Render

Project da co cac file:

- `Dockerfile`
- `.dockerignore`
- `docker/start.sh`
- `render.yaml`

Cach lam:

1. Push project len GitHub.
2. Vao Render Dashboard.
3. Tao **Blueprint** tu repository nay, hoac tao **Web Service** va chon runtime **Docker**.
4. Neu dung Blueprint, Render se doc `render.yaml`.
5. Khi Render hoi cac bien `sync: false`, nhap:

```text
APP_KEY=base64:...
APP_URL=https://ten-service-cua-ban.onrender.com
DB_URL=postgres://postgres.[PROJECT_REF]:[PASSWORD]@[REGION].pooler.supabase.com:5432/postgres
AI_CHAT_BASE_URL=https://url-ai-service-cua-ban
```

## 4. Bien moi truong quan trong

```text
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
DB_URL=postgres://...
DB_SSLMODE=require
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
RUN_MIGRATIONS=true
```

## 5. Luu y ve AI chat

Code hien tai goi AI qua `AI_CHAT_BASE_URL` theo API kieu Ollama:

```text
POST {AI_CHAT_BASE_URL}/api/chat
```

Mac dinh local la:

```text
AI_CHAT_BASE_URL=http://127.0.0.1:11434
AI_CHAT_MODEL=qwen2.5:3b
```

Khi len Render, `127.0.0.1:11434` se khong co Ollama. Can mot trong cac cach sau:

- Deploy rieng mot service Ollama/AI co endpoint `/api/chat`, roi gan URL vao `AI_CHAT_BASE_URL`.
- Hoac sua `OpenRouterService` de goi OpenRouter/OpenAI API that su.
- Hoac tam thoi de khong co AI service; app van fallback rule-based o mot so luong, nhung chat AI se loi neu endpoint AI khong phan hoi.

## 6. Kiem tra sau khi deploy

Mo cac URL:

```text
https://ten-service-cua-ban.onrender.com/
https://ten-service-cua-ban.onrender.com/api/users
```

Neu bi loi database, kiem tra lai:

- `DB_URL`
- mat khau database Supabase
- `DB_CONNECTION=pgsql`
- `DB_SSLMODE=require`
- cac bang database da du chua

Project hien tai dang tham chieu nhieu bang tuy bien, nen neu Supabase moi tinh chi chay migration mac dinh thi co the thieu bang. Can import schema/database hien co hoac bo sung migrations cho cac bang app dang dung.

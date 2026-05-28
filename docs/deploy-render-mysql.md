# Deploy backend Laravel len Render + MySQL

Tai lieu nay ap dung cho backend Laravel trong project hien tai.

## 1. Tao MySQL online

Dung mot dich vu MySQL online bat ky, mien la no cho connect tu ben ngoai internet. Vi project local dang chay MySQL/HeidiSQL, dung MySQL online se de hon Supabase PostgreSQL.

Ban can lay 5 thong tin:

```text
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
```

## 2. Export database tu HeidiSQL

Trong HeidiSQL:

1. Chon database local dang dung, vi du `trolysuckhoe`.
2. Right click database.
3. Chon **Export database as SQL**.
4. Tick **Create** va **Data** neu muon mang ca cau truc bang va du lieu demo.
5. Luu thanh file `.sql`.

Sau do connect toi MySQL online bang HeidiSQL va import file `.sql` do.

## 3. Tao APP_KEY cho Laravel

Chay lenh nay trong backend:

```bash
php artisan key:generate --show
```

Copy gia tri `base64:...` de dan vao Render.

## 4. Deploy len Render

Project da co san:

- `Dockerfile`
- `.dockerignore`
- `docker/start.sh`
- `render.yaml`

Cach lam:

1. Push project len GitHub.
2. Vao Render Dashboard.
3. Tao **Blueprint** tu repo `Frogbo197/BeautyHome`.
4. Render doc `render.yaml`.
5. Nhap cac bien moi truong bi `sync: false`:

```text
APP_KEY=base64:...
APP_URL=https://ten-service-cua-ban.onrender.com
DB_HOST=host-mysql-online
DB_DATABASE=ten_database
DB_USERNAME=user
DB_PASSWORD=password
AI_CHAT_BASE_URL=https://url-ai-service-cua-ban
```

Neu chua co AI service online, co the tam thoi dat:

```text
AI_CHAT_BASE_URL=http://127.0.0.1:11434
```

Nhung chat AI online se khong hoat dong cho toi khi co endpoint AI that. Cac luong rule-based/offline van dung duoc tuy man hinh.

## 5. Bien moi truong quan trong

```text
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
RUN_MIGRATIONS=false
```

Neu database da import day du tu HeidiSQL, nen dat `RUN_MIGRATIONS=false` de Render khong tu chay migration lam thay doi database.

## 6. Kiem tra sau deploy

Mo:

```text
https://ten-service-cua-ban.onrender.com/
```

Sau do test login hoac API app mobile.

Neu loi database, kiem tra lai:

- MySQL online co cho connect tu Render khong.
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- Database da import du bang chua.
- Neu MySQL bat buoc SSL, can cau hinh them SSL theo provider.

## 7. Build APK tro ve backend online

Sau khi Render co URL:

```bash
cd C:\Users\HP\Downloads\Allproblems\Allproblems\banana
flutter build apk --release --split-per-abi --dart-define=API_BASE_URL=https://ten-service-cua-ban.onrender.com/api
```

Lay file:

```text
build\app\outputs\flutter-apk\app-arm64-v8a-release.apk
```

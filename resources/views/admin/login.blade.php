<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập Admin</title>
    <style>
        :root {
            --blue: #0288d1;
            --blue-soft: #e1f5fe;
            --ink: #1f2d3d;
            --muted: #6b7c93;
            --line: #d7ecfb;
            --danger: #d32f2f;
        }

        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background: linear-gradient(135deg, #e1f5fe, #f7fbff 48%, #b3e5fc);
            color: var(--ink);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .login-card {
            width: min(420px, 100%);
            padding: 30px;
            border: 1px solid var(--line);
            border-radius: 22px;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 24px 60px rgba(2, 136, 209, .18);
        }

        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 22px; }
        .brand-icon {
            display: grid;
            width: 52px;
            height: 52px;
            place-items: center;
            border-radius: 50%;
            background: var(--blue-soft);
            color: var(--blue);
            font-size: 24px;
        }

        h1 { margin: 0; font-size: 24px; }
        .subtitle { margin: 4px 0 0; color: var(--muted); font-size: 14px; }
        .field { margin-top: 16px; }
        label { display: block; margin-bottom: 7px; font-weight: 700; }
        input {
            width: 100%;
            min-height: 48px;
            padding: 0 14px;
            border: 1.5px solid var(--line);
            border-radius: 12px;
            outline: none;
            font: inherit;
        }
        input:focus { border-color: var(--blue); box-shadow: 0 0 0 4px rgba(2, 136, 209, .1); }
        .btn {
            width: 100%;
            min-height: 48px;
            margin-top: 22px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, #4fc3f7, var(--blue));
            color: white;
            cursor: pointer;
            font: inherit;
            font-weight: 800;
        }
        .alert {
            margin: 14px 0;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fff3f3;
            color: var(--danger);
            font-size: 14px;
            font-weight: 600;
        }
        .status {
            margin: 14px 0;
            padding: 12px 14px;
            border-radius: 12px;
            background: #eef9f1;
            color: #087a2f;
            font-size: 14px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <main class="login-card">
        <div class="brand">
            <div class="brand-icon">🔐</div>
            <div>
                <h1>HealthAdmin</h1>
                <p class="subtitle">Đăng nhập khu vực quản trị hệ thống</p>
            </div>
        </div>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if (session('warning'))
            <div class="alert">{{ session('warning') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}" autocomplete="off">
            @csrf
            <div class="field">
                <label for="email">Email Admin</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div class="field">
                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" required>
            </div>

            <button class="btn" type="submit">Đăng nhập</button>
        </form>
    </main>
</body>
</html>

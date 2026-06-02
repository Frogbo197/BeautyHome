<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản trị Salud</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Baloo+2:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.x/tabler-icons.min.css">
    <style>
        :root {
            --blue: #4fc3f7;
            --blue-dark: #0288d1;
            --blue-soft: #e1f5fe;
            --blue-mid: #b3e5fc;
            --mint: #b2dfdb;
            --mint-dark: #26a69a;
            --rose: #ffcdd2;
            --rose-dark: #e57373;
            --lavender: #e8eaf6;
            --lavender-dark: #5c6bc0;
            --peach: #ffe0b2;
            --peach-dark: #fb8c00;
            --yellow: #fff59d;
            --cream: #fffde7;
            --bg: #f0f8ff;
            --white: #fff;
            --ink: #26384d;
            --muted: #78909c;
            --line: #e3f2fd;
            --sidebar: 264px;
            --topbar: 72px;
            --radius: 14px;
            --radius-sm: 10px;
            --font-main: 'Nunito', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --font-head: 'Baloo 2', var(--font-main);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { min-width: 0; overflow-x: hidden; }
        body {
            min-height: 100vh;
            background: var(--bg);
            color: var(--ink);
            font-family: var(--font-main);
            font-size: 14px;
        }
        button, input, select, textarea { font: inherit; }
        button { cursor: pointer; }
        [hidden] { display: none !important; }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 100;
            width: var(--sidebar);
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            background: linear-gradient(180deg, #4fc3f7 0%, #75cff8 42%, #aee6fb 100%);
            box-shadow: 4px 0 24px rgba(79, 195, 247, .24);
        }
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 22px 18px 18px;
            border-bottom: 1.5px solid rgba(255, 255, 255, .38);
        }
        .logo-circle {
            display: grid;
            width: 54px;
            height: 54px;
            place-items: center;
            border-radius: 50%;
            background: var(--white);
            color: var(--peach-dark);
            font-size: 24px;
            box-shadow: 0 8px 18px rgba(2, 136, 209, .16);
        }
        .logo-text {
            color: var(--white);
            font-family: var(--font-head);
            font-size: 23px;
            font-weight: 800;
            line-height: 1;
            text-shadow: 0 1px 3px rgba(0, 0, 0, .14);
        }
        .logo-text span {
            display: block;
            margin-top: 4px;
            font-family: var(--font-main);
            font-size: 12px;
            font-weight: 800;
            opacity: .9;
        }
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 16px 12px;
        }
        .nav-section {
            padding: 12px 10px 7px;
            color: rgba(255, 255, 255, .72);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .nav-item {
            width: 100%;
            border: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 46px;
            padding: 0 14px;
            margin-bottom: 4px;
            border-radius: 12px;
            background: transparent;
            color: rgba(255, 255, 255, .92);
            font-weight: 900;
            text-align: left;
            transition: .18s ease;
            white-space: nowrap;
        }
        .nav-item i { width: 20px; font-size: 20px; text-align: center; }
        .nav-item:hover { background: rgba(255, 255, 255, .22); color: var(--white); }
        .nav-item.active {
            background: var(--white);
            color: var(--blue-dark);
            box-shadow: 0 8px 18px rgba(2, 136, 209, .13);
        }
        .nav-badge {
            margin-left: auto;
            min-width: 24px;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--rose-dark);
            color: var(--white);
            font-size: 11px;
            font-weight: 900;
            text-align: center;
        }
        .sidebar-footer {
            padding: 14px;
            border-top: 1.5px solid rgba(255, 255, 255, .28);
        }
        .admin-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            padding: 10px;
            border-radius: 12px;
            background: rgba(255, 255, 255, .24);
            color: var(--white);
        }
        .admin-avatar {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 50%;
            background: var(--white);
            color: var(--lavender-dark);
            font-size: 20px;
        }
        .admin-name { font-weight: 900; }
        .admin-role { font-size: 11px; opacity: .8; }

        .main {
            margin-left: var(--sidebar);
            min-height: 100vh;
            min-width: 0;
        }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 70;
            display: flex;
            align-items: center;
            gap: 16px;
            height: var(--topbar);
            padding: 0 28px;
            background: rgba(255, 255, 255, .96);
            border-bottom: 1.5px solid var(--line);
            box-shadow: 0 2px 12px rgba(79, 195, 247, .09);
        }
        .topbar-title {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
            overflow: hidden;
            color: var(--blue-dark);
            font-family: var(--font-head);
            font-size: 24px;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .topbar-search {
            display: flex;
            align-items: center;
            gap: 8px;
            width: min(320px, 28vw);
            min-height: 40px;
            padding: 0 14px;
            border: 1.5px solid var(--blue-mid);
            border-radius: 999px;
            background: var(--bg);
            color: var(--muted);
        }
        .topbar-search input {
            width: 100%;
            min-width: 0;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--ink);
        }
        .topbar-circle {
            position: relative;
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            border: 1.5px solid var(--blue-mid);
            border-radius: 50%;
            background: var(--bg);
            color: var(--blue-dark);
            font-size: 18px;
            font-weight: 900;
        }
        .topbar-circle .dot {
            position: absolute;
            top: 6px;
            right: 8px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--rose-dark);
            border: 1.5px solid var(--white);
        }
        .topbar-avatar {
            display: grid;
            width: 46px;
            height: 46px;
            place-items: center;
            border-radius: 50%;
            color: var(--white);
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            font-size: 20px;
            font-weight: 900;
            box-shadow: 0 4px 14px rgba(2, 136, 209, .25);
        }

        .page {
            display: none;
            min-width: 0;
            padding: 28px 34px 42px;
        }
        .page.active { display: block; }
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }
        .section-title {
            color: #0277bd;
            font-family: var(--font-head);
            font-size: 28px;
            font-weight: 800;
            line-height: 1.1;
        }
        .section-subtitle {
            margin-top: 4px;
            color: var(--muted);
            font-size: 14px;
        }
        .card {
            min-width: 0;
            border: 1.5px solid var(--line);
            border-radius: var(--radius);
            background: var(--white);
            padding: 22px 24px;
        }
        .card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            color: var(--blue-dark);
            font-family: var(--font-head);
            font-size: 19px;
            font-weight: 800;
        }
        .card-title i { font-size: 20px; }
        .grid { display: grid; gap: 18px; min-width: 0; }
        .grid-2 { grid-template-columns: minmax(0, 1.5fr) minmax(320px, .85fr); }
        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .grid-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }

        .stat-card, .risk-card {
            position: relative;
            min-width: 0;
            overflow: hidden;
            min-height: 132px;
            padding: 20px 22px;
            border: 2px solid transparent;
            border-radius: var(--radius);
            background: var(--white);
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .stat-card:hover, .risk-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, .07);
        }
        .stat-card::before, .risk-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 4px;
        }
        .tone-blue { border-color: var(--blue-mid); }
        .tone-blue::before { background: linear-gradient(90deg, var(--blue), #81d4fa); }
        .tone-mint { border-color: var(--mint); }
        .tone-mint::before { background: linear-gradient(90deg, var(--mint-dark), #80cbc4); }
        .tone-rose { border-color: var(--rose); }
        .tone-rose::before { background: linear-gradient(90deg, var(--rose-dark), #ef9a9a); }
        .tone-lavender { border-color: #c5cae9; }
        .tone-lavender::before { background: linear-gradient(90deg, var(--lavender-dark), #9fa8da); }
        .tone-peach { border-color: var(--peach); }
        .tone-peach::before { background: linear-gradient(90deg, var(--peach-dark), #ffb74d); }
        .stat-icon {
            display: grid;
            width: 52px;
            height: 52px;
            place-items: center;
            margin-bottom: 14px;
            border-radius: 12px;
            background: var(--blue-soft);
            color: var(--blue-dark);
            font-size: 24px;
        }
        .tone-rose .stat-icon { background: #ffebee; color: #c62828; }
        .tone-mint .stat-icon { background: #e0f2f1; color: #00897b; }
        .tone-lavender .stat-icon { background: var(--lavender); color: #3949ab; }
        .tone-peach .stat-icon { background: #fff3e0; color: #e65100; }
        .stat-value {
            color: #121b73;
            font-family: var(--font-head);
            font-size: 34px;
            font-weight: 800;
            line-height: 1;
        }
        .stat-label { margin-top: 6px; color: #37474f; font-size: 14px; font-weight: 900; }
        .stat-note { margin-top: 4px; color: #90a4ae; font-size: 12px; overflow-wrap: anywhere; }

        .risk-card {
            border-radius: 18px;
            cursor: pointer;
            text-align: left;
        }
        .risk-card .stat-value { font-size: 30px; }
        .risk-card button { margin-top: 14px; }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 220px;
            max-width: 420px;
            min-height: 42px;
            padding: 0 14px;
            border: 1.5px solid var(--blue-mid);
            border-radius: 999px;
            background: var(--bg);
        }
        .search-box input {
            flex: 1;
            min-width: 0;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--ink);
        }
        .field, .filter-select {
            width: 100%;
            min-height: 42px;
            border: 1.5px solid var(--blue-mid);
            border-radius: 12px;
            outline: 0;
            background: var(--bg);
            color: var(--ink);
            padding: 9px 13px;
        }
        .filter-select {
            width: auto;
            min-width: 150px;
            border-radius: 999px;
            padding-inline: 16px;
        }
        textarea.field { min-height: 112px; resize: vertical; }
        .field:focus, .filter-select:focus, .search-box:focus-within {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(79, 195, 247, .18);
        }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .form-group { display: grid; gap: 7px; }
        .form-label { color: #455a64; font-size: 13px; font-weight: 900; }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1.5px solid var(--line);
        }
        .check-row { display: flex; align-items: center; gap: 8px; color: var(--muted); font-weight: 800; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 40px;
            border: 0;
            border-radius: 999px;
            padding: 0 18px;
            background: var(--blue);
            color: var(--white);
            font-weight: 900;
            text-decoration: none;
            transition: .18s ease;
            white-space: nowrap;
        }
        .btn:hover { background: var(--blue-dark); }
        .btn-ghost {
            border: 1.5px solid var(--blue-mid);
            background: var(--white);
            color: var(--blue-dark);
        }
        .btn-ghost:hover { background: var(--blue-soft); }
        .btn-danger { background: var(--rose); color: #c62828; }
        .btn-danger:hover { background: #ef9a9a; }
        .btn-success { background: var(--mint); color: #004d40; }
        .btn-success:hover { background: #80cbc4; }
        .btn-warning { background: var(--peach); color: #bf360c; }
        .btn-warning:hover { background: #ffcc80; }
        .btn-sm { min-height: 32px; padding: 0 12px; font-size: 12px; }
        .btn:disabled { opacity: .55; cursor: wait; }
        .actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        .table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            border-radius: var(--radius-sm);
        }
        .data-table {
            width: 100%;
            min-width: 800px;
            border-collapse: separate;
            border-spacing: 0;
        }
        .data-table th {
            padding: 12px 14px;
            border-bottom: 2px solid var(--blue-mid);
            background: var(--blue-soft);
            color: #0277bd;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .05em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .data-table th:first-child { border-radius: 10px 0 0 0; }
        .data-table th:last-child { border-radius: 0 10px 0 0; }
        .data-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            color: #37474f;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }
        .data-table tr:hover td { background: var(--bg); }

        .avatar-row { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .avatar {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            flex: 0 0 auto;
            overflow: hidden;
            border-radius: 50%;
            background: var(--blue-mid);
            color: #0277bd;
            font-weight: 900;
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-lg { width: 96px; height: 96px; font-size: 34px; }
        .primary-text { color: var(--ink); font-weight: 900; }
        .muted { color: var(--muted); font-size: 12.5px; }
        .badge, .tag, .macro-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 26px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }
        .badge-active { background: var(--mint); color: #004d40; }
        .badge-locked { background: var(--rose); color: #b71c1c; }
        .badge-high { background: var(--rose); color: #b71c1c; }
        .badge-medium { background: var(--peach); color: #bf360c; }
        .badge-low { background: #fff9c4; color: #f57f17; }
        .badge-info { background: var(--blue-mid); color: #01579b; }
        .badge-done { background: var(--mint); color: #00695c; }
        .tag-mint, .macro-p { background: var(--mint); color: #00695c; }
        .tag-yellow, .macro-c { background: #fff9c4; color: #f57f17; }
        .tag-rose, .macro-f { background: var(--rose); color: #ad1457; }
        .tag-blue { background: var(--blue-mid); color: #01579b; }
        .tag-lavender { background: var(--lavender); color: #283593; }
        .mini-stats { display: flex; flex-wrap: wrap; gap: 7px; }
        .mini-stats span {
            padding: 6px 9px;
            border-radius: 999px;
            background: #f3f6fb;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .notice-list { display: grid; gap: 12px; }
        .notice-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
            padding: 14px 16px;
            border: 1.5px solid var(--line);
            border-radius: 14px;
            background: var(--white);
        }
        .notice-card.alert-high { border-color: #ffab91; background: #fff8f3; }
        .notice-card.alert-medium { border-color: #ffcc80; background: #fffdf1; }
        .notice-icon {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 50%;
            background: var(--blue-soft);
            color: var(--blue-dark);
            font-size: 18px;
        }
        .notice-title { color: #37474f; font-size: 14px; font-weight: 900; }
        .notice-msg { margin-top: 3px; color: var(--muted); font-size: 13px; overflow-wrap: anywhere; }

        .empty-state {
            display: grid;
            place-items: center;
            min-height: 220px;
            padding: 26px;
            text-align: center;
            color: var(--muted);
        }
        .empty-icon {
            display: grid;
            width: 62px;
            height: 62px;
            place-items: center;
            margin-bottom: 12px;
            border-radius: 18px;
            background: var(--blue-soft);
            color: var(--blue-dark);
            font-size: 30px;
        }
        .empty-title { color: var(--ink); font-size: 18px; font-weight: 900; }
        .empty-desc { margin: 6px auto 14px; max-width: 360px; }
        .loading-skeleton {
            display: grid;
            gap: 12px;
            padding: 14px;
        }
        .skeleton-line {
            height: 18px;
            border-radius: 999px;
            background: linear-gradient(90deg, #e9f7ff, #fff, #e9f7ff);
            background-size: 240% 100%;
            animation: pulse 1.2s ease infinite;
        }
        @keyframes pulse {
            0% { background-position: 0% 50%; }
            100% { background-position: 100% 50%; }
        }

        .resource-editor {
            display: grid;
            gap: 14px;
            margin-bottom: 18px;
            padding: 16px;
            border: 1.5px solid var(--blue-mid);
            border-radius: var(--radius);
            background: var(--bg);
        }
        .resource-editor[hidden] { display: none !important; }

        .drawer {
            position: fixed;
            inset: 0 0 0 auto;
            z-index: 230;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            width: min(960px, calc(100vw - 24px));
            max-width: 100vw;
            background: rgba(255, 255, 255, .99);
            border-left: 1.5px solid var(--line);
            box-shadow: -24px 0 60px rgba(36, 48, 68, .18);
            transform: translateX(102%);
            transition: transform .24s ease;
        }
        .drawer.open { transform: translateX(0); }
        .drawer-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 22px;
            border-bottom: 1.5px solid var(--line);
        }
        .drawer-body { overflow: auto; padding: 20px 22px 24px; }
        .profile-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 20px; align-items: start; }
        .profile-card { text-align: center; }
        .profile-name { margin-top: 12px; color: var(--blue-dark); font-family: var(--font-head); font-size: 23px; font-weight: 800; }
        .profile-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 18px; }
        .profile-stat {
            padding: 12px;
            border-radius: 12px;
            background: var(--bg);
        }
        .profile-stat strong { display: block; color: var(--blue-dark); font-size: 20px; }
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 18px;
            padding: 4px;
            border-radius: 12px;
            background: var(--blue-soft);
            overflow-x: auto;
        }
        .tab {
            border: 0;
            flex: 0 0 auto;
            padding: 9px 16px;
            border-radius: 9px;
            background: transparent;
            color: #546e7a;
            font-weight: 900;
        }
        .tab.active { background: var(--white); color: var(--blue-dark); box-shadow: 0 2px 8px rgba(0, 0, 0, .08); }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        .info-grid { display: grid; gap: 10px; }
        .info-row {
            display: grid;
            grid-template-columns: 170px minmax(0, 1fr);
            gap: 12px;
            padding: 11px 0;
            border-bottom: 1px solid var(--line);
        }
        .info-label { color: var(--muted); font-weight: 800; }
        .info-value { color: var(--ink); font-weight: 800; overflow-wrap: anywhere; }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 240;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(36, 48, 68, .28);
        }
        .modal-backdrop.open { display: flex; }
        .modal {
            width: min(680px, 100%);
            max-height: calc(100vh - 36px);
            overflow: auto;
            border-radius: 18px;
            background: var(--white);
            box-shadow: 0 18px 60px rgba(36, 48, 68, .24);
        }
        .modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 20px;
            border-bottom: 1.5px solid var(--line);
        }
        .modal-body { padding: 20px; }
        .user-picker {
            position: relative;
            display: grid;
            gap: 10px;
        }
        .picker-results {
            display: grid;
            max-height: 220px;
            overflow: auto;
            gap: 8px;
            padding: 8px;
            border: 1.5px solid var(--blue-mid);
            border-radius: 12px;
            background: var(--bg);
        }
        .picker-item {
            border: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 9px;
            border-radius: 10px;
            background: var(--white);
            text-align: left;
        }
        .picker-item:hover, .picker-item.active { outline: 2px solid var(--blue-mid); }
        .overlay {
            position: fixed;
            inset: 0;
            z-index: 220;
            display: none;
            background: rgba(36, 48, 68, .22);
        }
        .overlay.open { display: block; }
        .toast {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 300;
            max-width: min(420px, calc(100vw - 44px));
            padding: 14px 16px;
            border-radius: 12px;
            color: var(--white);
            background: var(--ink);
            box-shadow: 0 10px 28px rgba(36, 48, 68, .2);
            opacity: 0;
            pointer-events: none;
            transform: translateY(18px);
            transition: .22s ease;
        }
        .toast.show { opacity: 1; transform: translateY(0); }

        @media (max-width: 1180px) {
            .sidebar { position: static; width: 100%; min-height: auto; }
            .sidebar-logo { padding: 14px 16px; }
            .sidebar-nav { padding: 10px; }
            .sidebar-nav nav { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 6px; }
            .nav-section, .sidebar-footer { display: none; }
            .main { margin-left: 0; }
            .grid-2, .profile-layout { grid-template-columns: 1fr; }
            .grid-5 { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 760px) {
            body { font-size: 13px; }
            .topbar { height: auto; min-height: 64px; padding: 10px 12px; display: grid; grid-template-columns: 1fr auto; }
            .topbar-search { width: 100%; grid-column: 1 / -1; }
            .topbar-circle { display: none; }
            .page { padding: 16px 12px 24px; }
            .section-header { display: grid; }
            .section-title { font-size: 24px; }
            .grid-3, .grid-4, .grid-5, .form-grid { grid-template-columns: 1fr; }
            .card { padding: 16px; }
            .data-table { min-width: 760px; }
            .drawer { width: 100vw; }
            .info-row { grid-template-columns: 1fr; gap: 4px; }
            .btn, .filter-select { width: 100%; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-circle">🔔</div>
            <div class="logo-text">HealthAdmin<span>Quản trị hệ thống</span></div>
        </div>
        <div class="sidebar-nav">
            <div class="nav-section">Tổng quan</div>
            <nav aria-label="Điều hướng quản trị">
                <button class="nav-item active" type="button" data-view-link="dashboard">📊 Dashboard</button>
                <button class="nav-item" type="button" data-view-link="users">👥 Người dùng <span class="nav-badge" id="navUserCount">0</span></button>
                <button class="nav-item" type="button" data-view-link="alerts">⚠️ Cảnh báo sức khỏe <span class="nav-badge" id="navAlertCount">0</span></button>
                <button class="nav-item" type="button" data-view-link="notifications">🔔 Thông báo <span class="nav-badge" id="navNoticeCount">0</span></button>
                <button class="nav-item" type="button" data-view-link="foods">🥗 Thực phẩm</button>
                <button class="nav-item" type="button" data-view-link="medicines">💊 Thuốc</button>
                <button class="nav-item" type="button" data-view-link="activities">🏃 Hoạt động</button>
                <button class="nav-item" type="button" data-view-link="reports">📈 Báo cáo</button>
                <button class="nav-item" type="button" data-view-link="settings">⚙️ Cài đặt</button>
            </nav>
        </div>
        <div class="sidebar-footer">
            <div class="admin-chip">
                <div class="admin-avatar">🤖</div>
                <div>
                    <div class="admin-name">Admin</div>
                    <div class="admin-role">Quản trị viên</div>
                </div>
            </div>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-title" id="topbarTitle">📊 Dashboard</div>
            <div class="topbar-search">
                <i class="ti ti-search"></i>
                <input id="globalSearch" type="search" placeholder="Tìm kiếm...">
            </div>
            <div class="topbar-circle"><i class="ti ti-bell"></i><span class="dot"></span></div>
            <div class="topbar-circle"><i class="ti ti-settings"></i></div>
            <div class="topbar-avatar">A</div>
        </header>

        <section class="page active" id="page-dashboard" data-view-panel="dashboard">
            <div class="section-header">
                <div>
                    <div class="section-title">Tổng quan hệ thống</div>
                    <div class="section-subtitle" id="generatedAt">Đang tải dữ liệu...</div>
                </div>
                <button class="btn" type="button" id="reloadBtn"><i class="ti ti-refresh"></i>Làm mới</button>
            </div>
            <div class="grid grid-5" id="overviewStats">
                <div class="loading-skeleton card" style="grid-column:1/-1">
                    <div class="skeleton-line"></div><div class="skeleton-line"></div><div class="skeleton-line"></div>
                </div>
            </div>

            <div class="section-header" style="margin-top:26px">
                <div>
                    <div class="section-title" style="font-size:23px">Cảnh báo sức khỏe nổi bật</div>
                    <div class="section-subtitle">Các nhóm rủi ro cần quản trị viên theo dõi</div>
                </div>
            </div>
            <div class="grid grid-4" id="riskSummary"></div>

            <div class="grid grid-2" style="margin-top:22px">
                <section class="card">
                    <div class="card-title"><i class="ti ti-alert-triangle"></i>Cảnh báo mới nhất</div>
                    <div class="notice-list" id="dashboardAlerts"></div>
                </section>
                <section class="card">
                    <div class="card-title"><i class="ti ti-chart-bar"></i>Thống kê 7 ngày qua</div>
                    <div class="grid" id="weeklyBars"></div>
                </section>
            </div>

            <div class="grid grid-2" style="margin-top:22px">
                <section class="card">
                    <div class="card-title"><i class="ti ti-apps"></i>Các tính năng</div>
                    <div class="grid grid-3" id="featureList"></div>
                </section>
                <section class="card">
                    <div class="card-title"><i class="ti ti-bell"></i>Thông báo gần đây</div>
                    <div class="mini-stats" id="notificationSummary" style="margin-bottom:14px"></div>
                    <div class="notice-list" id="noticeList"></div>
                </section>
            </div>
        </section>

        <section class="page" id="page-users" data-view-panel="users">
            <div class="section-header">
                <div>
                    <div class="section-title">Người dùng</div>
                    <div class="section-subtitle" id="accountMeta">Quản lý trạng thái và hồ sơ người dùng</div>
                </div>
            </div>
            <section class="card">
                <div class="toolbar">
                    <div class="search-box">
                        <i class="ti ti-search"></i>
                        <input id="searchInput" type="search" placeholder="Tìm họ tên hoặc email">
                    </div>
                    <select class="filter-select" id="statusFilter" aria-label="Trạng thái tài khoản">
                        <option value="">Tất cả trạng thái</option>
                        <option value="active">Đang hoạt động</option>
                        <option value="locked">Đã khóa</option>
                    </select>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Người dùng</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                                <th>Lần đăng nhập cuối</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="accountRows">
                            <tr><td colspan="5"><div class="loading-skeleton"><div class="skeleton-line"></div><div class="skeleton-line"></div></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        <section class="page" id="page-alerts" data-view-panel="alerts">
            <div class="section-header">
                <div>
                    <div class="section-title">Cảnh báo sức khỏe</div>
                    <div class="section-subtitle">Theo dõi các dấu hiệu bất thường và nhắc nhở người dùng</div>
                </div>
            </div>
            <div class="toolbar">
                <select class="filter-select" id="alertFilter">
                    <option value="all">Tất cả</option>
                    <option value="high">Nguy cơ cao</option>
                    <option value="watch">Cần theo dõi</option>
                    <option value="done">Đã xử lý</option>
                    <option value="open">Chưa xử lý</option>
                </select>
                <button class="btn btn-ghost" type="button" data-open-alerts><i class="ti ti-refresh"></i>Tải lại</button>
            </div>
            <section class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Người dùng</th>
                                <th>Loại cảnh báo</th>
                                <th>Mức độ</th>
                                <th>Thời gian phát hiện</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="alertRows"></tbody>
                    </table>
                </div>
            </section>
        </section>

        <section class="page" id="page-notifications" data-view-panel="notifications">
            <div class="section-header">
                <div>
                    <div class="section-title">Thông báo</div>
                    <div class="section-subtitle">Gửi thông báo cho người dùng bằng tên, không cần nhập ID thủ công</div>
                </div>
                <button class="btn" type="button" id="openNoticeModalBtn"><i class="ti ti-send"></i>Gửi thông báo</button>
            </div>
            <section class="card">
                <div class="toolbar">
                    <div class="search-box">
                        <i class="ti ti-search"></i>
                        <input id="noticeSearch" type="search" placeholder="Tìm theo nội dung hoặc người nhận">
                    </div>
                    <select class="filter-select" id="noticeReadFilter">
                        <option value="all">Tất cả</option>
                        <option value="unread">Chưa đọc</option>
                        <option value="read">Đã đọc</option>
                    </select>
                </div>
                <div class="notice-list" id="noticeListPage"></div>
            </section>
        </section>

        <section class="page" id="page-foods" data-view-panel="foods">
            <div class="section-header">
                <div>
                    <div class="section-title">Thực phẩm</div>
                    <div class="section-subtitle">Quản lý kho thực phẩm và chỉ số dinh dưỡng</div>
                </div>
            </div>
            <section class="card">
                <div class="resource-editor" id="foodEditor">
                    <div class="card-title" id="foodEditorTitle"><i class="ti ti-apple"></i>Thêm thực phẩm</div>
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">Tên món</label><input class="field" id="foodName" type="text" placeholder="Ví dụ: Cơm trắng"></div>
                        <div class="form-group"><label class="form-label">Đơn vị</label><input class="field" id="foodUnit" type="text" value="Gram"></div>
                        <div class="form-group"><label class="form-label">Calories</label><input class="field" id="foodCalories" type="number" min="0" step="0.1"></div>
                        <div class="form-group"><label class="form-label">Protein</label><input class="field" id="foodProtein" type="number" min="0" step="0.1"></div>
                        <div class="form-group"><label class="form-label">Carb</label><input class="field" id="foodCarb" type="number" min="0" step="0.1"></div>
                        <div class="form-group"><label class="form-label">Fat</label><input class="field" id="foodFat" type="number" min="0" step="0.1"></div>
                        <div class="form-group"><label class="form-label">Khối lượng gram</label><input class="field" id="foodWeight" type="number" min="0" step="0.1" value="100"></div>
                        <div class="form-group"><label class="form-label">Loại thực phẩm</label><input class="field" id="foodType" type="text"></div>
                        <div class="form-group"><label class="form-label">Từ khóa</label><input class="field" id="foodKeywords" type="text"></div>
                        <div class="form-group"><label class="form-label">Đánh giá</label><select class="field" id="foodHealthy"><option value="1">Lành mạnh</option><option value="0">Hạn chế</option></select></div>
                    </div>
                    <div class="actions">
                        <button class="btn btn-success" id="saveFoodBtn" type="button"><i class="ti ti-device-floppy"></i>Lưu thực phẩm</button>
                        <button class="btn btn-ghost" id="resetFoodBtn" type="button">Nhập mới</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Món ăn</th><th>Calories</th><th>Protein</th><th>Carb</th><th>Fat</th><th>Healthy</th><th>Thao tác</th></tr></thead>
                        <tbody id="foodRows"></tbody>
                    </table>
                </div>
            </section>
        </section>

        <section class="page" id="page-medicines" data-view-panel="medicines">
            <div class="section-header">
                <div>
                    <div class="section-title">Thuốc</div>
                    <div class="section-subtitle">Quản lý danh mục thuốc thông dụng</div>
                </div>
            </div>
            <section class="card">
                <div class="resource-editor" id="medicineEditor">
                    <div class="card-title" id="medicineEditorTitle"><i class="ti ti-pill"></i>Thêm thuốc</div>
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">Tên thuốc</label><input class="field" id="medicineName" type="text"></div>
                        <div class="form-group"><label class="form-label">Liều lượng</label><input class="field" id="medicineDose" type="text"></div>
                        <div class="form-group"><label class="form-label">Đơn vị</label><input class="field" id="medicineUnit" type="text"></div>
                        <div class="form-group"><label class="form-label">Số lần/ngày</label><input class="field" id="medicineTimes" type="number" min="0"></div>
                        <div class="form-group"><label class="form-label">Hoạt chất</label><input class="field" id="medicineActive" type="text"></div>
                        <div class="form-group"><label class="form-label">Nhóm thuốc</label><input class="field" id="medicineGroup" type="text"></div>
                        <div class="form-group"><label class="form-label">Trạng thái</label><input class="field" id="medicineStatus" type="text" value="chua_den"></div>
                        <div class="form-group"><label class="form-label">Mô tả</label><input class="field" id="medicineDesc" type="text"></div>
                    </div>
                    <textarea class="field" id="medicineSideEffect" placeholder="Tác dụng phụ"></textarea>
                    <textarea class="field" id="medicineWarning" placeholder="Cảnh báo"></textarea>
                    <textarea class="field" id="medicineNote" placeholder="Ghi chú"></textarea>
                    <div class="actions">
                        <button class="btn btn-success" id="saveMedicineBtn" type="button"><i class="ti ti-device-floppy"></i>Lưu thuốc</button>
                        <button class="btn btn-ghost" id="resetMedicineBtn" type="button">Nhập mới</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Thuốc</th><th>Hoạt chất</th><th>Liều dùng</th><th>Nhóm thuốc</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                        <tbody id="medicineRows"></tbody>
                    </table>
                </div>
            </section>
        </section>

        <section class="page" id="page-activities" data-view-panel="activities">
            <div class="section-header"><div><div class="section-title">Hoạt động</div><div class="section-subtitle">Tổng hợp hoạt động vận động từ dữ liệu người dùng</div></div></div>
            <section class="card"><div id="activitySummary" class="grid grid-3"></div></section>
        </section>

        <section class="page" id="page-reports" data-view-panel="reports">
            <div class="section-header"><div><div class="section-title">Báo cáo</div><div class="section-subtitle">Báo cáo tổng quan từ dữ liệu hiện có</div></div></div>
            <section class="card"><div id="reportSummary" class="grid grid-3"></div></section>
        </section>

        <section class="page" id="page-settings" data-view-panel="settings">
            <div class="section-header"><div><div class="section-title">Cài đặt</div><div class="section-subtitle">Thông tin cấu hình giao diện quản trị</div></div></div>
            <section class="card">
                <div class="empty-state">
                    <div class="empty-icon"><i class="ti ti-settings"></i></div>
                    <div class="empty-title">Chưa có cấu hình cần chỉnh</div>
                    <div class="empty-desc">Trang này chỉ hiển thị thông tin giao diện, không thay đổi backend hoặc cấu trúc dữ liệu.</div>
                    <button class="btn btn-ghost" type="button" id="settingsRefreshBtn">Làm mới dữ liệu</button>
                </div>
            </section>
        </section>
    </main>

    <div class="overlay" id="overlay"></div>
    <aside class="drawer" id="profileDrawer" aria-label="Chi tiết người dùng">
        <div class="drawer-head">
            <div>
                <div class="section-title" id="drawerTitle">Chi tiết người dùng</div>
                <div class="section-subtitle" id="drawerSubtitle"></div>
            </div>
            <button class="btn btn-ghost btn-sm" type="button" id="closeDrawer"><i class="ti ti-x"></i>Đóng</button>
        </div>
        <div class="drawer-body">
            <div class="profile-layout">
                <section class="card profile-card">
                    <div class="avatar avatar-lg" id="profileAvatar">U</div>
                    <div class="profile-name" id="profileName">Người dùng</div>
                    <div class="muted" id="profileEmail">email@example.com</div>
                    <div style="margin-top:12px" id="profileStatus"></div>
                    <div class="profile-stats" id="profileStats"></div>
                    <div class="actions" style="justify-content:center;margin-top:16px">
                        <button class="btn btn-danger btn-sm" type="button" id="profileToggleBtn">Khóa</button>
                        <button class="btn btn-ghost btn-sm" type="button" id="profileNoticeBtn">Gửi thông báo</button>
                    </div>
                </section>
                <section class="card">
                    <div class="tabs" id="profileTabs">
                        <button class="tab active" type="button" data-tab="overview">Tổng quan</button>
                        <button class="tab" type="button" data-tab="health">Sức khỏe</button>
                        <button class="tab" type="button" data-tab="nutrition">Dinh dưỡng</button>
                        <button class="tab" type="button" data-tab="medicine">Thuốc</button>
                        <button class="tab" type="button" data-tab="activity">Hoạt động</button>
                        <button class="tab" type="button" data-tab="notice">Thông báo</button>
                    </div>
                    <div id="tab-overview" class="tab-panel active"></div>
                    <div id="tab-health" class="tab-panel"></div>
                    <div id="tab-nutrition" class="tab-panel"></div>
                    <div id="tab-medicine" class="tab-panel"></div>
                    <div id="tab-activity" class="tab-panel"></div>
                    <div id="tab-notice" class="tab-panel"></div>
                </section>
            </div>
        </div>
    </aside>

    <div class="modal-backdrop" id="noticeModal">
        <div class="modal">
            <div class="modal-head">
                <div class="card-title" style="margin:0"><i class="ti ti-send"></i>Gửi thông báo</div>
                <button class="btn btn-ghost btn-sm" type="button" data-close-modal="noticeModal"><i class="ti ti-x"></i>Đóng</button>
            </div>
            <div class="modal-body">
                <div class="user-picker">
                    <label class="form-label">Người nhận</label>
                    <input class="field" id="noticeUserSearchInput" type="search" placeholder="Tìm theo họ tên hoặc email">
                    <div class="picker-results" id="noticeUserResults"></div>
                    <input id="noticeUserId" type="hidden">
                    <div class="notice-card" id="selectedNoticeUser" hidden></div>
                </div>
                <div class="form-grid" style="margin-top:14px">
                    <div class="form-group">
                        <label class="form-label">Loại thông báo</label>
                        <input class="field" id="noticeType" type="text" value="HeThong">
                    </div>
                    <label class="check-row" style="align-self:end"><input id="noticeAll" type="checkbox"> Gửi đến tất cả người dùng</label>
                </div>
                <div class="form-group" style="margin-top:14px">
                    <label class="form-label">Nội dung</label>
                    <textarea class="field" id="noticeContent" placeholder="Nhập nội dung thông báo..."></textarea>
                </div>
                <div class="form-actions">
                    <button class="btn btn-ghost" type="button" data-close-modal="noticeModal">Hủy</button>
                    <button class="btn" type="button" id="sendNoticeBtn"><i class="ti ti-send"></i>Gửi thông báo</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="confirmModal">
        <div class="modal" style="width:min(460px,100%)">
            <div class="modal-head">
                <div class="card-title" style="margin:0"><i class="ti ti-alert-circle"></i>Xác nhận thao tác</div>
                <button class="btn btn-ghost btn-sm" type="button" data-close-modal="confirmModal"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <div class="notice-msg" id="confirmMessage">Bạn có chắc chắn muốn thực hiện thao tác này?</div>
                <div class="form-actions">
                    <button class="btn btn-ghost" type="button" data-close-modal="confirmModal">Hủy</button>
                    <button class="btn btn-danger" type="button" id="confirmOkBtn">Xác nhận</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        const state = {
            accounts: [],
            accountMap: new Map(),
            alerts: [],
            notifications: [],
            foods: [],
            medicines: [],
            selectedAccount: null,
            editingFoodId: null,
            editingMedicineId: null,
            q: '',
            status: '',
            noticeQ: '',
            noticeRead: 'all',
            alertFilter: 'all',
            confirmAction: null,
        };
        const pageTitles = {
            dashboard: '📊 Dashboard',
            users: '👥 Người dùng',
            alerts: '⚠️ Cảnh báo sức khỏe',
            notifications: '🔔 Thông báo',
            foods: '🥗 Thực phẩm',
            medicines: '💊 Thuốc',
            activities: '🏃 Hoạt động',
            reports: '📈 Báo cáo',
            settings: '⚙️ Cài đặt',
        };
        const urlParams = new URLSearchParams(window.location.search);
        const tokenFromUrl = urlParams.get('admin_token') || urlParams.get('token');
        if (tokenFromUrl) {
            localStorage.setItem('admin_api_token', tokenFromUrl);
            urlParams.delete('admin_token');
            urlParams.delete('token');
            history.replaceState(null, '', `${window.location.pathname}${urlParams.toString() ? `?${urlParams}` : ''}${window.location.hash || ''}`);
        }
        const adminApiToken = localStorage.getItem('admin_api_token') || '';
        const adminUserId = localStorage.getItem('admin_user_id') || localStorage.getItem('user_id') || '1';

        const $ = (id) => document.getElementById(id);
        const toastEl = $('toast');
        const drawerEl = $('profileDrawer');
        const overlayEl = $('overlay');

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }
        function number(value) {
            return new Intl.NumberFormat('vi-VN').format(Number(value || 0));
        }
        function dateTime(value) {
            if (!value) return 'Chưa có';
            const parsed = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(parsed.getTime())) return escapeHtml(value);
            return parsed.toLocaleString('vi-VN');
        }
        function initials(name, email) {
            const raw = (name && name !== 'Chưa cập nhật' ? name : email || 'ND').trim();
            const parts = raw.split(/\s+/).filter(Boolean);
            return escapeHtml((parts.length > 1 ? parts[0][0] + parts.at(-1)[0] : raw.slice(0, 2)).toUpperCase());
        }
        function stripUserId(text) {
            return String(text || 'Người dùng').replace(/\s*\(#\d+\)\s*$/, '');
        }
        function userAvatar(user) {
            if (user?.avatar) return `<div class="avatar"><img src="${escapeHtml(user.avatar)}" alt=""></div>`;
            return `<div class="avatar">${initials(user?.name, user?.email)}</div>`;
        }
        function profileAvatarHtml(user) {
            if (user?.avatar) return `<img src="${escapeHtml(user.avatar)}" alt="">`;
            return initials(user?.name, user?.email);
        }
        function showToast(message) {
            toastEl.textContent = message;
            toastEl.classList.add('show');
            clearTimeout(showToast.timer);
            showToast.timer = setTimeout(() => toastEl.classList.remove('show'), 3000);
        }
        function emptyState(icon, title, desc, button = '') {
            return `<div class="empty-state"><div class="empty-icon">${icon}</div><div class="empty-title">${escapeHtml(title)}</div><div class="empty-desc">${escapeHtml(desc)}</div>${button}</div>`;
        }
        function skeletonRows(cols = 5) {
            return `<tr><td colspan="${cols}"><div class="loading-skeleton"><div class="skeleton-line"></div><div class="skeleton-line"></div><div class="skeleton-line"></div></div></td></tr>`;
        }
        async function getJson(url, options = {}) {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    ...(adminApiToken ? { 'X-Admin-Token': adminApiToken } : {}),
                    'X-Admin-User-Id': adminUserId,
                    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                    ...(options.headers || {}),
                },
                ...options,
            });
            const data = await response.json();
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Không thể tải dữ liệu');
            }
            return data;
        }

        function showView(view) {
            const safeView = Object.keys(pageTitles).includes(view) ? view : 'dashboard';
            document.querySelectorAll('[data-view-panel]').forEach(panel => panel.classList.toggle('active', panel.dataset.viewPanel === safeView));
            document.querySelectorAll('[data-view-link]').forEach(link => link.classList.toggle('active', link.dataset.viewLink === safeView));
            $('topbarTitle').textContent = pageTitles[safeView];
            const hash = `#${safeView}`;
            if (window.location.hash !== hash) history.pushState(null, '', hash);
            if (safeView === 'foods') loadFoods().catch(error => showToast(error.message));
            if (safeView === 'medicines') loadMedicines().catch(error => showToast(error.message));
            renderDerivedPages();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function openModal(id) {
            $(id).classList.add('open');
        }
        function closeModal(id) {
            $(id).classList.remove('open');
            if (id === 'confirmModal') state.confirmAction = null;
        }
        function openConfirm(message, action) {
            $('confirmMessage').textContent = message;
            state.confirmAction = action;
            openModal('confirmModal');
        }

        function badgeSeverity(severity) {
            if (severity === 'high') return '<span class="badge badge-high">Nguy cơ cao</span>';
            if (severity === 'medium') return '<span class="badge badge-medium">Cần theo dõi</span>';
            return '<span class="badge badge-low">Theo dõi</span>';
        }
        function normalizeAlert(item, index) {
            const account = state.accountMap.get(Number(item.user_id));
            const fallbackName = stripUserId(item.user);
            return {
                id: `${item.type || 'alert'}-${item.user_id || index}-${index}`,
                raw: item,
                userId: Number(item.user_id || 0),
                name: account?.name || fallbackName || 'Người dùng',
                email: account?.email || 'Chưa có email',
                avatar: account?.avatar || '',
                title: item.title || 'Cảnh báo sức khỏe',
                message: item.message || '',
                type: item.type || 'Sức khỏe',
                severity: item.severity || 'low',
                action: item.action || '',
                time: item.detected_at || item.time || item.created_at || new Date().toISOString(),
                handled: localStorage.getItem(`alert-handled:${item.user_id}:${item.type}:${item.title}`) === '1',
            };
        }
        function currentAlerts() {
            return state.alerts.map(normalizeAlert);
        }
        function alertMatchesFilter(alert) {
            if (state.alertFilter === 'high') return alert.severity === 'high';
            if (state.alertFilter === 'watch') return alert.severity !== 'high';
            if (state.alertFilter === 'done') return alert.handled;
            if (state.alertFilter === 'open') return !alert.handled;
            return true;
        }

        function renderOverview(items) {
            const icons = ['ti-users', 'ti-lock', 'ti-bell', 'ti-alert-triangle', 'ti-heartbeat'];
            $('overviewStats').innerHTML = (items || []).map((item, index) => {
                const tone = item.tone || (index === 0 ? 'blue' : 'mint');
                return `<article class="stat-card tone-${tone === 'sky' ? 'blue' : tone}">
                    <div class="stat-icon"><i class="ti ${icons[index] || 'ti-chart-bar'}"></i></div>
                    <div class="stat-value">${number(item.value)}</div>
                    <div class="stat-label">${escapeHtml(item.label)}</div>
                    <div class="stat-note">${escapeHtml(item.note || '')}</div>
                </article>`;
            }).join('');
        }
        function renderRiskSummary() {
            const alerts = currentAlerts();
            const high = alerts.filter(a => a.severity === 'high').length;
            const watch = alerts.filter(a => a.severity !== 'high').length;
            const missedMedicine = alerts.filter(a => /thuoc|thuốc/i.test(`${a.type} ${a.title} ${a.message}`)).length;
            const waterDrop = alerts.filter(a => /nuoc|nước/i.test(`${a.type} ${a.title} ${a.message}`)).length;
            const cards = [
                ['tone-rose', 'ti-alert-octagon', 'Nguy cơ cao', high, 'Cần xử lý ngay', 'high'],
                ['tone-peach', 'ti-eye', 'Cần theo dõi', watch, 'Theo dõi trong 24h', 'watch'],
                ['tone-lavender', 'ti-pill-off', 'Quên uống thuốc', missedMedicine, 'Có dấu hiệu bỏ lỡ', 'all'],
                ['tone-mint', 'ti-droplet-off', 'Bỏ theo dõi nước uống', waterDrop, 'Lượng nước bất thường', 'all'],
            ];
            $('riskSummary').innerHTML = cards.map(([tone, icon, label, value, note, filter]) => `
                <button class="risk-card ${tone}" type="button" data-risk-filter="${filter}">
                    <div class="stat-icon"><i class="ti ${icon}"></i></div>
                    <div class="stat-value">${number(value)}</div>
                    <div class="stat-label">${label}</div>
                    <div class="stat-note">${note}</div>
                </button>
            `).join('');
            $('navAlertCount').textContent = number(alerts.filter(a => !a.handled).length);
        }
        function renderDashboardAlerts() {
            const alerts = currentAlerts().slice(0, 5);
            $('dashboardAlerts').innerHTML = alerts.length ? alerts.map(alertCard).join('') : emptyState('✨', 'Không có cảnh báo mới', 'Hệ thống chưa phát hiện dấu hiệu sức khỏe bất thường.', '<button class="btn btn-ghost" type="button" data-open-alerts>Xem trang cảnh báo</button>');
        }
        function alertCard(alert) {
            return `<article class="notice-card ${alert.severity === 'high' ? 'alert-high' : 'alert-medium'}">
                ${userAvatar(alert)}
                <div style="min-width:0;flex:1">
                    <div class="notice-title">${escapeHtml(alert.name)} ${badgeSeverity(alert.severity)}</div>
                    <div class="muted">${escapeHtml(alert.email)}</div>
                    <div class="notice-msg"><strong>${escapeHtml(alert.title)}</strong> - ${escapeHtml(alert.message)}</div>
                    <div class="actions" style="margin-top:10px">
                        <button class="btn btn-ghost btn-sm" type="button" data-profile="${alert.userId}"><i class="ti ti-user"></i>Xem hồ sơ</button>
                        <button class="btn btn-sm" type="button" data-notice-user="${alert.userId}" data-notice-message="${escapeHtml(alert.message)}"><i class="ti ti-send"></i>Gửi thông báo</button>
                    </div>
                </div>
            </article>`;
        }
        function renderAlertTable() {
            const rows = currentAlerts().filter(alertMatchesFilter);
            $('alertRows').innerHTML = rows.length ? rows.map(alert => `<tr>
                <td><div class="avatar-row">${userAvatar(alert)}<div><div class="primary-text">${escapeHtml(alert.name)}</div><div class="muted">${escapeHtml(alert.email)}</div></div></div></td>
                <td><span class="tag tag-blue">${escapeHtml(alert.type)}</span><div class="muted">${escapeHtml(alert.title)}</div></td>
                <td>${badgeSeverity(alert.severity)}</td>
                <td>${dateTime(alert.time)}</td>
                <td>${alert.handled ? '<span class="badge badge-done">Đã xử lý</span>' : '<span class="badge badge-high">Chưa xử lý</span>'}</td>
                <td><div class="actions">
                    <button class="btn btn-ghost btn-sm" type="button" data-profile="${alert.userId}">Xem hồ sơ</button>
                    <button class="btn btn-sm" type="button" data-notice-user="${alert.userId}" data-notice-message="${escapeHtml(alert.message)}">Gửi thông báo</button>
                    <button class="btn btn-success btn-sm" type="button" data-handle-alert="${alert.id}">Đã xử lý</button>
                </div></td>
            </tr>`).join('') : `<tr><td colspan="6">${emptyState('✅', 'Không có cảnh báo phù hợp', 'Bộ lọc hiện tại chưa có cảnh báo nào.', '<button class="btn btn-ghost" type="button" data-open-alerts>Tải lại cảnh báo</button>')}</td></tr>`;
        }

        function renderFeatures(items) {
            $('featureList').innerHTML = (items || []).length ? items.map(item => `
                <div class="notice-card" style="background:var(--bg)">
                    <div class="notice-icon"><i class="ti ti-activity"></i></div>
                    <div><div class="notice-title">${escapeHtml(item.label)}</div><div class="notice-msg">${number(item.value)} - ${escapeHtml(item.note || '')}</div></div>
                </div>
            `).join('') : emptyState('📦', 'Chưa có dữ liệu tính năng', 'Dữ liệu module sẽ hiển thị khi hệ thống có bản ghi.');
        }
        function renderWeekly(days) {
            const max = Math.max(1, ...days.map(day => Math.max(day.accounts || 0, day.notifications || 0)));
            $('weeklyBars').innerHTML = `<div style="display:flex;align-items:flex-end;gap:12px;height:180px">${days.map(day => {
                const h = Math.max(10, Math.round((Math.max(day.accounts || 0, day.notifications || 0) / max) * 100));
                return `<div style="flex:1;display:grid;gap:8px;justify-items:center;align-items:end;height:100%">
                    <div title="${number(day.accounts)} tài khoản, ${number(day.notifications)} thông báo" style="width:100%;height:${h}%;border-radius:10px 10px 2px 2px;background:linear-gradient(180deg,var(--blue),#0b84c6)"></div>
                    <span class="muted">${escapeHtml(day.label)}</span>
                </div>`;
            }).join('')}</div>`;
        }

        function notificationMatches(item) {
            const q = state.noticeQ.toLowerCase();
            if (state.noticeRead === 'read' && !item.is_read) return false;
            if (state.noticeRead === 'unread' && item.is_read) return false;
            if (!q) return true;
            return `${item.type} ${item.content} ${item.user}`.toLowerCase().includes(q);
        }
        function notificationCard(item) {
            const name = stripUserId(item.user);
            return `<article class="notice-card">
                <div class="notice-icon"><i class="ti ti-bell"></i></div>
                <div style="min-width:0;flex:1">
                    <div class="notice-title">${escapeHtml(item.type || 'Thông báo')}</div>
                    <div class="notice-msg">${escapeHtml(item.content || 'Không có nội dung')}</div>
                    <div class="muted">${escapeHtml(name)} - ${dateTime(item.time)} ${item.is_read ? '• Đã đọc' : '• Chưa đọc'}</div>
                    <div class="actions" style="margin-top:10px">
                        <button class="btn btn-ghost btn-sm" data-read-notice="${item.id}" type="button">Đánh dấu đọc</button>
                        <button class="btn btn-danger btn-sm" data-delete-notice="${item.id}" type="button">Xóa</button>
                    </div>
                </div>
            </article>`;
        }
        function renderNotifications(data) {
            state.notifications = data.recent || [];
            $('notificationSummary').innerHTML = `<span>Tổng: ${number(data.total)}</span><span>Chưa đọc: ${number(data.unread)}</span><span>Đã đọc: ${number(data.read)}</span><span>Hôm nay: ${number(data.today)}</span>`;
            $('navNoticeCount').textContent = number(data.unread || 0);
            renderNoticeLists();
        }
        function renderNoticeLists() {
            const filtered = state.notifications.filter(notificationMatches);
            const html = filtered.length ? filtered.map(notificationCard).join('') : emptyState('🔔', 'Chưa có thông báo nào', 'Khi hệ thống có thông báo, danh sách sẽ xuất hiện tại đây.', '<button class="btn" type="button" id="emptyOpenNotice">Gửi thông báo đầu tiên</button>');
            $('noticeList').innerHTML = state.notifications.length ? state.notifications.slice(0, 4).map(notificationCard).join('') : emptyState('🔔', 'Chưa có thông báo', 'Thông báo gần đây sẽ hiển thị tại đây.');
            $('noticeListPage').innerHTML = html;
            $('emptyOpenNotice')?.addEventListener('click', () => openNoticeModal());
        }

        function renderAccounts(payload) {
            state.accounts = payload.data || [];
            state.accountMap = new Map(state.accounts.map(account => [Number(account.id), account]));
            $('accountMeta').textContent = `${number(payload.meta?.total || 0)} tài khoản trong hệ thống`;
            $('navUserCount').textContent = number(payload.meta?.total || state.accounts.length);
            renderAccountRows();
            renderNoticeUserPicker();
        }
        function renderAccountRows() {
            const rows = state.accounts;
            $('accountRows').innerHTML = rows.length ? rows.map(account => `<tr>
                <td><div class="avatar-row">${userAvatar(account)}<div><div class="primary-text">${escapeHtml(account.name)}</div><div class="muted">${escapeHtml(account.email)}</div></div></div></td>
                <td><span class="badge ${account.is_active ? 'badge-active' : 'badge-locked'}">${escapeHtml(account.status)}</span></td>
                <td>${dateTime(account.created_at)}</td>
                <td>${dateTime(account.last_login)}</td>
                <td><div class="actions">
                    <button class="btn btn-ghost btn-sm" data-profile="${account.id}" type="button">Xem chi tiết</button>
                    <button class="btn ${account.is_active ? 'btn-danger' : 'btn-success'} btn-sm" data-toggle="${account.id}" data-locked="${account.is_active ? '1' : '0'}" type="button">${account.is_active ? 'Khóa' : 'Mở khóa'}</button>
                </div></td>
            </tr>`).join('') : `<tr><td colspan="5">${emptyState('👥', 'Chưa có người dùng', 'Danh sách người dùng sẽ hiển thị sau khi có tài khoản.', '<button class="btn btn-ghost" type="button" id="reloadUsersEmpty">Tải lại</button>')}</td></tr>`;
            $('reloadUsersEmpty')?.addEventListener('click', () => loadAccounts().catch(error => showToast(error.message)));
        }

        function renderFoods() {
            $('foodRows').innerHTML = state.foods.length ? state.foods.map((food, index) => `<tr>
                <td><div class="avatar-row"><div class="avatar">🥗</div><div><div class="primary-text">${escapeHtml(food.Ten)}</div><div class="muted">${escapeHtml(food.KhoiLuongGram || 100)}g • ${escapeHtml(food.LoaiThucPham || 'Chưa phân loại')}</div></div></div></td>
                <td><strong>${number(food.Calo)}</strong> kcal</td>
                <td><span class="macro-pill macro-p">${escapeHtml(food.Protein || 0)}g</span></td>
                <td><span class="macro-pill macro-c">${escapeHtml(food.Carb || 0)}g</span></td>
                <td><span class="macro-pill macro-f">${escapeHtml(food.ChatBeo || 0)}g</span></td>
                <td>${Number(food.IsHealthy) === 1 ? '<span class="badge badge-active">Lành mạnh</span>' : '<span class="badge badge-locked">Hạn chế</span>'}</td>
                <td><div class="actions"><button class="btn btn-ghost btn-sm" data-edit-food="${index}" type="button">Sửa</button><button class="btn btn-danger btn-sm" data-delete-food="${food.ID}" type="button">Xóa</button></div></td>
            </tr>`).join('') : `<tr><td colspan="7">${emptyState('🥗', 'Chưa có thực phẩm nào', 'Thêm thực phẩm đầu tiên để người dùng ghi nhận bữa ăn.', '<button class="btn" type="button" id="focusFoodForm">Thêm thực phẩm đầu tiên</button>')}</td></tr>`;
            $('focusFoodForm')?.addEventListener('click', () => $('foodName').focus());
        }
        function renderMedicines() {
            $('medicineRows').innerHTML = state.medicines.length ? state.medicines.map((medicine, index) => `<tr>
                <td><div class="avatar-row"><div class="avatar">💊</div><div><div class="primary-text">${escapeHtml(medicine.TenThuoc)}</div><div class="muted">${escapeHtml(medicine.MoTa || medicine.GhiChu || '')}</div></div></div></td>
                <td>${escapeHtml(medicine.HoatChat || 'Chưa cập nhật')}</td>
                <td>${escapeHtml(medicine.LieuLuong || '')} ${escapeHtml(medicine.DonVi || '')}</td>
                <td><span class="tag tag-lavender">${escapeHtml(medicine.NhomThuoc || 'Khác')}</span></td>
                <td><span class="badge badge-info">${escapeHtml(medicine.TrangThai || 'Hoạt động')}</span></td>
                <td><div class="actions"><button class="btn btn-ghost btn-sm" data-edit-medicine="${index}" type="button">Sửa</button><button class="btn btn-danger btn-sm" data-delete-medicine="${medicine.ID}" type="button">Xóa</button></div></td>
            </tr>`).join('') : `<tr><td colspan="6">${emptyState('💊', 'Chưa có thuốc nào', 'Thêm thuốc đầu tiên để người dùng tìm kiếm và lập lịch uống thuốc.', '<button class="btn" type="button" id="focusMedicineForm">Thêm thuốc đầu tiên</button>')}</td></tr>`;
            $('focusMedicineForm')?.addEventListener('click', () => $('medicineName').focus());
        }

        function renderDerivedPages() {
            const totalActivities = state.accounts.reduce((sum, account) => sum + Number(account.stats?.activities || 0), 0);
            $('activitySummary').innerHTML = [
                ['🏃', 'Tổng hoạt động', totalActivities, 'Dựa trên thống kê người dùng'],
                ['🔥', 'Tài khoản có vận động', state.accounts.filter(a => Number(a.stats?.activities || 0) > 0).length, 'Đã ghi nhận hoạt động'],
                ['📋', 'Nguồn dữ liệu', 'activity/stats', 'Giữ nguyên API hiện có'],
            ].map(([icon, title, value, note]) => `<div class="stat-card tone-blue"><div class="stat-icon">${icon}</div><div class="stat-value">${number(value)}</div><div class="stat-label">${title}</div><div class="stat-note">${note}</div></div>`).join('');
            const totalMeals = state.accounts.reduce((sum, account) => sum + Number(account.stats?.meals || 0), 0);
            const totalWater = state.accounts.reduce((sum, account) => sum + Number(account.stats?.water_logs || 0), 0);
            const totalMeds = state.accounts.reduce((sum, account) => sum + Number(account.stats?.medicines || 0), 0);
            $('reportSummary').innerHTML = [
                ['🥗', 'Bữa ăn', totalMeals, 'Tổng bản ghi bữa ăn'],
                ['💧', 'Uống nước', totalWater, 'Tổng lần ghi nhận nước'],
                ['💊', 'Thuốc', totalMeds, 'Tổng lịch sử thuốc'],
            ].map(([icon, title, value, note]) => `<div class="stat-card tone-mint"><div class="stat-icon">${icon}</div><div class="stat-value">${number(value)}</div><div class="stat-label">${title}</div><div class="stat-note">${note}</div></div>`).join('');
        }

        function openDrawer() {
            drawerEl.classList.add('open');
            overlayEl.classList.add('open');
        }
        function closeDrawer() {
            drawerEl.classList.remove('open');
            overlayEl.classList.remove('open');
            state.selectedAccount = null;
        }
        function activateTab(tab) {
            document.querySelectorAll('.tab').forEach(btn => btn.classList.toggle('active', btn.dataset.tab === tab));
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.toggle('active', panel.id === `tab-${tab}`));
        }
        function infoRows(items) {
            return `<div class="info-grid">${items.map(([label, value]) => `<div class="info-row"><div class="info-label">${escapeHtml(label)}</div><div class="info-value">${escapeHtml(value || 'Chưa có dữ liệu')}</div></div>`).join('')}</div>`;
        }
        function renderDetailList(items, icon, title, desc) {
            if (!items || !items.length) return emptyState(icon, title, desc);
            return `<div class="notice-list">${items.map(item => `<div class="notice-card"><div class="notice-icon">${icon}</div><div style="min-width:0">${Object.entries(item).slice(0, 5).map(([key, value]) => `<div><strong>${escapeHtml(key)}:</strong> <span class="muted">${escapeHtml(value)}</span></div>`).join('')}</div></div>`).join('')}</div>`;
        }
        async function openProfile(userId) {
            if (!userId) return;
            try {
                const data = await getJson(`/api/admin/accounts/${userId}`);
                const account = data.account;
                state.selectedAccount = account;
                $('drawerTitle').textContent = `Chi tiết người dùng`;
                $('drawerSubtitle').textContent = `${account.name} • ${account.email}`;
                $('profileAvatar').innerHTML = profileAvatarHtml(account);
                $('profileName').textContent = account.name || 'Người dùng';
                $('profileEmail').textContent = account.email || 'Chưa có email';
                $('profileStatus').innerHTML = `<span class="badge ${account.is_active ? 'badge-active' : 'badge-locked'}">${escapeHtml(account.status)}</span>`;
                $('profileStats').innerHTML = `<div class="profile-stat"><strong>${number(account.stats.meals)}</strong><span class="muted">Bữa ăn</span></div><div class="profile-stat"><strong>${number(account.stats.water_logs)}</strong><span class="muted">Uống nước</span></div><div class="profile-stat"><strong>${number(account.stats.medicines)}</strong><span class="muted">Thuốc</span></div><div class="profile-stat"><strong>${number(account.stats.activities)}</strong><span class="muted">Hoạt động</span></div>`;
                $('profileToggleBtn').textContent = account.is_active ? 'Khóa' : 'Mở khóa';
                $('profileToggleBtn').className = `btn ${account.is_active ? 'btn-danger' : 'btn-success'} btn-sm`;
                $('profileToggleBtn').dataset.toggle = account.id;
                $('profileToggleBtn').dataset.locked = account.is_active ? '1' : '0';
                $('profileNoticeBtn').dataset.noticeUser = account.id;
                $('profileNoticeBtn').dataset.noticeMessage = '';
                const latestIndex = data.health?.latest_index || {};
                const bmi = latestIndex.BMI || (account.height && account.weight ? (Number(account.weight) / ((Number(account.height) / 100) ** 2)).toFixed(1) : '');
                $('tab-overview').innerHTML = infoRows([
                    ['Họ tên', account.name],
                    ['Email', account.email],
                    ['Ngày sinh', account.birthday],
                    ['Giới tính', account.gender],
                    ['Chiều cao', account.height ? `${account.height} cm` : ''],
                    ['Cân nặng', account.weight ? `${account.weight} kg` : ''],
                    ['BMI', bmi],
                ]);
                const profile = data.health?.profile || {};
                const score = data.health?.latest_score || {};
                $('tab-health').innerHTML = infoRows([
                    ['Hồ sơ sức khỏe', `Nhóm máu ${profile.NhomMau || 'N/A'}, bệnh nền ${profile.BenhNen || 'N/A'}, thể trạng ${profile.TheTrang || 'N/A'}`],
                    ['Chỉ số gần nhất', `BMI ${latestIndex.BMI || 'N/A'}, cân nặng ${latestIndex.CanNang || 'N/A'}, huyết áp ${latestIndex.HuyetAp || 'N/A'}, nhịp tim ${latestIndex.NhipTim || 'N/A'}`],
                    ['Điểm sức khỏe', score.Diem ? `${score.Diem} điểm - ${score.NhanXetAI || ''}` : 'Chưa có dữ liệu'],
                ]);
                $('tab-nutrition').innerHTML = renderDetailList(data.recent?.meals, '🥗', 'Chưa có bữa ăn nào', 'Bữa ăn gần đây sẽ hiển thị tại đây.');
                $('tab-medicine').innerHTML = renderDetailList(data.recent?.medicines, '💊', 'Chưa có thuốc nào', 'Lịch sử thuốc sẽ hiển thị tại đây.');
                $('tab-activity').innerHTML = renderDetailList(data.recent?.activities, '🏃', 'Chưa có hoạt động nào', 'Lịch sử vận động sẽ hiển thị tại đây.');
                $('tab-notice').innerHTML = renderDetailList(data.recent?.notifications, '🔔', 'Chưa có thông báo nào', 'Các thông báo đã nhận sẽ hiển thị tại đây.');
                activateTab('overview');
                openDrawer();
            } catch (error) {
                showToast(error.message);
            }
        }

        function renderNoticeUserPicker(query = '') {
            const q = query.trim().toLowerCase();
            const users = state.accounts.filter(account => !q || `${account.name} ${account.email}`.toLowerCase().includes(q)).slice(0, 8);
            $('noticeUserResults').innerHTML = users.length ? users.map(account => `<button class="picker-item" type="button" data-pick-user="${account.id}">${userAvatar(account)}<span style="min-width:0"><span class="primary-text">${escapeHtml(account.name)}</span><br><span class="muted">${escapeHtml(account.email)}</span></span></button>`).join('') : emptyState('👥', 'Không tìm thấy người dùng', 'Thử nhập tên hoặc email khác.');
        }
        function selectNoticeUser(userId) {
            const account = state.accountMap.get(Number(userId));
            if (!account) return;
            $('noticeUserId').value = account.id;
            $('selectedNoticeUser').hidden = false;
            $('selectedNoticeUser').innerHTML = `${userAvatar(account)}<div style="min-width:0"><div class="notice-title">${escapeHtml(account.name)}</div><div class="notice-msg">${escapeHtml(account.email)}</div></div>`;
            $('noticeUserSearchInput').value = account.name || account.email;
            renderNoticeUserPicker(account.name || account.email);
        }
        function openNoticeModal(userId = null, message = '') {
            $('noticeContent').value = message || '';
            $('noticeType').value = message ? 'HealthRisk' : 'HeThong';
            $('noticeAll').checked = false;
            if (userId) {
                selectNoticeUser(userId);
            } else {
                $('noticeUserId').value = '';
                $('selectedNoticeUser').hidden = true;
                $('noticeUserSearchInput').value = '';
                renderNoticeUserPicker();
            }
            openModal('noticeModal');
        }

        async function loadStats() {
            const data = await getJson('/api/admin/stats');
            $('generatedAt').textContent = `Cập nhật lúc ${dateTime(data.generated_at)} • Múi giờ Asia/Ho_Chi_Minh`;
            state.alerts = data.alerts || [];
            renderOverview(data.overview || []);
            renderFeatures(data.features || []);
            renderNotifications(data.notifications || {});
            renderWeekly(data.weekly || []);
            renderRiskSummary();
            renderDashboardAlerts();
            renderAlertTable();
        }
        async function loadAccounts() {
            $('accountRows').innerHTML = skeletonRows(5);
            const params = new URLSearchParams({ per_page: '50' });
            if (state.q) params.set('q', state.q);
            if (state.status) params.set('status', state.status);
            const data = await getJson(`/api/admin/accounts?${params}`);
            renderAccounts(data);
            renderDerivedPages();
        }
        async function loadFoods() {
            if (state.foods.length) return renderFoods();
            const data = await getJson('/api/admin/resources?type=foods&limit=50');
            state.foods = data.data || [];
            renderFoods();
        }
        async function loadMedicines() {
            if (state.medicines.length) return renderMedicines();
            const data = await getJson('/api/admin/resources?type=medicines&limit=50');
            state.medicines = data.data || [];
            renderMedicines();
        }
        async function reloadAll() {
            try {
                await Promise.all([loadAccounts(), loadStats()]);
                state.foods = [];
                state.medicines = [];
                if ($('page-foods').classList.contains('active')) await loadFoods();
                if ($('page-medicines').classList.contains('active')) await loadMedicines();
            } catch (error) {
                showToast(error.message);
            }
        }

        function resetFoodForm() {
            state.editingFoodId = null;
            $('foodEditorTitle').innerHTML = '<i class="ti ti-apple"></i>Thêm thực phẩm';
            ['foodName','foodCalories','foodProtein','foodCarb','foodFat','foodType','foodKeywords'].forEach(id => $(id).value = '');
            $('foodUnit').value = 'Gram';
            $('foodWeight').value = '100';
            $('foodHealthy').value = '1';
        }
        function fillFoodForm(food) {
            state.editingFoodId = food.ID;
            $('foodEditorTitle').innerHTML = `<i class="ti ti-apple"></i>Sửa thực phẩm #${food.ID}`;
            $('foodName').value = food.Ten || '';
            $('foodUnit').value = food.DonVi || 'Gram';
            $('foodCalories').value = food.Calo ?? '';
            $('foodProtein').value = food.Protein ?? '';
            $('foodCarb').value = food.Carb ?? '';
            $('foodFat').value = food.ChatBeo ?? '';
            $('foodWeight').value = food.KhoiLuongGram ?? 100;
            $('foodType').value = food.LoaiThucPham || '';
            $('foodKeywords').value = food.Keywords || '';
            $('foodHealthy').value = String(food.IsHealthy ?? 1);
            $('foodName').focus();
        }
        function resetMedicineForm() {
            state.editingMedicineId = null;
            $('medicineEditorTitle').innerHTML = '<i class="ti ti-pill"></i>Thêm thuốc';
            ['medicineName','medicineDose','medicineUnit','medicineTimes','medicineActive','medicineGroup','medicineDesc','medicineSideEffect','medicineWarning','medicineNote'].forEach(id => $(id).value = '');
            $('medicineStatus').value = 'chua_den';
        }
        function fillMedicineForm(medicine) {
            state.editingMedicineId = medicine.ID;
            $('medicineEditorTitle').innerHTML = `<i class="ti ti-pill"></i>Sửa thuốc #${medicine.ID}`;
            $('medicineName').value = medicine.TenThuoc || '';
            $('medicineDose').value = medicine.LieuLuong || '';
            $('medicineUnit').value = medicine.DonVi || '';
            $('medicineTimes').value = medicine.SoLanMoiNgay ?? '';
            $('medicineActive').value = medicine.HoatChat || '';
            $('medicineGroup').value = medicine.NhomThuoc || '';
            $('medicineStatus').value = medicine.TrangThai || 'chua_den';
            $('medicineDesc').value = medicine.MoTa || '';
            $('medicineSideEffect').value = medicine.TacDungPhu || '';
            $('medicineWarning').value = medicine.CanhBao || '';
            $('medicineNote').value = medicine.GhiChu || '';
            $('medicineName').focus();
        }

        document.querySelectorAll('[data-view-link]').forEach(link => link.addEventListener('click', () => showView(link.dataset.viewLink)));
        window.addEventListener('hashchange', () => showView(window.location.hash.replace('#', '')));
        $('reloadBtn').addEventListener('click', reloadAll);
        $('settingsRefreshBtn').addEventListener('click', reloadAll);
        $('closeDrawer').addEventListener('click', closeDrawer);
        overlayEl.addEventListener('click', closeDrawer);
        $('profileTabs').addEventListener('click', event => {
            const tab = event.target.closest('[data-tab]');
            if (tab) activateTab(tab.dataset.tab);
        });
        document.querySelectorAll('[data-close-modal]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.dataset.closeModal)));
        $('confirmOkBtn').addEventListener('click', async () => {
            if (!state.confirmAction) return;
            const action = state.confirmAction;
            closeModal('confirmModal');
            await action();
        });
        $('openNoticeModalBtn').addEventListener('click', () => openNoticeModal());
        $('noticeUserSearchInput').addEventListener('input', event => renderNoticeUserPicker(event.target.value));
        $('noticeUserResults').addEventListener('click', event => {
            const btn = event.target.closest('[data-pick-user]');
            if (btn) selectNoticeUser(btn.dataset.pickUser);
        });
        $('noticeAll').addEventListener('change', event => {
            if (event.target.checked) {
                $('noticeUserId').value = '';
                $('selectedNoticeUser').hidden = true;
            }
        });
        $('searchInput').addEventListener('input', event => {
            state.q = event.target.value.trim();
            clearTimeout(loadAccounts.timer);
            loadAccounts.timer = setTimeout(() => loadAccounts().catch(error => showToast(error.message)), 260);
        });
        $('statusFilter').addEventListener('change', event => {
            state.status = event.target.value;
            loadAccounts().catch(error => showToast(error.message));
        });
        $('noticeSearch').addEventListener('input', event => {
            state.noticeQ = event.target.value.trim();
            renderNoticeLists();
        });
        $('noticeReadFilter').addEventListener('change', event => {
            state.noticeRead = event.target.value;
            renderNoticeLists();
        });
        $('alertFilter').addEventListener('change', event => {
            state.alertFilter = event.target.value;
            renderAlertTable();
        });
        document.body.addEventListener('click', async event => {
            const profileBtn = event.target.closest('[data-profile]');
            const noticeBtn = event.target.closest('[data-notice-user]');
            const openAlertsBtn = event.target.closest('[data-open-alerts]');
            const riskBtn = event.target.closest('[data-risk-filter]');
            const toggleBtn = event.target.closest('[data-toggle]');
            const handleAlertBtn = event.target.closest('[data-handle-alert]');
            if (profileBtn) {
                await openProfile(Number(profileBtn.dataset.profile));
            }
            if (noticeBtn) {
                openNoticeModal(Number(noticeBtn.dataset.noticeUser), noticeBtn.dataset.noticeMessage || '');
            }
            if (openAlertsBtn) {
                showView('alerts');
            }
            if (riskBtn) {
                state.alertFilter = riskBtn.dataset.riskFilter || 'all';
                $('alertFilter').value = state.alertFilter === 'all' ? 'all' : state.alertFilter;
                showView('alerts');
                renderAlertTable();
            }
            if (toggleBtn) {
                const id = toggleBtn.dataset.toggle;
                const locked = toggleBtn.dataset.locked === '1';
                openConfirm(locked ? 'Bạn có chắc muốn khóa tài khoản này?' : 'Bạn có chắc muốn mở khóa tài khoản này?', async () => {
                    const result = await getJson(`/api/admin/accounts/${id}/toggle`, { method: 'PUT', body: JSON.stringify({ locked }) });
                    showToast(result.message || 'Đã cập nhật tài khoản');
                    closeDrawer();
                    await reloadAll();
                });
            }
            if (handleAlertBtn) {
                const alert = currentAlerts().find(item => item.id === handleAlertBtn.dataset.handleAlert);
                if (alert) {
                    localStorage.setItem(`alert-handled:${alert.userId}:${alert.raw.type}:${alert.raw.title}`, '1');
                    renderRiskSummary();
                    renderDashboardAlerts();
                    renderAlertTable();
                    showToast('Đã đánh dấu cảnh báo là đã xử lý');
                }
            }
        });

        $('sendNoticeBtn').addEventListener('click', async event => {
            event.target.disabled = true;
            try {
                const body = {
                    user_id: $('noticeUserId').value || null,
                    send_all: $('noticeAll').checked,
                    type: $('noticeType').value.trim() || 'HeThong',
                    content: $('noticeContent').value.trim(),
                };
                if (!body.send_all && !body.user_id) throw new Error('Vui lòng chọn người nhận hoặc chọn gửi tất cả');
                if (!body.content) throw new Error('Vui lòng nhập nội dung thông báo');
                const result = await getJson('/api/admin/notifications', { method: 'POST', body: JSON.stringify(body) });
                showToast(result.message || 'Đã gửi thông báo');
                closeModal('noticeModal');
                await loadStats();
            } catch (error) {
                showToast(error.message);
            } finally {
                event.target.disabled = false;
            }
        });
        function handleNoticeClick(event) {
            const readButton = event.target.closest('[data-read-notice]');
            const deleteButton = event.target.closest('[data-delete-notice]');
            if (!readButton && !deleteButton) return;
            const id = readButton?.dataset.readNotice || deleteButton?.dataset.deleteNotice;
            const method = deleteButton ? 'DELETE' : 'PUT';
            const url = deleteButton ? `/api/admin/notifications/${id}` : `/api/admin/notifications/${id}/read`;
            openConfirm(deleteButton ? 'Bạn có chắc muốn xóa thông báo này?' : 'Đánh dấu thông báo này là đã đọc?', async () => {
                const result = await getJson(url, { method });
                showToast(result.message || 'Đã cập nhật thông báo');
                await loadStats();
            });
        }
        $('noticeList').addEventListener('click', handleNoticeClick);
        $('noticeListPage').addEventListener('click', handleNoticeClick);

        $('saveFoodBtn').addEventListener('click', async event => {
            event.target.disabled = true;
            try {
                const body = {
                    Ten: $('foodName').value.trim(),
                    DonVi: $('foodUnit').value.trim() || 'Gram',
                    Calo: $('foodCalories').value || 0,
                    Protein: $('foodProtein').value || 0,
                    Carb: $('foodCarb').value || 0,
                    ChatBeo: $('foodFat').value || 0,
                    KhoiLuongGram: $('foodWeight').value || 100,
                    LoaiThucPham: $('foodType').value.trim(),
                    Keywords: $('foodKeywords').value.trim(),
                    IsHealthy: $('foodHealthy').value === '1',
                };
                const url = state.editingFoodId ? `/api/admin/foods/${state.editingFoodId}` : '/api/admin/foods';
                const method = state.editingFoodId ? 'PUT' : 'POST';
                const result = await getJson(url, { method, body: JSON.stringify(body) });
                showToast(result.message || 'Đã lưu thực phẩm');
                resetFoodForm();
                state.foods = [];
                await loadFoods();
                await loadStats();
            } catch (error) {
                showToast(error.message);
            } finally {
                event.target.disabled = false;
            }
        });
        $('saveMedicineBtn').addEventListener('click', async event => {
            event.target.disabled = true;
            try {
                const body = {
                    TenThuoc: $('medicineName').value.trim(),
                    MoTa: $('medicineDesc').value.trim(),
                    TacDungPhu: $('medicineSideEffect').value.trim(),
                    LieuLuong: $('medicineDose').value.trim(),
                    DonVi: $('medicineUnit').value.trim(),
                    SoLanMoiNgay: $('medicineTimes').value || null,
                    HoatChat: $('medicineActive').value.trim(),
                    NhomThuoc: $('medicineGroup').value.trim(),
                    TrangThai: $('medicineStatus').value.trim() || 'chua_den',
                    CanhBao: $('medicineWarning').value.trim(),
                    GhiChu: $('medicineNote').value.trim(),
                };
                const url = state.editingMedicineId ? `/api/admin/medicines/${state.editingMedicineId}` : '/api/admin/medicines';
                const method = state.editingMedicineId ? 'PUT' : 'POST';
                const result = await getJson(url, { method, body: JSON.stringify(body) });
                showToast(result.message || 'Đã lưu thuốc');
                resetMedicineForm();
                state.medicines = [];
                await loadMedicines();
                await loadStats();
            } catch (error) {
                showToast(error.message);
            } finally {
                event.target.disabled = false;
            }
        });
        $('resetFoodBtn').addEventListener('click', resetFoodForm);
        $('resetMedicineBtn').addEventListener('click', resetMedicineForm);
        $('foodRows').addEventListener('click', event => {
            const edit = event.target.closest('[data-edit-food]');
            const del = event.target.closest('[data-delete-food]');
            if (edit) fillFoodForm(state.foods[Number(edit.dataset.editFood)]);
            if (del) openConfirm('Bạn có chắc muốn xóa thực phẩm này?', async () => {
                const result = await getJson(`/api/admin/foods/${del.dataset.deleteFood}`, { method: 'DELETE' });
                showToast(result.message || 'Đã xóa thực phẩm');
                state.foods = [];
                await loadFoods();
                await loadStats();
            });
        });
        $('medicineRows').addEventListener('click', event => {
            const edit = event.target.closest('[data-edit-medicine]');
            const del = event.target.closest('[data-delete-medicine]');
            if (edit) fillMedicineForm(state.medicines[Number(edit.dataset.editMedicine)]);
            if (del) openConfirm('Bạn có chắc muốn xóa thuốc này?', async () => {
                const result = await getJson(`/api/admin/medicines/${del.dataset.deleteMedicine}`, { method: 'DELETE' });
                showToast(result.message || 'Đã xóa thuốc');
                state.medicines = [];
                await loadMedicines();
                await loadStats();
            });
        });

        showView(window.location.hash.replace('#', '') || 'dashboard');
        reloadAll();
    </script>
</body>
</html>

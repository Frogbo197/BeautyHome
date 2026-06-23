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
            --danger: #ef5350;
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
            position: relative;
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
        .global-search-results {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            z-index: 220;
            width: min(420px, 82vw);
            max-height: 430px;
            overflow: auto;
            padding: 8px;
            border: 1.5px solid var(--line);
            border-radius: 16px;
            background: var(--white);
            box-shadow: 0 18px 40px rgba(36, 48, 68, .18);
        }
        .global-search-results[hidden] { display: none; }
        .global-search-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px;
            border: 0;
            border-radius: 12px;
            background: transparent;
            color: var(--ink);
            text-align: left;
            cursor: pointer;
        }
        .global-search-item:hover,
        .global-search-item:focus-visible {
            background: var(--bg);
            outline: 0;
        }
        .global-search-icon {
            display: grid;
            flex: 0 0 36px;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: 12px;
            background: var(--blue-soft);
            color: var(--blue-dark);
            font-size: 18px;
        }
        .global-search-title {
            overflow: hidden;
            font-weight: 900;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .global-search-sub {
            overflow: hidden;
            margin-top: 2px;
            color: var(--muted);
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .global-search-empty {
            padding: 14px 12px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
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
        button.topbar-circle { cursor: pointer; }
        .topbar-icon-symbol {
            display: grid;
            place-items: center;
            font-family: "Segoe UI Symbol", "Apple Color Emoji", "Noto Color Emoji", sans-serif;
            font-size: 20px;
            line-height: 1;
        }
        .topbar-circle .ti + .topbar-icon-symbol { display: none; }
        .topbar-circle .ti:empty + .topbar-icon-symbol { display: grid; }
        .topbar-circle.alert-pulse {
            border-color: var(--rose-dark);
            background: #fff5f5;
            color: var(--rose-dark);
            animation: topbarBellPulse 1.2s ease-in-out infinite;
        }
        @keyframes topbarBellPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(198, 40, 40, .34); }
            50% { box-shadow: 0 0 0 9px rgba(198, 40, 40, 0); }
        }
        .topbar-actions {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar-action:hover,
        .topbar-action:focus-visible,
        .topbar-avatar:hover,
        .topbar-avatar:focus-visible {
            border-color: var(--blue);
            background: var(--blue-soft);
            outline: 0;
            box-shadow: 0 4px 14px rgba(2, 136, 209, .14);
        }
        .topbar-count {
            position: absolute;
            right: -5px;
            bottom: -4px;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            border: 2px solid var(--white);
            border-radius: 999px;
            background: var(--rose-dark);
            color: var(--white);
            font-size: 10px;
            line-height: 16px;
            text-align: center;
        }
        .topbar-logout { margin: 0; }
        .topbar-logout-button {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 40px;
            padding: 0 14px;
            border: 1.5px solid rgba(239, 83, 80, .32);
            border-radius: 999px;
            background: #fff7f7;
            color: var(--rose-dark);
            font-size: 13px;
            font-weight: 900;
            cursor: pointer;
        }
        .topbar-logout-button i { font-size: 18px; }
        .topbar-logout-button:hover,
        .topbar-logout-button:focus-visible {
            border-color: var(--rose);
            background: #fff3f3;
            outline: 0;
            box-shadow: 0 4px 14px rgba(198, 40, 40, .12);
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
            border: 0;
            border-radius: 50%;
            color: var(--white);
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            font-size: 20px;
            font-weight: 900;
            box-shadow: 0 4px 14px rgba(2, 136, 209, .25);
        }
        .topbar-profile { position: relative; }
        .topbar-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            z-index: 210;
            width: 250px;
            padding: 10px;
            border: 1.5px solid var(--line);
            border-radius: var(--radius);
            background: var(--white);
            box-shadow: 0 16px 38px rgba(36, 48, 68, .16);
        }
        .topbar-menu[hidden] { display: none; }
        .topbar-menu-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 8px 12px;
            border-bottom: 1px solid var(--line);
        }
        .topbar-menu-avatar {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            color: var(--white);
            font-weight: 900;
        }
        .topbar-menu-item {
            display: flex;
            align-items: center;
            gap: 9px;
            width: 100%;
            margin-top: 6px;
            padding: 10px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: var(--ink);
            text-align: left;
            font-weight: 800;
        }
        .topbar-menu-item:hover { background: var(--bg); }
        .topbar-menu-item.danger { color: var(--rose-dark); }

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
        button.stat-card {
            width: 100%;
            cursor: pointer;
            color: inherit;
            font: inherit;
            text-align: left;
        }
        .stat-card[data-stat-view] { cursor: pointer; }
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
        .food-form-grid {
            grid-template-columns: repeat(4, minmax(150px, 1fr));
            align-items: start;
        }
        .food-form-grid .span-2 { grid-column: span 2; }
        .food-form-grid .span-4 { grid-column: 1 / -1; }
        .form-group { display: grid; gap: 7px; }
        .form-label { display: inline-flex; align-items: center; gap: 6px; color: #455a64; font-size: 13px; font-weight: 900; }
        .label-help {
            position: relative;
            display: inline-grid;
            width: 18px;
            height: 18px;
            place-items: center;
            border-radius: 50%;
            background: var(--blue-soft);
            color: var(--blue-dark);
            font-size: 11px;
            font-weight: 900;
            cursor: help;
        }
        .label-help::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 50%;
            bottom: calc(100% + 8px);
            z-index: 260;
            width: min(260px, 72vw);
            padding: 9px 10px;
            border-radius: 10px;
            background: #263238;
            color: var(--white);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
            opacity: 0;
            pointer-events: none;
            transform: translateX(-50%) translateY(4px);
            transition: opacity .16s ease, transform .16s ease;
        }
        .label-help:hover::after,
        .label-help:focus-visible::after {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        .field.is-invalid {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(239, 83, 80, .12);
        }
        .field-error {
            min-height: 16px;
            color: var(--danger);
            font-size: 12px;
            font-weight: 800;
            line-height: 1.35;
        }
        .form-error {
            margin-top: 12px;
            padding: 10px 12px;
            border: 1px solid rgba(239, 83, 80, .28);
            border-radius: 12px;
            background: rgba(239, 83, 80, .08);
            color: var(--danger);
            font-weight: 900;
        }
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            align-items: start;
        }
        .config-note {
            margin: -6px 0 16px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }
        .switch-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 14px;
            border: 1.5px solid var(--line);
            border-radius: 14px;
            background: var(--bg);
        }
        .switch-row input {
            width: 48px;
            height: 26px;
            accent-color: var(--rose-dark);
        }
        .settings-summary {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
            margin-top: 18px;
        }
        .settings-pill {
            padding: 12px;
            border: 1.5px solid var(--line);
            border-radius: 14px;
            background: var(--white);
        }
        .settings-pill strong {
            display: block;
            color: var(--blue-dark);
            font-size: 18px;
            font-weight: 900;
        }
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
        .resource-table-block {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
        }
        .table-toolbar {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .table-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--blue-dark);
            font-size: 18px;
            font-weight: 900;
        }
        .table-note { color: var(--muted); font-size: 13px; margin-top: 4px; }
        .table-thumb {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            object-fit: cover;
            background: var(--blue-soft);
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

        .ops-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }
        .ops-card {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            padding: 16px;
            border: 1.5px solid var(--blue-mid);
            border-radius: 18px;
            background: linear-gradient(135deg, #ffffff, #f4fbff);
        }
        .ops-card .stat-icon { margin: 0; flex: 0 0 auto; }
        .ops-card strong {
            display: block;
            color: #121b73;
            font-family: var(--font-head);
            font-size: 26px;
            line-height: 1;
        }
        .follow-list, .resource-grid, .user-grid {
            display: grid;
            gap: 14px;
        }
        .follow-card, .resource-card, .user-card {
            min-width: 0;
            border: 1.5px solid var(--line);
            border-radius: 18px;
            background: var(--white);
            padding: 16px;
            box-shadow: 0 8px 22px rgba(79, 195, 247, .08);
        }
        .follow-card {
            display: grid;
            grid-template-columns: minmax(220px, 1.1fr) repeat(3, minmax(130px, .65fr)) auto;
            align-items: center;
            gap: 14px;
        }
        .follow-card.alert-high { border-color: #ffab91; background: #fff8f3; }
        .follow-card.alert-medium { border-color: #ffcc80; background: #fffdf1; }
        .field-label {
            margin-bottom: 3px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .resource-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .resource-card {
            display: grid;
            gap: 13px;
        }
        .resource-head {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
        }
        .resource-emoji {
            display: grid;
            width: 52px;
            height: 52px;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 16px;
            background: var(--blue-soft);
            font-size: 26px;
        }
        .macro-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .macro-box {
            min-width: 0;
            padding: 10px;
            border-radius: 13px;
            background: var(--bg);
            text-align: center;
        }
        .macro-box strong {
            display: block;
            color: var(--blue-dark);
            font-size: 15px;
        }
        .user-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .user-card {
            display: grid;
            gap: 14px;
        }
        .user-card-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }
        .user-stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 8px;
        }
        .user-stat {
            min-width: 0;
            padding: 9px 6px;
            border-radius: 12px;
            background: var(--bg);
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            text-align: center;
        }
        .user-stat strong {
            display: block;
            color: var(--blue-dark);
            font-size: 16px;
        }

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
        .error-state {
            min-height: 190px;
            border: 1.5px solid #ffcdd2;
            border-radius: var(--radius);
            background: #fff8f8;
        }
        .error-state .empty-icon,
        .notice-icon.warning-icon {
            background: #ffebee;
            color: #c62828;
        }
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
        .modal-close {
            width: 36px;
            min-width: 36px;
            height: 36px;
            min-height: 36px;
            padding: 0;
            border-radius: 50%;
            font-size: 18px;
            line-height: 1;
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
            .grid-2, .profile-layout, .resource-grid, .user-grid, .settings-grid, .settings-summary { grid-template-columns: 1fr; }
            .grid-5 { grid-template-columns: repeat(3, 1fr); }
            .food-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .food-form-grid .span-2, .food-form-grid .span-4 { grid-column: 1 / -1; }
            .ops-grid { grid-template-columns: 1fr; }
            .follow-card { grid-template-columns: 1fr 1fr; }
            .follow-card .actions { grid-column: 1 / -1; }
        }
        @media (max-width: 760px) {
            body { font-size: 13px; }
            .topbar { height: auto; min-height: 64px; padding: 10px 12px; display: grid; grid-template-columns: 1fr auto; }
            .topbar-search { width: 100%; grid-column: 1 / -1; }
            .topbar-actions { grid-column: 2; gap: 8px; }
            .topbar-actions .topbar-circle, .topbar-avatar { display: grid; width: 40px; height: 40px; }
            .topbar-menu { right: -44px; width: min(250px, calc(100vw - 24px)); }
            .page { padding: 16px 12px 24px; }
            .section-header { display: grid; }
            .section-title { font-size: 24px; }
            .grid-3, .grid-4, .grid-5, .form-grid, .follow-card, .macro-grid, .user-stats, .settings-grid, .settings-summary { grid-template-columns: 1fr; }
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
                <button class="nav-item" type="button" data-view-link="users">👥 Người dùng <span class="nav-badge" id="navUserCount" hidden>0</span></button>
                <button class="nav-item" type="button" data-view-link="alerts">⚠️ Người dùng cần theo dõi <span class="nav-badge" id="navAlertCount" hidden>0</span></button>
                <button class="nav-item" type="button" data-view-link="foods">🥗 Thực phẩm</button>
                <button class="nav-item" type="button" data-view-link="medicines">💊 Thuốc</button>
                <button class="nav-item" type="button" data-view-link="activities">🏃 Hoạt động</button>
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
                <input id="globalSearch" type="search" placeholder="Tìm nhanh người dùng, thuốc, thực phẩm...">
                <div class="global-search-results" id="globalSearchResults" role="listbox" hidden></div>
            </div>
            <div class="topbar-actions" aria-label="Tác vụ quản trị">
                <button class="topbar-circle topbar-action" id="topbarNotifications" type="button" title="Chuông thông báo" aria-label="Chuông thông báo">
                    <span class="topbar-icon-symbol" aria-hidden="true">🔔</span>
                    <span class="dot" id="topbarNoticeDot" hidden></span>
                    <span class="topbar-count" id="topbarNoticeCount" hidden>0</span>
                </button>
                <button class="topbar-circle topbar-action" id="topbarSettings" type="button" title="Cài đặt hệ thống" aria-label="Cài đặt hệ thống">
                    <span class="topbar-icon-symbol" aria-hidden="true">⚙</span>
                </button>
                <div class="topbar-profile">
                    <button class="topbar-avatar" id="topbarAvatar" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="profileMenu">
                        <span id="topbarAvatarInitials">A</span>
                    </button>
                    <div class="topbar-menu" id="profileMenu" role="menu" hidden>
                        <div class="topbar-menu-head">
                            <div class="topbar-menu-avatar" id="profileMenuAvatar">A</div>
                            <div>
                                <div class="primary-text" id="profileMenuName">Admin</div>
                                <div class="muted" id="profileMenuRole">Quản trị viên</div>
                            </div>
                        </div>
                        <button class="topbar-menu-item" type="button" data-view-shortcut="dashboard" role="menuitem"><i class="ti ti-layout-dashboard"></i>Dashboard</button>
                        <button class="topbar-menu-item" type="button" data-view-shortcut="settings" role="menuitem"><i class="ti ti-settings"></i>Cài đặt hệ thống</button>
                        <button class="topbar-menu-item danger" type="button" data-logout-trigger role="menuitem"><i class="ti ti-logout"></i>Đăng xuất</button>
                    </div>
                </div>
                <form class="topbar-logout" id="logoutForm" method="POST" action="<?php echo e(route('admin.logout')); ?>">
                    <?php echo csrf_field(); ?>
                    <button class="topbar-logout-button topbar-action" id="logoutBtn" type="submit" title="Đăng xuất" aria-label="Đăng xuất">
                        <i class="ti ti-logout"></i>
                        <span>Đăng xuất</span>
                    </button>
                </form>
            </div>
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

            <section class="card" style="margin-top:22px">
                <div class="card-title"><i class="ti ti-chart-bar"></i>Thống kê 7 ngày gần nhất</div>
                <div class="section-subtitle" style="margin:-8px 0 16px">Theo dõi số người dùng mới và thông báo mới theo từng ngày</div>
                <div class="grid" id="weeklyBars"></div>
            </section>

            <div class="grid grid-2" style="margin-top:22px">
                <section class="card">
                    <div class="card-title"><i class="ti ti-alert-triangle"></i>Cảnh báo bất thường <span class="badge badge-high" id="anomalyBadge" hidden>0</span></div>
                    <div class="notice-list" id="dashboardAlerts"></div>
                </section>
                <section class="card">
                    <div class="card-title"><i class="ti ti-bell"></i>Thông báo mới nhất</div>
                    <div class="notice-list" id="noticeList"></div>
                </section>
            </div>

            <div class="grid grid-2" style="margin-top:22px">
                <section class="card">
                    <div class="card-title"><i class="ti ti-activity"></i>Hoạt động hệ thống</div>
                    <div class="ops-grid" id="systemActivity">
                        <div class="loading-skeleton card"><div class="skeleton-line"></div><div class="skeleton-line"></div></div>
                    </div>
                </section>
                <section class="card">
                    <div class="card-title"><i class="ti ti-category"></i>Thống kê nhanh</div>
                    <div class="mini-stats" id="notificationSummary" style="margin-bottom:14px"></div>
                    <div class="notice-list" id="notificationTypeList"></div>
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
                <div class="user-grid" id="accountRows">
                    <div class="loading-skeleton"><div class="skeleton-line"></div><div class="skeleton-line"></div></div>
                </div>
            </section>
        </section>

        <section class="page" id="page-alerts" data-view-panel="alerts">
            <div class="section-header">
                <div>
                    <div class="section-title">Người dùng cần theo dõi</div>
                    <div class="section-subtitle">Ưu tiên người dùng có dấu hiệu sức khỏe bất thường và cần admin xử lý</div>
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
                <div class="follow-list" id="alertRows"></div>
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
                    <div class="form-grid food-form-grid">
                        <div class="form-group span-2"><label class="form-label">Tên món</label><input class="field" id="foodName" name="ten_thuc_pham" type="text" placeholder="Ví dụ: Cơm trắng"><div class="field-error" data-error-for="ten_thuc_pham"></div></div>
                        <div class="form-group"><label class="form-label">Đơn vị</label><select class="field" id="foodUnit"></select></div>
                        <div class="form-group"><label class="form-label">Calories</label><input class="field" id="foodCalories" name="calo_goc" type="number" min="0" step="0.1"><div class="field-error" data-error-for="calo_goc"></div></div>
                        <div class="form-group"><label class="form-label">Protein</label><input class="field" id="foodProtein" type="number" min="0" step="0.1"></div>
                        <div class="form-group"><label class="form-label">Carb</label><input class="field" id="foodCarb" type="number" min="0" step="0.1"></div>
                        <div class="form-group"><label class="form-label">Fat</label><input class="field" id="foodFat" type="number" min="0" step="0.1"></div>
                        <div class="form-group"><label class="form-label">Khối lượng gram</label><select class="field" id="foodWeight"></select></div>
                        <div class="form-group span-2"><label class="form-label">Loại thực phẩm</label><select class="field" id="foodType" name="loai_thuc_pham"></select><div class="field-error" data-error-for="loai_thuc_pham"></div></div>
                        <div class="form-group span-2"><label class="form-label">Từ khóa</label><input class="field" id="foodKeywords" type="text"></div>
                        <div class="form-group span-4"><label class="form-label" for="foodImage">Hình ảnh</label><input class="field" id="foodImage" name="hinh_anh" type="text" placeholder="https://... hoặc /storage/uploads/images/..."><div class="field-error" data-error-for="hinh_anh"></div></div>
                    </div>
                    <div class="form-error" data-form-error="food" hidden></div>
                    <div class="actions">
                        <button class="btn btn-success" id="saveFoodBtn" type="button"><i class="ti ti-device-floppy"></i>Lưu thực phẩm</button>
                        <button class="btn btn-ghost" id="resetFoodBtn" type="button">Nhập mới</button>
                    </div>
                </div>
                <div class="resource-grid" id="foodRows"></div>
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
                        <div class="form-group">
                            <label class="form-label" for="medicineName">Tên thuốc</label>
                            <input class="field" id="medicineName" name="ten_thuoc" type="text" placeholder="Ví dụ: Panadol Extra, Hapacol...">
                            <div class="field-error" data-error-for="ten_thuoc"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="medicineActive">Tên hoạt chất chính <span class="label-help" tabindex="0" data-tooltip="Nhập tên dược chất gốc dùng để app Flutter tra cứu và gợi ý thuốc, ví dụ Paracetamol hoặc Ibuprofen.">?</span></label>
                            <input class="field" id="medicineActive" name="hoat_chat" type="text" placeholder="Ví dụ: Paracetamol, Ibuprofen...">
                            <div class="field-error" data-error-for="hoat_chat"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="medicineStrength">Hàm lượng gốc/Định lượng viên <span class="label-help" tabindex="0" data-tooltip="Nhập định lượng chuẩn của một đơn vị thuốc, ví dụ 500mg, 10ml hoặc 1 viên; không nhập số lần uống mỗi ngày ở đây.">?</span></label>
                            <input class="field" id="medicineStrength" name="ham_luong_goc" type="text" placeholder="Ví dụ: 500mg, 10ml, 1 viên...">
                            <div class="field-error" data-error-for="ham_luong_goc"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="medicineGroup">Nhóm thuốc</label>
                            <select class="field" id="medicineGroup" name="nhom_thuoc"></select>
                            <div class="field-error" data-error-for="nhom_thuoc"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="medicineDesc">Công dụng</label>
                            <textarea class="field" id="medicineDesc" name="mo_ta" placeholder="Mô tả công dụng chính, lưu ý sử dụng..."></textarea>
                            <div class="field-error" data-error-for="mo_ta"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="medicineImage">Hình ảnh</label>
                            <input class="field" id="medicineImage" name="hinh_anh" type="url" placeholder="https://... hoặc /storage/uploads/images/...">
                            <div class="field-error" data-error-for="hinh_anh"></div>
                        </div>
                    </div>
                    <div class="form-error" data-form-error="medicine" hidden></div>
                    <div class="actions">
                        <button class="btn btn-success" id="saveMedicineBtn" type="button"><i class="ti ti-device-floppy"></i>Lưu thuốc</button>
                        <button class="btn btn-ghost" id="resetMedicineBtn" type="button">Nhập mới</button>
                    </div>
                </div>
                <div class="resource-grid" id="medicineRows"></div>
            </section>
        </section>

        <section class="page" id="page-activities" data-view-panel="activities">
            <div class="section-header"><div><div class="section-title">Hoạt động</div><div class="section-subtitle">Tổng hợp hoạt động vận động từ dữ liệu người dùng</div></div></div>
            <section class="card">
                <div class="resource-editor" id="activityEditor">
                    <div class="card-title"><i class="ti ti-run"></i>Thêm hoạt động vận động</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="activityName">Tên môn vận động</label>
                            <input class="field" id="activityName" name="ten_hoat_dong" type="text" placeholder="Ví dụ: Chạy bộ, Đạp xe, Bơi sải...">
                            <div class="field-error" data-error-for="ten_hoat_dong"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="activityMet">Chỉ số tiêu hao năng lượng MET <span class="label-help" tabindex="0" data-tooltip="MET là hệ số cường độ vận động. App sẽ tự nhân MET với cân nặng và số phút tập thực tế của từng người dùng.">?</span></label>
                            <input class="field" id="activityMet" name="chi_so_met" type="number" min="1" max="50" step="0.1" placeholder="Ví dụ: Chạy bộ = 7.0, Đi bộ = 3.3...">
                            <div class="field-error" data-error-for="chi_so_met"></div>
                        </div>
                        <div class="form-group" style="grid-column:1 / -1">
                            <label class="form-label" for="activityDesc">Mô tả cường độ</label>
                            <textarea class="field" id="activityDesc" name="mo_ta" placeholder="Mô tả ngắn gọn..."></textarea>
                            <div class="field-error" data-error-for="mo_ta"></div>
                        </div>
                        <div class="form-group" style="grid-column:1 / -1">
                            <label class="form-label" for="activityImage">Hình ảnh</label>
                            <input class="field" id="activityImage" name="hinh_anh" type="text" placeholder="https://... hoặc /storage/uploads/images/...">
                            <div class="field-error" data-error-for="hinh_anh"></div>
                        </div>
                    </div>
                    <div class="form-error" data-form-error="activity" hidden></div>
                    <div class="actions">
                        <button class="btn btn-success" id="saveActivityBtn" type="button"><i class="ti ti-device-floppy"></i>Lưu hoạt động</button>
                        <button class="btn btn-ghost" id="resetActivityBtn" type="button">Nhập mới</button>
                    </div>
                </div>
                <div id="activityRows"></div>
            </section>
            <section class="card"><div id="activitySummary" class="grid grid-3"></div></section>
        </section>

        <section class="page" id="page-settings" data-view-panel="settings">
            <div class="section-header">
                <div>
                    <div class="section-title">Cài đặt</div>
                    <div class="section-subtitle">Cấu hình tham số vận hành dùng chung cho cảnh báo và hệ thống</div>
                </div>
                <button class="btn btn-ghost" type="button" id="settingsRefreshBtn"><i class="ti ti-refresh"></i>Làm mới cấu hình</button>
            </div>

            <div class="settings-grid">
                <section class="card">
                    <form id="healthThresholdForm" autocomplete="off">
                        <div class="card-title"><i class="ti ti-heart-rate-monitor"></i>Cấu hình Ngưỡng Cảnh báo Sức khỏe</div>
                        <p class="config-note">Các ngưỡng này được dùng khi hệ thống quét bảng cảnh báo bất thường và treo tag đỏ cho ca chưa xử lý.</p>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="configWeightLoss">Phần trăm sụt cân khẩn cấp</label>
                                <input class="field" id="configWeightLoss" name="nguong_sut_can" type="number" min="0.1" max="100" step="0.1" placeholder="Ví dụ: 5%">
                                <div class="field-error" data-error-for="nguong_sut_can"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="configWatchDays">Trong vòng số ngày</label>
                                <input class="field" id="configWatchDays" name="so_ngay_theo_doi" type="number" min="1" max="365" step="1" placeholder="Ví dụ: 30 ngày">
                                <div class="field-error" data-error-for="so_ngay_theo_doi"></div>
                            </div>
                            <div class="form-group" style="grid-column:1 / -1">
                                <label class="form-label" for="configWaterMinimum">Ngưỡng nhắc nhở uống nước tối thiểu trong ngày</label>
                                <input class="field" id="configWaterMinimum" name="nuoc_toi_thieu" type="number" min="0" max="10000" step="50" placeholder="Ví dụ: 2000 ml">
                                <div class="field-error" data-error-for="nuoc_toi_thieu"></div>
                            </div>
                        </div>
                        <div class="form-error" data-form-error="health-config" hidden></div>
                        <div class="form-actions">
                            <button class="btn btn-ghost" id="resetHealthConfigBtn" type="button">Khôi phục mặc định</button>
                            <button class="btn btn-success" id="saveHealthConfigBtn" type="submit"><i class="ti ti-device-floppy"></i>Lưu ngưỡng cảnh báo</button>
                        </div>
                    </form>
                </section>

                <section class="card">
                    <form id="systemConfigForm" autocomplete="off">
                        <div class="card-title"><i class="ti ti-adjustments-cog"></i>Cấu hình Hệ thống</div>
                        <p class="config-note">Các tham số này phục vụ vận hành hằng ngày, bảo trì và dọn dữ liệu log cũ.</p>
                        <div class="form-grid">
                            <div class="form-group" style="grid-column:1 / -1">
                                <label class="switch-row" for="configMaintenance">
                                    <span>
                                        <span class="primary-text">Chế độ bảo trì ứng dụng</span><br>
                                        <span class="muted">Bật khi cần tạm ngưng thao tác người dùng để bảo trì.</span>
                                    </span>
                                    <input id="configMaintenance" name="che_do_bao_tri" type="checkbox">
                                </label>
                                <div class="field-error" data-error-for="che_do_bao_tri"></div>
                            </div>
                            <div class="form-group" style="grid-column:1 / -1">
                                <label class="form-label" for="configLogRetention">Tự động xóa log lịch sử cũ sau số ngày</label>
                                <input class="field" id="configLogRetention" name="so_ngay_xoa_log" type="number" min="1" max="3650" step="1" placeholder="Ví dụ: 30 ngày">
                                <div class="field-error" data-error-for="so_ngay_xoa_log"></div>
                            </div>
                        </div>
                        <div class="form-error" data-form-error="system-config" hidden></div>
                        <div class="form-actions">
                            <button class="btn btn-ghost" id="resetSystemConfigBtn" type="button">Khôi phục mặc định</button>
                            <button class="btn btn-success" id="saveSystemConfigBtn" type="submit"><i class="ti ti-device-floppy"></i>Lưu cấu hình hệ thống</button>
                        </div>
                    </form>
                </section>
            </div>

            <section class="card" style="margin-top:18px">
                <div class="card-title"><i class="ti ti-database-cog"></i>Trạng thái cấu hình hiện tại</div>
                <div class="settings-summary" id="settingsSummary"></div>
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
                <button class="btn btn-ghost btn-sm modal-close" type="button" data-close-modal="noticeModal" aria-label="Đóng"><i class="ti ti-x"></i></button>
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
                <div class="card-title" id="confirmTitle" style="margin:0"><i class="ti ti-alert-circle"></i>Xác nhận thao tác</div>
                <button class="btn btn-ghost btn-sm modal-close" type="button" data-close-modal="confirmModal" aria-label="Đóng"><i class="ti ti-x"></i></button>
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
            badges: {},
            foods: [],
            medicines: [],
            activities: [],
            systemConfig: {},
            selectedAccount: null,
            editingFoodId: null,
            editingMedicineId: null,
            q: '',
            foodQ: '',
            medicineQ: '',
            globalSearchTimer: null,
            status: '',
            noticeQ: '',
            noticeRead: 'all',
            alertFilter: 'all',
            confirmAction: null,
            confirmOptions: {},
            alertFetchError: '',
        };
        const pageTitles = {
            dashboard: '📊 Dashboard',
            users: '👥 Người dùng',
            alerts: '⚠️ Người dùng cần theo dõi',
            notifications: '🔔 Thông báo',
            foods: '🥗 Thực phẩm',
            medicines: '💊 Thuốc',
            activities: '🏃 Hoạt động',
            settings: '⚙️ Cài đặt',
        };
        const choiceSets = {
            foodUnits: [
                ['Gram', 'Gram'],
                ['ml', 'ml'],
                ['Phan', 'Phần'],
                ['Chen', 'Chén'],
                ['Dia', 'Đĩa'],
                ['Ly', 'Ly'],
                ['Cai', 'Cái'],
                ['Goi', 'Gói'],
            ],
            foodWeights: [
                ['50', '50g'],
                ['100', '100g'],
                ['150', '150g'],
                ['200', '200g'],
                ['250', '250g'],
                ['300', '300g'],
                ['500', '500g'],
            ],
            foodTypes: [
                ['Tinh bot', 'Tinh bột'],
                ['Dam', 'Đạm'],
                ['Rau cu', 'Rau củ'],
                ['Trai cay', 'Trái cây'],
                ['Sua va che pham', 'Sữa và chế phẩm'],
                ['Do uong', 'Đồ uống'],
                ['Mon chinh', 'Món chính'],
                ['Mon phu', 'Món phụ'],
                ['Khac', 'Khác'],
            ],
            medicineGroups: [
                ['Giam dau, ha sot', 'Giảm đau, hạ sốt'],
                ['Giam dau, khang viem', 'Giảm đau, kháng viêm'],
                ['Vitamin va khoang chat', 'Vitamin và khoáng chất'],
                ['Khang sinh', 'Kháng sinh'],
                ['Di ung', 'Dị ứng'],
                ['Da day', 'Dạ dày'],
                ['Tieu hoa', 'Tiêu hóa'],
                ['Huyet ap, tim mach', 'Huyết áp, tim mạch'],
                ['Tieu duong', 'Tiểu đường'],
                ['Ho hap', 'Hô hấp'],
                ['Sat trung', 'Sát trùng'],
                ['Khac', 'Khác'],
            ],
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
        const adminDisplayName = localStorage.getItem('admin_name') || localStorage.getItem('user_name') || 'Admin';
        const adminDisplayEmail = localStorage.getItem('admin_email') || localStorage.getItem('email') || '';

        const $ = (id) => document.getElementById(id);
        const toastEl = $('toast');
        const drawerEl = $('profileDrawer');
        const overlayEl = $('overlay');

        function safeArray(value) {
            return Array.isArray(value) ? value.filter(item => item !== null && item !== undefined) : [];
        }
        function safeNumber(value, fallback = 0) {
            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : fallback;
        }
        function foldText(value) {
            return String(value ?? '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replaceAll('đ', 'd')
                .replaceAll('Đ', 'D')
                .toLowerCase();
        }

        function hydrateChoiceFields() {
            fillSelect('foodUnit', choiceSets.foodUnits);
            fillSelect('foodWeight', choiceSets.foodWeights);
            fillSelect('foodType', choiceSets.foodTypes);
            fillSelect('medicineGroup', choiceSets.medicineGroups);
        }
        function fillSelect(id, items) {
            const el = $(id);
            if (!el) return;
            el.innerHTML = items.map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join('');
        }
        function setSelectValue(id, value, fallback = '') {
            const el = $(id);
            if (!el) return;
            const next = String(value ?? fallback ?? '');
            el.value = next;
            if (next && el.value !== next) {
                el.value = fallback;
            }
        }
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
        function setBadge(id, value) {
            const el = $(id);
            if (!el) return;
            const count = Number(value || 0);
            el.textContent = number(count);
            el.hidden = count <= 0;
        }
        function dateTime(value) {
            if (!value) return 'Chưa có';
            const parsed = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(parsed.getTime())) return escapeHtml(value);
            return parsed.toLocaleString('vi-VN');
        }
        function initialsText(name, email) {
            const raw = (name && name !== 'Chưa cập nhật' ? name : email || 'ND').trim();
            const parts = raw.split(/\s+/).filter(Boolean);
            return (parts.length > 1 ? parts[0][0] + parts.at(-1)[0] : raw.slice(0, 2)).toUpperCase();
        }
        function initials(name, email) {
            return escapeHtml(initialsText(name, email));
        }
        function stripUserId(text) {
            const cleaned = String(text || 'Người dùng')
                .replace(/\s*\(#\d+\)\s*$/, '')
                .replace(/\s*#\d+\s*$/, '')
                .replace(/^user$/i, 'Người dùng')
                .replace(/^nguoi dung$/i, 'Người dùng');
            return cleaned.trim() || 'Người dùng';
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
            const isFormData = options.body instanceof FormData;
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    ...(adminApiToken ? { 'X-Admin-Token': adminApiToken } : {}),
                    'X-Admin-User-Id': adminUserId,
                    ...(options.body && !isFormData ? { 'Content-Type': 'application/json' } : {}),
                    ...(options.headers || {}),
                },
                ...options,
            });
            const contentType = response.headers.get('content-type') || '';
            let data = null;
            if (contentType.includes('application/json')) {
                try {
                    data = await response.json();
                } catch (_) {
                    throw new Error('Phản hồi API không đọc được. Vui lòng thử lại.');
                }
            } else {
                throw new Error(response.ok
                    ? 'Phản hồi API không hợp lệ. Vui lòng đăng nhập lại hoặc thử tải lại.'
                    : `Không thể tải dữ liệu từ máy chủ (${response.status}).`);
            }
            if (!response.ok || data.success === false) {
                const error = new Error(data.message || 'Không thể tải dữ liệu');
                error.errors = data.errors || {};
                error.status = response.status;
                throw error;
            }
            return data;
        }
        function appendIfPresent(formData, key, value) {
            if (value !== undefined && value !== null && String(value).trim() !== '') {
                formData.append(key, value);
            }
        }
        function appendFileIfPresent(formData, key, inputId) {
            const file = $(inputId)?.files?.[0];
            if (file) formData.append(key, file);
        }

        function clearFormErrors(form) {
            form.querySelectorAll('.field.is-invalid').forEach(field => field.classList.remove('is-invalid'));
            form.querySelectorAll('[data-error-for]').forEach(slot => { slot.textContent = ''; });
            form.querySelectorAll('[data-form-error]').forEach(slot => {
                slot.textContent = '';
                slot.hidden = true;
            });
        }
        function cssEscape(value) {
            return window.CSS?.escape ? CSS.escape(value) : String(value).replaceAll('\\', '\\\\').replaceAll('"', '\\"');
        }
        function showFormErrors(form, error) {
            clearFormErrors(form);
            const errors = error?.errors || {};
            let hasFieldError = false;
            Object.entries(errors).forEach(([key, messages]) => {
                const escapedKey = cssEscape(key);
                const slot = form.querySelector(`[data-error-for="${escapedKey}"]`);
                const field = form.querySelector(`[name="${escapedKey}"]`) || slot?.previousElementSibling;
                const message = Array.isArray(messages) ? messages.join(' ') : String(messages || '');
                if (slot && message) {
                    slot.textContent = message;
                    hasFieldError = true;
                }
                if (field?.classList?.contains('field')) field.classList.add('is-invalid');
            });
            if (!hasFieldError) {
                const slot = form.querySelector('[data-form-error]');
                if (slot) {
                    slot.textContent = error?.message || 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.';
                    slot.hidden = false;
                }
            }
        }
        async function withSubmitLock(button, loadingText, action) {
            if (button.disabled) return;
            const original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `<i class="ti ti-loader-2"></i>${escapeHtml(loadingText)}`;
            try {
                await action();
            } finally {
                button.disabled = false;
                button.innerHTML = original;
            }
        }

        function defaultSystemConfig() {
            return {
                nguong_sut_can: 5,
                so_ngay_theo_doi: 30,
                nuoc_toi_thieu: 2000,
                che_do_bao_tri: false,
                so_ngay_xoa_log: 30,
            };
        }
        function fillSystemConfigForm(config = {}) {
            const next = { ...defaultSystemConfig(), ...config };
            state.systemConfig = next;
            $('configWeightLoss').value = next.nguong_sut_can ?? 5;
            $('configWatchDays').value = next.so_ngay_theo_doi ?? 30;
            $('configWaterMinimum').value = next.nuoc_toi_thieu ?? 2000;
            $('configMaintenance').checked = Boolean(next.che_do_bao_tri);
            $('configLogRetention').value = next.so_ngay_xoa_log ?? 30;
            renderSystemConfigSummary(next);
        }
        function renderSystemConfigSummary(config = state.systemConfig) {
            const next = { ...defaultSystemConfig(), ...config };
            $('settingsSummary').innerHTML = [
                ['Sụt cân khẩn cấp', `${next.nguong_sut_can}%`],
                ['Cửa sổ theo dõi', `${next.so_ngay_theo_doi} ngày`],
                ['Nước tối thiểu', `${number(next.nuoc_toi_thieu)} ml/ngày`],
                ['Bảo trì', next.che_do_bao_tri ? 'Đang bật' : 'Đang tắt'],
                ['Giữ log', `${next.so_ngay_xoa_log} ngày`],
            ].map(([label, value]) => `<div class="settings-pill"><span class="muted">${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
        }
        async function loadSystemConfig() {
            try {
                const result = await getJson('/api/admin/system-config');
                fillSystemConfigForm(result.data || {});
            } catch (error) {
                fillSystemConfigForm(defaultSystemConfig());
                showToast(error.message || 'Không thể tải cấu hình hệ thống');
            }
        }
        function healthConfigPayload() {
            return {
                nguong_sut_can: Number($('configWeightLoss').value),
                so_ngay_theo_doi: Number($('configWatchDays').value),
                nuoc_toi_thieu: Number($('configWaterMinimum').value),
            };
        }
        function systemConfigPayload() {
            return {
                che_do_bao_tri: $('configMaintenance').checked,
                so_ngay_xoa_log: Number($('configLogRetention').value),
            };
        }

        function hideGlobalSearchResults() {
            const panel = $('globalSearchResults');
            if (!panel) return;
            panel.hidden = true;
            panel.innerHTML = '';
        }
        function globalSearchLoading() {
            const panel = $('globalSearchResults');
            if (!panel) return;
            panel.hidden = false;
            panel.innerHTML = '<div class="global-search-empty">Đang tìm kiếm...</div>';
        }
        function globalSearchEmpty(message = 'Không tìm thấy dữ liệu phù hợp.') {
            const panel = $('globalSearchResults');
            if (!panel) return;
            panel.hidden = false;
            panel.innerHTML = `<div class="global-search-empty">${escapeHtml(message)}</div>`;
        }
        function globalSearchItem({ target, query, id = '', icon, title, subtitle }) {
            return `<button class="global-search-item" type="button" role="option" data-global-target="${escapeHtml(target)}" data-global-query="${escapeHtml(query)}" data-global-id="${escapeHtml(id)}">
                <span class="global-search-icon"><i class="ti ${escapeHtml(icon)}"></i></span>
                <span style="min-width:0;flex:1">
                    <span class="global-search-title">${escapeHtml(title)}</span>
                    <span class="global-search-sub">${escapeHtml(subtitle)}</span>
                </span>
            </button>`;
        }
        async function runGlobalSearch(rawQuery) {
            const query = rawQuery.trim();
            if (query.length < 2) {
                hideGlobalSearchResults();
                return;
            }

            globalSearchLoading();
            const params = encodeURIComponent(query);
            const [accountsResult, medicinesResult, foodsResult] = await Promise.allSettled([
                getJson(`/api/admin/accounts?per_page=5&q=${params}`),
                getJson(`/api/admin/thuoc?limit=5&q=${params}`),
                getJson(`/api/admin/resources?type=foods&limit=5&q=${params}`),
            ]);

            const items = [];
            if (accountsResult.status === 'fulfilled') {
                safeArray(accountsResult.value?.data).slice(0, 5).forEach(user => {
                    items.push(globalSearchItem({
                        target: 'users',
                        query,
                        id: user.id || user.ID || '',
                        icon: 'ti-user',
                        title: user.name || user.Ten || user.email || 'Người dùng',
                        subtitle: user.email || user.Email || 'Hồ sơ người dùng',
                    }));
                });
            }
            if (medicinesResult.status === 'fulfilled') {
                safeArray(medicinesResult.value?.data).slice(0, 5).forEach(medicine => {
                    const name = medicine.ten_thuoc || medicine.TenThuoc || 'Thuốc';
                    const active = medicine.hoat_chat || medicine.HoatChat || medicine.nhom_thuoc || medicine.NhomThuoc || '';
                    items.push(globalSearchItem({
                        target: 'medicines',
                        query,
                        id: medicine.id || medicine.ID || '',
                        icon: 'ti-pill',
                        title: name,
                        subtitle: active ? `Thuốc - ${active}` : 'Danh mục thuốc',
                    }));
                });
            }
            if (foodsResult.status === 'fulfilled') {
                safeArray(foodsResult.value?.data).slice(0, 5).forEach(food => {
                    items.push(globalSearchItem({
                        target: 'foods',
                        query,
                        id: food.ID || food.id || '',
                        icon: 'ti-apple',
                        title: food.Ten || food.ten_thuc_pham || 'Thực phẩm',
                        subtitle: food.LoaiThucPham || food.loai_thuc_pham || 'Kho thực phẩm',
                    }));
                });
            }

            const panel = $('globalSearchResults');
            if (!panel) return;
            if (!items.length) {
                const failed = [accountsResult, medicinesResult, foodsResult].some(result => result.status === 'rejected');
                globalSearchEmpty(failed ? 'Một số nguồn tìm kiếm chưa phản hồi. Vui lòng thử lại.' : 'Không tìm thấy dữ liệu phù hợp.');
                return;
            }
            panel.hidden = false;
            panel.innerHTML = items.join('');
        }
        async function openGlobalSearchResult(button) {
            const target = button.dataset.globalTarget;
            const query = button.dataset.globalQuery || $('globalSearch').value.trim();
            hideGlobalSearchResults();

            if (target === 'users') {
                state.q = query;
                if ($('searchInput')) $('searchInput').value = query;
                showView('users');
                await loadAccounts();
                const userId = Number(button.dataset.globalId || 0);
                if (userId) await openProfile(userId);
                return;
            }
            if (target === 'medicines') {
                state.medicineQ = query;
                state.medicines = [];
                await showView('medicines');
                return;
            }
            if (target === 'foods') {
                state.foodQ = query;
                state.foods = [];
                await showView('foods');
            }
        }

        function showView(view) {
            const safeView = Object.keys(pageTitles).includes(view) ? view : 'dashboard';
            document.querySelectorAll('[data-view-panel]').forEach(panel => panel.classList.toggle('active', panel.dataset.viewPanel === safeView));
            document.querySelectorAll('[data-view-link]').forEach(link => link.classList.toggle('active', link.dataset.viewLink === safeView));
            $('topbarTitle').textContent = pageTitles[safeView];
            const hash = `#${safeView}`;
            if (window.location.hash !== hash) history.pushState(null, '', hash);
            let loadPromise = Promise.resolve();
            if (safeView === 'foods') loadPromise = loadFoods().catch(error => showToast(error.message));
            if (safeView === 'medicines') loadPromise = loadMedicines().catch(error => showToast(error.message));
            if (safeView === 'activities') loadPromise = loadActivities().catch(error => showToast(error.message));
            if (safeView === 'settings') loadPromise = loadSystemConfig();
            renderDerivedPages();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return loadPromise;
        }

        function openModal(id) {
            $(id).classList.add('open');
        }
        function closeModal(id) {
            $(id).classList.remove('open');
            if (id === 'confirmModal') {
                state.confirmAction = null;
                state.confirmOptions = {};
            }
        }
        function openConfirm(message, action, options = {}) {
            const title = options.title || 'Xác nhận thao tác';
            const icon = options.icon || 'ti-alert-circle';
            $('confirmMessage').textContent = message;
            $('confirmTitle').innerHTML = `<i class="ti ${escapeHtml(icon)}"></i>${escapeHtml(title)}`;
            $('confirmOkBtn').textContent = options.confirmText || 'Xác nhận';
            $('confirmOkBtn').className = `btn ${options.danger === false ? '' : 'btn-danger'}`.trim();
            state.confirmAction = action;
            state.confirmOptions = options;
            openModal('confirmModal');
        }
        function hydrateAdminProfile() {
            const initialsValue = initialsText(adminDisplayName, adminDisplayEmail || adminDisplayName);
            $('topbarAvatarInitials').textContent = initialsValue;
            $('profileMenuAvatar').textContent = initialsValue;
            $('profileMenuName').textContent = adminDisplayName;
            $('profileMenuRole').textContent = adminDisplayEmail ? `Quản trị viên • ${adminDisplayEmail}` : 'Quản trị viên';
        }
        function openProfileMenu() {
            $('profileMenu').hidden = false;
            $('topbarAvatar').setAttribute('aria-expanded', 'true');
        }
        function closeProfileMenu() {
            $('profileMenu').hidden = true;
            $('topbarAvatar').setAttribute('aria-expanded', 'false');
        }
        function toggleProfileMenu() {
            $('profileMenu').hidden ? openProfileMenu() : closeProfileMenu();
        }
        function clearAdminClientSession() {
            ['admin_api_token', 'admin_user_id', 'admin_name', 'admin_email', 'token', 'user_id', 'user_name', 'email'].forEach(key => localStorage.removeItem(key));
            sessionStorage.clear();
        }
        function confirmLogout() {
            closeProfileMenu();
            openConfirm(
                'Bạn có chắc chắn muốn đăng xuất khỏi hệ thống không?',
                async () => {
                    clearAdminClientSession();
                    $('logoutForm').submit();
                },
                {
                    title: 'Xác nhận đăng xuất',
                    icon: 'ti-logout',
                    confirmText: 'Xác nhận',
                }
            );
        }
        function openTopbarNotifications() {
            const hasOpenAlerts = currentAlerts().some(alert => !alert.handled);
            if (hasOpenAlerts) {
                state.alertFilter = 'open';
                if ($('alertFilter')) $('alertFilter').value = 'open';
                renderAlertTable();
                showView('alerts');
                return;
            }
            showView('notifications');
        }

        function badgeSeverity(severity) {
            if (severity === 'high') return '<span class="badge badge-high">Nguy cơ cao</span>';
            if (severity === 'medium') return '<span class="badge badge-medium">Cần theo dõi</span>';
            return '<span class="badge badge-low">Theo dõi</span>';
        }
        function severityLevel(value) {
            const text = foldText(value);
            if (['critical', 'high', 'nguy co cao', 'cao', 'do'].some(key => text.includes(key))) return 'high';
            if (['medium', 'warning', 'can theo doi', 'trung binh'].some(key => text.includes(key))) return 'medium';
            return 'low';
        }
        function normalizeAlert(item, index) {
            const rawUserId = Number(item.user_id || item.userId || item.NguoiDungID || 0);
            const account = state.accountMap.get(rawUserId);
            const fallbackName = stripUserId(item.user || item.ten_nguoi_dung || item.name);
            const source = item.source || (item.loai_canh_bao ? 'he_thong_canh_bao' : (item.rule || item.category ? 'risk_event' : 'generated_alert'));
            const eventId = Number(item.event_id || item.eventId || (source === 'risk_event' ? item.id : 0) || 0);
            const reviewAction = item.review_action || item.reviewAction || item.nut_xac_nhan_da_xu_ly || null;
            const reviewKey = item.review_key || item.reviewKey
                || (source === 'he_thong_canh_bao' ? `he_thong_canh_bao:${item.id}` : '')
                || (source === 'risk_event' ? `risk_event:${item.id}` : '')
                || item.id
                || `${item.type || item.loai_canh_bao || 'alert'}:${rawUserId || index}:${item.title || item.ten_loai_canh_bao || index}`;
            const status = String(item.status || '').toLowerCase();
            return {
                id: String(item.id || reviewKey),
                reviewKey: String(reviewKey),
                eventId,
                source,
                raw: item,
                userId: rawUserId,
                name: account?.name || fallbackName || 'Người dùng',
                email: account?.email || item.email || 'Chưa có email',
                avatar: account?.avatar || item.anh_dai_dien || '',
                title: item.title || item.ten_loai_canh_bao || item.loai_canh_bao || 'Cảnh báo sức khỏe',
                message: item.message || item.noi_dung_chi_tiet || '',
                type: item.type || item.category || item.loai_canh_bao || 'Sức khỏe',
                severity: severityLevel(item.severity || item.muc_do_nguy_hiem || item.Severity),
                action: item.action || item.Action || '',
                time: item.detected_at || item.last_detected_at || item.time || item.created_at || new Date().toISOString(),
                handled: Boolean(item.is_read || item.handled || ['reviewed', 'resolved', 'done'].includes(status)),
                reviewAction,
            };
        }
        function currentAlerts() {
            return safeArray(state.alerts).map(normalizeAlert);
        }
        function alertMatchesFilter(alert) {
            if (state.alertFilter === 'high') return alert.severity === 'high';
            if (state.alertFilter === 'watch') return alert.severity !== 'high';
            if (state.alertFilter === 'done') return alert.handled;
            if (state.alertFilter === 'open') return !alert.handled;
            return true;
        }

        function statValueByLabel(items, keywords) {
            const normalizedKeywords = safeArray(keywords).map(foldText);
            const found = safeArray(items).find(item => normalizedKeywords.some(keyword => foldText(item.label).includes(keyword)));
            return safeNumber(found?.value);
        }
        function renderOverview(items) {
            const overviewItems = safeArray(items);
            const totalAccounts = statValueByLabel(items, ['tai khoan']);
            const totalNotifications = statValueByLabel(items, ['thong bao']);
            const totalFoods = statValueByLabel(items, ['thuc pham']) || state.foods.length;
            const totalMedicines = statValueByLabel(items, ['thuoc']) || state.medicines.length;
            const totalActivities = statValueByLabel(items, ['hoat dong']) || state.accounts.reduce((sum, account) => sum + safeNumber(account.stats?.activities), 0);
            const totalAlerts = currentAlerts().length;
            const cards = [
                ['tone-blue', 'ti-users', 'Người dùng', totalAccounts, 'Tài khoản trong hệ thống'],
                ['tone-lavender', 'ti-bell', 'Thông báo', totalNotifications, 'Thông báo đã gửi'],
                ['tone-peach', 'ti-apple', 'Thực phẩm', totalFoods, 'Kho dữ liệu dinh dưỡng'],
                ['tone-mint', 'ti-pill', 'Thuốc', totalMedicines, 'Danh mục thuốc hiện có'],
                ['tone-blue', 'ti-run', 'Hoạt động', totalActivities, 'Lịch sử vận động'],
                ['tone-rose', 'ti-alert-triangle', 'Cảnh báo', totalAlerts, 'Người dùng cần theo dõi'],
            ];
            $('overviewStats').innerHTML = cards.map(([tone, icon, label, value, note]) => `<button class="stat-card ${tone}" type="button">
                <div class="stat-icon"><i class="ti ${icon}"></i></div>
                <div class="stat-value">${number(value)}</div>
                <div class="stat-label">${label}</div>
                <div class="stat-note">${note}</div>
            </button>`).join('');
            overviewItems.forEach((item, index) => {
                const card = $('overviewStats').children[index];
                if (!card || !item.view) return;
                card.dataset.statView = item.view;
                card.dataset.statUrl = item.url || '';
                card.dataset.statFilters = JSON.stringify(item.filters || {});
                card.title = `Mở trang ${item.view}`;
            });
        }
        function featureValue(items, keywords) {
            const normalizedKeywords = safeArray(keywords).map(foldText);
            const found = safeArray(items).find(item => normalizedKeywords.some(keyword => foldText(item.label).includes(keyword)));
            return safeNumber(found?.value);
        }
        function renderSystemActivity(items = []) {
            const totalMeals = featureValue(items, ['dinh dưỡng', 'dinh duong', 'bua an', 'bữa ăn']);
            const totalWater = featureValue(items, ['uong nuoc', 'uống nước', 'nuoc']);
            const totalMedicines = featureValue(items, ['thuoc', 'thuốc']);
            const totalActivities = featureValue(items, ['van dong', 'vận động', 'hoat dong']);
            const cards = [
                ['🍽', 'Tổng bữa ăn', totalMeals, 'Bản ghi dinh dưỡng của người dùng'],
                ['💧', 'Tổng ghi nhận nước', totalWater, 'Số lần theo dõi nước uống'],
                ['💊', 'Tổng lịch sử thuốc', totalMedicines, 'Lịch sử và lịch dùng thuốc'],
                ['🏃', 'Tổng hoạt động', totalActivities, 'Hoạt động vận động đã ghi nhận'],
            ];
            $('systemActivity').innerHTML = cards.map(([icon, label, value, note]) => `<article class="ops-card">
                <div class="stat-icon">${icon}</div>
                <div><strong>${number(value)}</strong><div class="primary-text">${label}</div><div class="muted">${note}</div></div>
            </article>`).join('');
        }
        function updateAlertBadges() {
            const openAlerts = currentAlerts().filter(alert => !alert.handled).length;
            const unreadNotifications = safeNumber(state.badges?.notifications_unread);
            const topbarTotal = openAlerts + unreadNotifications;
            setBadge('navAlertCount', openAlerts);
            setBadge('anomalyBadge', openAlerts);
            setBadge('topbarNoticeCount', topbarTotal);
            if ($('topbarNoticeDot')) $('topbarNoticeDot').hidden = topbarTotal <= 0;
            if ($('topbarNotifications')) {
                $('topbarNotifications').classList.toggle('alert-pulse', topbarTotal > 0);
                $('topbarNotifications').setAttribute('aria-live', topbarTotal > 0 ? 'polite' : 'off');
                $('topbarNotifications').title = openAlerts
                    ? `${number(openAlerts)} cảnh báo bất thường cần xử lý`
                    : 'Chuông thông báo';
            }
        }
        function renderRiskSummary() {
            const alerts = currentAlerts();
            const high = alerts.filter(a => a.severity === 'high').length;
            const watch = alerts.filter(a => a.severity !== 'high').length;
            const open = alerts.filter(a => !a.handled).length;
            const done = alerts.filter(a => a.handled).length;
            const cards = [
                ['tone-rose', 'ti-alert-octagon', 'Nguy cơ cao', high, 'Cần xử lý ngay', 'high'],
                ['tone-peach', 'ti-eye', 'Cần theo dõi', watch, 'Theo dõi trong 24h', 'watch'],
                ['tone-lavender', 'ti-clock-exclamation', 'Chưa xử lý', open, 'Đang chờ admin xử lý', 'open'],
                ['tone-mint', 'ti-circle-check', 'Đã xử lý', done, 'Đã được admin đánh dấu', 'done'],
            ];
            if ($('riskSummary')) $('riskSummary').innerHTML = cards.map(([tone, icon, label, value, note, filter]) => `
                <button class="risk-card ${tone}" type="button" data-risk-filter="${filter}">
                    <div class="stat-icon"><i class="ti ${icon}"></i></div>
                    <div class="stat-value">${number(value)}</div>
                    <div class="stat-label">${label}</div>
                    <div class="stat-note">${note}</div>
                </button>
            `).join('');
            updateAlertBadges();
        }
        function renderDashboardAlerts() {
            const alerts = currentAlerts().filter(alert => !alert.handled).slice(0, 5);
            $('dashboardAlerts').innerHTML = alerts.length ? alerts.map(alertCard).join('') : emptyState('✨', 'Không có cảnh báo bất thường', 'Hệ thống chưa phát hiện dấu hiệu sức khỏe bất thường.', '<button class="btn btn-ghost" type="button" data-open-alerts>Xem trang cảnh báo</button>');
        }
        function alertCard(alert) {
            return `<article class="notice-card ${alert.severity === 'high' ? 'alert-high' : 'alert-medium'}">
                <div class="notice-icon warning-icon"><i class="ti ${alert.severity === 'high' ? 'ti-alert-octagon' : 'ti-alert-triangle'}"></i></div>
                <div style="min-width:0;flex:1">
                    <div class="avatar-row">${userAvatar(alert)}<div><div class="notice-title">${escapeHtml(alert.name)}</div><div class="muted">${escapeHtml(alert.email)}</div></div></div>
                    <div class="notice-msg"><span class="field-label">Mức độ</span>${badgeSeverity(alert.severity)}</div>
                    <div class="notice-msg"><span class="field-label">Vấn đề</span><strong>${escapeHtml(alert.title)}</strong></div>
                    ${alert.message ? `<div class="notice-msg"><span class="field-label">Chi tiết</span>${escapeHtml(alert.message)}</div>` : ''}
                    <div class="notice-msg"><span class="field-label">Thời gian</span>${dateTime(alert.time)}</div>
                    <div class="actions" style="margin-top:10px">
                        <button class="btn btn-ghost btn-sm" type="button" data-profile="${alert.userId}"><i class="ti ti-user"></i>Xem hồ sơ</button>
                        <button class="btn btn-sm" type="button" data-notice-user="${alert.userId}" data-notice-message="${escapeHtml(alert.message)}"><i class="ti ti-send"></i>Gửi thông báo</button>
                        <button class="btn btn-success btn-sm" type="button" data-handle-alert="${alert.id}"><i class="ti ti-check"></i>Xác nhận đã xử lý</button>
                    </div>
                </div>
            </article>`;
        }
        function renderAlertTable() {
            const rows = currentAlerts().filter(alertMatchesFilter);
            $('alertRows').innerHTML = rows.length ? rows.map(alert => `<article class="follow-card ${alert.severity === 'high' ? 'alert-high' : 'alert-medium'}">
                <div class="avatar-row">${userAvatar(alert)}<div><div class="primary-text">${escapeHtml(alert.name)}</div><div class="muted">${escapeHtml(alert.email)}</div></div></div>
                <div><div class="field-label">Loại cảnh báo</div><span class="tag tag-blue">${escapeHtml(alert.type)}</span><div class="muted">${escapeHtml(alert.title)}</div></div>
                <div><div class="field-label">Mức độ</div>${badgeSeverity(alert.severity)}</div>
                <div><div class="field-label">Thời gian</div><div class="primary-text">${dateTime(alert.time)}</div><div style="margin-top:6px">${alert.handled ? '<span class="badge badge-done">Đã xử lý</span>' : '<span class="badge badge-high">Chưa xử lý</span>'}</div></div>
                <div class="actions">
                    <button class="btn btn-ghost btn-sm" type="button" data-profile="${alert.userId}">Xem hồ sơ</button>
                    <button class="btn btn-sm" type="button" data-notice-user="${alert.userId}" data-notice-message="${escapeHtml(alert.message)}">Gửi thông báo</button>
                    <button class="btn btn-success btn-sm" type="button" data-handle-alert="${alert.id}">Xác nhận đã xử lý</button>
                </div>
            </article>`).join('') : emptyState('✅', 'Không có người dùng phù hợp', 'Bộ lọc hiện tại chưa có người dùng cần theo dõi.', '<button class="btn btn-ghost" type="button" data-open-alerts>Tải lại dữ liệu</button>');
        }

        function renderFeatures(items) {
            if (!$('featureList')) return;
            const featureItems = safeArray(items);
            $('featureList').innerHTML = featureItems.length ? featureItems.map(item => `
                <div class="notice-card" style="background:var(--bg)">
                    <div class="notice-icon"><i class="ti ti-activity"></i></div>
                    <div><div class="notice-title">${escapeHtml(item.label)}</div><div class="notice-msg">${number(item.value)} - ${escapeHtml(item.note || '')}</div></div>
                </div>
            `).join('') : emptyState('📦', 'Chưa có dữ liệu tính năng', 'Dữ liệu module sẽ hiển thị khi hệ thống có bản ghi.');
        }
        function renderWeekly(days) {
            const safeDays = safeArray(days);
            if (!safeDays.length) {
                $('weeklyBars').innerHTML = emptyState('📊', 'Chưa có thống kê 7 ngày', 'Khi hệ thống phát sinh tài khoản hoặc thông báo mới, biểu đồ sẽ hiển thị tại đây.');
                return;
            }
            const max = Math.max(1, ...safeDays.map(day => Math.max(safeNumber(day.accounts), safeNumber(day.notifications))));
            const totalAccounts = safeDays.reduce((sum, day) => sum + safeNumber(day.accounts), 0);
            const totalNotifications = safeDays.reduce((sum, day) => sum + safeNumber(day.notifications), 0);
            $('weeklyBars').innerHTML = `
                <div class="mini-stats" style="margin-bottom:14px">
                    <span>👥 Người dùng mới: ${number(totalAccounts)}</span>
                    <span>🔔 Thông báo mới: ${number(totalNotifications)}</span>
                </div>
                <div style="display:flex;align-items:flex-end;gap:12px;height:190px">
                    ${safeDays.map(day => {
                        const accounts = safeNumber(day.accounts);
                        const notifications = safeNumber(day.notifications);
                        const accountHeight = Math.max(8, Math.round((accounts / max) * 100));
                        const noticeHeight = Math.max(8, Math.round((notifications / max) * 100));
                        return `<div style="flex:1;display:grid;gap:8px;justify-items:center;align-items:end;height:100%;min-width:54px">
                            <div style="display:flex;align-items:flex-end;justify-content:center;gap:5px;width:100%;height:145px">
                                <div title="Người dùng mới: ${number(accounts)}" style="width:38%;height:${accountHeight}%;border-radius:10px 10px 2px 2px;background:linear-gradient(180deg,var(--blue),#0b84c6)"></div>
                                <div title="Thông báo mới: ${number(notifications)}" style="width:38%;height:${noticeHeight}%;border-radius:10px 10px 2px 2px;background:linear-gradient(180deg,var(--lavender-dark),#9fa8da)"></div>
                            </div>
                            <span class="muted">${escapeHtml(day.label || day.date || 'N/A')}</span>
                        </div>`;
                    }).join('')}
                </div>
                <div class="mini-stats" style="margin-top:12px">
                    <span style="border-left:10px solid var(--blue)">Người dùng mới</span>
                    <span style="border-left:10px solid var(--lavender-dark)">Thông báo mới</span>
                </div>`;
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
            const payload = data && typeof data === 'object' ? data : {};
            state.notifications = safeArray(payload.recent);
            $('notificationSummary').innerHTML = `<span>Tổng: ${number(payload.total)}</span><span>Chưa đọc: ${number(payload.unread)}</span><span>Đã đọc: ${number(payload.read)}</span><span>Hôm nay: ${number(payload.today)}</span>`;
            syncNotificationBadges(payload.unread || 0);
            renderNotificationTypes(payload.by_type || []);
            renderNoticeLists();
        }
        function renderNotificationTypes(items) {
            const typeItems = safeArray(items);
            const iconForType = (label = '') => {
                const text = String(label).toLowerCase();
                if (/thuoc|thuốc/.test(text)) return '💊';
                if (/nuoc|nước/.test(text)) return '💧';
                if (/canh bao|cảnh báo|risk|health/.test(text)) return '⚠️';
                if (/bua|bữa|dinh/.test(text)) return '🍽';
                return '🔔';
            };
            $('notificationTypeList').innerHTML = typeItems.length ? typeItems.map(item => `<article class="notice-card">
                <div class="notice-icon">${iconForType(item.label)}</div>
                <div style="min-width:0;flex:1">
                    <div class="notice-title">${escapeHtml(item.label || 'Thông báo')}</div>
                    <div class="notice-msg">${number(item.value)} thông báo</div>
                </div>
            </article>`).join('') : emptyState('🔔', 'Chưa có loại thông báo phổ biến', 'Khi hệ thống phát sinh thông báo, thống kê nhanh sẽ xuất hiện tại đây.');
        }
        function renderNoticeLists() {
            const filtered = state.notifications.filter(notificationMatches);
            const html = filtered.length ? filtered.map(notificationCard).join('') : emptyState('🔔', 'Chưa có thông báo nào', 'Khi hệ thống có thông báo, danh sách sẽ xuất hiện tại đây.', '<button class="btn" type="button" id="emptyOpenNotice">Gửi thông báo đầu tiên</button>');
            $('noticeList').innerHTML = state.notifications.length ? state.notifications.slice(0, 4).map(notificationCard).join('') : emptyState('🔔', 'Chưa có thông báo', 'Thông báo gần đây sẽ hiển thị tại đây.');
            $('noticeListPage').innerHTML = html;
            $('emptyOpenNotice')?.addEventListener('click', () => openNoticeModal());
        }
        function syncNotificationBadges(unread) {
            const count = safeNumber(unread);
            state.badges = { ...(state.badges || {}), notifications_unread: count };
            setBadge('navNoticeCount', count);
            updateAlertBadges();
        }

        function renderAccounts(payload) {
            state.accounts = payload.data || [];
            state.accountMap = new Map(state.accounts.map(account => [Number(account.id), account]));
            $('accountMeta').textContent = `${number(payload.meta?.total || 0)} tài khoản trong hệ thống`;
            setBadge('navUserCount', state.badges?.users_unread || 0);
            renderAccountRows();
            renderNoticeUserPicker();
        }
        function renderAccountRows() {
            const rows = state.accounts;
            $('accountRows').innerHTML = rows.length ? rows.map(account => `<article class="user-card">
                <div class="user-card-head">
                    <div class="avatar-row">${userAvatar(account)}<div><div class="primary-text">${escapeHtml(account.name)}</div><div class="muted">${escapeHtml(account.email)}</div></div></div>
                    <span class="badge ${account.is_active ? 'badge-active' : 'badge-locked'}">${escapeHtml(account.status)}</span>
                </div>
                <div class="mini-stats">
                    <span>Ngày tạo: ${dateTime(account.created_at)}</span>
                    <span>Đăng nhập cuối: ${dateTime(account.last_login)}</span>
                </div>
                <div class="user-stats">
                    <div class="user-stat">🍽<strong>${number(account.stats?.meals)}</strong>Bữa ăn</div>
                    <div class="user-stat">💧<strong>${number(account.stats?.water_logs)}</strong>Uống nước</div>
                    <div class="user-stat">💊<strong>${number(account.stats?.medicines)}</strong>Thuốc</div>
                    <div class="user-stat">🏃<strong>${number(account.stats?.activities)}</strong>Hoạt động</div>
                    <div class="user-stat">🔔<strong>${number(account.stats?.notifications)}</strong>Thông báo</div>
                </div>
                <div class="actions">
                    <button class="btn btn-ghost btn-sm" data-profile="${account.id}" type="button">Xem chi tiết</button>
                    <button class="btn ${account.is_active ? 'btn-danger' : 'btn-success'} btn-sm" data-toggle="${account.id}" data-locked="${account.is_active ? '1' : '0'}" type="button">${account.is_active ? 'Khóa' : 'Mở khóa'}</button>
                </div>
            </article>`).join('') : emptyState('👥', 'Chưa có người dùng', 'Danh sách người dùng sẽ hiển thị sau khi có tài khoản.', '<button class="btn btn-ghost" type="button" id="reloadUsersEmpty">Tải lại</button>');
            $('reloadUsersEmpty')?.addEventListener('click', () => loadAccounts().catch(error => showToast(error.message)));
        }

        function renderFoods() {
            $('foodRows').innerHTML = state.foods.length ? state.foods.map((food, index) => `<article class="resource-card">
                <div class="resource-head">
                    <div class="resource-emoji">${food.hinh_anh ? `<img src="${escapeHtml(food.hinh_anh)}" alt="" style="width:34px;height:34px;border-radius:10px;object-fit:cover">` : '🥗'}</div>
                    <div style="min-width:0;flex:1">
                        <div class="primary-text" style="font-size:17px">${escapeHtml(food.Ten)}</div>
                        <div class="muted">${escapeHtml(food.KhoiLuongGram || 100)}g • ${escapeHtml(food.LoaiThucPham || 'Chưa phân loại')}</div>
                    </div>
                </div>
                <div class="macro-grid">
                    <div class="macro-box"><span class="muted">Calories</span><strong>${number(food.Calo)}</strong></div>
                    <div class="macro-box"><span class="muted">Protein</span><strong>${escapeHtml(food.Protein || 0)}g</strong></div>
                    <div class="macro-box"><span class="muted">Carb</span><strong>${escapeHtml(food.Carb || 0)}g</strong></div>
                    <div class="macro-box"><span class="muted">Fat</span><strong>${escapeHtml(food.ChatBeo || 0)}g</strong></div>
                </div>
                <div class="actions"><button class="btn btn-ghost btn-sm" data-edit-food="${index}" type="button">Sửa</button><button class="btn btn-danger btn-sm" data-delete-food="${food.ID}" type="button">Xóa</button></div>
            </article>`).join('') : emptyState('🥗', 'Chưa có thực phẩm nào', 'Thêm thực phẩm đầu tiên để người dùng ghi nhận bữa ăn.', '<button class="btn" type="button" id="focusFoodForm">Thêm thực phẩm đầu tiên</button>');
            $('focusFoodForm')?.addEventListener('click', () => $('foodName').focus());
        }
        function renderMedicines() {
            $('medicineRows').innerHTML = state.medicines.length ? state.medicines.map((medicine, index) => {
                const name = medicine.ten_thuoc || medicine.TenThuoc || '';
                const active = medicine.hoat_chat || medicine.HoatChat || '';
            const strength = medicine.ham_luong_goc || medicine.lieu_luong_goc || medicine.LieuLuong || '';
                const group = medicine.nhom_thuoc || medicine.NhomThuoc || 'Khác';
                const usage = medicine.mo_ta || medicine.MoTa || 'Chưa có công dụng';
                const image = medicine.hinh_anh || '';
                return `<article class="resource-card">
                <div class="resource-head">
                    <div class="resource-emoji">${image ? `<img src="${escapeHtml(image)}" alt="" style="width:34px;height:34px;border-radius:10px;object-fit:cover">` : '💊'}</div>
                    <div style="min-width:0;flex:1">
                        <div class="primary-text" style="font-size:17px">${escapeHtml(name)}</div>
                        <div class="muted">${escapeHtml(usage)}</div>
                    </div>
                </div>
                <div class="info-grid">
                    <div class="info-row"><div class="info-label">Hoạt chất chính</div><div class="info-value">${escapeHtml(active || 'Chưa cập nhật')}</div></div>
                    <div class="info-row"><div class="info-label">Nhóm thuốc</div><div class="info-value"><span class="tag tag-lavender">${escapeHtml(group)}</span></div></div>
                    <div class="info-row"><div class="info-label">Hàm lượng gốc</div><div class="info-value">${escapeHtml(strength || 'Chưa cập nhật')}</div></div>
                    <div class="info-row"><div class="info-label">Hình ảnh</div><div class="info-value">${image ? 'Đã cập nhật' : 'Chưa có hình ảnh'}</div></div>
                </div>
                <div class="actions"><button class="btn btn-ghost btn-sm" data-edit-medicine="${index}" type="button">Sửa</button><button class="btn btn-danger btn-sm" data-delete-medicine="${medicine.id || medicine.ID}" type="button">Xóa</button></div>
            </article>`;
            }).join('') : emptyState('💊', 'Chưa có thuốc nào', 'Thêm thuốc đầu tiên để người dùng tìm kiếm và lập lịch uống thuốc.', '<button class="btn" type="button" id="focusMedicineForm">Thêm thuốc đầu tiên</button>');
            $('focusMedicineForm')?.addEventListener('click', () => $('medicineName').focus());
        }
        function renderActivities() {
            const rows = state.activities.map(activity => {
                const id = activity.id || activity.ID || '';
                const name = activity.ten_hoat_dong || activity.ten_van_dong || '';
                const met = activity.chi_so_met || activity.MET || 0;
                const image = activity.hinh_anh || activity.hinh_anh_icon || '';
                return `<tr>
                    <td><strong>#${escapeHtml(id)}</strong></td>
                    <td>${image ? `<img class="table-thumb" src="${escapeHtml(image)}" alt="${escapeHtml(name)}">` : '<span class="resource-emoji" style="width:44px;height:44px">🏃</span>'}</td>
                    <td><strong>${escapeHtml(name)}</strong><div class="muted">ten_hoat_dong / ten_van_dong</div></td>
                    <td><span class="tag tag-lavender">${escapeHtml(met)}</span></td>
                    <td>${escapeHtml(activity.mo_ta || 'Chưa có mô tả cường độ')}</td>
                    <td><code>MET × cân nặng × phút tập</code></td>
                    <td><button class="btn btn-danger btn-sm" data-delete-activity="${escapeHtml(id)}" type="button">Xóa</button></td>
                </tr>`;
            }).join('');

            $('activityRows').innerHTML = `<div class="resource-table-block">
                <div class="table-toolbar">
                    <div>
                        <div class="table-title"><i class="ti ti-list-check"></i>Danh sách hoạt động vận động</div>
                        <div class="table-note">Đồng bộ các field client đang đọc trong vandong.dart: id, ten_hoat_dong, ten_van_dong, mo_ta, chi_so_met.</div>
                    </div>
                    <span class="tag tag-lavender">${number(state.activities.length)} hoạt động</span>
                </div>
                ${state.activities.length ? `<div class="table-wrap"><table class="data-table">
                    <thead><tr><th>ID</th><th>Hình</th><th>Tên hoạt động</th><th>MET</th><th>Mô tả</th><th>Payload client</th><th>Thao tác</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table></div>` : emptyState('🏃', 'Chưa có hoạt động vận động', 'Thêm môn vận động đầu tiên để đồng bộ thư viện MET.', '<button class="btn" type="button" id="focusActivityForm">Thêm hoạt động đầu tiên</button>')}
            </div>`;
            $('focusActivityForm')?.addEventListener('click', () => $('activityName').focus());
        }

        function renderDerivedPages() {
            const totalActivities = state.accounts.reduce((sum, account) => sum + Number(account.stats?.activities || 0), 0);
            $('activitySummary').innerHTML = [
                ['🏃', 'Tổng hoạt động', totalActivities, 'Dựa trên thống kê người dùng'],
                ['🔥', 'Tài khoản có vận động', state.accounts.filter(a => Number(a.stats?.activities || 0) > 0).length, 'Đã ghi nhận hoạt động'],
                ['📋', 'Nguồn dữ liệu', 'activity/stats', 'Giữ nguyên API hiện có'],
            ].map(([icon, title, value, note]) => `<div class="stat-card tone-blue"><div class="stat-icon">${icon}</div><div class="stat-value">${number(value)}</div><div class="stat-label">${title}</div><div class="stat-note">${note}</div></div>`).join('');
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
                if (data.reviewed?.changed) {
                    await loadStats();
                }
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

        function normalizeRiskEventLog(item) {
            return {
                id: `risk-event-${item.id}`,
                event_id: item.id,
                review_key: `risk_event:${item.id}`,
                source: 'risk_event',
                user_id: item.user_id,
                user: item.user,
                type: item.category || item.rule || 'Sức khỏe',
                severity: item.severity,
                title: item.title || 'Cảnh báo sức khỏe',
                message: item.message || '',
                action: item.action || '',
                time: item.last_detected_at,
                status: item.status || 'open',
            };
        }
        function normalizeSystemAlertLog(item) {
            return {
                id: `he-thong-canh-bao-${item.id}`,
                review_key: `he_thong_canh_bao:${item.id}`,
                source: 'he_thong_canh_bao',
                user_id: item.user_id,
                user: item.ten_nguoi_dung,
                email: item.email,
                avatar: item.anh_dai_dien,
                type: item.loai_canh_bao,
                severity: item.muc_do_nguy_hiem,
                title: item.ten_loai_canh_bao || item.loai_canh_bao || 'Cảnh báo bất thường',
                message: item.noi_dung_chi_tiet || '',
                time: item.detected_at,
                status: item.status || 'pending',
                review_action: item.review_action || item.nut_xac_nhan_da_xu_ly,
            };
        }
        function mergeAlertSources(...sources) {
            const rank = { high: 3, medium: 2, low: 1 };
            const merged = new Map();
            sources.flatMap(safeArray).forEach((item, index) => {
                const alert = normalizeAlert(item, index);
                const key = alert.reviewKey || `${alert.source}:${alert.id}`;
                const current = merged.get(key);
                if (!current || (rank[alert.severity] || 0) > (rank[current.severity] || 0)) {
                    merged.set(key, alert);
                }
            });

            return [...merged.values()].sort((a, b) => {
                const rankDiff = (rank[b.severity] || 0) - (rank[a.severity] || 0);
                if (rankDiff) return rankDiff;
                return new Date(b.time).getTime() - new Date(a.time).getTime();
            });
        }
        async function loadEmergencyAlerts() {
            // Emergency logs are optional data sources; one failed source must not blank the dashboard.
            try {
                await getJson('/api/admin/he-thong-canh-bao/quet', { method: 'POST', body: JSON.stringify({}) });
            } catch (_) {
                // The dashboard can still render generated/risk-event alerts if the persisted-alert scan is unavailable.
            }

            const results = await Promise.allSettled([
                getJson('/api/admin/risk-events?status=open&per_page=30'),
                getJson('/api/admin/he-thong-canh-bao?status=pending&per_page=30'),
            ]);
            const failed = results
                .filter(result => result.status === 'rejected')
                .map(result => result.reason?.message || 'Không thể tải cảnh báo');
            state.alertFetchError = failed.join(' ');

            const riskEvents = results[0].status === 'fulfilled'
                ? safeArray(results[0].value?.data).map(normalizeRiskEventLog)
                : [];
            const systemAlerts = results[1].status === 'fulfilled'
                ? safeArray(results[1].value?.data).map(normalizeSystemAlertLog)
                : [];

            return [...riskEvents, ...systemAlerts];
        }

        function dashboardLoadingSkeleton() {
            return '<div class="loading-skeleton card" style="grid-column:1/-1"><div class="skeleton-line"></div><div class="skeleton-line"></div><div class="skeleton-line"></div></div>';
        }
        function renderDashboardLoading() {
            $('generatedAt').textContent = 'Đang tải dữ liệu...';
            $('overviewStats').innerHTML = dashboardLoadingSkeleton();
            $('weeklyBars').innerHTML = '<div class="loading-skeleton"><div class="skeleton-line"></div><div class="skeleton-line"></div></div>';
            $('dashboardAlerts').innerHTML = '<div class="loading-skeleton"><div class="skeleton-line"></div><div class="skeleton-line"></div></div>';
            $('noticeList').innerHTML = '<div class="loading-skeleton"><div class="skeleton-line"></div><div class="skeleton-line"></div></div>';
            $('systemActivity').innerHTML = '<div class="loading-skeleton card"><div class="skeleton-line"></div><div class="skeleton-line"></div></div>';
            $('notificationSummary').innerHTML = '';
            $('notificationTypeList').innerHTML = '';
        }
        function renderDashboardError(error) {
            const message = error?.message || 'Không thể tải dữ liệu tổng quan. Vui lòng thử lại.';
            $('generatedAt').textContent = 'Không thể tải dữ liệu tổng quan';
            const retry = '<button class="btn" type="button" data-retry-dashboard><i class="ti ti-refresh"></i>Thử lại</button>';
            $('overviewStats').innerHTML = `<div style="grid-column:1/-1">${emptyState('<i class="ti ti-alert-triangle"></i>', 'Không thể tải dữ liệu', message, retry)}</div>`;
            $('overviewStats').querySelector('.empty-state')?.classList.add('error-state');
            $('weeklyBars').innerHTML = emptyState('📊', 'Chưa có dữ liệu biểu đồ', 'Biểu đồ sẽ hiển thị sau khi dữ liệu tổng quan tải thành công.');
            $('dashboardAlerts').innerHTML = emptyState('⚠️', 'Chưa tải được cảnh báo', 'Nhấn Thử lại để tải lại danh sách cảnh báo bất thường.', retry);
            $('noticeList').innerHTML = emptyState('🔔', 'Chưa tải được thông báo', 'Thông báo mới nhất sẽ hiển thị sau khi kết nối API ổn định.');
            $('systemActivity').innerHTML = emptyState('📡', 'Chưa tải được hoạt động', 'Không có dữ liệu hoạt động hệ thống để hiển thị lúc này.');
            $('notificationSummary').innerHTML = '';
            $('notificationTypeList').innerHTML = '';
            updateAlertBadges();
        }

        async function loadStats() {
            renderDashboardLoading();
            try {
                const data = await getJson('/api/admin/stats');
                const emergencyAlerts = await loadEmergencyAlerts();
                $('generatedAt').textContent = `Cập nhật lúc ${dateTime(data.generated_at)} • Múi giờ Asia/Ho_Chi_Minh`;
                state.badges = data.badges || {};
                setBadge('navUserCount', state.badges.users_unread || 0);
                setBadge('navNoticeCount', state.badges.notifications_unread || 0);
                state.alerts = mergeAlertSources(data.alerts || [], emergencyAlerts);
                renderOverview(data.overview || []);
                renderSystemActivity(data.features || []);
                renderNotifications(data.notifications || {});
                renderWeekly(data.weekly || []);
                renderRiskSummary();
                renderDashboardAlerts();
                renderAlertTable();
                if (state.alertFetchError) showToast(`Một số nguồn cảnh báo chưa tải được: ${state.alertFetchError}`);
            } catch (error) {
                renderDashboardError(error);
                throw error;
            }
        }
        async function loadAccounts() {
            $('accountRows').innerHTML = '<div class="loading-skeleton"><div class="skeleton-line"></div><div class="skeleton-line"></div><div class="skeleton-line"></div></div>';
            const params = new URLSearchParams({ per_page: '50' });
            if (state.q) params.set('q', state.q);
            if (state.status) params.set('status', state.status);
            const data = await getJson(`/api/admin/accounts?${params}`);
            renderAccounts(data);
            renderDerivedPages();
        }
        async function loadFoods() {
            if (!state.foodQ && state.foods.length) return renderFoods();
            const params = new URLSearchParams({ type: 'foods', limit: '50' });
            if (state.foodQ) params.set('q', state.foodQ);
            const data = await getJson(`/api/admin/resources?${params}`);
            state.foods = data.data || [];
            renderFoods();
        }
        async function loadMedicines() {
            if (!state.medicineQ && state.medicines.length) return renderMedicines();
            const params = new URLSearchParams({ limit: '50' });
            if (state.medicineQ) params.set('q', state.medicineQ);
            const data = await getJson(`/api/admin/thuoc?${params}`);
            state.medicines = data.data || [];
            renderMedicines();
        }
        async function loadActivities() {
            if (state.activities.length) return renderActivities();
            const data = await getJson('/api/admin/hoat-dong');
            state.activities = data.data || [];
            renderActivities();
        }
        async function reloadAll() {
            state.foods = [];
            state.medicines = [];
            state.activities = [];

            const resourceLoads = Promise.allSettled([loadAccounts(), loadFoods(), loadMedicines(), loadActivities()]);
            const statsResult = await Promise.allSettled([loadStats()]);
            const resourceResults = await resourceLoads;
            const failures = [
                ...statsResult,
                ...resourceResults,
            ].filter(result => result.status === 'rejected');

            if (failures.length) {
                showToast(failures[0].reason?.message || 'Một số dữ liệu chưa tải được. Vui lòng thử lại.');
            }
        }

        function resetFoodForm() {
            state.editingFoodId = null;
            $('foodEditorTitle').innerHTML = '<i class="ti ti-apple"></i>Thêm thực phẩm';
            ['foodName','foodCalories','foodProtein','foodCarb','foodFat','foodType','foodKeywords','foodImage'].forEach(id => $(id).value = '');
            setSelectValue('foodUnit', 'Gram', 'Gram');
            setSelectValue('foodWeight', '100', '100');
            setSelectValue('foodType', 'Khac', 'Khac');
            clearFormErrors($('foodEditor'));
        }
        function fillFoodForm(food) {
            state.editingFoodId = food.ID;
            $('foodEditorTitle').innerHTML = '<i class="ti ti-apple"></i>Sửa thực phẩm';
            $('foodName').value = food.Ten || '';
            setSelectValue('foodUnit', food.DonVi || 'Gram', 'Gram');
            $('foodCalories').value = food.Calo ?? '';
            $('foodProtein').value = food.Protein ?? '';
            $('foodCarb').value = food.Carb ?? '';
            $('foodFat').value = food.ChatBeo ?? '';
            setSelectValue('foodWeight', String(food.KhoiLuongGram ?? 100), '100');
            setSelectValue('foodType', food.LoaiThucPham || 'Khac', 'Khac');
            $('foodKeywords').value = food.Keywords || '';
            $('foodImage').value = food.hinh_anh || food.HinhAnh || '';
            clearFormErrors($('foodEditor'));
            $('foodName').focus();
        }
        function resetMedicineForm() {
            state.editingMedicineId = null;
            $('medicineEditorTitle').innerHTML = '<i class="ti ti-pill"></i>Thêm thuốc';
            ['medicineName','medicineActive','medicineStrength','medicineGroup','medicineDesc','medicineImage'].forEach(id => $(id).value = '');
            setSelectValue('medicineGroup', 'Khac', 'Khac');
            clearFormErrors($('medicineEditor'));
        }
        function fillMedicineForm(medicine) {
            state.editingMedicineId = medicine.id || medicine.ID;
            $('medicineEditorTitle').innerHTML = '<i class="ti ti-pill"></i>Sửa thuốc';
            $('medicineName').value = medicine.ten_thuoc || medicine.TenThuoc || '';
            $('medicineActive').value = medicine.hoat_chat || medicine.HoatChat || '';
            $('medicineStrength').value = medicine.ham_luong_goc || medicine.lieu_luong_goc || medicine.LieuLuong || '';
            setSelectValue('medicineGroup', medicine.nhom_thuoc || medicine.NhomThuoc || 'Khac', 'Khac');
            $('medicineDesc').value = medicine.mo_ta || medicine.MoTa || '';
            $('medicineImage').value = medicine.hinh_anh || '';
            clearFormErrors($('medicineEditor'));
            $('medicineName').focus();
        }
        function resetActivityForm() {
            ['activityName','activityMet','activityDesc','activityImage'].forEach(id => $(id).value = '');
            clearFormErrors($('activityEditor'));
        }

        $('resetHealthConfigBtn').addEventListener('click', () => fillSystemConfigForm({
            ...state.systemConfig,
            nguong_sut_can: defaultSystemConfig().nguong_sut_can,
            so_ngay_theo_doi: defaultSystemConfig().so_ngay_theo_doi,
            nuoc_toi_thieu: defaultSystemConfig().nuoc_toi_thieu,
        }));
        $('resetSystemConfigBtn').addEventListener('click', () => fillSystemConfigForm({
            ...state.systemConfig,
            che_do_bao_tri: defaultSystemConfig().che_do_bao_tri,
            so_ngay_xoa_log: defaultSystemConfig().so_ngay_xoa_log,
        }));
        $('healthThresholdForm').addEventListener('submit', async event => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = $('saveHealthConfigBtn');
            await withSubmitLock(button, 'Đang lưu...', async () => {
                clearFormErrors(form);
                try {
                    const result = await getJson('/api/admin/system-config', {
                        method: 'PUT',
                        body: JSON.stringify(healthConfigPayload()),
                    });
                    fillSystemConfigForm(result.data || {});
                    showToast(result.message || 'Đã lưu ngưỡng cảnh báo');
                    await loadStats();
                } catch (error) {
                    showFormErrors(form, error);
                    showToast(error.message || 'Không thể lưu ngưỡng cảnh báo');
                }
            });
        });
        $('systemConfigForm').addEventListener('submit', async event => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = $('saveSystemConfigBtn');
            await withSubmitLock(button, 'Đang lưu...', async () => {
                clearFormErrors(form);
                try {
                    const result = await getJson('/api/admin/system-config', {
                        method: 'PUT',
                        body: JSON.stringify(systemConfigPayload()),
                    });
                    fillSystemConfigForm(result.data || {});
                    const pruned = Number(result.pruned_logs || 0);
                    showToast(pruned > 0 ? `Đã lưu cấu hình và xóa ${number(pruned)} log cũ` : (result.message || 'Đã lưu cấu hình hệ thống'));
                } catch (error) {
                    showFormErrors(form, error);
                    showToast(error.message || 'Không thể lưu cấu hình hệ thống');
                }
            });
        });
        $('globalSearch').addEventListener('input', event => {
            clearTimeout(state.globalSearchTimer);
            state.globalSearchTimer = setTimeout(() => {
                runGlobalSearch(event.target.value).catch(error => globalSearchEmpty(error.message));
            }, 260);
        });
        $('globalSearch').addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                hideGlobalSearchResults();
                event.currentTarget.blur();
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                const first = $('globalSearchResults')?.querySelector('[data-global-target]');
                if (first) openGlobalSearchResult(first).catch(error => showToast(error.message));
            }
        });
        $('globalSearchResults').addEventListener('click', event => {
            const result = event.target.closest('[data-global-target]');
            if (result) openGlobalSearchResult(result).catch(error => showToast(error.message));
        });
        $('topbarNotifications').addEventListener('click', openTopbarNotifications);
        $('topbarSettings').addEventListener('click', () => showView('settings'));
        $('topbarAvatar').addEventListener('click', event => {
            event.stopPropagation();
            toggleProfileMenu();
        });
        $('logoutForm').addEventListener('submit', event => {
            event.preventDefault();
            confirmLogout();
        });
        document.querySelectorAll('[data-logout-trigger]').forEach(btn => btn.addEventListener('click', confirmLogout));
        document.querySelectorAll('[data-view-shortcut]').forEach(btn => btn.addEventListener('click', () => {
            closeProfileMenu();
            showView(btn.dataset.viewShortcut);
        }));
        document.addEventListener('click', event => {
            if (!event.target.closest('.topbar-profile')) closeProfileMenu();
            if (!event.target.closest('.topbar-search')) hideGlobalSearchResults();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeProfileMenu();
        });
        document.querySelectorAll('[data-view-link]').forEach(link => link.addEventListener('click', () => showView(link.dataset.viewLink)));
        window.addEventListener('hashchange', () => showView(window.location.hash.replace('#', '')));
        $('reloadBtn').addEventListener('click', reloadAll);
        $('settingsRefreshBtn').addEventListener('click', loadSystemConfig);
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
            const retryDashboard = event.target.closest('[data-retry-dashboard]');
            const statCard = event.target.closest('[data-stat-view]');
            const profileBtn = event.target.closest('[data-profile]');
            const noticeBtn = event.target.closest('[data-notice-user]');
            const openAlertsBtn = event.target.closest('[data-open-alerts]');
            const riskBtn = event.target.closest('[data-risk-filter]');
            const toggleBtn = event.target.closest('[data-toggle]');
            const handleAlertBtn = event.target.closest('[data-handle-alert]');
            if (retryDashboard) {
                await reloadAll();
                return;
            }
            if (statCard) {
                const view = statCard.dataset.statView;
                let filters = {};
                try { filters = JSON.parse(statCard.dataset.statFilters || '{}'); } catch (_) { filters = {}; }
                if (view === 'users') {
                    state.status = filters.status && filters.status !== 'all' ? filters.status : '';
                    if ($('statusFilter')) $('statusFilter').value = state.status;
                    await loadAccounts();
                }
                if (view === 'notifications') {
                    state.noticeRead = filters.read || 'all';
                    if ($('noticeReadFilter')) $('noticeReadFilter').value = state.noticeRead;
                    renderNoticeLists();
                }
                if (view === 'alerts') {
                    state.alertFilter = filters.status === 'open' ? 'open' : 'all';
                    if ($('alertFilter')) $('alertFilter').value = state.alertFilter;
                    renderAlertTable();
                }
                if (view === 'foods') {
                    state.foodQ = '';
                    state.foods = [];
                }
                if (view === 'medicines') {
                    state.medicineQ = '';
                    state.medicines = [];
                }
                showView(view || 'dashboard');
            }
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
                    const id = alert.eventId || alert.reviewKey || alert.id;
                    const defaultType = alert.source === 'he_thong_canh_bao'
                        ? 'he_thong_canh_bao'
                        : (alert.eventId ? 'risk_event' : 'generated_alert');
                    const reviewAction = alert.reviewAction || {};
                    const result = await getJson(reviewAction.url || `/api/admin/read/${encodeURIComponent(id)}`, {
                        method: reviewAction.method || 'PUT',
                        body: JSON.stringify(reviewAction.body || {
                            type: defaultType,
                            user_id: alert.userId || null,
                            title: alert.title || '',
                        }),
                    });
                    showToast(result.message || 'Đã đánh dấu cảnh báo là đã xử lý');
                    await loadStats();
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
                const noticeId = Number(id);
                if (deleteButton) {
                    state.notifications = state.notifications.filter(item => Number(item.id) !== noticeId);
                } else {
                    state.notifications = state.notifications.map(item => Number(item.id) === noticeId
                        ? { ...item, is_read: true }
                        : item);
                }
                const fallbackUnread = state.notifications.filter(item => !item.is_read).length;
                syncNotificationBadges(result?.meta?.unread ?? fallbackUnread);
                renderNoticeLists();
                showToast(result.message || 'Đã cập nhật thông báo');
                await loadStats();
            });
        }
        $('noticeList').addEventListener('click', handleNoticeClick);
        $('noticeListPage').addEventListener('click', handleNoticeClick);

        $('saveFoodBtn').addEventListener('click', async event => {
            const button = event.currentTarget;
            const form = $('foodEditor');
            await withSubmitLock(button, 'Đang lưu...', async () => {
                clearFormErrors(form);
                try {
                    const body = new FormData();
                    appendIfPresent(body, 'ten_thuc_pham', $('foodName').value.trim());
                    appendIfPresent(body, 'loai_thuc_pham', $('foodType').value);
                    appendIfPresent(body, 'calo_goc', $('foodCalories').value || 0);
                    appendIfPresent(body, 'thanh_phan', $('foodKeywords').value.trim());
                    appendIfPresent(body, 'hinh_anh', $('foodImage').value.trim());
                    const url = state.editingFoodId ? `/api/admin/foods/${state.editingFoodId}` : '/api/admin/foods';
                    if (state.editingFoodId) body.append('_method', 'PUT');
                    const result = await getJson(url, { method: 'POST', body });
                    showToast(result.message || (state.editingFoodId ? 'Cập nhật thành công!' : 'Thêm thành công!'));
                    resetFoodForm();
                    state.foods = [];
                    await loadFoods();
                    await loadStats();
                } catch (error) {
                    showFormErrors(form, error);
                    showToast(error.message || 'Không thể lưu thực phẩm');
                }
            });
        });
        $('saveMedicineBtn').addEventListener('click', async event => {
            const button = event.currentTarget;
            const form = $('medicineEditor');
            await withSubmitLock(button, 'Đang lưu...', async () => {
                clearFormErrors(form);
                try {
                    const body = {
                        ten_thuoc: $('medicineName').value.trim(),
                        hoat_chat: $('medicineActive').value.trim(),
                        ham_luong_goc: $('medicineStrength').value.trim(),
                        nhom_thuoc: $('medicineGroup').value,
                        loai_thuoc: $('medicineGroup').value,
                        mo_ta: $('medicineDesc').value.trim() || null,
                    };
                    const imageValue = $('medicineImage').value.trim();
                    if (imageValue) body.hinh_anh = imageValue;
                    const url = state.editingMedicineId ? `/api/admin/medicines/${state.editingMedicineId}` : '/api/admin/thuoc';
                    const method = state.editingMedicineId ? 'PUT' : 'POST';
                    const result = await getJson(url, { method, body: JSON.stringify(body) });
                    showToast(state.editingMedicineId ? 'Cập nhật thành công!' : 'Thêm thành công!');
                    resetMedicineForm();
                    state.medicines = [];
                    await loadMedicines();
                    await loadStats();
                } catch (error) {
                    showFormErrors(form, error);
                    showToast(error.message || 'Không thể lưu thuốc');
                }
            });
        });
        $('saveActivityBtn').addEventListener('click', async event => {
            const button = event.currentTarget;
            const form = $('activityEditor');
            await withSubmitLock(button, 'Đang lưu...', async () => {
                clearFormErrors(form);
                try {
                    const body = new FormData();
                    appendIfPresent(body, 'ten_hoat_dong', $('activityName').value.trim());
                    appendIfPresent(body, 'mo_ta', $('activityDesc').value.trim());
                    appendIfPresent(body, 'chi_so_met', $('activityMet').value);
                    appendIfPresent(body, 'hinh_anh', $('activityImage').value.trim());
                    const result = await getJson('/api/admin/hoat-dong', { method: 'POST', body });
                    showToast('Thêm thành công!');
                    resetActivityForm();
                    state.activities = [];
                    await loadActivities();
                    await loadStats();
                } catch (error) {
                    showFormErrors(form, error);
                    showToast(error.message || 'Không thể lưu hoạt động');
                }
            });
        });
        $('resetFoodBtn').addEventListener('click', resetFoodForm);
        $('resetMedicineBtn').addEventListener('click', resetMedicineForm);
        $('resetActivityBtn').addEventListener('click', resetActivityForm);
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
        $('activityRows').addEventListener('click', event => {
            const del = event.target.closest('[data-delete-activity]');
            if (del) openConfirm('Bạn có chắc muốn xóa hoạt động này?', async () => {
                const result = await getJson(`/api/admin/hoat-dong/${del.dataset.deleteActivity}`, { method: 'DELETE' });
                showToast(result.message || 'Đã xóa hoạt động');
                state.activities = [];
                await loadActivities();
                await loadStats();
            });
        });

        hydrateChoiceFields();
        hydrateAdminProfile();
        resetFoodForm();
        resetMedicineForm();
        resetActivityForm();
        showView(window.location.hash.replace('#', '') || 'dashboard');
        reloadAll();
    </script>
</body>
</html>
<?php /**PATH C:\Users\HP\Downloads\Allproblems\Allproblems\caiduoicuavande\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>

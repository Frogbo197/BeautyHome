<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - Trợ lý sức khỏe</title>
    <style>
        :root {
            --ink: #243044;
            --muted: #6f7c91;
            --line: #e7edf7;
            --bg: #f7fbff;
            --white: #ffffff;
            --mint: #cff4df;
            --mint-strong: #48ad7d;
            --rose: #ffd7e1;
            --rose-strong: #e77391;
            --lavender: #ded9ff;
            --lavender-strong: #7c70d4;
            --sky: #d8f0ff;
            --sky-strong: #529fc9;
            --peach: #ffe2c7;
            --shadow: 0 20px 52px rgba(86, 105, 139, .15);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            background:
                linear-gradient(135deg, rgba(222, 217, 255, .62), transparent 30%),
                linear-gradient(230deg, rgba(207, 244, 223, .72), transparent 36%),
                var(--bg);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        button, input, select, textarea { font: inherit; }
        button { cursor: pointer; }
        a { color: inherit; }

        .shell {
            display: grid;
            grid-template-columns: 260px minmax(0, 1fr);
            min-height: 100vh;
        }
        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            padding: 26px 20px;
            background: rgba(255, 255, 255, .72);
            border-right: 1px solid rgba(231, 237, 247, .9);
            backdrop-filter: blur(16px);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
        }
        .brand-mark {
            display: grid;
            width: 46px;
            height: 46px;
            place-items: center;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--mint), var(--lavender));
            color: #36516f;
            font-weight: 850;
            box-shadow: 0 12px 28px rgba(124, 112, 212, .2);
        }
        .brand strong { display: block; line-height: 1.15; }
        .small, .brand span, .nav-label { color: var(--muted); font-size: 13px; }
        .nav { display: grid; gap: 8px; }
        .nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 42px;
            padding: 0 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
        }
        .nav a:hover, .nav a.active {
            background: var(--white);
            box-shadow: 0 10px 24px rgba(84, 104, 140, .12);
        }
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--mint-strong);
        }

        .main { padding: 30px; overflow: hidden; }
        .topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 22px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: clamp(27px, 4vw, 42px);
            line-height: 1;
            letter-spacing: 0;
        }
        h2 { margin: 0; font-size: 18px; letter-spacing: 0; }
        h3 { margin: 0; font-size: 15px; letter-spacing: 0; }

        .grid { display: grid; gap: 16px; }
        [data-view-panel][hidden] { display: none !important; }
        .overview { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .layout { grid-template-columns: minmax(0, 1.45fr) minmax(330px, .95fr); align-items: start; }
        .side-stack { display: grid; gap: 16px; }
        .card {
            border: 1px solid rgba(231, 237, 247, .92);
            border-radius: 8px;
            background: rgba(255, 255, 255, .85);
            box-shadow: var(--shadow);
        }
        .panel { padding: 18px; }
        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }
        .stat {
            position: relative;
            min-height: 132px;
            overflow: hidden;
            padding: 18px;
        }
        .stat::after {
            content: "";
            position: absolute;
            right: 0;
            bottom: -24px;
            width: 78px;
            height: 78px;
            border-radius: 50%;
            opacity: .68;
        }
        .stat.mint::after { background: var(--mint); }
        .stat.rose::after { background: var(--rose); }
        .stat.lavender::after { background: var(--lavender); }
        .stat.sky::after { background: var(--sky); }
        .stat-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .stat-value { margin: 10px 0 4px; font-size: 36px; font-weight: 850; }

        .btn {
            min-height: 40px;
            border: 0;
            border-radius: 8px;
            padding: 0 13px;
            color: var(--white);
            background: var(--ink);
            font-weight: 800;
            box-shadow: 0 12px 28px rgba(36, 48, 68, .16);
        }
        .btn.secondary {
            color: var(--ink);
            background: var(--white);
            border: 1px solid var(--line);
            box-shadow: none;
        }
        .btn.danger { background: var(--rose-strong); }
        .btn.good { background: var(--mint-strong); }
        .btn.small-btn { min-height: 32px; padding: 0 10px; font-size: 12px; }
        .btn:disabled { opacity: .55; cursor: wait; }

        .actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
        .filters { display: grid; grid-template-columns: minmax(180px, 1fr) 150px; gap: 10px; margin-bottom: 14px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .field {
            width: 100%;
            min-height: 40px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 0 12px;
            color: var(--ink);
            background: rgba(255, 255, 255, .94);
            outline: none;
        }
        textarea.field { min-height: 96px; padding-top: 10px; resize: vertical; }
        .field:focus {
            border-color: var(--lavender-strong);
            box-shadow: 0 0 0 4px rgba(124, 112, 212, .14);
        }
        .check-row { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 13px; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; min-width: 820px; border-collapse: collapse; }
        th, td { padding: 13px 12px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; }
        .resource-table { min-width: 620px; }
        .resource-editor {
            display: grid;
            gap: 10px;
            margin-bottom: 14px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(255, 255, 255, .62);
        }
        .resource-editor[hidden] { display: none !important; }

        .user-cell { display: flex; align-items: center; gap: 10px; }
        .avatar {
            flex: 0 0 auto;
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 8px;
            color: #36617b;
            background: var(--sky);
            font-weight: 850;
            overflow: hidden;
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 28px;
            border-radius: 999px;
            padding: 0 10px;
            font-size: 12px;
            font-weight: 850;
        }
        .badge.active { color: #267253; background: var(--mint); }
        .badge.locked { color: #a2415a; background: var(--rose); }
        .mini-stats { display: flex; flex-wrap: wrap; gap: 6px; }
        .mini-stats span {
            border-radius: 999px;
            background: #f3f6fb;
            padding: 6px 8px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 750;
        }

        .feature-list, .notice-list, .detail-list { display: grid; gap: 10px; }
        .feature-item, .notice-item, .detail-item {
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(255, 255, 255, .68);
        }
        .feature-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 10px;
        }
        .feature-value {
            display: grid;
            min-width: 54px;
            min-height: 38px;
            place-items: center;
            border-radius: 8px;
            background: var(--peach);
            font-weight: 850;
        }
        .notice-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 6px;
        }
        .bars {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            align-items: end;
            gap: 10px;
            height: 150px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(255, 255, 255, .58);
        }
        .bar-cell {
            display: grid;
            align-items: end;
            justify-items: center;
            gap: 8px;
            height: 100%;
            color: var(--muted);
            font-size: 12px;
        }
        .bar {
            width: 100%;
            min-height: 8px;
            border-radius: 8px 8px 3px 3px;
            background: linear-gradient(180deg, var(--lavender), var(--sky));
        }
        .empty, .loading { padding: 24px; text-align: center; color: var(--muted); }

        .drawer {
            position: fixed;
            inset: 0 0 0 auto;
            z-index: 10;
            display: grid;
            width: min(560px, 100vw);
            max-width: 100vw;
            grid-template-rows: auto minmax(0, 1fr);
            background: rgba(255, 255, 255, .96);
            border-left: 1px solid var(--line);
            box-shadow: -24px 0 60px rgba(36, 48, 68, .18);
            transform: translateX(102%);
            transition: transform .24s ease;
        }
        .drawer.open { transform: translateX(0); }
        .drawer-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 18px;
            border-bottom: 1px solid var(--line);
        }
        .drawer-body { overflow: auto; padding: 18px; }
        .drawer-section { margin-bottom: 18px; }
        .drawer-section h3 { margin-bottom: 10px; }
        .overlay {
            position: fixed;
            inset: 0;
            z-index: 9;
            display: none;
            background: rgba(36, 48, 68, .22);
        }
        .overlay.open { display: block; }

        .toast {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 20;
            max-width: min(420px, calc(100vw - 44px));
            padding: 14px 16px;
            border-radius: 8px;
            color: var(--white);
            background: var(--ink);
            box-shadow: var(--shadow);
            opacity: 0;
            pointer-events: none;
            transform: translateY(18px);
            transition: .22s ease;
        }
        .toast.show { opacity: 1; transform: translateY(0); }

        @media (max-width: 1120px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar { display: none; }
            .overview, .layout { grid-template-columns: 1fr 1fr; }
            .layout > .card:first-child { grid-column: 1 / -1; }
        }
        @media (max-width: 760px) {
            .main { padding: 18px; }
            .topbar, .panel-head { display: grid; }
            .actions, .btn { width: 100%; }
            .overview, .layout, .filters, .form-grid { grid-template-columns: 1fr; }
            .drawer { width: 100vw; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">AI</div>
                <div>
                    <strong>Admin Health</strong>
                    <span>Trợ lý sức khỏe</span>
                </div>
            </div>
            <div class="nav-label">Quản trị</div>
            <nav class="nav" aria-label="Admin">
                <a href="#overview" class="active" data-view-link="overview"><span class="dot"></span>Tổng quan</a>
                <a href="#accounts" data-view-link="accounts"><span class="dot" style="background: var(--rose-strong)"></span>Tài khoản</a>
                <a href="#sendNotice" data-view-link="sendNotice"><span class="dot" style="background: var(--lavender-strong)"></span>Gửi thông báo</a>
                <a href="#resources" data-view-link="resources"><span class="dot" style="background: var(--sky-strong)"></span>Dữ liệu</a>
            </nav>
        </aside>

        <main class="main">
            <section class="topbar">
                <div>
                    <h1>Quản trị hệ thống</h1>
                    <div class="small" id="generatedAt">Đang tải dữ liệu...</div>
                </div>
                <div class="actions">
                    <button class="btn secondary" type="button" id="reloadBtn">Làm mới</button>
                    <a class="btn secondary" href="/api/admin/stats" target="_blank" rel="noreferrer" style="display:inline-grid;place-items:center;text-decoration:none">API</a>
                </div>
            </section>

            <section class="grid overview" id="overview" data-view-panel="overview">
                <div class="loading card">Đang tải tổng quan...</div>
            </section>

            <section class="grid layout">
                <div class="card panel" id="accounts" data-view-panel="accounts" hidden>
                    <div class="panel-head">
                        <div>
                            <h2>Tài khoản người dùng</h2>
                            <div class="small" id="accountMeta">Khóa/mở, xem chi tiết, sửa hồ sơ</div>
                        </div>
                    </div>
                    <div class="filters">
                        <input class="field" id="searchInput" type="search" placeholder="Tìm tên hoặc email">
                        <select class="field" id="statusFilter" aria-label="Trạng thái tài khoản">
                            <option value="">Tất cả</option>
                            <option value="active">Đang hoạt động</option>
                            <option value="locked">Đã khóa</option>
                        </select>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Người dùng</th>
                                    <th>Trạng thái</th>
                                    <th>Hoạt động</th>
                                    <th>Đăng nhập</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="accountRows">
                                <tr><td colspan="5" class="loading">Đang tải tài khoản...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="side-stack" data-view-panel="sendNotice" hidden>
                    <section class="card panel" id="sendNotice">
                        <div class="panel-head">
                            <div>
                                <h2>Gửi thông báo</h2>
                                <div class="small">Gửi cho một tài khoản hoặc toàn bộ người dùng</div>
                            </div>
                        </div>
                        <div class="form-grid">
                            <input class="field" id="noticeUserId" type="number" min="1" placeholder="ID người nhận">
                            <input class="field" id="noticeType" type="text" placeholder="Loại: Health, System..." value="HeThong">
                        </div>
                        <textarea class="field" id="noticeContent" style="margin-top:10px" placeholder="Nội dung thông báo"></textarea>
                        <div class="panel-head" style="margin:10px 0 0">
                            <label class="check-row"><input id="noticeAll" type="checkbox"> Gửi tất cả</label>
                            <button class="btn good" type="button" id="sendNoticeBtn">Gửi</button>
                        </div>
                    </section>

                    <section class="card panel" id="notifications">
                        <div class="panel-head">
                            <div>
                                <h2>Thông báo gần đây</h2>
                                <div class="small" id="noticeMeta">Theo bảng thongbao</div>
                            </div>
                        </div>
                        <div id="notificationSummary" class="mini-stats"></div>
                        <div class="notice-list" id="noticeList" style="margin-top:14px"></div>
                    </section>

                    <section class="card panel">
                        <div class="panel-head">
                            <div>
                                <h2>7 ngày gần đây</h2>
                                <div class="small">Tài khoản mới và thông báo</div>
                            </div>
                        </div>
                        <div class="bars" id="weeklyBars"></div>
                    </section>
                </div>
            </section>

            <section class="grid layout" style="margin-top:16px">
                <section class="card panel" id="resources" data-view-panel="resources" hidden>
                    <div class="panel-head">
                        <div>
                            <h2>Dữ liệu chức năng</h2>
                            <div class="small">Xem nhanh thực phẩm, thuốc, nhắc nhở, điểm sức khỏe</div>
                        </div>
                        <select class="field" id="resourceType" style="max-width:190px">
                            <option value="foods">Thực phẩm</option>
                            <option value="medicines">Thuốc</option>
                            <option value="reminders">Nhắc nhở</option>
                            <option value="scores">Điểm sức khỏe</option>
                        </select>
                    </div>
                    <div class="resource-editor" id="foodEditor">
                        <h3 id="foodEditorTitle">Thêm thực phẩm</h3>
                        <div class="form-grid">
                            <input class="field" id="foodName" type="text" placeholder="Tên thực phẩm">
                            <input class="field" id="foodUnit" type="text" placeholder="Đơn vị" value="Gram">
                            <input class="field" id="foodCalories" type="number" min="0" step="0.1" placeholder="Calo / 100g">
                            <input class="field" id="foodProtein" type="number" min="0" step="0.1" placeholder="Protein">
                            <input class="field" id="foodCarb" type="number" min="0" step="0.1" placeholder="Carb">
                            <input class="field" id="foodFat" type="number" min="0" step="0.1" placeholder="Chất béo">
                            <input class="field" id="foodWeight" type="number" min="0" step="0.1" placeholder="Khối lượng gram" value="100">
                            <input class="field" id="foodType" type="text" placeholder="Nhóm thực phẩm">
                            <input class="field" id="foodKeywords" type="text" placeholder="Từ khóa tìm kiếm">
                            <select class="field" id="foodHealthy">
                                <option value="1">Lành mạnh</option>
                                <option value="0">Hạn chế</option>
                            </select>
                        </div>
                        <div class="actions" style="justify-content:flex-start">
                            <button class="btn good" id="saveFoodBtn" type="button">Lưu thực phẩm</button>
                            <button class="btn secondary" id="resetFoodBtn" type="button">Nhập mới</button>
                        </div>
                    </div>
                    <div class="resource-editor" id="medicineEditor" hidden>
                        <h3 id="medicineEditorTitle">Thêm thuốc</h3>
                        <input class="field" id="medicineName" type="text" placeholder="Tên thuốc">
                        <div class="form-grid">
                            <input class="field" id="medicineDose" type="text" placeholder="Liều lượng">
                            <input class="field" id="medicineUnit" type="text" placeholder="Đơn vị">
                            <input class="field" id="medicineTimes" type="number" min="0" step="1" placeholder="Số lần/ngày">
                            <input class="field" id="medicineActive" type="text" placeholder="Hoạt chất">
                            <input class="field" id="medicineGroup" type="text" placeholder="Nhóm thuốc">
                            <input class="field" id="medicineStatus" type="text" placeholder="Trạng thái" value="chua_den">
                        </div>
                        <textarea class="field" id="medicineDesc" placeholder="Mô tả"></textarea>
                        <textarea class="field" id="medicineSideEffect" placeholder="Tác dụng phụ"></textarea>
                        <textarea class="field" id="medicineWarning" placeholder="Cảnh báo"></textarea>
                        <textarea class="field" id="medicineNote" placeholder="Ghi chú"></textarea>
                        <div class="actions" style="justify-content:flex-start">
                            <button class="btn good" id="saveMedicineBtn" type="button">Lưu thuốc</button>
                            <button class="btn secondary" id="resetMedicineBtn" type="button">Nhập mới</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="resource-table">
                            <thead id="resourceHead"></thead>
                            <tbody id="resourceRows">
                                <tr><td class="loading">Đang tải dữ liệu...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="card panel" id="features" data-view-panel="overview">
                    <div class="panel-head">
                        <div>
                            <h2>Thông tin chức năng</h2>
                            <div class="small">Tổng hợp theo từng module</div>
                        </div>
                    </div>
                    <div class="feature-list" id="featureList"></div>
                </section>
            </section>
        </main>
    </div>

    <div class="overlay" id="overlay"></div>
    <aside class="drawer" id="drawer" aria-label="Chi tiết tài khoản">
        <div class="drawer-head">
            <div>
                <h2 id="drawerTitle">Chi tiết tài khoản</h2>
                <div class="small" id="drawerSubtitle"></div>
            </div>
            <button class="btn secondary small-btn" type="button" id="closeDrawer">Đóng</button>
        </div>
        <div class="drawer-body">
            <section class="drawer-section">
                <h3>Sửa hồ sơ</h3>
                <div class="form-grid">
                    <input class="field" id="editEmail" type="email" placeholder="Email">
                    <input class="field" id="editName" type="text" placeholder="Tên">
                    <select class="field" id="editGender">
                        <option value="">Giới tính</option>
                        <option value="Nam">Nam</option>
                        <option value="Nữ">Nữ</option>
                        <option value="Khác">Khác</option>
                    </select>
                    <input class="field" id="editBirthday" type="date">
                    <input class="field" id="editHeight" type="number" min="0" placeholder="Chiều cao cm">
                    <input class="field" id="editWeight" type="number" min="0" placeholder="Cân nặng kg">
                </div>
                <input class="field" id="editAvatar" type="text" placeholder="Link ảnh đại diện" style="margin-top:10px">
                <div class="panel-head" style="margin:10px 0 0">
                    <label class="check-row"><input id="editActive" type="checkbox"> Tài khoản hoạt động</label>
                    <button class="btn" id="saveAccountBtn" type="button">Lưu hồ sơ</button>
                </div>
            </section>

            <section class="drawer-section">
                <h3>Đặt lại mật khẩu</h3>
                <div class="form-grid">
                    <input class="field" id="newPassword" type="text" placeholder="Mật khẩu mới, tối thiểu 6 ký tự">
                    <button class="btn danger" id="resetPasswordBtn" type="button">Reset mật khẩu</button>
                </div>
            </section>

            <section class="drawer-section">
                <h3>Cập nhật chế độ</h3>
                <div class="form-grid">
                    <input class="field" id="modeGoals" type="text" placeholder="Mục tiêu, cách nhau bằng dấu phẩy">
                    <select class="field" id="modeActivity">
                        <option value="">Mức vận động</option>
                        <option value="Ít vận động">Ít vận động</option>
                        <option value="Trung bình">Trung bình</option>
                        <option value="Cao">Cao</option>
                    </select>
                    <select class="field" id="modeDiet">
                        <option value="">Chế độ ăn</option>
                        <option value="Eat Clean">Eat Clean</option>
                        <option value="Giảm cân">Giảm cân</option>
                        <option value="Tăng cơ">Tăng cơ</option>
                        <option value="Ăn chay">Ăn chay</option>
                        <option value="Bình thường">Bình thường</option>
                    </select>
                    <input class="field" id="modeWaterGoal" type="number" min="0" placeholder="Mục tiêu nước ml/ngày">
                </div>
                <button class="btn good" id="saveModeBtn" type="button" style="margin-top:10px">Lưu chế độ</button>
            </section>

            <section class="drawer-section">
                <h3>Tổng quan người dùng</h3>
                <div class="mini-stats" id="drawerStats"></div>
            </section>

            <section class="drawer-section">
                <h3>Sức khỏe</h3>
                <div class="detail-list" id="healthDetails"></div>
            </section>

            <section class="drawer-section">
                <h3>Hoạt động gần đây</h3>
                <div class="detail-list" id="recentDetails"></div>
            </section>
        </div>
    </aside>

    <div class="toast" id="toast"></div>

    <script>
        const state = { q: '', status: '', loadingAccount: false, selectedAccount: null, editingFoodId: null, editingMedicineId: null };
        const adminUserId = localStorage.getItem('admin_user_id') || localStorage.getItem('user_id') || '1';
        const overviewEl = document.getElementById('overview');
        const accountRowsEl = document.getElementById('accountRows');
        const accountMetaEl = document.getElementById('accountMeta');
        const toastEl = document.getElementById('toast');
        const drawerEl = document.getElementById('drawer');
        const overlayEl = document.getElementById('overlay');

        const resourceColumns = {
            foods: ['ID', 'Ten', 'Calo', 'Protein', 'Carb', 'ChatBeo', 'DonVi', 'KhoiLuongGram', 'LoaiThucPham', 'IsHealthy'],
            medicines: ['ID', 'TenThuoc', 'LieuLuong', 'DonVi', 'SoLanMoiNgay', 'HoatChat', 'NhomThuoc', 'TrangThai'],
            reminders: ['ID', 'NguoiDungID', 'LoaiDoiTuong', 'DoiTuongId', 'ThoiGian', 'LapLai', 'NgayTrongTuan', 'TrangThai'],
            scores: ['ID', 'NguoiDungID', 'Diem', 'NgayTinh', 'NhanXetAI'],
        };

        function number(value) {
            return new Intl.NumberFormat('vi-VN').format(Number(value || 0));
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function dateTime(value) {
            if (!value) return 'Chưa có';
            const parsed = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(parsed.getTime())) return escapeHtml(value);
            return parsed.toLocaleString('vi-VN');
        }

        function initials(name, email) {
            const text = (name && name !== 'Chưa cập nhật' ? name : email || 'U').trim();
            return escapeHtml(text.charAt(0).toUpperCase());
        }

        function showToast(message) {
            toastEl.textContent = message;
            toastEl.classList.add('show');
            clearTimeout(showToast.timer);
            showToast.timer = setTimeout(() => toastEl.classList.remove('show'), 3000);
        }

        function showView(view) {
            const safeView = ['overview', 'accounts', 'sendNotice', 'resources'].includes(view)
                ? view
                : 'overview';

            document.querySelectorAll('[data-view-panel]').forEach(panel => {
                panel.hidden = panel.dataset.viewPanel !== safeView;
            });

            document.querySelectorAll('[data-view-link]').forEach(link => {
                link.classList.toggle('active', link.dataset.viewLink === safeView);
            });

            const hash = `#${safeView}`;
            if (window.location.hash !== hash) {
                history.pushState(null, '', hash);
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        async function getJson(url, options = {}) {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
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

        function renderOverview(items) {
            overviewEl.innerHTML = items.map(item => `
                <article class="card stat ${escapeHtml(item.tone || 'mint')}">
                    <div class="stat-label">${escapeHtml(item.label)}</div>
                    <div class="stat-value">${number(item.value)}</div>
                    <div class="small">${escapeHtml(item.note || '')}</div>
                </article>
            `).join('');
        }

        function renderFeatures(items) {
            document.getElementById('featureList').innerHTML = items.map(item => `
                <div class="feature-item">
                    <div>
                        <strong>${escapeHtml(item.label)}</strong>
                        <div class="small">${escapeHtml(item.note || '')}</div>
                    </div>
                    <div class="feature-value">${number(item.value)}</div>
                </div>
            `).join('');
        }

        function renderNotifications(data) {
            document.getElementById('notificationSummary').innerHTML = `
                <span>Tổng: ${number(data.total)}</span>
                <span>Chưa đọc: ${number(data.unread)}</span>
                <span>Đã đọc: ${number(data.read)}</span>
                <span>Hôm nay: ${number(data.today)}</span>
            `;

            const notices = data.recent || [];
            document.getElementById('noticeList').innerHTML = notices.length ? notices.map(item => `
                <article class="notice-item">
                    <div class="notice-meta">
                        <strong>${escapeHtml(item.type)}</strong>
                        <span>${item.is_read ? 'Đã đọc' : 'Chưa đọc'}</span>
                    </div>
                    <div>${escapeHtml(item.content || 'Không có nội dung')}</div>
                    <div class="small">${escapeHtml(item.user)} · ${dateTime(item.time)}</div>
                    <div class="actions" style="justify-content:flex-start;margin-top:8px">
                        <button class="btn secondary small-btn" data-read-notice="${item.id}" type="button">Đã đọc</button>
                        <button class="btn danger small-btn" data-delete-notice="${item.id}" type="button">Xóa</button>
                    </div>
                </article>
            `).join('') : '<div class="empty">Chưa có thông báo nào.</div>';
        }

        function renderWeekly(days) {
            const max = Math.max(1, ...days.map(day => Math.max(day.accounts, day.notifications)));
            document.getElementById('weeklyBars').innerHTML = days.map(day => {
                const height = Math.max(8, Math.round((Math.max(day.accounts, day.notifications) / max) * 100));
                return `
                    <div class="bar-cell" title="${day.accounts} tài khoản, ${day.notifications} thông báo">
                        <div class="bar" style="height:${height}%"></div>
                        <span>${escapeHtml(day.label)}</span>
                    </div>
                `;
            }).join('');
        }

        function renderAccounts(payload) {
            const rows = payload.data || [];
            accountMetaEl.textContent = `${number(payload.meta?.total || 0)} tài khoản trong hệ thống`;

            if (!rows.length) {
                accountRowsEl.innerHTML = '<tr><td colspan="5" class="empty">Không tìm thấy tài khoản phù hợp.</td></tr>';
                return;
            }

            accountRowsEl.innerHTML = rows.map(account => `
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="avatar">${account.avatar ? `<img src="${escapeHtml(account.avatar)}" alt="">` : initials(account.name, account.email)}</div>
                            <div>
                                <strong>${escapeHtml(account.name)}</strong>
                                <div class="small">${escapeHtml(account.email)}</div>
                                <div class="small">ID: ${account.id}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge ${account.is_active ? 'active' : 'locked'}">${escapeHtml(account.status)}</span></td>
                    <td>
                        <div class="mini-stats">
                            <span>TB ${number(account.stats.notifications)}</span>
                            <span>Chưa đọc ${number(account.stats.unread_notifications)}</span>
                            <span>Bữa ${number(account.stats.meals)}</span>
                            <span>Nước ${number(account.stats.water_logs)}</span>
                            <span>Thuốc ${number(account.stats.medicines)}</span>
                            <span>Tập ${number(account.stats.activities)}</span>
                        </div>
                    </td>
                    <td>
                        <div>${dateTime(account.last_login)}</div>
                        <div class="small">Tạo: ${dateTime(account.created_at)}</div>
                    </td>
                    <td>
                        <div class="actions" style="justify-content:flex-start">
                            <button class="btn secondary small-btn" data-detail="${account.id}" type="button">Chi tiết</button>
                            <button class="btn ${account.is_active ? 'danger' : 'good'} small-btn" data-toggle="${account.id}" data-locked="${account.is_active ? '1' : '0'}" type="button">
                                ${account.is_active ? 'Khóa' : 'Mở'}
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
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

        function fillAccountForm(account) {
            document.getElementById('drawerTitle').textContent = account.name;
            document.getElementById('drawerSubtitle').textContent = `${account.email} · ID ${account.id}`;
            document.getElementById('editEmail').value = account.email || '';
            document.getElementById('editName').value = account.name === 'Chưa cập nhật' ? '' : account.name || '';
            document.getElementById('editGender').value = account.gender || '';
            document.getElementById('editBirthday').value = account.birthday || '';
            document.getElementById('editHeight').value = account.height || '';
            document.getElementById('editWeight').value = account.weight || '';
            document.getElementById('editAvatar').value = account.avatar || '';
            document.getElementById('editActive').checked = Boolean(account.is_active);
            document.getElementById('noticeUserId').value = account.id;
        }

        function renderDrawerStats(account) {
            document.getElementById('drawerStats').innerHTML = `
                <span>Thông báo ${number(account.stats.notifications)}</span>
                <span>Chưa đọc ${number(account.stats.unread_notifications)}</span>
                <span>Bữa ăn ${number(account.stats.meals)}</span>
                <span>Nước ${number(account.stats.water_logs)}</span>
                <span>Thuốc ${number(account.stats.medicines)}</span>
                <span>Tập ${number(account.stats.activities)}</span>
            `;
        }

        function fillModeForm(data) {
            const preferences = data.health?.preferences || {};
            const goals = data.health?.goals || [];
            const waterGoal = goals.find(goal => goal.LoaiMucTieu === 'Nuoc' || goal.TenMucTieu === 'Uống nước');
            const goalNames = preferences.MucTieu || goals
                .filter(goal => goal.LoaiMucTieu !== 'Nuoc')
                .map(goal => goal.TenMucTieu)
                .filter(Boolean)
                .join(', ');

            document.getElementById('modeGoals').value = goalNames || '';
            document.getElementById('modeActivity').value = preferences.MucDoVanDong || '';
            document.getElementById('modeDiet').value = preferences.CheDoAn || '';
            document.getElementById('modeWaterGoal').value = waterGoal?.GiaTriMucTieu || '';
        }

        function detailLine(title, body) {
            return `<div class="detail-item"><strong>${escapeHtml(title)}</strong><div class="small">${escapeHtml(body || 'Chưa có dữ liệu')}</div></div>`;
        }

        function renderAccountDetail(data) {
            const account = data.account;
            state.selectedAccount = account;
            fillAccountForm(account);
            fillModeForm(data);
            renderDrawerStats(account);

            const score = data.health?.latest_score;
            const index = data.health?.latest_index;
            const healthProfile = data.health?.profile;
            document.getElementById('healthDetails').innerHTML = [
                detailLine('Điểm sức khỏe', score ? `${score.Diem || 0} điểm · ${score.NgayTinh || ''} · ${score.NhanXetAI || ''}` : ''),
                detailLine('Chỉ số mới nhất', index ? `BMI ${index.BMI || 'N/A'}, cân nặng ${index.CanNang || 'N/A'}, HA ${index.HuyetAp || 'N/A'}, nhịp tim ${index.NhipTim || 'N/A'}` : ''),
                detailLine('Hồ sơ sức khỏe', healthProfile ? `Máu ${healthProfile.NhomMau || 'N/A'}, bệnh nền ${healthProfile.BenhNen || 'N/A'}, thể trạng ${healthProfile.TheTrang || 'N/A'}` : ''),
            ].join('');

            const recent = data.recent || {};
            document.getElementById('recentDetails').innerHTML = [
                detailLine('Bữa ăn', (recent.meals || []).map(item => `${item.Ngay || ''} ${item.LoaiBuaAn || ''} (${item.SoMon || 0} món, ${number(item.TongCalo)} kcal)`).join(' | ')),
                detailLine('Nước', (recent.water || []).map(item => `${item.Ngay || ''}: ${number(item.LuongNuoc)} ml`).join(' | ')),
                detailLine('Thuốc', (recent.medicines || []).map(item => `${item.TenThuoc || 'Thuốc'} ${item.LieuLuong || ''} - ${item.TrangThai || ''}`).join(' | ')),
                detailLine('Vận động', (recent.activities || []).map(item => `${item.TenHoatDong || 'Hoạt động'} ${item.TrangThai || ''}`).join(' | ')),
            ].join('');
        }

        function renderResources(payload) {
            const columns = resourceColumns[payload.type] || [];
            document.getElementById('resourceHead').innerHTML = `<tr>${columns.map(col => `<th>${escapeHtml(col)}</th>`).join('')}<th>Thao tác</th></tr>`;
            const rows = payload.data || [];
            document.getElementById('resourceRows').innerHTML = rows.length ? rows.map(row => `
                <tr>
                    ${columns.map(col => `<td>${escapeHtml(row[col] ?? '')}</td>`).join('')}
                    <td>${resourceActions(payload.type, row)}</td>
                </tr>
            `).join('') : `<tr><td colspan="${(columns.length || 1) + 1}" class="empty">Chưa có dữ liệu.</td></tr>`;
            syncResourceEditors(payload.type);
        }

        function resourceActions(type, row) {
            if (type === 'foods') {
                return `
                    <div class="actions" style="justify-content:flex-start">
                        <button class="btn secondary small-btn" data-edit-food='${escapeHtml(JSON.stringify(row))}' type="button">Sửa</button>
                        <button class="btn danger small-btn" data-delete-food="${row.ID}" type="button">Xóa</button>
                    </div>
                `;
            }

            if (type === 'medicines') {
                return `
                    <div class="actions" style="justify-content:flex-start">
                        <button class="btn secondary small-btn" data-edit-medicine='${escapeHtml(JSON.stringify(row))}' type="button">Sửa</button>
                        <button class="btn danger small-btn" data-delete-medicine="${row.ID}" type="button">Xóa</button>
                    </div>
                `;
            }

            return '<span class="small">Chỉ xem</span>';
        }

        function syncResourceEditors(type) {
            document.getElementById('foodEditor').hidden = type !== 'foods';
            document.getElementById('medicineEditor').hidden = type !== 'medicines';
        }

        function resetFoodForm() {
            state.editingFoodId = null;
            document.getElementById('foodEditorTitle').textContent = 'Thêm thực phẩm';
            document.getElementById('foodName').value = '';
            document.getElementById('foodUnit').value = 'Gram';
            document.getElementById('foodCalories').value = '';
            document.getElementById('foodProtein').value = '';
            document.getElementById('foodCarb').value = '';
            document.getElementById('foodFat').value = '';
            document.getElementById('foodWeight').value = '100';
            document.getElementById('foodType').value = '';
            document.getElementById('foodKeywords').value = '';
            document.getElementById('foodHealthy').value = '1';
        }

        function fillFoodForm(food) {
            state.editingFoodId = food.ID;
            document.getElementById('foodEditorTitle').textContent = `Sửa thực phẩm #${food.ID}`;
            document.getElementById('foodName').value = food.Ten || '';
            document.getElementById('foodUnit').value = food.DonVi || 'Gram';
            document.getElementById('foodCalories').value = food.Calo ?? '';
            document.getElementById('foodProtein').value = food.Protein ?? '';
            document.getElementById('foodCarb').value = food.Carb ?? '';
            document.getElementById('foodFat').value = food.ChatBeo ?? '';
            document.getElementById('foodWeight').value = food.KhoiLuongGram ?? 100;
            document.getElementById('foodType').value = food.LoaiThucPham || '';
            document.getElementById('foodKeywords').value = food.Keywords || '';
            document.getElementById('foodHealthy').value = String(food.IsHealthy ?? 1);
        }

        function resetMedicineForm() {
            state.editingMedicineId = null;
            document.getElementById('medicineEditorTitle').textContent = 'Thêm thuốc';
            document.getElementById('medicineName').value = '';
            document.getElementById('medicineDose').value = '';
            document.getElementById('medicineUnit').value = '';
            document.getElementById('medicineTimes').value = '';
            document.getElementById('medicineActive').value = '';
            document.getElementById('medicineGroup').value = '';
            document.getElementById('medicineStatus').value = 'chua_den';
            document.getElementById('medicineDesc').value = '';
            document.getElementById('medicineSideEffect').value = '';
            document.getElementById('medicineWarning').value = '';
            document.getElementById('medicineNote').value = '';
        }

        function fillMedicineForm(medicine) {
            state.editingMedicineId = medicine.ID;
            document.getElementById('medicineEditorTitle').textContent = `Sửa thuốc #${medicine.ID}`;
            document.getElementById('medicineName').value = medicine.TenThuoc || '';
            document.getElementById('medicineDose').value = medicine.LieuLuong || '';
            document.getElementById('medicineUnit').value = medicine.DonVi || '';
            document.getElementById('medicineTimes').value = medicine.SoLanMoiNgay ?? '';
            document.getElementById('medicineActive').value = medicine.HoatChat || '';
            document.getElementById('medicineGroup').value = medicine.NhomThuoc || '';
            document.getElementById('medicineStatus').value = medicine.TrangThai || 'chua_den';
            document.getElementById('medicineDesc').value = medicine.MoTa || '';
            document.getElementById('medicineSideEffect').value = medicine.TacDungPhu || '';
            document.getElementById('medicineWarning').value = medicine.CanhBao || '';
            document.getElementById('medicineNote').value = medicine.GhiChu || '';
        }

        async function loadStats() {
            const data = await getJson('/api/admin/stats');
            document.getElementById('generatedAt').textContent = `Cập nhật lúc ${dateTime(data.generated_at)}`;
            renderOverview(data.overview || []);
            renderFeatures(data.features || []);
            renderNotifications(data.notifications || {});
            renderWeekly(data.weekly || []);
        }

        async function loadAccounts() {
            if (state.loadingAccount) return;
            state.loadingAccount = true;
            accountRowsEl.innerHTML = '<tr><td colspan="5" class="loading">Đang tải tài khoản...</td></tr>';
            try {
                const params = new URLSearchParams({ per_page: '50' });
                if (state.q) params.set('q', state.q);
                if (state.status) params.set('status', state.status);
                renderAccounts(await getJson(`/api/admin/accounts?${params}`));
            } finally {
                state.loadingAccount = false;
            }
        }

        async function loadResources() {
            const type = document.getElementById('resourceType').value;
            renderResources(await getJson(`/api/admin/resources?type=${encodeURIComponent(type)}&limit=30`));
        }

        async function reloadAll() {
            try {
                await Promise.all([loadStats(), loadAccounts(), loadResources()]);
            } catch (error) {
                overviewEl.innerHTML = '<div class="card empty" style="grid-column:1/-1">Không tải được dữ liệu. Kiểm tra MySQL và database trolysuckhoe.</div>';
                accountRowsEl.innerHTML = '<tr><td colspan="5" class="empty">Không tải được danh sách tài khoản.</td></tr>';
                showToast(error.message);
            }
        }

        document.getElementById('reloadBtn').addEventListener('click', reloadAll);
        document.getElementById('closeDrawer').addEventListener('click', closeDrawer);
        overlayEl.addEventListener('click', closeDrawer);
        document.getElementById('resourceType').addEventListener('change', () => {
            resetFoodForm();
            resetMedicineForm();
            loadResources().catch(error => showToast(error.message));
        });
        document.getElementById('resetFoodBtn').addEventListener('click', resetFoodForm);
        document.getElementById('resetMedicineBtn').addEventListener('click', resetMedicineForm);

        document.getElementById('saveFoodBtn').addEventListener('click', async event => {
            event.target.disabled = true;
            try {
                const body = {
                    Ten: document.getElementById('foodName').value.trim(),
                    DonVi: document.getElementById('foodUnit').value.trim() || 'Gram',
                    Calo: document.getElementById('foodCalories').value || 0,
                    Protein: document.getElementById('foodProtein').value || 0,
                    Carb: document.getElementById('foodCarb').value || 0,
                    ChatBeo: document.getElementById('foodFat').value || 0,
                    KhoiLuongGram: document.getElementById('foodWeight').value || 100,
                    LoaiThucPham: document.getElementById('foodType').value.trim(),
                    Keywords: document.getElementById('foodKeywords').value.trim(),
                    IsHealthy: document.getElementById('foodHealthy').value === '1',
                };
                const url = state.editingFoodId ? `/api/admin/foods/${state.editingFoodId}` : '/api/admin/foods';
                const method = state.editingFoodId ? 'PATCH' : 'POST';
                const result = await getJson(url, { method, body: JSON.stringify(body) });
                showToast(result.message || 'Đã lưu thực phẩm');
                resetFoodForm();
                await Promise.all([loadResources(), loadStats()]);
            } catch (error) {
                showToast(error.message);
            } finally {
                event.target.disabled = false;
            }
        });

        document.getElementById('saveMedicineBtn').addEventListener('click', async event => {
            event.target.disabled = true;
            try {
                const body = {
                    TenThuoc: document.getElementById('medicineName').value.trim(),
                    MoTa: document.getElementById('medicineDesc').value.trim(),
                    TacDungPhu: document.getElementById('medicineSideEffect').value.trim(),
                    LieuLuong: document.getElementById('medicineDose').value.trim(),
                    DonVi: document.getElementById('medicineUnit').value.trim(),
                    SoLanMoiNgay: document.getElementById('medicineTimes').value || null,
                    HoatChat: document.getElementById('medicineActive').value.trim(),
                    NhomThuoc: document.getElementById('medicineGroup').value.trim(),
                    TrangThai: document.getElementById('medicineStatus').value.trim() || 'chua_den',
                    CanhBao: document.getElementById('medicineWarning').value.trim(),
                    GhiChu: document.getElementById('medicineNote').value.trim(),
                };
                const url = state.editingMedicineId ? `/api/admin/medicines/${state.editingMedicineId}` : '/api/admin/medicines';
                const method = state.editingMedicineId ? 'PATCH' : 'POST';
                const result = await getJson(url, { method, body: JSON.stringify(body) });
                showToast(result.message || 'Đã lưu thuốc');
                resetMedicineForm();
                await Promise.all([loadResources(), loadStats()]);
            } catch (error) {
                showToast(error.message);
            } finally {
                event.target.disabled = false;
            }
        });

        document.querySelectorAll('[data-view-link]').forEach(link => {
            link.addEventListener('click', event => {
                event.preventDefault();
                showView(link.dataset.viewLink);
            });
        });

        window.addEventListener('hashchange', () => {
            showView(window.location.hash.replace('#', ''));
        });

        document.getElementById('searchInput').addEventListener('input', event => {
            state.q = event.target.value.trim();
            clearTimeout(loadAccounts.timer);
            loadAccounts.timer = setTimeout(() => loadAccounts().catch(error => showToast(error.message)), 260);
        });
        document.getElementById('statusFilter').addEventListener('change', event => {
            state.status = event.target.value;
            loadAccounts().catch(error => showToast(error.message));
        });

        document.getElementById('resourceRows').addEventListener('click', async event => {
            const editFood = event.target.closest('[data-edit-food]');
            const deleteFood = event.target.closest('[data-delete-food]');
            const editMedicine = event.target.closest('[data-edit-medicine]');
            const deleteMedicine = event.target.closest('[data-delete-medicine]');

            try {
                if (editFood) {
                    fillFoodForm(JSON.parse(editFood.dataset.editFood));
                    return;
                }
                if (editMedicine) {
                    fillMedicineForm(JSON.parse(editMedicine.dataset.editMedicine));
                    return;
                }
                if (deleteFood) {
                    const result = await getJson(`/api/admin/foods/${deleteFood.dataset.deleteFood}`, { method: 'DELETE' });
                    showToast(result.message || 'Đã xóa thực phẩm');
                    await Promise.all([loadResources(), loadStats()]);
                    return;
                }
                if (deleteMedicine) {
                    const result = await getJson(`/api/admin/medicines/${deleteMedicine.dataset.deleteMedicine}`, { method: 'DELETE' });
                    showToast(result.message || 'Đã xóa thuốc');
                    await Promise.all([loadResources(), loadStats()]);
                }
            } catch (error) {
                showToast(error.message);
            }
        });

        accountRowsEl.addEventListener('click', async event => {
            const detailButton = event.target.closest('[data-detail]');
            const toggleButton = event.target.closest('[data-toggle]');

            if (detailButton) {
                try {
                    const data = await getJson(`/api/admin/accounts/${detailButton.dataset.detail}`);
                    renderAccountDetail(data);
                    openDrawer();
                } catch (error) {
                    showToast(error.message);
                }
                return;
            }

            if (toggleButton) {
                toggleButton.disabled = true;
                try {
                    const id = toggleButton.dataset.toggle;
                    const locked = toggleButton.dataset.locked === '1';
                    const result = await getJson(`/api/admin/accounts/${id}/toggle`, {
                        method: 'PATCH',
                        body: JSON.stringify({ locked }),
                    });
                    showToast(result.message || 'Đã cập nhật tài khoản');
                    await reloadAll();
                } catch (error) {
                    showToast(error.message);
                } finally {
                    toggleButton.disabled = false;
                }
            }
        });

        document.getElementById('saveAccountBtn').addEventListener('click', async event => {
            if (!state.selectedAccount) return;
            event.target.disabled = true;
            try {
                const body = {
                    email: document.getElementById('editEmail').value.trim(),
                    name: document.getElementById('editName').value.trim(),
                    gender: document.getElementById('editGender').value,
                    birthday: document.getElementById('editBirthday').value,
                    height: document.getElementById('editHeight').value,
                    weight: document.getElementById('editWeight').value,
                    avatar: document.getElementById('editAvatar').value.trim(),
                    active: document.getElementById('editActive').checked,
                };
                const result = await getJson(`/api/admin/accounts/${state.selectedAccount.id}`, {
                    method: 'PATCH',
                    body: JSON.stringify(body),
                });
                state.selectedAccount = result.account;
                showToast(result.message || 'Đã lưu hồ sơ');
                await reloadAll();
            } catch (error) {
                showToast(error.message);
            } finally {
                event.target.disabled = false;
            }
        });

        document.getElementById('resetPasswordBtn').addEventListener('click', async event => {
            if (!state.selectedAccount) return;
            const password = document.getElementById('newPassword').value.trim();
            if (password.length < 6) {
                showToast('Mật khẩu cần tối thiểu 6 ký tự');
                return;
            }
            event.target.disabled = true;
            try {
                const result = await getJson(`/api/admin/accounts/${state.selectedAccount.id}/password`, {
                    method: 'PATCH',
                    body: JSON.stringify({ password }),
                });
                document.getElementById('newPassword').value = '';
                showToast(result.message || 'Đã reset mật khẩu');
            } catch (error) {
                showToast(error.message);
            } finally {
                event.target.disabled = false;
            }
        });

        document.getElementById('saveModeBtn').addEventListener('click', async event => {
            if (!state.selectedAccount) return;
            event.target.disabled = true;
            try {
                const body = {
                    goals: document.getElementById('modeGoals').value.trim(),
                    activity_level: document.getElementById('modeActivity').value,
                    diet_mode: document.getElementById('modeDiet').value,
                    water_goal: document.getElementById('modeWaterGoal').value || null,
                };
                const result = await getJson(`/api/admin/accounts/${state.selectedAccount.id}/mode`, {
                    method: 'PATCH',
                    body: JSON.stringify(body),
                });
                showToast(result.message || 'Đã lưu chế độ');
                const detail = await getJson(`/api/admin/accounts/${state.selectedAccount.id}`);
                renderAccountDetail(detail);
            } catch (error) {
                showToast(error.message);
            } finally {
                event.target.disabled = false;
            }
        });

        document.getElementById('sendNoticeBtn').addEventListener('click', async event => {
            event.target.disabled = true;
            try {
                const body = {
                    user_id: document.getElementById('noticeUserId').value || null,
                    send_all: document.getElementById('noticeAll').checked,
                    type: document.getElementById('noticeType').value.trim() || 'HeThong',
                    content: document.getElementById('noticeContent').value.trim(),
                };
                const result = await getJson('/api/admin/notifications', {
                    method: 'POST',
                    body: JSON.stringify(body),
                });
                document.getElementById('noticeContent').value = '';
                showToast(result.message || 'Đã gửi thông báo');
                await loadStats();
            } catch (error) {
                showToast(error.message);
            } finally {
                event.target.disabled = false;
            }
        });

        document.getElementById('noticeList').addEventListener('click', async event => {
            const readButton = event.target.closest('[data-read-notice]');
            const deleteButton = event.target.closest('[data-delete-notice]');
            if (!readButton && !deleteButton) return;

            const id = readButton?.dataset.readNotice || deleteButton?.dataset.deleteNotice;
            const method = deleteButton ? 'DELETE' : 'PATCH';
            const url = deleteButton ? `/api/admin/notifications/${id}` : `/api/admin/notifications/${id}/read`;

            try {
                const result = await getJson(url, { method });
                showToast(result.message || 'Đã cập nhật thông báo');
                await loadStats();
            } catch (error) {
                showToast(error.message);
            }
        });

        showView(window.location.hash.replace('#', '') || 'overview');
        reloadAll();
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="{{ $app_name }} — Warehouse Management REST API. Version {{ $app_version }}. Powering real-time stock, dispatch, inventory and more." />
    <title>{{ $app_name }} API</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --yellow: #FFF345;
            --cream:  #EAE6DA;
            --dark:   #111110;
            --mid:    #2C2C2A;
            --muted:  #6B6B65;
            --card-bg: rgba(255,255,255,0.55);
            --border:  rgba(0,0,0,0.08);
            --shadow:  0 8px 40px rgba(0,0,0,0.10);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--cream);
            color: var(--dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ─── ANIMATED BACKGROUND ─── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 10% -10%, rgba(255,243,69,0.28) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 90% 110%, rgba(255,243,69,0.18) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* ─── NOISE TEXTURE OVERLAY ─── */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
            opacity: 0.5;
        }

        .page-wrapper {
            position: relative;
            z-index: 1;
            max-width: 1140px;
            margin: 0 auto;
            padding: 0 24px 80px;
        }

        /* ─── NAVBAR ─── */
        nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 28px 0 0;
            margin-bottom: 80px;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-logo .dot {
            width: 34px;
            height: 34px;
            background: var(--yellow);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            box-shadow: 0 4px 14px rgba(255,243,69,0.45);
        }

        .nav-logo .brand {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--dark);
        }

        .nav-logo .brand span {
            color: var(--muted);
            font-weight: 500;
        }

        .nav-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.7);
            border: 1px solid var(--border);
            border-radius: 100px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
            backdrop-filter: blur(12px);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            animation: pulse-dot 2s ease-in-out infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.5; transform: scale(0.75); }
        }

        /* ─── HERO ─── */
        .hero {
            text-align: center;
            margin-bottom: 72px;
            animation: fadeUp 0.7s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--yellow);
            border-radius: 100px;
            padding: 6px 18px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--dark);
            margin-bottom: 28px;
            box-shadow: 0 4px 20px rgba(255,243,69,0.50);
        }

        h1 {
            font-size: clamp(40px, 6vw, 72px);
            font-weight: 900;
            letter-spacing: -2.5px;
            line-height: 1.05;
            color: var(--dark);
            margin-bottom: 20px;
        }

        h1 em {
            font-style: normal;
            background: linear-gradient(135deg, #b8a800 0%, #FFF345 40%, #e6d900 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-sub {
            font-size: 17px;
            color: var(--muted);
            font-weight: 400;
            max-width: 540px;
            margin: 0 auto 36px;
            line-height: 1.7;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 12px;
            padding: 13px 28px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.22s ease;
            border: none;
        }

        .btn-primary {
            background: var(--dark);
            color: var(--yellow);
            box-shadow: 0 6px 24px rgba(0,0,0,0.22);
        }

        .btn-primary:hover {
            background: var(--mid);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.28);
        }

        .btn-outline {
            background: rgba(255,255,255,0.65);
            color: var(--dark);
            border: 1px solid var(--border);
            backdrop-filter: blur(12px);
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.90);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.09);
        }

        /* ─── STATUS STRIP ─── */
        .status-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 72px;
            animation: fadeUp 0.7s 0.1s ease both;
        }

        .status-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px 24px;
            backdrop-filter: blur(16px);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.22s ease, box-shadow 0.22s ease;
        }

        .status-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        .status-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--yellow);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .status-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 4px;
        }

        .status-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark);
        }

        .badge-healthy {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            font-weight: 600;
            color: #16a34a;
        }

        .badge-loading {
            color: var(--muted);
            font-size: 13px;
            font-weight: 500;
        }

        /* ─── SECTION HEADER ─── */
        .section-header {
            margin-bottom: 28px;
        }

        .section-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .section-title {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.8px;
            color: var(--dark);
        }

        /* ─── MODULE GRID ─── */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
            margin-bottom: 72px;
            animation: fadeUp 0.7s 0.2s ease both;
        }

        .module-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            backdrop-filter: blur(16px);
            cursor: default;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .module-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,243,69,0.10) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .module-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(0,0,0,0.12);
            border-color: rgba(255,243,69,0.50);
        }

        .module-card:hover::before {
            opacity: 1;
        }

        .module-icon {
            font-size: 26px;
            margin-bottom: 14px;
            display: block;
        }

        .module-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 6px;
        }

        .module-desc {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 14px;
        }

        .module-methods {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .method-tag {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .tag-get    { background: rgba(34,197,94,0.14);  color: #16a34a; }
        .tag-post   { background: rgba(59,130,246,0.14); color: #1d4ed8; }
        .tag-put    { background: rgba(234,179,8,0.15);  color: #854d0e; }
        .tag-delete { background: rgba(239,68,68,0.14);  color: #dc2626; }

        /* ─── ENDPOINT SECTION ─── */
        .endpoint-section {
            margin-bottom: 72px;
            animation: fadeUp 0.7s 0.3s ease both;
        }

        .endpoint-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .endpoint-row {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            backdrop-filter: blur(12px);
            transition: all 0.22s ease;
        }

        .endpoint-row:hover {
            transform: translateX(4px);
            border-color: rgba(255,243,69,0.45);
            box-shadow: 0 6px 24px rgba(0,0,0,0.08);
        }

        .http-method {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.05em;
            padding: 4px 10px;
            border-radius: 7px;
            min-width: 50px;
            text-align: center;
            flex-shrink: 0;
        }

        .method-GET    { background: rgba(34,197,94,0.14);  color: #16a34a; }
        .method-POST   { background: rgba(59,130,246,0.14); color: #1d4ed8; }
        .method-PUT    { background: rgba(234,179,8,0.15);  color: #854d0e; }
        .method-DELETE { background: rgba(239,68,68,0.14);  color: #dc2626; }

        .endpoint-path {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
            flex: 1;
        }

        .endpoint-desc {
            font-size: 12px;
            color: var(--muted);
            font-weight: 400;
        }

        /* ─── FOOTER ─── */
        footer {
            border-top: 1px solid var(--border);
            padding-top: 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: gap;
            gap: 16px;
            animation: fadeUp 0.7s 0.4s ease both;
        }

        .footer-left {
            font-size: 13px;
            color: var(--muted);
        }

        .footer-left strong {
            color: var(--dark);
        }

        .footer-right {
            display: flex;
            gap: 10px;
        }

        .footer-badge {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 5px 12px;
            border-radius: 8px;
            background: rgba(0,0,0,0.06);
            color: var(--muted);
        }

        .footer-badge.env-production {
            background: rgba(239,68,68,0.10);
            color: #dc2626;
        }

        .footer-badge.env-local {
            background: rgba(34,197,94,0.12);
            color: #16a34a;
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 640px) {
            nav { margin-bottom: 52px; }
            .hero { margin-bottom: 52px; }
            .endpoint-row { flex-wrap: wrap; }
            .endpoint-desc { display: none; }
            footer { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="page-wrapper">

    <!-- NAVBAR -->
    <nav>
        <div class="nav-logo">
            <div class="dot">⚡</div>
            <div class="brand">{{ $app_name }} <span>API</span></div>
        </div>
        <div class="nav-pill">
            <span class="status-dot" id="nav-dot"></span>
            <span id="nav-status">Checking…</span>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-badge">⚙ REST API &nbsp;·&nbsp; v{{ $app_version }}</div>
        <h1>The <em>Warehouse</em><br>Management API</h1>
        <p class="hero-sub">
            A high-performance Laravel REST API powering stock management,
            batch tracking, dispatch workflows, and real-time inventory reporting.
        </p>
        <div class="hero-actions">
            <a href="{{ $app_url }}/health" class="btn btn-primary" target="_blank">
                🩺 Health Check
            </a>
            <a href="{{ $app_url }}/api/v1/login" class="btn btn-outline" target="_blank">
                🔐 Auth Endpoint
            </a>
        </div>
    </section>

    <!-- STATUS STRIP -->
    <div class="status-strip">
        <div class="status-card">
            <div class="status-icon">🗄</div>
            <div>
                <div class="status-label">Database</div>
                <div class="status-value" id="db-status"><span class="badge-loading">Loading…</span></div>
            </div>
        </div>
        <div class="status-card">
            <div class="status-icon">🌐</div>
            <div>
                <div class="status-label">API Server</div>
                <div class="status-value" id="server-status"><span class="badge-loading">Loading…</span></div>
            </div>
        </div>
        <div class="status-card">
            <div class="status-icon">🕐</div>
            <div>
                <div class="status-label">Server Time</div>
                <div class="status-value" id="server-time"><span class="badge-loading">Loading…</span></div>
            </div>
        </div>
        <div class="status-card">
            <div class="status-icon">📦</div>
            <div>
                <div class="status-label">API Prefix</div>
                <div class="status-value">/api/v1</div>
            </div>
        </div>
    </div>

    <!-- MODULES GRID -->
    <div class="section-header">
        <div class="section-label">System Modules</div>
        <div class="section-title">Everything in one API</div>
    </div>

    <div class="modules-grid">
        <div class="module-card">
            <span class="module-icon">🔐</span>
            <div class="module-name">Authentication</div>
            <div class="module-desc">JWT-based login and logout with IP-logged activity tracking.</div>
            <div class="module-methods">
                <span class="method-tag tag-post">POST</span>
            </div>
        </div>
        <div class="module-card">
            <span class="module-icon">👤</span>
            <div class="module-name">Users & Roles</div>
            <div class="module-desc">Multi-scope user management with Spatie permission-based access control.</div>
            <div class="module-methods">
                <span class="method-tag tag-get">GET</span>
                <span class="method-tag tag-post">POST</span>
                <span class="method-tag tag-put">PUT</span>
                <span class="method-tag tag-delete">DELETE</span>
            </div>
        </div>
        <div class="module-card">
            <span class="module-icon">🏭</span>
            <div class="module-name">Warehouses & Branches</div>
            <div class="module-desc">Manage multi-branch, multi-warehouse hierarchy and associations.</div>
            <div class="module-methods">
                <span class="method-tag tag-get">GET</span>
                <span class="method-tag tag-post">POST</span>
                <span class="method-tag tag-put">PUT</span>
            </div>
        </div>
        <div class="module-card">
            <span class="module-icon">📥</span>
            <div class="module-name">Stock In Batches</div>
            <div class="module-desc">Create and manage incoming stock batches with supplier and item details.</div>
            <div class="module-methods">
                <span class="method-tag tag-get">GET</span>
                <span class="method-tag tag-post">POST</span>
                <span class="method-tag tag-put">PUT</span>
                <span class="method-tag tag-delete">DELETE</span>
            </div>
        </div>
        <div class="module-card">
            <span class="module-icon">🛍</span>
            <div class="module-name">Stock Bags</div>
            <div class="module-desc">Individual bag tracking with scanned QR/Barcode support and weight records.</div>
            <div class="module-methods">
                <span class="method-tag tag-get">GET</span>
                <span class="method-tag tag-post">POST</span>
                <span class="method-tag tag-put">PUT</span>
                <span class="method-tag tag-delete">DELETE</span>
            </div>
        </div>
        <div class="module-card">
            <span class="module-icon">🔬</span>
            <div class="module-name">Quality Inspection</div>
            <div class="module-desc">Grade and inspect incoming stock with inspection logs per batch.</div>
            <div class="module-methods">
                <span class="method-tag tag-get">GET</span>
                <span class="method-tag tag-post">POST</span>
                <span class="method-tag tag-put">PUT</span>
            </div>
        </div>
        <div class="module-card">
            <span class="module-icon">🚚</span>
            <div class="module-name">Stock Dispatch</div>
            <div class="module-desc">Manage dispatch orders from warehouse to buyer with gate-exit confirmation.</div>
            <div class="module-methods">
                <span class="method-tag tag-get">GET</span>
                <span class="method-tag tag-post">POST</span>
                <span class="method-tag tag-put">PUT</span>
            </div>
        </div>
        <div class="module-card">
            <span class="module-icon">🧾</span>
            <div class="module-name">Invoices</div>
            <div class="module-desc">Generate and manage buyer invoices linked to dispatched stock.</div>
            <div class="module-methods">
                <span class="method-tag tag-get">GET</span>
                <span class="method-tag tag-post">POST</span>
                <span class="method-tag tag-put">PUT</span>
            </div>
        </div>
        <div class="module-card">
            <span class="module-icon">📊</span>
            <div class="module-name">Inventory Reports</div>
            <div class="module-desc">Real-time balance, valuation, aging, and low-stock alert reports.</div>
            <div class="module-methods">
                <span class="method-tag tag-get">GET</span>
            </div>
        </div>
        <div class="module-card">
            <span class="module-icon">🔳</span>
            <div class="module-name">Barcode Tokens</div>
            <div class="module-desc">Generate and verify unique barcode tokens for bag authentication.</div>
            <div class="module-methods">
                <span class="method-tag tag-get">GET</span>
                <span class="method-tag tag-post">POST</span>
            </div>
        </div>
        <div class="module-card">
            <span class="module-icon">📋</span>
            <div class="module-name">Activity Logs</div>
            <div class="module-desc">Comprehensive audit trail with IP, action, module, and payload logging.</div>
            <div class="module-methods">
                <span class="method-tag tag-get">GET</span>
            </div>
        </div>
        <div class="module-card">
            <span class="module-icon">💾</span>
            <div class="module-name">Database Export</div>
            <div class="module-desc">Authenticated MySQL dump export streamed as a downloadable SQL file.</div>
            <div class="module-methods">
                <span class="method-tag tag-get">GET</span>
            </div>
        </div>
    </div>

    <!-- KEY ENDPOINTS -->
    <div class="endpoint-section">
        <div class="section-header">
            <div class="section-label">Key Endpoints</div>
            <div class="section-title">Start exploring</div>
        </div>
        <div class="endpoint-list">
            <div class="endpoint-row">
                <span class="http-method method-POST">POST</span>
                <span class="endpoint-path">/api/v1/login</span>
                <span class="endpoint-desc">Authenticate and receive JWT token</span>
            </div>
            <div class="endpoint-row">
                <span class="http-method method-GET">GET</span>
                <span class="endpoint-path">/api/v1/me</span>
                <span class="endpoint-desc">Get authenticated user profile</span>
            </div>
            <div class="endpoint-row">
                <span class="http-method method-GET">GET</span>
                <span class="endpoint-path">/api/v1/stock-in-batches</span>
                <span class="endpoint-desc">List all stock in batches with filters</span>
            </div>
            <div class="endpoint-row">
                <span class="http-method method-POST">POST</span>
                <span class="endpoint-path">/api/v1/stock-bags</span>
                <span class="endpoint-desc">Create bags (single or bulk) with QR/Barcode</span>
            </div>
            <div class="endpoint-row">
                <span class="http-method method-GET">GET</span>
                <span class="endpoint-path">/api/v1/inventory-reports/balance</span>
                <span class="endpoint-desc">Live inventory balance report</span>
            </div>
            <div class="endpoint-row">
                <span class="http-method method-GET">GET</span>
                <span class="endpoint-path">/api/v1/activity-logs</span>
                <span class="endpoint-desc">Full activity audit log with search & filters</span>
            </div>
            <div class="endpoint-row">
                <span class="http-method method-GET">GET</span>
                <span class="endpoint-path">/api/v1/database/export</span>
                <span class="endpoint-desc">Download database SQL backup (protected)</span>
            </div>
            <div class="endpoint-row">
                <span class="http-method method-GET">GET</span>
                <span class="endpoint-path">/health</span>
                <span class="endpoint-desc">System health check — DB and server status</span>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="footer-left">
            &copy; {{ date('Y') }} <strong>{{ $app_name }}</strong> &mdash; Warehouse Management API
        </div>
        <div class="footer-right">
            <div class="footer-badge">v{{ $app_version }}</div>
            <div class="footer-badge env-{{ $app_env }}">{{ strtoupper($app_env) }}</div>
        </div>
    </footer>

</div><!-- end page-wrapper -->

<script>
    // Fetch health status on page load
    (async function () {
        try {
            const res  = await fetch('/health');
            const data = await res.json();
            const ok   = data.status === 'healthy';

            // Nav dot
            const dot = document.getElementById('nav-dot');
            dot.style.background = ok ? '#22c55e' : '#ef4444';
            document.getElementById('nav-status').textContent = ok ? 'All Systems Operational' : 'Degraded';

            // DB status
            const dbOk = data.components?.database === 'healthy';
            document.getElementById('db-status').innerHTML =
                dbOk ? '<span class="badge-healthy">✓ Connected</span>' : '⚠ Unhealthy';

            // Server status
            document.getElementById('server-status').innerHTML =
                ok ? '<span class="badge-healthy">✓ Online</span>' : '⚠ Degraded';

            // Time
            if (data.date && data.time) {
                document.getElementById('server-time').textContent = data.date + '  ' + data.time;
            }
        } catch (e) {
            document.getElementById('nav-dot').style.background = '#ef4444';
            document.getElementById('nav-status').textContent   = 'Unreachable';
            document.getElementById('db-status').textContent    = 'Unknown';
            document.getElementById('server-status').textContent = 'Offline';
            document.getElementById('server-time').textContent  = '—';
        }
    })();
</script>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Dashboard') — A &amp; J OASIS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ asset('favicon.ico') }}" rel="icon" sizes="any">
    <link href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏠</text></svg>" rel="icon" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html { -webkit-text-size-adjust: 100%; }
        body { background: #f4f5f7; }
        img { max-width: 100%; height: auto; }
        .table { white-space: nowrap; }
        .sidebar {
            width: 260px;
            flex-shrink: 0;
            background: #1a1d23;
            color: #c9cdd4;
            min-height: 100vh;
        }
        .sidebar .brand { color: #fff; font-weight: 600; letter-spacing: .02em; }
        .sidebar .nav-link { color: #c9cdd4; border-radius: .4rem; padding: .55rem .9rem; margin-bottom: .15rem; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,.06); color: #fff; }
        .sidebar .nav-link.active { background: #2e5ff2; color: #fff; }
        .sidebar .nav-link i { width: 1.25rem; }
        .app-shell { display: flex; min-height: 100vh; }
        .content-area { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .topbar { background: #fff; border-bottom: 1px solid #e5e7eb; }
        .btn:disabled { cursor: not-allowed; }

        /* Mobile / touch (iOS Safari especially) */
        a, button, .btn, .nav-link { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
        input, select, textarea, .form-control, .form-select { font-size: 1rem; } /* keeps iOS from auto-zooming on focus */
        .btn-sm { padding: .35rem .55rem; }
        .table td > .btn:not(:first-child),
        .table td > form:not(:first-child) { margin-left: .35rem; }
        .table-responsive { -webkit-overflow-scrolling: touch; }

        @media (max-width: 991.98px) {
            .sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: -260px;
                z-index: 1045;
                transition: left .2s ease;
                padding-left: max(1rem, env(safe-area-inset-left)) !important;
                padding-bottom: calc(1rem + env(safe-area-inset-bottom)) !important;
            }
            .sidebar.show { left: 0; }
            .sidebar-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1044; display: none; }
            .sidebar-backdrop.show { display: block; }
            .topbar { padding-top: calc(.5rem + env(safe-area-inset-top)) !important; }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
        <aside class="sidebar p-3" id="sidebar">
            <a href="{{ url('/') }}" class="d-flex align-items-center gap-2 text-decoration-none brand mb-4 px-1">
                <i class="bi bi-house-door-fill fs-4"></i>
                <span>A &amp; J OASIS</span>
            </a>

            <nav class="nav flex-column">
                @if (auth()->user()->isAdmin())
                    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                    <a class="nav-link {{ request()->routeIs('admin.tenants.*') ? 'active' : '' }}" href="{{ route('admin.tenants.index') }}"><i class="bi bi-people me-2"></i>Tenants</a>
                    <a class="nav-link {{ request()->routeIs('admin.properties.*') ? 'active' : '' }}" href="{{ route('admin.properties.index') }}"><i class="bi bi-buildings me-2"></i>Properties</a>
                    <a class="nav-link {{ request()->routeIs('admin.rooms.*') ? 'active' : '' }}" href="{{ route('admin.rooms.index') }}"><i class="bi bi-door-closed me-2"></i>Rooms</a>
                    <a class="nav-link {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}" href="{{ route('admin.bookings.index') }}"><i class="bi bi-calendar-check me-2"></i>Bookings</a>
                    <a class="nav-link {{ request()->routeIs('admin.leases.*') ? 'active' : '' }}" href="{{ route('admin.leases.index') }}"><i class="bi bi-file-earmark-text me-2"></i>Leases</a>
                    <a class="nav-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}"><i class="bi bi-credit-card me-2"></i>Payments</a>
                    <a class="nav-link {{ request()->routeIs('admin.utility-bills.*') ? 'active' : '' }}" href="{{ route('admin.utility-bills.index') }}"><i class="bi bi-lightning-charge me-2"></i>Utility Bills</a>
                    <a class="nav-link {{ request()->routeIs('admin.occupancy.*') ? 'active' : '' }}" href="{{ route('admin.occupancy.index') }}"><i class="bi bi-grid-3x3-gap me-2"></i>Occupancy</a>
                    <a class="nav-link position-relative {{ request()->routeIs('admin.room-transfers.*') ? 'active' : '' }}" href="{{ route('admin.room-transfers.index') }}">
                        <i class="bi bi-arrow-left-right me-2"></i>Transfer Req.
                        @if ($pendingTransferCount = \App\Models\RoomTransfer::where('status', 'pending')->count())
                            <span class="badge rounded-pill bg-danger ms-1">{{ $pendingTransferCount }}</span>
                        @endif
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.maintenance-requests.*') ? 'active' : '' }}" href="{{ route('admin.maintenance-requests.index') }}"><i class="bi bi-tools me-2"></i>Maintenance</a>
                    <a class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}" href="{{ route('admin.announcements.index') }}"><i class="bi bi-megaphone me-2"></i>Announce.</a>
                    <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}"><i class="bi bi-graph-up me-2"></i>Reports</a>
                    <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="bi bi-person-badge me-2"></i>Users</a>
                @elseif (auth()->user()->isStaff())
                    <a class="nav-link {{ request()->routeIs('staff.maintenance.*') ? 'active' : '' }}" href="{{ route('staff.maintenance.index') }}"><i class="bi bi-tools me-2"></i>Maintenance Queue</a>
                @endif

                <hr class="border-secondary my-2">
                <a class="nav-link {{ request()->routeIs('notifications.index') ? 'active' : '' }}" href="{{ route('notifications.index') }}">
                    <i class="bi bi-bell me-2"></i>Notifications
                    @php($unread = auth()->user()->notifications()->whereNull('read_at')->count())
                    @if ($unread)
                        <span class="badge rounded-pill bg-danger ms-1">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </a>
            </nav>
        </aside>

        <div class="content-area">
            <header class="topbar d-flex align-items-center justify-content-between px-3 py-2">
                <button class="btn btn-outline-secondary d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle navigation">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="ms-auto dropdown">
                    <a class="d-flex align-items-center gap-2 text-decoration-none text-dark dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        @if (auth()->user()->photoUrl())
                            <img src="{{ auth()->user()->photoUrl() }}" alt="{{ auth()->user()->name }}" class="rounded-circle" style="width: 28px; height: 28px; object-fit: cover;">
                        @else
                            <i class="bi bi-person-circle fs-5"></i>
                        @endif
                        <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear me-2"></i>My Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </header>

            <main class="container-fluid p-4">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <div>{{ session('status') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const toggle = document.getElementById('sidebarToggle');

        function closeSidebar() {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        }

        toggle?.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('show');
        });
        backdrop?.addEventListener('click', closeSidebar);

        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                const btn = e.submitter || form.querySelector('button[type=submit], button:not([type])');
                if (btn && !btn.disabled) {
                    // Deferred: disabling the submitter synchronously in the submit event strips its
                    // name/value from the submitted form data (the browser builds the entry list right
                    // after this event fires, while the button is already disabled).
                    setTimeout(function () {
                        btn.disabled = true;
                        btn.dataset.originalHtml = btn.innerHTML;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Please wait…';
                    }, 0);
                }
            });
        });
    </script>
</body>
</html>

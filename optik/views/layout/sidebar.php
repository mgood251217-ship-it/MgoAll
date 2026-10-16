

<div class="sidebar">
    <div class="sidebar-logo-container">
        <div class="sidebar-logo-icon">OP</div>
        <div class="sidebar-logo-text">Optik POS</div>
    </div>

    <a href="/dashboard" class="sidebar-item <?= ($module == 'dashboard') ? 'active' : ''; ?>">
        <div class="sidebar-icon">

            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3z"/>
            </svg>
        </div>
        <div class="sidebar-text">Dashboard</div>
    </a>

    <a href="/order" class="sidebar-item <?= ($module == 'order') ? 'active' : ''; ?>">
        <div class="sidebar-icon">

            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                <path d="M21 16V8l-9-5-9 5v8l9 5 9-5zM12 5.2L18.6 9 12 12.8 5.4 9 12 5.2zM5 10.5l6 3.3v6.5l-6-3.3v-6.5zm14 0v6.5l-6 3.3v-6.5l6-3.3z"/>
            </svg>
        </div>
        <div class="sidebar-text">Kasir Order</div>
    </a>

    <a href="/product" class="sidebar-item <?= ($module == 'product') ? 'active' : ''; ?>">
        <div class="sidebar-icon">

            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                <path d="M4 6h2v2H4V6zm4 0h12v2H8V6zM4 11h2v2H4v-2zm4 0h12v2H8v-2zM4 16h2v2H4v-2zm4 0h12v2H8v-2z"/>
            </svg>
        </div>
        <div class="sidebar-text">Produk</div>
    </a>

    <a href="/aruskas" class="sidebar-item <?= ($module == 'aruskas') ? 'active' : ''; ?>">
        <div class="sidebar-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                <path d="M6.99 11L3 15l3.99 4v-3H14v-2H6.99v-3zM21 9l-3.99-4v3H10v2h7.01v3L21 9z"/>
            </svg>
        </div>
        <div class="sidebar-text">Arus Kas</div>
    </a>

    <a href="/setting" class="sidebar-item <?= ($module == 'setting') ? 'active' : ''; ?>">
        <div class="sidebar-icon">

            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                <path d="M19.14 12.94a7.07 7.07 0 000-1.88l2.03-1.58-1.92-3.32-2.39.96a7.14 7.14 0 00-1.63-.95l-.36-2.54h-3.84l-.36 2.54a7.14 7.14 0 00-1.63.95l-2.39-.96-1.92 3.32 2.03 1.58a7.07 7.07 0 000 1.88l-2.03 1.58 1.92 3.32 2.39-.96c.5.39 1.05.71 1.63.95l.36 2.54h3.84l.36-2.54c.58-.24 1.13-.56 1.63-.95l2.39.96 1.92-3.32-2.03-1.58zM12 15.5A3.5 3.5 0 1112 8a3.5 3.5 0 010 7.5z"/>
            </svg>
        </div>
        <div class="sidebar-text">Pengaturan</div>
    </a>

    <div class="sidebar-bottom">

        <a href="/logout" class="sidebar-item sidebar-logout">
            <div class="sidebar-icon">

                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M10 17l5-5-5-5v3H3v4h7v3zM13 3h6a2 2 0 012 2v14a2 2 0 01-2 2h-6v-2h6V5h-6V3z"/>
                </svg>
            </div>
            <div class="sidebar-text">Logout</div>
        </a>

    </div>
</div>
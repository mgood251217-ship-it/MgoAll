<style>
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 76px;
        background-color: #0f172a;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        white-space: nowrap;
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        z-index: 1000;
        border-right: 1px solid #1e293b;
    }
    .sidebar:hover {
        width: 260px;
    }
    .sidebar-brand {
        height: 70px;
        display: flex;
        align-items: center;
        padding: 0 24px;
        color: #ffffff;
        border-bottom: 1px solid #1e293b;
    }
    .sidebar-brand i {
        min-width: 28px;
        font-size: 1.5rem;
        text-align: center;
        color: #3b82f6;
    }
    .sidebar-brand span {
        margin-left: 14px;
        font-weight: 600;
        font-size: 1.25rem;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .sidebar:hover .sidebar-brand span {
        opacity: 1;
        transition-delay: 0.1s;
    }
    .sidebar-menu {
        list-style: none;
        padding: 16px 12px;
        margin: 0;
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
    }
    .sidebar-menu::-webkit-scrollbar {
        width: 4px;
    }
    .sidebar-menu::-webkit-scrollbar-thumb {
        background-color: #334155;
        border-radius: 4px;
    }
    .sidebar-menu li {
        width: 100%;
        margin-bottom: 6px;
    }
    .sidebar-menu li a {
        display: flex;
        align-items: center;
        height: 48px;
        text-decoration: none;
        color: #94a3b8;
        padding: 0 14px;
        border-radius: 8px;
        transition: all 0.2s ease;
        position: relative;
    }
    .sidebar-menu li a:hover, 
    .sidebar-menu li a.active {
        background-color: #1e293b;
        color: #ffffff;
    }
    .sidebar-menu li a.active::before {
        content: '';
        position: absolute;
        left: -12px;
        top: 50%;
        transform: translateY(-50%);
        height: 24px;
        width: 4px;
        background-color: #3b82f6;
        border-radius: 0 4px 4px 0;
    }
    .sidebar-menu li a i {
        min-width: 24px;
        font-size: 1.25rem;
        text-align: center;
        transition: all 0.2s ease;
    }
    .sidebar-menu li a:hover i,
    .sidebar-menu li a.active i {
        transform: scale(1.1);
        color: #3b82f6;
    }
    .sidebar-menu li a span {
        margin-left: 16px;
        opacity: 0;
        transition: opacity 0.3s ease;
        font-size: 0.95rem;
        font-weight: 500;
    }
    .sidebar:hover .sidebar-menu li a span {
        opacity: 1;
        transition-delay: 0.1s;
    }
</style>

<div class="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-layer-group"></i>
        <span>App Center</span>
    </div>
    <ul class="sidebar-menu">
        <li><a href="/dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
        <li><a href="/orders"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a></li>
        <li><a href="/products"><i class="fas fa-box"></i> <span>Products</span></a></li>
        <li><a href="/transactions"><i class="fas fa-exchange-alt"></i> <span>Transactions</span></a></li>
        <li><a href="/stores"><i class="fas fa-store"></i> <span>Stores</span></a></li>
        <li><a href="/finance"><i class="fas fa-wallet"></i> <span>Finance</span></a></li>
        <li><a href="/piutang"><i class="fas fa-file-invoice-dollar"></i> <span>Piutang</span></a></li>
        <li><a href="/users"><i class="fas fa-users"></i> <span>Users</span></a></li>
        <li><a href="/productions"><i class="fas fa-industry"></i> <span>Productions</span></a></li>
        <li><a href="/analysis"><i class="fas fa-chart-line"></i> <span>Analysis</span></a></li>
        <li><a href="/setting"><i class="fas fa-cog"></i> <span>Setting</span></a></li>
    </ul>
</div>
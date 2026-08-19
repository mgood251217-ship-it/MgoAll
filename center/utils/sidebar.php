<style>
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 70px;
        background-color: #0f172a;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        white-space: nowrap;
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        z-index: 1000;
    }
    .sidebar:hover {
        width: 260px;
    }
    .sidebar-brand {
        height: 60px;
        display: flex;
        align-items: center;
        padding: 0 20px;
        color: #ffffff;
        font-size: 1.5rem;
        border-bottom: 1px solid #1e293b;
    }
    .sidebar-brand span {
        margin-left: 15px;
        font-weight: 600;
        font-size: 1.2rem;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .sidebar:hover .sidebar-brand span {
        opacity: 1;
        transition-delay: 0.1s;
    }
    .sidebar ul {
        list-style: none;
        padding: 12px;
        margin: 0;
        flex: 1;
    }
    .sidebar ul li {
        width: 100%;
        margin-bottom: 4px;
    }
    .sidebar ul li a {
        display: flex;
        align-items: center;
        height: 48px;
        text-decoration: none;
        color: #94a3b8;
        padding: 0 11px;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    .sidebar ul li a:hover {
        background-color: #1e293b;
        color: #ffffff;
    }
    .sidebar ul li a i {
        min-width: 24px;
        font-size: 1.25rem;
        text-align: center;
    }
    .sidebar ul li a span {
        margin-left: 16px;
        opacity: 0;
        transition: opacity 0.3s ease;
        font-size: 0.95rem;
        font-weight: 500;
    }
    .sidebar:hover ul li a span {
        opacity: 1;
        transition-delay: 0.1s;
    }
</style>
<div class="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-layer-group"></i>
        <span>App Center</span>
    </div>
    <ul>
        <li><a href="/dashboard"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
        <li><a href="/orders"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a></li>
        <li><a href="/stores"><i class="fas fa-store"></i> <span>Stores</span></a></li>
        <li><a href="/finance"><i class="fas fa-wallet"></i> <span>Finance</span></a></li>
        <li><a href="/users"><i class="fas fa-users"></i> <span>Users</span></a></li>
        <li><a href="/productions"><i class="fas fa-industry"></i> <span>Productions</span></a></li>
        <li><a href="/analysis"><i class="fas fa-chart-line"></i> <span>Analysis</span></a></li>
        <li><a href="/setting"><i class="fas fa-cog"></i> <span>Setting</span></a></li>
    </ul>
</div>
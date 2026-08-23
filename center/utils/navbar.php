<style>
    .navbar {
        background-color: #ffffff;
        color: #0f172a;
        padding: 0 24px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        position: sticky;
        top: 0;
        z-index: 99;
    }
    .navbar h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .btn-logout {
        background-color: #ef4444;
        color: white;
        text-decoration: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background-color 0.2s ease;
    }
    .btn-logout:hover {
        background-color: #dc2626;
    }
</style>
<div class="navbar">
    <h3>Overview</h3>
    <a href="/action?action=logout" class="btn-logout">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>
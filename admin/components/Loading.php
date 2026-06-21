<?php
function renderLoading() {
    ob_start();
    ?>
    <style>
    #globalLoadingOverlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background-color: rgba(15, 23, 42, 0.55) !important;
        backdrop-filter: blur(6px) !important;
        -webkit-backdrop-filter: blur(6px) !important;
        z-index: 999999 !important;
        display: none;
        overflow: hidden !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .loading-center-box {
        position: absolute !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        width: 100% !important;
    }

    .rocket-wrapper {
        position: relative;
        animation: rocket-float 2s ease-in-out infinite, rocket-shake 0.15s infinite;
        z-index: 10;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .rocket-svg {
        width: 90px;
        height: 90px;
        filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.2));
        z-index: 10;
        display: block;
        margin: 0 auto;
    }

    .fire-exhaust {
        position: absolute;
        bottom: -40px;
        left: 50%;
        transform: translateX(-50%);
        width: 26px;
        height: 60px;
        background: linear-gradient(to bottom, #fde047, #f97316, #ef4444, transparent);
        border-radius: 50% 50% 20% 20%;
        animation: fire-burn 0.08s infinite alternate;
        filter: blur(3px);
        z-index: 9;
    }

    @keyframes rocket-float {
        0%, 100% { margin-top: 0; }
        50% { margin-top: -15px; }
    }

    @keyframes rocket-shake {
        0% { margin-left: 0; }
        25% { margin-left: -1.5px; }
        50% { margin-left: 0; }
        75% { margin-left: 1.5px; }
        100% { margin-left: 0; }
    }

    @keyframes fire-burn {
        0% { height: 50px; opacity: 0.8; }
        100% { height: 70px; opacity: 1; }
    }

    .speed-line {
        position: absolute;
        width: 2px;
        background: linear-gradient(to bottom, transparent, rgba(255, 255, 255, 0.8), transparent);
        animation: speed-drop linear infinite;
        border-radius: 2px;
    }

    @keyframes speed-drop {
        0% { top: -20%; opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { top: 120%; opacity: 0; }
    }

    .loading-text {
        margin-top: 65px;
        color: #ffffff;
        font-family: system-ui, -apple-system, sans-serif;
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 4px;
        text-transform: uppercase;
        text-align: center !important;
        animation: pulse-text 1.5s infinite ease-in-out;
        z-index: 10;
        text-shadow: 0 2px 10px rgba(0,0,0,0.5);
        width: 100%;
    }

    @keyframes pulse-text {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    </style>

    <div id="globalLoadingOverlay">
        <div class="speed-line" style="left: 20%; height: 100px; animation-duration: 0.4s;"></div>
        <div class="speed-line" style="left: 35%; height: 140px; animation-duration: 0.7s; animation-delay: 0.2s;"></div>
        <div class="speed-line" style="left: 65%; height: 80px; animation-duration: 0.5s; animation-delay: 0.1s;"></div>
        <div class="speed-line" style="left: 80%; height: 120px; animation-duration: 0.6s; animation-delay: 0.3s;"></div>

        <div class="loading-center-box">
            <div class="rocket-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="rocket-svg">
                    <path fill="#e2e8f0" d="M256 24C160 136 144 296 144 408h224C368 296 352 136 256 24z"/>
                    <path fill="#ef4444" d="M144 320c-64 0-104 48-120 136h120V320zm224 0c64 0 104 48 120 136H368V320z"/>
                    <path fill="#94a3b8" d="M208 408h96v56h-96z"/>
                    <circle cx="256" cy="208" r="48" fill="#cbd5e1"/>
                    <circle cx="256" cy="208" r="32" fill="#3b82f6"/>
                    <path fill="#f8fafc" d="M256 24c0 0 56 48 72 136H184C200 72 256 24 256 24z"/>
                    <path fill="#cbd5e1" d="M256 24v136c-16 0-36-8-51-18C218 96 256 24 256 24z" opacity="0.5"/>
                </svg>
                <div class="fire-exhaust"></div>
            </div>
            
            <div class="loading-text">Memproses</div>
        </div>
    </div>

    <script>
    function showLoading() {
        const overlay = document.getElementById('globalLoadingOverlay');
        if (overlay) overlay.style.display = 'block';
    }

    function hideLoading() {
        const overlay = document.getElementById('globalLoadingOverlay');
        if (overlay) overlay.style.display = 'none';
    }
    </script>
    <?php
    return ob_get_clean();
}
?>
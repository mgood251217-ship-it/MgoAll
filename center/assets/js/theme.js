(() => {
    const key = 'mgo_center_theme';
    const root = document.documentElement;
    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
    const validTheme = value => value === 'dark' || value === 'light';
    let preference = null;

    try {
        const saved = localStorage.getItem(key);
        if (validTheme(saved)) preference = saved;
    } catch (_) {}

    const applyTheme = () => {
        const theme = preference || (systemTheme.matches ? 'dark' : 'light');
        root.dataset.theme = theme;
        root.dataset.bsTheme = theme;
        document.querySelectorAll('[data-theme-toggle]').forEach(button => {
            const label = theme === 'dark' ? 'Mode terang' : 'Mode gelap';
            button.setAttribute('aria-label', 'Gunakan ' + label.toLowerCase());
            button.title = 'Gunakan ' + label.toLowerCase();
            button.querySelector('[data-theme-label]').textContent = label;
        });
    };

    applyTheme();
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-theme-toggle]').forEach(button => {
            button.addEventListener('click', () => {
                preference = root.dataset.theme === 'dark' ? 'light' : 'dark';
                try { localStorage.setItem(key, preference); } catch (_) {}
                applyTheme();
            });
        });
        applyTheme();
    });
    systemTheme.addEventListener('change', () => { if (!preference) applyTheme(); });
    window.addEventListener('storage', event => {
        if (event.key !== key && event.key !== null) return;
        preference = validTheme(event.newValue) ? event.newValue : null;
        applyTheme();
    });
})();

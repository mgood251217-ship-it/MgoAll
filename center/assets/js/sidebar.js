document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    const groups = Array.from(sidebar.querySelectorAll('.sidebar-group'));
    const mobile = window.matchMedia('(max-width: 768px)');
    const closeGroups = () => groups.forEach(group => { group.open = false; });

    // Di layar kecil, submenu dibuka saat dipilih, bukan langsung menutupi halaman.
    if (mobile.matches) closeGroups();
    mobile.addEventListener('change', () => {
        closeGroups();
        if (!mobile.matches) {
            const active = sidebar.querySelector('.sidebar-group.has-active');
            if (active) active.open = true;
        }
    });
    groups.forEach(group => {
        group.addEventListener('toggle', () => {
            if (group.open) groups.forEach(other => { if (other !== group) other.open = false; });
        });
    });
    document.addEventListener('click', event => {
        if (mobile.matches && !sidebar.contains(event.target)) closeGroups();
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const open = groups.find(group => group.open);
        if (open) { open.open = false; open.querySelector('summary').focus(); }
    });
});

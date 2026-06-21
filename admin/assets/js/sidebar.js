  const sidebar = document.getElementById('sidebar-wrapper');

  sidebar.addEventListener('mouseenter', () => {
    sidebar.classList.add('hovered');
  });

  sidebar.addEventListener('mouseleave', () => {
    sidebar.classList.remove('hovered');
  });
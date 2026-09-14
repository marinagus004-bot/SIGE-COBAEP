document.addEventListener('DOMContentLoaded', () => {
    // Referencias del DOM
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const mobileMenu = document.getElementById('mobileMenu');
    const collapseToggle = document.getElementById('collapseToggle');
    const fechaTexto = document.getElementById('fechaTexto');
    const body = document.body;

    /* =======================================
       1. LÓGICA DE COLAPSO EN ESCRITORIO
       ======================================= */
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        body.classList.add('sidebar-collapsed');
    }

    if (collapseToggle) {
        collapseToggle.addEventListener('click', () => {
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
        });
    }

    /* =======================================
       2. LÓGICA DEL MENÚ MÓVIL
       ======================================= */
    function abrirMenu() {
        sidebar.classList.add('mobile-open');
        overlay.classList.add('visible');
        body.classList.add('menu-open');
    }

    function cerrarMenu() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('visible');
        body.classList.remove('menu-open');
    }

    if (mobileMenu) {
        mobileMenu.addEventListener('click', abrirMenu);
    }

    if (overlay) {
        overlay.addEventListener('click', cerrarMenu);
    }

    // Cierra el menú móvil si se agranda la pantalla
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            cerrarMenu();
        }
    });

    /* =======================================
       3. RELOJ EN TIEMPO REAL
       ======================================= */
    function actualizarHora() {
        if (!fechaTexto) return;

        const now = new Date();
        const opciones = {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };

        fechaTexto.textContent = now.toLocaleString('es-MX', opciones);
    }

    actualizarHora();
    setInterval(actualizarHora, 60000);
});
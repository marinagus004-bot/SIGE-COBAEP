/* =======================================================
   0. FIX DEL BRINCO (EJECUCIÓN INMEDIATA)
   ======================================================= */
// Al sacar esta validación del 'DOMContentLoaded', se ejecuta en el 
// milisegundo exacto en que carga la página, ANTES de dibujarla.
// Esto elimina por completo el "brinco" o parpadeo de la barra.
if (localStorage.getItem('sidebarCollapsed') === 'true') {
    // Agregamos la clase de inmediato.
    document.body.classList.add('sidebar-collapsed');
    
    // (Opcional) Congelamos las animaciones por 50ms para que 
    // la transición no se vea al cargar la página nueva.
    document.body.classList.add('preload-transitions');
    setTimeout(() => {
        document.body.classList.remove('preload-transitions');
    }, 50);
}

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
        if(sidebar) sidebar.classList.add('mobile-open');
        if(overlay) overlay.classList.add('visible');
        body.classList.add('menu-open');
    }

    function cerrarMenu() {
        if(sidebar) sidebar.classList.remove('mobile-open');
        if(overlay) overlay.classList.remove('visible');
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

    /* ==========================================
       4. LÓGICA PARA EL MENÚ DESPLEGABLE DEL PERFIL
       ========================================== */
    const btnPerfil = document.getElementById('btnPerfil');
    const menuPerfil = document.getElementById('menuPerfil');

    if (btnPerfil && menuPerfil) {
        // Mostrar/Ocultar al hacer clic en el nombre
        btnPerfil.addEventListener('click', (e) => {
            e.stopPropagation(); // Evita que el clic se propague al documento
            menuPerfil.classList.toggle('active');
        });

        // Cerrar el menú si el usuario hace clic en cualquier otra parte de la pantalla
        document.addEventListener('click', (e) => {
            if (!btnPerfil.contains(e.target) && !menuPerfil.contains(e.target)) {
                menuPerfil.classList.remove('active');
            }
        });
    }
});
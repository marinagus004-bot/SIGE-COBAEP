document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mobileMenu = document.getElementById('mobileMenu');
            const fechaTexto = document.getElementById('fechaTexto');
            const collapseToggle = document.getElementById('collapseToggle');
            const body = document.body;

            // --- LÓGICA DE COLAPSO EN ESCRITORIO ---
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                body.classList.add('sidebar-collapsed');
            }

            if (collapseToggle) {
                collapseToggle.addEventListener('click', () => {
                    body.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
                });
            }

            // --- LÓGICA DE MENÚ MÓVIL ---
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

            mobileMenu.addEventListener('click', abrirMenu);
            overlay.addEventListener('click', cerrarMenu);

            window.addEventListener('resize', () => {
                if (window.innerWidth > 900) {
                    cerrarMenu();
                }
            });

            // --- LÓGICA DE HORA ---
            function actualizarHora() {
                if (!fechaTexto) return;
                const now = new Date();
                fechaTexto.textContent = now.toLocaleString('es-MX', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                });
            }

            actualizarHora();
            setInterval(actualizarHora, 60000);
        });

        // --- LÓGICA DEL MODAL MANUAL ---
        const modal = document.getElementById('modalManual');
        
        function abrirModal() {
            modal.style.display = 'flex';
            document.getElementById('matricula_manual').focus();
        }

        function cerrarModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                cerrarModal();
            }
        }
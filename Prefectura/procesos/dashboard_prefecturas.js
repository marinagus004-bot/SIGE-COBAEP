function reproducirSonidoExito() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gainNode = ctx.createGain();

        osc.connect(gainNode);
        gainNode.connect(ctx.destination);

        // Onda senoidal para un sonido digital limpio
        osc.type = 'sine'; 

        // Primera nota (Sonido medio)
        osc.frequency.setValueAtTime(523.25, ctx.currentTime); 
        // Segunda nota (Salta rápido a un sonido agudo indicando éxito)
        osc.frequency.setValueAtTime(783.99, ctx.currentTime + 0.1); 

        // Control de volumen para simular dos pitidos (beep-beep)
        gainNode.gain.setValueAtTime(0, ctx.currentTime);
        // Volumen de la primera nota
        gainNode.gain.linearRampToValueAtTime(0.3, ctx.currentTime + 0.02); 
        gainNode.gain.setValueAtTime(0.3, ctx.currentTime + 0.08);          
        gainNode.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.1);    // Silencio rápido
        
        // Volumen de la segunda nota (se desvanece suavemente)
        gainNode.gain.linearRampToValueAtTime(0.4, ctx.currentTime + 0.12); 
        gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4); 

        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.4);
    } catch (e) {
        console.log("El navegador no soporta Web Audio API");
    }
}
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

    if (mobileMenu) mobileMenu.addEventListener('click', abrirMenu);
    if (overlay) overlay.addEventListener('click', cerrarMenu);

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

    // =========================================================
    // --- LÓGICA PARA ALERTAS Y MENSAJES (DESDE LA URL) ---
    // =========================================================
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    const retorno = urlParams.get('retorno');
    const detalle = urlParams.get('detalle');

    if (msg === 'alumno_suspendido') {
        const returnDateEl = document.getElementById('alertReturnDate');
        const alertModal = document.getElementById('alertModal');
        if (returnDateEl && alertModal) {
            returnDateEl.innerText = 'Podrá reingresar el: ' + retorno;
            alertModal.style.display = 'flex';
            window.history.replaceState(null, null, window.location.pathname);
        }
    } else if (msg === 'alumno_baja') {
        const returnDateEl = document.getElementById('alertReturnDate');
        const alertModal = document.getElementById('alertModal');
        if (returnDateEl && alertModal) {
            returnDateEl.innerText = 'El alumno fue dado de baja definitivamente.';
            alertModal.style.display = 'flex';
            window.history.replaceState(null, null, window.location.pathname);
        }
  } else if (msg === 'exito_entrada' || msg === 'exito_salida') {
        const modalExito = document.getElementById('modalExito');
        const exitoText = document.getElementById('exitoText'); // Busca el texto del modal
        
        if (modalExito) {
            // Cambia el texto dependiendo de la acción
            if (exitoText) {
                if (msg === 'exito_salida') {
                    exitoText.innerText = 'La SALIDA del alumno se ha registrado correctamente en el sistema.';
                } else {
                    exitoText.innerText = 'La ENTRADA del alumno se ha registrado correctamente en el sistema.';
                }
            }
            modalExito.style.display = 'flex';
            reproducirSonidoExito(); // Ejecuta el "beep" de éxito
            window.history.replaceState(null, null, window.location.pathname);
        }
    } else if (msg === 'error_manual') {
        const modalError = document.getElementById('modalError');
        const errorText = document.getElementById('errorText');
        if (modalError) {
            if (detalle && errorText) {
                errorText.innerText = detalle; // Muestra el mensaje del PHP
            }
            modalError.style.display = 'flex';
            window.history.replaceState(null, null, window.location.pathname);
        }
    }
});

// --- LÓGICA DEL MODAL MANUAL Y CIERRE DE MODALES AL HACER CLIC AFUERA ---
const modalManual = document.getElementById('modalManual');

function abrirModal() {
    if (modalManual) {
        modalManual.style.display = 'flex';
        const inputMatricula = document.getElementById('matricula_manual');
        if(inputMatricula) inputMatricula.focus();
    }
}

function cerrarModal() {
    if (modalManual) modalManual.style.display = 'none';
}

window.onclick = function(event) {
    // Cerramos el modal manual si se hace clic fuera de él
    if (event.target == modalManual) {
        cerrarModal();
    }
    
    // Lista de los otros modales (fondo oscuro)
    const alertModal = document.getElementById('alertModal');
    const modalExito = document.getElementById('modalExito');
    const modalError = document.getElementById('modalError');
    const modalSuspension = document.getElementById('modalSuspension');

    // Si el usuario hace clic en el fondo oscuro de cualquiera de ellos, se cierra
    if (event.target == alertModal) alertModal.style.display = 'none';
    if (event.target == modalExito) modalExito.style.display = 'none';
    if (event.target == modalError) modalError.style.display = 'none';
    if (event.target == modalSuspension) modalSuspension.style.display = 'none';
}
document.addEventListener("DOMContentLoaded", function () {
    
    // 1. Funcionalidad para mostrar/ocultar contraseña
    const togglePasswordBtn = document.getElementById("togglePasswordBtn");
    const passwordInput = document.getElementById("password");
    const toggleIcon = document.getElementById("toggleIcon");

    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener("click", function (e) {
            // Esta línea es clave: evita que el botón recargue la página por accidente
            e.preventDefault(); 
            
            // Alternar el tipo de input entre 'password' y 'text'
            const isPassword = passwordInput.getAttribute("type") === "password";
            passwordInput.setAttribute("type", isPassword ? "text" : "password");
            
            // Alternar el icono de FontAwesome
            if (isPassword) {
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            } else {
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            }
        });
    }

    // 1.1 "¿Olvidaste tu contraseña?": las contraseñas las restablece Control Escolar
    const forgotLink = document.getElementById("forgotLink");
    if (forgotLink) {
        forgotLink.addEventListener("click", function (e) {
            e.preventDefault();
            const previo = document.getElementById("infoToast");
            if (previo) previo.remove();
            const aviso = document.createElement("div");
            aviso.id = "infoToast";
            aviso.className = "toast-notification";
            aviso.style.backgroundColor = "#16a05d";
            aviso.style.boxShadow = "0 10px 25px rgba(22, 160, 93, 0.3)";
            aviso.innerHTML = '<i class="fas fa-info-circle" style="font-size: 1.2rem;"></i>' +
                '<span>Para restablecer tu contraseña acude a Control Escolar con tu credencial. ' +
                'El personal administrativo puede asignarte una nueva desde el sistema.</span>';
            document.body.appendChild(aviso);
            setTimeout(() => aviso.classList.add("hide-toast"), 7000);
        });
    }

    // 2. Lógica para ocultar el mensaje de error automáticamente (Toast)
    const toast = document.getElementById("errorToast");
    if (toast) {
        // Desaparece después de 4.5 segundos
        setTimeout(() => {
            toast.classList.add("hide-toast");
        }, 4500);
    }

});



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

    // 2. Lógica para ocultar el mensaje de error automáticamente (Toast)
    const toast = document.getElementById("errorToast");
    if (toast) {
        // Desaparece después de 4.5 segundos
        setTimeout(() => {
            toast.classList.add("hide-toast");
        }, 4500);
    }

});



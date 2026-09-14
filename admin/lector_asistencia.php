<?php
session_start();
// Solo administrativos pueden abrir el lector
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pase de Lista QR - COBAEP 27</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #112d4e; color: white; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .lector-container { background: white; color: #333; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: center; width: 100%; max-width: 600px; }
        h1 { color: #1a7541; margin-bottom: 5px; }
        p { color: #666; margin-bottom: 30px; }
        
        /* El input que recibe el código del escáner */
        #inputQR { width: 80%; padding: 15px; font-size: 24px; text-align: center; border: 2px solid #ccc; border-radius: 8px; margin-bottom: 20px; outline: none; transition: border-color 0.3s; }
        #inputQR:focus { border-color: #1a7541; box-shadow: 0 0 10px rgba(26, 117, 65, 0.2); }
        
        /* Caja donde se mostrará si es asistencia, retardo o falta */
        #resultado { margin-top: 20px; padding: 20px; border-radius: 8px; font-size: 28px; font-weight: bold; display: none; }
        
        .btn-volver { display: inline-block; margin-top: 30px; color: #fff; text-decoration: none; background: #1a7541; padding: 10px 20px; border-radius: 5px; }
    </style>
</head>
<body>

    <div class="lector-container">
        <h1>📸 Control de Acceso</h1>
        <p>Por favor, pase la credencial por el escáner</p>
        
        <input type="text" id="inputQR" autofocus autocomplete="off" placeholder="Esperando código QR...">
        
        <div id="resultado"></div>
    </div>

    <a href="dashboard_admin.php" class="btn-volver">← Volver al Panel</a>

    <script>
        const inputQR = document.getElementById('inputQR');
        const resultadoDiv = document.getElementById('resultado');

        // Escuchar el escáner
        inputQR.addEventListener('keypress', function(e) {
            // El escáner USB siempre envía la tecla "Enter" al terminar de leer
            if (e.key === 'Enter') {
                let matricula = this.value.trim();
                
                if(matricula !== "") {
                    // Mostrar mensaje temporal de "Procesando"
                    resultadoDiv.style.display = 'block';
                    resultadoDiv.style.backgroundColor = '#f1f1f1';
                    resultadoDiv.style.color = '#333';
                    resultadoDiv.innerHTML = "⏳ Procesando matrícula: " + matricula;

                    // Limpiar el input al instante para el siguiente alumno en la fila
                    this.value = ''; 
                    
                    // Aquí llamaremos al procesador PHP que hicimos al inicio usando Fetch API
                    // Por ahora, simularemos la respuesta visual:
                    setTimeout(() => {
                        // Simulación visual (Luego conectaremos esto al PHP real)
                        resultadoDiv.style.backgroundColor = '#e8f5e9';
                        resultadoDiv.style.color = '#2e7d32';
                        resultadoDiv.innerHTML = "✅ ASISTENCIA <br><span style='font-size:18px; color:#555;'>Matrícula: " + matricula + "</span>";
                    }, 500);
                }
            }
        });

        // Truco de UX: Si el administrativo da clic fuera del input por accidente, 
        // lo regresamos automáticamente para que el escáner no falle.
        document.addEventListener('click', function() {
            inputQR.focus();
        });
    </script>

</body>
</html>
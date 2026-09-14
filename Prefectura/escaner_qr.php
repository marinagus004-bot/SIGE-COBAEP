<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escáner de Asistencia · COBAEP 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #032b1e; color: white; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 1.5rem; margin: 0 0 5px 0; }
        .reader-container { width: 100%; max-width: 500px; background: white; border-radius: 15px; padding: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        #reader { width: 100%; border-radius: 10px; overflow: hidden; }
        #resultado { text-align: center; margin-top: 15px; font-weight: 600; font-size: 1.2rem; min-height: 30px; }
        .btn-volver { margin-top: 20px; background: #ffffff; color: #032b1e; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .success { color: #059669; }
        .error { color: #dc2626; }
    </style>
</head>
<body>

    <div class="header">
        <h1><i class="fas fa-camera"></i> Escáner de Credenciales</h1>
        <p>Apunta el código QR o código de barras a la cámara</p>
    </div>

    <div class="reader-container">
        <div id="reader"></div>
        <div id="resultado">Esperando escaneo...</div>
    </div>

    <a href="dashboard_prefectura.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Panel</a>

    <script>
        const html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: {width: 250, height: 250} }, false);
        let escaneando = false; // Bloqueo para evitar doble escaneo

        function onScanSuccess(decodedText, decodedResult) {
            if (escaneando) return;
            escaneando = true;
            document.getElementById('resultado').innerHTML = "<span style='color:#f59e0b;'><i class='fas fa-spinner fa-spin'></i> Procesando: " + decodedText + "</span>";
            
            // Enviar la matrícula al servidor mediante AJAX
            fetch('procesos/procesar_qr.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'matricula=' + encodeURIComponent(decodedText)
            })
            .then(response => response.json())
            .then(data => {
                const resDiv = document.getElementById('resultado');
                if(data.status === 'success') {
                    resDiv.innerHTML = `<span class='success'><i class='fas fa-check-circle'></i> ${data.message}</span>`;
                } else {
                    resDiv.innerHTML = `<span class='error'><i class='fas fa-times-circle'></i> ${data.message}</span>`;
                }
                
                // Esperar 2.5 segundos antes de permitir otro escaneo
                setTimeout(() => {
                    resDiv.innerHTML = "Esperando siguiente escaneo...";
                    escaneando = false;
                }, 2500);
            })
            .catch(error => {
                document.getElementById('resultado').innerHTML = "<span class='error'>Error de conexión.</span>";
                escaneando = false;
            });
        }

        html5QrcodeScanner.render(onScanSuccess);
    </script>
</body>
</html>
<?php
// config/firebase.php

function sincronizarConFirebase(string $ruta, array $datos) {
    // Reemplaza con la URL de tu proyecto en Firebase
    $url = "https://tu-proyecto-cobaep-default-rtdb.firebaseio.com/" . $ruta . ".json";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/plain'));
    
    $respuesta = curl_exec($ch);
    curl_close($ch);
    
    return $respuesta;
}
?>
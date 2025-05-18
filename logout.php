<?php
session_start();

// Destruir la sesión local
$session_name = session_name();
$session_path = session_save_path();
$session_file = $session_path . "/sess_" . session_id();

$_SESSION = [];
session_destroy();

if (isset($_COOKIE[$session_name])) {
    setcookie($session_name, '', time() - 42000, '/');
}

session_start();
session_regenerate_id(true);

if (file_exists($session_file)) {
    chmod($session_file, 0777);
    unlink($session_file);
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Pragma: no-cache");

// Mostrar interfaz
echo '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cerrando sesión...</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 50px;
        }
        button {
            margin-top: 20px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }
        .info {
            margin-top: 30px;
        }
    </style>
    <script>
        window.onload = () => {
            const popup = window.open("https://accounts.spotify.com/logout", "_blank");
            if (!popup) {
                document.getElementById("manual-logout").style.display = "block";
            }

            // Redirigir automáticamente después de 5 segundos
            setTimeout(() => {
                window.location.href = "login.php?logout=1";
            }, 10000);
        };
    </script>
</head>
<body>
    <h2>Has cerrado sesión en Burrito Musical</h2>
    <p>Intentando cerrar sesión en Spotify...</p>

    <div id="manual-logout" style="display:none;" class="info">
        <p><strong>⚠️ Tu navegador ha bloqueado la pestaña.</strong></p>
        <p>Haz clic en el botón para cerrar sesión manualmente:</p>
        <a href="https://accounts.spotify.com/logout" target="_blank">
            <button>Cerrar sesión de Spotify</button>
        </a>
    </div>

    <div class="info">
        <p>Serás redirigido al inicio de sesión automáticamente.</p>
    </div>
</body>
</html>
';
exit();
?>

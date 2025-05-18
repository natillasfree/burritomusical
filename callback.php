<?php
session_start();
require_once 'config.php'; 

$client_id = '73c9c86354c74074b52a7bc1b23fd2e5';
$client_secret = '53a967887a004b95825a530c03c88a58';
$redirect_uri = 'http://localhost/burritomusical/callback.php';

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Cerrar sesión anterior para evitar conflictos al cambiar de cuenta
    session_destroy();
    session_start();

    // Obtener el token de acceso de Spotify
    $token_url = "https://accounts.spotify.com/api/token";
    $data = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri,
        'client_id' => $client_id,
        'client_secret' => $client_secret
    ];

    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($token_url, false, $context);
    $token_data = json_decode($response, true);

    if (isset($token_data['access_token'])) {
        $_SESSION['access_token'] = $token_data['access_token'];

        // Obtener datos del usuario desde Spotify
        $user_url = "https://api.spotify.com/v1/me";
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "Authorization: Bearer " . $_SESSION['access_token']
            ]
        ];

        $context = stream_context_create($opts);
        $user_response = file_get_contents($user_url, false, $context);
        $user_data = json_decode($user_response, true);

        if ($user_data) {
            $spotify_id = $user_data['id'];
            $nombre_usuario = $user_data['display_name'];

            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE spotify_id = ?");
            $stmt->bind_param("s", $spotify_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO usuarios (spotify_id, nombre_usuario) VALUES (?, ?)");
                $stmt->bind_param("ss", $spotify_id, $nombre_usuario);
                $stmt->execute();
            }

            $_SESSION['spotify_id'] = $spotify_id;

            $stmt->close();
        }

        header("Location: dashboard.php");
        exit();
    } else {
        echo "Error al obtener el token.";
    }
} else {
    echo "Error en la autenticación.";
}
?>

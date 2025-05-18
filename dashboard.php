<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['access_token']) || empty($_SESSION['access_token'])) {
    session_destroy();
    setcookie(session_name(), '', time() - 42000, '/');
    header("Location: login.php");
    exit();
}

//Si el usuario cambió o hay un problema con la sesión, forzar logout
if (!isset($_SESSION['spotify_id'])) {
    session_destroy();
    setcookie(session_name(), '', time() - 42000, '/');
    header("Location: login.php");
    exit();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Pragma: no-cache");

$access_token = $_SESSION['access_token'];
$spotify_id = $_SESSION['spotify_id'];

// Función para obtener datos de Spotify
function getSpotifyData($url, $access_token) {
    $options = [
        "http" => [
            "method" => "GET",
            "header" => "Authorization: Bearer " . $access_token
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    return $response ? json_decode($response, true) : null;
}

// Función para obtener preview desde Deezer
function getDeezerPreview($song_name, $artist_name) {
    $query = urlencode("$song_name $artist_name");
    $url = "https://api.deezer.com/search?q=$query&limit=1";

    $response = @file_get_contents($url);
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data['data'][0]['preview'])) {
            return $data['data'][0]['preview'];
        }
    }
    return null;
}

// Obtener datos del usuario desde Spotify
$user_data = getSpotifyData("https://api.spotify.com/v1/me", $access_token);
$nombre_usuario = $user_data['display_name'];
$imagen_perfil = !empty($user_data['images']) ? $user_data['images'][0]['url'] : '';

// Selector de tiempo (Semana, Mes, Año)
$time_range = isset($_GET['time_range']) ? $_GET['time_range'] : 'short_term';

// Obtener canciones más escuchadas del usuario
$top_tracks = getSpotifyData("https://api.spotify.com/v1/me/top/tracks?limit=10&time_range=$time_range", $access_token);
// Obtener ID numérico del usuario actual
$query_usuario = $conn->prepare("SELECT id FROM usuarios WHERE spotify_id = ?");
$query_usuario->bind_param("s", $spotify_id);
$query_usuario->execute();
$result_usuario = $query_usuario->get_result();
if ($result_usuario->num_rows == 0) {
    die("Usuario no encontrado en la base de datos.");
}
$id_usuario_real = $result_usuario->fetch_assoc()['id'];

// Obtener comentarios desde la base de datos
$comentarios = [];
$canciones_ids = [];
foreach ($top_tracks['items'] as $track) {
    $spotify_song_id = $track['id'];

    $stmt = $conn->prepare("SELECT id FROM canciones WHERE spotify_id = ?");
    $stmt->bind_param("s", $spotify_song_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO canciones (spotify_id, titulo, artista, album) VALUES (?, ?, ?, ?)");
        $insert->bind_param("ssss", $spotify_song_id, $track['name'], $track['artists'][0]['name'], $track['album']['name']);
        $insert->execute();
        $canciones_ids[$spotify_song_id] = $insert->insert_id;
    } else {
        $canciones_ids[$spotify_song_id] = $res->fetch_assoc()['id'];
    }
}

$result = $conn->query("SELECT c.id_cancion, c.comentario, u.nombre_usuario FROM comentarios c JOIN usuarios u ON c.id_usuario = u.id");
while ($row = $result->fetch_assoc()) {
    $comentarios[$row['id_cancion']][] = $row;
}

// Validar datos
if (!$top_tracks) {
    die("Error obteniendo datos de Spotify. <a href='login.php'>Iniciar sesión de nuevo</a>.");
}
// Obtener ID numérico del usuario actual usando el spotify_id de la sesión
$query_usuario = $conn->prepare("SELECT id FROM usuarios WHERE spotify_id = ?");
$query_usuario->bind_param("s", $spotify_id);
$query_usuario->execute();
$result_usuario = $query_usuario->get_result();

if ($result_usuario->num_rows == 0) {
    die("Usuario no encontrado en la base de datos.");
}

$usuario = $result_usuario->fetch_assoc();
$id_usuario_real = $usuario['id']; // Este es el que necesitas

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BurritoMusical</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 20px;
        }

        .user-info img {
            border-radius: 50%;
            width: 100px;
            margin-top: 10px;
        }

        .song-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .song {
            text-align: center;
            width: 150px;
            position: relative;
        }

        .song img {
            width: 100%;
            border-radius: 10px;
            display: block;
        }

        .play-btn, .comment-btn {
            position: absolute;
            background: rgba(255, 255, 255, 0.8);
            border: none;
            padding: 10px;
            border-radius: 50%;
            font-size: 16px;
            cursor: pointer;
            display: none;
        }

        .play-btn {
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .comment-btn {
            top: 10px;
            right: 10px;
        }

        .song:hover .play-btn, .song:hover .comment-btn {
            display: block;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            width: 50%;
            border-radius: 10px;
        }

        .close {
            float: right;
            font-size: 28px;
            cursor: pointer;
        }

        canvas {
            max-width: 600px;
            margin: 20px auto;
        }
    </style>
</head>

<body>

    <div class="user-info">
        <h1>Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?></h1>
        <?php if ($imagen_perfil): ?>
            <img src="<?php echo $imagen_perfil; ?>" alt="Foto de perfil">
        <?php endif; ?>
    </div>

    <!-- Selector para elegir entre Semana, Mes y Año -->
    <h2>
         Tus canciones más escuchadas 
        <select id="timeRange">
            <option value="short_term" <?php if ($time_range == 'short_term') echo 'selected'; ?>>Última Semana</option>
            <option value="medium_term" <?php if ($time_range == 'medium_term') echo 'selected'; ?>>Último Mes</option>
            <option value="long_term" <?php if ($time_range == 'long_term') echo 'selected'; ?>>Último Año</option>
        </select>
    </h2>

    <script>
        document.getElementById("timeRange").addEventListener("change", function() {
            window.location.href = "dashboard.php?time_range=" + this.value;
        });

        let audio = new Audio();
        audio.volume = 0.2;

        function playPreview(url, button) {
            if (audio.src !== url) {
                audio.src = url;
                audio.play();
                button.innerText = "⏸️";
            } else if (!audio.paused) {
                audio.pause();
                button.innerText = "▶️";
            } else {
                audio.play();
                button.innerText = "⏸️";
            }
        }
    </script>

    <div class="song-container">
        <?php foreach ($top_tracks['items'] as $track): 
            $song_id = htmlspecialchars($track['id']);
            $song_name = htmlspecialchars($track['name']);
            $artist_name = htmlspecialchars($track['artists'][0]['name']);
            $album_image = $track['album']['images'][0]['url'];
            $preview_url = $track['preview_url'] ?: getDeezerPreview($song_name, $artist_name);
        ?>
            <div class="song">
                <img src="<?php echo $album_image; ?>" alt="Imagen de <?php echo $song_name; ?>">
                <?php if ($preview_url): ?>
                    <button class="play-btn" onclick="playPreview('<?php echo $preview_url; ?>', this)">▶️</button>
                <?php endif; ?>
                <button class="comment-btn" onclick="openModal('<?php echo $canciones_ids[$song_id]; ?>')">💬</button>
                <p><strong><?php echo $song_name; ?></strong></p>
                <p style="font-size: 12px;"> <?php echo $artist_name; ?> </p>
            </div>
        <?php endforeach; ?>
    </div>
    <!-- Modal de comentarios -->
<div id="commentModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Comentarios</h2>
        <div id="commentsContainer"></div>
        <form id="commentForm" method="post" action="guardar_comentario.php">
            <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($id_usuario_real); ?>">
            <input type="hidden" name="id_cancion" id="modalSongId">
            <textarea name="comentario" placeholder="Escribe tu comentario..." required></textarea>
            <button type="submit">Enviar</button>
        </form>
    </div>
</div>
<a href="logout.php" style="display: inline-block; padding: 10px 20px; background: red; color: white; text-decoration: none; border-radius: 5px;">
    Cerrar Sesión
</a>
<script>
    function openModal(songId) {
        document.getElementById("modalSongId").value = songId;
        document.getElementById("commentsContainer").innerHTML = "";
        
        let comments = <?php echo json_encode($comentarios); ?>;
        if (comments[songId]) {
            comments[songId].forEach(comment => {
                document.getElementById("commentsContainer").innerHTML += `<p><strong>${comment.nombre_usuario}:</strong> ${comment.comentario}</p>`;
            });
        } else {
            document.getElementById("commentsContainer").innerHTML = "<p>No hay comentarios aún.</p>";
        }
        
        document.getElementById("commentModal").style.display = "block";
    }

    function closeModal() {
        document.getElementById("commentModal").style.display = "none";
    }

    let ctx = document.getElementById('chart').getContext('2d');
    let chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($top_tracks['items'], 'name')); ?>,
            datasets: [{
                label: 'Popularidad',
                data: <?php echo json_encode(array_column($top_tracks['items'], 'popularity')); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)'
            }]
        }
    });
</script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        let chartCanvas = document.getElementById("chart");

        if (chartCanvas) {
            let ctx = chartCanvas.getContext('2d');
            let chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($top_tracks['items'], 'name')); ?>,
                    datasets: [{
                        label: 'Popularidad',
                        data: <?php echo json_encode(array_column($top_tracks['items'], 'popularity')); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        } else {
            console.error("No se encontró el elemento <canvas id='chart'>. Asegúrate de que está en el HTML.");
        }
    });
</script>
    <canvas id="chart"></canvas>

</body>
</html>

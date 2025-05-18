<?php
include 'config.php'; // Archivo donde se configura la conexión a la base de datos

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0;
    $id_cancion = isset($_POST['id_cancion']) ? intval($_POST['id_cancion']) : 0;
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

    // Validar que los campos no estén vacíos
    if ($id_usuario > 0 && $id_cancion > 0 && !empty($comentario)) {
        // Preparar la consulta para evitar inyecciones SQL
        $stmt = $conn->prepare("INSERT INTO comentarios (id_usuario, id_cancion, comentario) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $id_usuario, $id_cancion, $comentario);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Comentario agregado correctamente"]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al guardar el comentario"]);
        }

        $stmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "Todos los campos son obligatorios"]);
    }
}

$conn->close();
?>

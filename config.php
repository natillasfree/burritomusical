<?php
//ini_set('session.save_path', 'C:/xampp/htdocs/burritomusical/sesiones');
//ini_set('session.gc_probability', 1);
//ini_set('session.gc_divisor', 1);

define('SPOTIFY_CLIENT_ID', '73c9c86354c74074b52a7bc1b23fd2e5');
define('SPOTIFY_CLIENT_SECRET', '53a967887a004b95825a530c03c88a58');
define('SPOTIFY_REDIRECT_URI', 'http://localhost/burritomusical/callback.php');

$servername = "localhost";  
$username = "root"; 
$password = "";  
$database = "burritomusical";  

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>



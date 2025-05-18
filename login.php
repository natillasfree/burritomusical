<?php
session_start();

$client_id = '73c9c86354c74074b52a7bc1b23fd2e5';
$client_secret = '53a967887a004b95825a530c03c88a58';
$redirect_uri = 'http://localhost/burritomusical/callback.php';

$scope = 'user-top-read';
$auth_url = "https://accounts.spotify.com/authorize?client_id=$client_id&response_type=code&redirect_uri=$redirect_uri&scope=$scope";

header("Location: $auth_url");
exit();
?>

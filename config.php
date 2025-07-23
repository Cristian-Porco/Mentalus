<?php
header("Content-Type: application/json");

session_start();

$host = 'localhost:3306/phpmyadmin';
$db = 'mentalus';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione fallita: ' . $conn->connect_error]);
    exit;
}
?>
<?php
header("Content-Type: application/json");
session_start();

// Funzione per caricare le variabili da .env
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || !str_contains($line, '=')) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Carica le variabili da .env
loadEnv(__DIR__ . '/.env');

// Recupera l'API key (e altre eventuali variabili)
$openai_api_key = $_ENV['OPENAI_API_KEY'] ?? null;

// Configurazione DB
$host = 'localhost:3306/phpmyadmin';
$db = 'mentalus';
$user = 'root';
$pass = '';

// Connessione
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione fallita: ' . $conn->connect_error]);
    exit;
}
?>
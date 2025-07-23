<?php
include 'config.php';

$nome = $_POST['nome'] ?? '';
$cognome = $_POST['cognome'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (!$nome || !$cognome || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Tutti i campi sono obbligatori.']);
    exit;
}

// Controlla se l'email è già registrata
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['error' => 'Email già registrata.']);
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (nome, cognome, email, password) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $nome, $cognome, $email, $password_hash);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'user_id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Errore nella registrazione.']);
}
?>
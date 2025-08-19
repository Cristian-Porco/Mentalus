<?php
include '../config/db.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Email e password obbligatorie.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, password, nome, cognome FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'nome' => $user['nome'],
                'cognome' => $user['cognome'],
                'email' => $email
            ]
        ]);
    } else {
        echo json_encode(['error' => 'Password errata.']);
    }
} else {
    echo json_encode(['error' => 'Utente non trovato.']);
}
?>
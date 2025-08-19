<?php
// /app/mentalus/api/check_login.php
// Verifica autenticazione utente all'apertura pagina

include '../config/db.php'; // qui carichi la OPENAI_API_KEY dal .env

// === CONFIGURAZIONE ===
// Cambia questa chiave se usi un nome diverso in sessione
$SESSION_USER_KEY = 'user_id';

// 1) Assenza dell'ID utente in sessione -> 401
if (empty($_SESSION[$SESSION_USER_KEY])) {
    http_response_code(401);
    echo json_encode([
        'ok'    => false,
        'error' => 'Not authenticated'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int) $_SESSION[$SESSION_USER_KEY];

// 2) Verifica che l'utente esista nel DB
// !!! Se la tabella non si chiama `users`, cambiala qui sotto.
$sql = "SELECT id, nome, cognome, email FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'DB prepare failed: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param('i', $userId);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'DB execute failed: ' . $stmt->error
    ], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$user   = $result ? $result->fetch_assoc() : null;
$stmt->close();

// 3) Se l'utente non esiste più -> invalida la sessione e 401
if (!$user) {
    // Pulisci la sessione
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    http_response_code(401);
    echo json_encode([
        'ok'    => false,
        'error' => 'User not found'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4) Autenticato -> 200
http_response_code(200);
echo json_encode([
    'ok'   => true,
    'user' => [
        'id'      => (int) $user['id'],
        'nome'    => $user['nome'],
        'cognome' => $user['cognome'],
        'email'   => $user['email'],
    ]
], JSON_UNESCAPED_UNICODE);
exit;
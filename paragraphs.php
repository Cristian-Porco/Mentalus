<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Utente non autenticato']);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

/**
 * Estrai parole chiave singole da un testo usando OpenAI
 */
function estraiParoleChiave($testo) {
    global $openai_api_key;

    $prompt = "Estrai da questo paragrafo solo parole chiave significative, singole parole, senza frasi. Restituisci l'elenco come array JSON di stringhe:\n\n\"$testo\"";

    $data = [
        "model" => "gpt-4o",
        "messages" => [
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.3,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $openai_api_key"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) return [];

    $json = json_decode($response, true);
    $text = $json['choices'][0]['message']['content'] ?? '';

    // Pulizia extra se il modello ha aggiunto testo prima/dopo
    $text = trim($text);

    // Se la risposta è una stringa come ["SQL", "injection", ...]
    if (str_starts_with($text, '[') && str_ends_with($text, ']')) {
        $parole = json_decode($text, true);
    } else {
        // Tentativo alternativo: estrae JSON array da dentro testo extra
        if (preg_match('/\[(.*?)\]/s', $text, $matches)) {
            $maybeArray = "[" . $matches[1] . "]";
            $parole = json_decode($maybeArray, true);
        } else {
            $parole = [];
        }
    }

    // Controllo finale
    if (!is_array($parole)) {
        $parole = [];
    }

    error_log(implode(', ', $parole));

    return is_array($parole) ? $parole : [];
}

/**
 * Salva le parole chiave in DB
 */
function salvaKeywords($conn, $parole, $paragraph_id) {
    $stmt = $conn->prepare("INSERT INTO keywords (parola, paragraph_id) VALUES (?, ?)");
    foreach ($parole as $parola) {
        $p = strtolower(trim($parola));
        if ($p) {
            $stmt->bind_param("si", $p, $paragraph_id);
            $stmt->execute();
        }
    }
}

/**
 * Rimuovi parole chiave associate a un paragrafo
 */
function rimuoviKeywords($conn, $paragraph_id) {
    $stmt = $conn->prepare("DELETE FROM keywords WHERE paragraph_id = ?");
    $stmt->bind_param("i", $paragraph_id);
    $stmt->execute();
}

switch ($action) {

    case 'add':
        $descrizione = $_POST['descrizione'] ?? '';
        $sheet_id = $_POST['sheet_id'] ?? null;

        if (!$descrizione || !$sheet_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Descrizione o sheet_id mancante']);
            exit;
        }

        $check = $conn->prepare("SELECT id FROM text_sheets WHERE id = ? AND user_id = ?");
        $check->bind_param("ii", $sheet_id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorizzato ad aggiungere paragrafi a questo foglio']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO paragraphs (descrizione, sheet_id) VALUES (?, ?)");
        $stmt->bind_param("si", $descrizione, $sheet_id);
        $stmt->execute();
        $paragraph_id = $stmt->insert_id;

        // OpenAI: estrai parole chiave e salva
        $keywords = estraiParoleChiave($descrizione);
        salvaKeywords($conn, $keywords, $paragraph_id);

        echo json_encode(['success' => true, 'id' => $paragraph_id]);
        break;

    case 'edit':
        $id = $_POST['id'] ?? null;
        $descrizione = $_POST['descrizione'] ?? '';
        if (!$id || !$descrizione) {
            http_response_code(400);
            echo json_encode(['error' => 'ID o descrizione mancante']);
            exit;
        }

        $check = $conn->prepare("
            SELECT p.id FROM paragraphs p
            JOIN text_sheets s ON p.sheet_id = s.id
            WHERE p.id = ? AND s.user_id = ?
        ");
        $check->bind_param("ii", $id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorizzato a modificare questo paragrafo']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE paragraphs SET descrizione = ? WHERE id = ?");
        $stmt->bind_param("si", $descrizione, $id);
        $stmt->execute();

        // OpenAI: aggiorna parole chiave
        rimuoviKeywords($conn, $id);
        $keywords = estraiParoleChiave($descrizione);
        error_log(serialize($keywords));
        salvaKeywords($conn, $keywords, $id);

        echo json_encode(['success' => true]);
        break;

    case 'view':
        if (isset($_GET['sheet_id'])) {
            $sheet_id = intval($_GET['sheet_id']);
            $check = $conn->prepare("SELECT id FROM text_sheets WHERE id = ? AND user_id = ?");
            $check->bind_param("ii", $sheet_id, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'Accesso negato a questo foglio']);
                exit;
            }

            $stmt = $conn->prepare("SELECT * FROM paragraphs WHERE sheet_id = ?");
            $stmt->bind_param("i", $sheet_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        } else {
            $query = "
                SELECT p.* FROM paragraphs p
                JOIN text_sheets s ON p.sheet_id = s.id
                WHERE s.user_id = ?
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        }
        break;

    case 'remove':
        $id = $_POST['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID mancante']);
            exit;
        }

        $check = $conn->prepare("
            SELECT p.id FROM paragraphs p
            JOIN text_sheets s ON p.sheet_id = s.id
            WHERE p.id = ? AND s.user_id = ?
        ");
        $check->bind_param("ii", $id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorizzato a eliminare questo paragrafo']);
            exit;
        }

        rimuoviKeywords($conn, $id);

        $stmt = $conn->prepare("DELETE FROM paragraphs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Azione non valida']);
}
?>
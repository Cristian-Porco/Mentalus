<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Utente non autenticato']);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

switch ($action) {

    case 'add':
        $parola = $_POST['parola'] ?? '';
        $paragraph_id = $_POST['paragraph_id'] ?? null;

        if (!$parola || !$paragraph_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Parola o paragraph_id mancante']);
            exit;
        }

        // Verifica proprietà: il paragrafo è in un foglio dell’utente?
        $check = $conn->prepare("
            SELECT p.id FROM paragraphs p
            JOIN text_sheets s ON p.sheet_id = s.id
            WHERE p.id = ? AND s.user_id = ?
        ");
        $check->bind_param("ii", $paragraph_id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorizzato ad aggiungere parole chiave a questo paragrafo']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO keywords (parola, paragraph_id) VALUES (?, ?)");
        $stmt->bind_param("si", $parola, $paragraph_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        break;

    case 'view':
        if (isset($_GET['paragraph_id'])) {
            $paragraph_id = intval($_GET['paragraph_id']);

            // Verifica proprietà del paragrafo
            $check = $conn->prepare("
                SELECT p.id FROM paragraphs p
                JOIN text_sheets s ON p.sheet_id = s.id
                WHERE p.id = ? AND s.user_id = ?
            ");
            $check->bind_param("ii", $paragraph_id, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'Accesso negato al paragrafo']);
                exit;
            }

            $stmt = $conn->prepare("SELECT * FROM keywords WHERE paragraph_id = ?");
            $stmt->bind_param("i", $paragraph_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        } else {
            // Tutte le keyword dell'utente (via i paragrafi)
            $stmt = $conn->prepare("
                SELECT k.* FROM keywords k
                JOIN paragraphs p ON k.paragraph_id = p.id
                JOIN text_sheets s ON p.sheet_id = s.id
                WHERE s.user_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        }
        break;

    case 'view_by_keyword':
        $keyword = $_GET['parola'] ?? '';
        if (!$keyword) {
            http_response_code(400);
            echo json_encode(['error' => 'Parametro "parola" mancante']);
            exit;
        }

        // Restituisce i paragrafi che contengono la keyword, solo se sono dell’utente
        $stmt = $conn->prepare("
            SELECT p.* FROM paragraphs p
            JOIN keywords k ON k.paragraph_id = p.id
            JOIN text_sheets s ON p.sheet_id = s.id
            WHERE k.parola = ? AND s.user_id = ?
        ");
        $stmt->bind_param("si", $keyword, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'edit':
        $id = $_POST['id'] ?? null;
        $parola = $_POST['parola'] ?? '';
        if (!$id || !$parola) {
            http_response_code(400);
            echo json_encode(['error' => 'ID o parola mancante']);
            exit;
        }

        // Verifica proprietà
        $check = $conn->prepare("
            SELECT k.id FROM keywords k
            JOIN paragraphs p ON k.paragraph_id = p.id
            JOIN text_sheets s ON p.sheet_id = s.id
            WHERE k.id = ? AND s.user_id = ?
        ");
        $check->bind_param("ii", $id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorizzato a modificare questa keyword']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE keywords SET parola = ? WHERE id = ?");
        $stmt->bind_param("si", $parola, $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    case 'remove':
        $id = $_POST['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID mancante']);
            exit;
        }

        // Verifica proprietà
        $check = $conn->prepare("
            SELECT k.id FROM keywords k
            JOIN paragraphs p ON k.paragraph_id = p.id
            JOIN text_sheets s ON p.sheet_id = s.id
            WHERE k.id = ? AND s.user_id = ?
        ");
        $check->bind_param("ii", $id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorizzato a eliminare questa keyword']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM keywords WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Azione non valida']);
}
?>
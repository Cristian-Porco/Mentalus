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
        $descrizione = $_POST['descrizione'] ?? '';
        $sheet_id = $_POST['sheet_id'] ?? null;

        if (!$descrizione || !$sheet_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Descrizione o sheet_id mancante']);
            exit;
        }

        // Verifica che il foglio appartenga all'utente loggato
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

        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        break;

    case 'view':
        if (isset($_GET['sheet_id'])) {
            // Paragrafi per uno specifico foglio, se appartiene all'utente
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
            // Tutti i paragrafi appartenenti a fogli dell'utente loggato
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

    case 'edit':
        $id = $_POST['id'] ?? null;
        $descrizione = $_POST['descrizione'] ?? '';
        if (!$id || !$descrizione) {
            http_response_code(400);
            echo json_encode(['error' => 'ID o descrizione mancante']);
            exit;
        }

        // Verifica proprietà: il paragrafo appartiene a un foglio dell’utente?
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
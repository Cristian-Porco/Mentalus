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
        $titolo = $_POST['titolo'] ?? '';
        $category = $_POST['category'] ?? null;

        if (!$titolo) {
            http_response_code(400);
            echo json_encode(['error' => 'Titolo mancante']);
            exit;
        }

        if ($category) {
            $stmt = $conn->prepare("INSERT INTO text_sheets (titolo, category, user_id) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $titolo, $category, $user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO text_sheets (titolo, user_id) VALUES (?, ?)");
            $stmt->bind_param("si", $titolo, $user_id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Errore durante l\'inserimento']);
        }
        break;

    case 'view':
        if (isset($_GET['id'])) {
            // Singolo foglio
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM text_sheets WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $sheet = $result->fetch_assoc();
            if ($sheet) {
                echo json_encode($sheet);
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Accesso negato o foglio non trovato']);
            }
        } else {
            // Tutti i fogli dell’utente, dal più recente al più vecchio
            $stmt = $conn->prepare("SELECT * FROM text_sheets WHERE user_id = ? ORDER BY data_creazione DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        }
        break;

    case 'edit':
        $id = $_POST['id'] ?? null;
        $titolo = $_POST['titolo'] ?? '';
        $category = $_POST['category'] ?? null;

        if (!$id || !$titolo) {
            http_response_code(400);
            echo json_encode(['error' => 'ID o titolo mancante']);
            exit;
        }

        // Verifica proprietà del foglio
        $check = $conn->prepare("SELECT id FROM text_sheets WHERE id = ? AND user_id = ?");
        $check->bind_param("ii", $id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorizzato a modificare questo foglio']);
            exit;
        }

        if ($category !== null) {
            $stmt = $conn->prepare("UPDATE text_sheets SET titolo = ?, category = ?, data_modifica = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $titolo, $category, $id);
        } else {
            $stmt = $conn->prepare("UPDATE text_sheets SET titolo = ?, data_modifica = NOW() WHERE id = ?");
            $stmt->bind_param("si", $titolo, $id);
        }

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

        // Verifica proprietà del foglio
        $check = $conn->prepare("SELECT id FROM text_sheets WHERE id = ? AND user_id = ?");
        $check->bind_param("ii", $id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorizzato a eliminare questo foglio']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM text_sheets WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Azione non valida']);
}
?>
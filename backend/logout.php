<?php
include 'config.php';
session_destroy();
echo json_encode(['success' => true, 'message' => 'Logout effettuato']);
?>
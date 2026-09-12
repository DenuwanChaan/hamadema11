<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active', 'sold']) ? $_POST['status'] : null;
    $userId = current_user_id();

    if ($id && $status) {
        $stmt = $conn->prepare("UPDATE ads SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sii", $status, $id, $userId);
        $stmt->execute();
        flash_set($status === 'sold' ? 'Marked as sold.' : 'Ad is active again.');
    }
}
header('Location: dashboard.php');
exit;

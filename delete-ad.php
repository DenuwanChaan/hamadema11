<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $userId = current_user_id();

    $stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $ad = $stmt->get_result()->fetch_assoc();

    if ($ad) {
        $images = json_decode($ad['images'] ?? '[]', true) ?: [];
        foreach ($images as $img) delete_image_file($img);

        $stmt = $conn->prepare("DELETE FROM ads WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $userId);
        $stmt->execute();
        flash_set('Ad deleted.');
    }
}
header('Location: dashboard.php');
exit;

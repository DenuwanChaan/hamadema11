<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$userId = current_user_id();
$stmt = $conn->prepare("
  SELECT a.*, c.name AS category_name FROM ads a
  JOIN categories c ON c.id = a.category_id
  WHERE a.user_id = ?
  ORDER BY a.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myAds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'My Ads';
include __DIR__ . '/includes/header.php';
?>

<div class="dashboard-page">
  <h1>My ads</h1>

  <?php if (empty($myAds)): ?>
    <div class="empty-state">
      <div class="emoji">📭</div>
      <h3>You haven't posted anything yet</h3>
      <a href="post-ad.php">Post your first ad →</a>
    </div>
  <?php else: ?>
    <?php foreach ($myAds as $ad): $images = json_decode($ad['images'] ?? '[]', true) ?: []; ?>
      <div class="my-ad-row">
        <a class="thumb" href="ad.php?id=<?= (int)$ad['id'] ?>">
          <?php if (!empty($images)): ?>
            <img src="uploads/<?= e($images[0]) ?>">
          <?php else: ?>🛍️<?php endif; ?>
        </a>
        <div class="info">
          <div class="t">
            <a href="ad.php?id=<?= (int)$ad['id'] ?>"><?= e($ad['title']) ?></a>
            <span class="status-pill <?= e($ad['status']) ?>"><?= e($ad['status']) ?></span>
          </div>
          <div class="p"><?= money($ad['price']) ?></div>
          <div class="m"><?= (int)$ad['views'] ?> views &middot; posted <?= time_ago($ad['created_at']) ?></div>
        </div>
        <div class="actions">
          <?php if ($ad['status'] === 'active'): ?>
            <form method="post" action="toggle-status.php" style="display:inline">
              <input type="hidden" name="id" value="<?= (int)$ad['id'] ?>">
              <input type="hidden" name="status" value="sold">
              <button type="submit">Mark as sold</button>
            </form>
          <?php else: ?>
            <form method="post" action="toggle-status.php" style="display:inline">
              <input type="hidden" name="id" value="<?= (int)$ad['id'] ?>">
              <input type="hidden" name="status" value="active">
              <button type="submit">Relist</button>
            </form>
          <?php endif; ?>
          <a href="post-ad.php?edit=<?= (int)$ad['id'] ?>">Edit</a>
          <form method="post" action="delete-ad.php" style="display:inline" onsubmit="return confirm('Delete this ad permanently? This cannot be undone.');">
            <input type="hidden" name="id" value="<?= (int)$ad['id'] ?>">
            <button type="submit" class="danger">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

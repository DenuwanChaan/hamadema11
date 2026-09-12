<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = null; // uses default title

$categories = get_categories($conn);

$recent = $conn->query("
  SELECT a.*, c.name AS category_name, c.slug AS category_slug
  FROM ads a JOIN categories c ON c.id = a.category_id
  WHERE a.status = 'active'
  ORDER BY a.created_at DESC
  LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="hero-blob b1"></div>
  <div class="hero-blob b2"></div>
  <div class="hero-chips">
    <div class="hero-chip c1"><i class="fi fi-rr-car-side"></i> Motors</div>
    <div class="hero-chip c2"><i class="fi fi-rr-home"></i> Real Estate</div>
    <div class="hero-chip c3"><i class="fi fi-rr-mobile"></i> Electronics</div>
  </div>
  <div class="hero-inner">
    <span class="hero-eyebrow"><i class="fi fi-rr-map-marker"></i> Trusted by sellers near you</span>
    <h1>Buy and sell anything, locally.</h1>
    <p>From phones to furniture, cars to jobs — post an ad in minutes, or find what you need nearby.</p>
    <form class="hero-search" action="browse.php" method="get">
      <input type="text" name="q" placeholder="What are you looking for?">
      <button type="submit">Search</button>
    </form>
  </div>
</section>

<div class="container">
  <div class="section-header">
    <h2>Browse categories</h2>
  </div>
  <div class="cat-grid">
    <?php $toneCount = 3; foreach ($categories as $i => $cat): ?>
      <a class="cat-card tone-<?= $i % $toneCount ?>" href="browse.php?category=<?= e($cat['slug']) ?>">
        <span class="emoji"><i class="<?= e($cat['icon']) ?>"></i></span>
        <span class="name"><?= e($cat['name']) ?></span>
        <span class="count"><?= (int)$cat['ad_count'] ?> <?= $cat['ad_count'] == 1 ? 'ad' : 'ads' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="container">
  <div class="section-header">
    <h2>Recently listed</h2>
    <a href="browse.php" class="view-all">See all →</a>
  </div>
  <?php if (empty($recent)): ?>
    <div class="empty-state">
      <div class="emoji">📭</div>
      <h3>No ads yet</h3>
      <p>Be the first to post something.</p>
      <a href="post-ad.php">Post an ad →</a>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($recent as $ad): include __DIR__ . '/includes/ad-card.php'; endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

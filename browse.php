<?php
require_once __DIR__ . '/includes/functions.php';

$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$minPrice = str_replace(',', '', trim($_GET['minPrice'] ?? ''));
$maxPrice = str_replace(',', '', trim($_GET['maxPrice'] ?? ''));
$condition = $_GET['condition'] ?? '';
$location = trim($_GET['location'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 24;
$offset = ($page - 1) * $pageSize;

$where = ["a.status = 'active'"];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $like = "%$q%";
    $params[] = $like; $params[] = $like; $types .= 'ss';
}
if ($category !== '') {
    $where[] = "c.slug = ?";
    $params[] = $category; $types .= 's';
}
if ($minPrice !== '' && is_numeric($minPrice)) {
    $where[] = "a.price >= ?";
    $params[] = (float)$minPrice; $types .= 'd';
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $where[] = "a.price <= ?";
    $params[] = (float)$maxPrice; $types .= 'd';
}
if ($condition !== '' && in_array($condition, ['new', 'used'])) {
    $where[] = "a.condition = ?";
    $params[] = $condition; $types .= 's';
}
if ($location !== '') {
    $where[] = "a.location LIKE ?";
    $params[] = "%$location%"; $types .= 's';
}

$sortMap = [
    'newest' => 'a.created_at DESC',
    'oldest' => 'a.created_at ASC',
    'price_low' => 'a.price ASC',
    'price_high' => 'a.price DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['newest'];
$whereSql = implode(' AND ', $where);

// Count total
$countSql = "SELECT COUNT(*) AS c FROM ads a JOIN categories c ON c.id = a.category_id WHERE $whereSql";
$stmt = $conn->prepare($countSql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['c'];
$totalPages = max(1, ceil($total / $pageSize));

// Fetch page
$sql = "
  SELECT a.*, c.name AS category_name, c.slug AS category_slug
  FROM ads a JOIN categories c ON c.id = a.category_id
  WHERE $whereSql
  ORDER BY $orderBy
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$allParams = $params;
$allTypes = $types . 'ii';
$allParams[] = $pageSize;
$allParams[] = $offset;
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$ads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$categories = get_categories($conn);
$page_title = $q !== '' ? "Results for \"$q\"" : 'Browse ads';

include __DIR__ . '/includes/header.php';

function qs($overrides = []) {
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}
?>

<div class="container">
  <div class="browse-layout">
    <aside class="filters">
      <form method="get" action="browse.php">
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>

        <h4>Category</h4>
        <select name="category">
          <option value="">All categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat['slug']) ?>" <?= $category === $cat['slug'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <h4>Price range (Rs.)</h4>
        <div class="price-range">
          <input type="text" inputmode="numeric" id="minPriceInput" name="minPrice" placeholder="Min" value="<?= e($minPrice) ?>">
          <input type="text" inputmode="numeric" id="maxPriceInput" name="maxPrice" placeholder="Max" value="<?= e($maxPrice) ?>">
        </div>

        <h4>Condition</h4>
        <label><input type="radio" name="condition" value="" <?= $condition === '' ? 'checked' : '' ?>> Any</label>
        <label><input type="radio" name="condition" value="new" <?= $condition === 'new' ? 'checked' : '' ?>> New</label>
        <label><input type="radio" name="condition" value="used" <?= $condition === 'used' ? 'checked' : '' ?>> Used</label>

        <h4>Location</h4>
        <input type="text" name="location" placeholder="e.g. Colombo" value="<?= e($location) ?>">

        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <button class="btn-apply" type="submit">Apply filters</button>
      </form>
    </aside>

    <section>
      <div class="results-header">
        <h1><?= e($page_title) ?> <span class="count">(<?= (int)$total ?> results)</span></h1>
        <select class="sort-select" onchange="window.location.href = '<?= e(qs(['sort' => ''])) ?>' + this.value">
          <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'price_low' => 'Price: low to high', 'price_high' => 'Price: high to low'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= $sort === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (empty($ads)): ?>
        <div class="empty-state">
          <div class="emoji">🔍</div>
          <h3>No ads match your search</h3>
          <p>Try different filters or a broader search term.</p>
        </div>
      <?php else: ?>
        <div class="grid">
          <?php foreach ($ads as $ad): include __DIR__ . '/includes/ad-card.php'; endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i === $page): ?>
              <span class="active"><?= $i ?></span>
            <?php else: ?>
              <a href="<?= e(qs(['page' => $i])) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<script>
// Format the price filter fields with thousands commas as the user types
function formatPriceValue(raw) {
  const digits = (raw || '').replace(/[^\d]/g, '');
  if (digits === '') return '';
  return Number(digits).toLocaleString('en-US');
}
['minPriceInput', 'maxPriceInput'].forEach(id => {
  const input = document.getElementById(id);
  if (!input) return;
  input.value = formatPriceValue(input.value);
  input.addEventListener('input', () => { input.value = formatPriceValue(input.value); });
  input.form.addEventListener('submit', () => { input.value = input.value.replace(/,/g, ''); });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$currentUser = get_user($conn, current_user_id());

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$ad = null;
$existingImages = [];
$existingDetails = [];

if ($editId) {
    $stmt = $conn->prepare("SELECT a.*, c.slug AS category_slug FROM ads a JOIN categories c ON c.id = a.category_id WHERE a.id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $ad = $stmt->get_result()->fetch_assoc();
    if (!$ad || (int)$ad['user_id'] !== current_user_id()) {
        flash_set('You can only edit your own ads.', 'error');
        header('Location: dashboard.php');
        exit;
    }
    $existingImages = json_decode($ad['images'] ?? '[]', true) ?: [];
    $existingDetails = json_decode($ad['details'] ?? '[]', true) ?: [];
} else {
    // Pre-fill contact number with the account phone for a brand-new ad
    $ad = ['phone' => $currentUser['phone'] ?? ''];
}

$categories = get_categories($conn);
$categoryFieldsConfig = get_category_fields();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = str_replace(',', '', trim($_POST['price'] ?? ''));
    $negotiable = isset($_POST['negotiable']) ? 1 : 0;
    $condition = in_array($_POST['condition'] ?? '', ['new', 'used']) ? $_POST['condition'] : 'used';
    $location = trim($_POST['location'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $categorySlug = $_POST['category_slug'] ?? '';

    if ($title === '') $errors[] = 'Please give your ad a title.';
    if ($location === '') $errors[] = 'Please add a location.';
    if ($phone === '') $errors[] = 'Please provide a contact phone number.';
    $category = get_category_by_slug($conn, $categorySlug);
    if (!$category) $errors[] = 'Please choose a category.';
    if ($price !== '' && !is_numeric($price)) $errors[] = 'Price must be a number.';

    // Validate + collect the category-specific fields
    $fieldDefs = $categoryFieldsConfig[$categorySlug] ?? [];
    $detailsInput = $_POST['details_' . $categorySlug] ?? [];
    $detailsFinal = [];
    foreach ($fieldDefs as $fdef) {
        $val = trim($detailsInput[$fdef['key']] ?? '');
        if ($val === '' && !empty($fdef['required'])) {
            $errors[] = $fdef['label'] . ' is required.';
        }
        if ($val !== '') $detailsFinal[$fdef['key']] = $val;
    }

    // Work out final image list: kept existing + newly uploaded, capped at 5
    $keep = $_POST['keep_images'] ?? []; // array of filenames the user chose to keep (edit mode)
    if ($editId) {
        $removed = array_diff($existingImages, $keep);
        foreach ($removed as $r) delete_image_file($r);
        $finalImages = array_values(array_intersect($existingImages, $keep));
    } else {
        $finalImages = [];
    }
    $newUploads = handle_image_uploads('images', 5 - count($finalImages));
    $finalImages = array_merge($finalImages, $newUploads);

    if (empty($errors)) {
        $priceVal = $price === '' ? null : (float)$price;
        $imagesJson = json_encode($finalImages);
        $detailsJson = json_encode($detailsFinal, JSON_UNESCAPED_UNICODE);

        if ($editId) {
            $stmt = $conn->prepare("
                UPDATE ads SET title=?, description=?, price=?, negotiable=?, `condition`=?, location=?, district=?, phone=?, category_id=?, images=?, details=?
                WHERE id=? AND user_id=?
            ");
            $types = "ss"      // title, description
                   . "d"       // price
                   . "i"       // negotiable
                   . "ssss"    // condition, location, district, phone
                   . "i"       // category_id
                   . "ss"      // images, details
                   . "ii";     // id, user_id
            $stmt->bind_param(
                $types,
                $title, $description, $priceVal, $negotiable, $condition, $location, $district, $phone,
                $category['id'], $imagesJson, $detailsJson, $editId, $_SESSION['user_id']
            );
            $stmt->execute();
            flash_set('Ad updated!');
            header('Location: ad.php?id=' . $editId);
            exit;
        } else {
            $stmt = $conn->prepare("
                INSERT INTO ads (user_id, category_id, title, description, price, negotiable, `condition`, location, district, phone, images, details)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $types = "ii"      // user_id, category_id
                   . "ssdi"    // title, description, price, negotiable
                   . "ssss"    // condition, location, district, phone
                   . "ss";     // images, details
            $userId = current_user_id();
            $stmt->bind_param(
                $types,
                $userId, $category['id'], $title, $description, $priceVal, $negotiable, $condition, $location, $district, $phone, $imagesJson, $detailsJson
            );
            $stmt->execute();
            $newId = $conn->insert_id;
            flash_set('Ad posted!');
            header('Location: ad.php?id=' . $newId);
            exit;
        }
    } else {
        // Re-show form with what the user already typed
        $ad = [
            'title' => $title, 'description' => $description, 'price' => $price,
            'negotiable' => $negotiable, 'condition' => $condition, 'location' => $location,
            'district' => $district, 'phone' => $phone, 'category_slug' => $categorySlug,
        ];
        $existingImages = $finalImages;
        $existingDetails = $detailsFinal;
    }
}

$page_title = $editId ? 'Edit your ad' : 'Post an ad';
include __DIR__ . '/includes/header.php';
?>

<div class="post-ad-page">
  <h1><?= $editId ? 'Edit your ad' : 'Post an ad' ?></h1>
  <p class="subtitle"><?= $editId ? 'Update the details below.' : "Fill in the details below — the more specific, the faster it sells." ?></p>

  <?php if ($errors): ?>
    <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="adForm">
    <div class="form-section">
      <h3>Category</h3>
      <div class="cat-select-grid">
        <?php foreach ($categories as $cat): ?>
          <label class="cat-select-btn <?= (($ad['category_slug'] ?? '') === $cat['slug']) ? 'selected' : '' ?>">
            <input type="radio" name="category_slug" value="<?= e($cat['slug']) ?>" <?= (($ad['category_slug'] ?? '') === $cat['slug']) ? 'checked' : '' ?> onchange="selectCatBtn(this)">
            <span class="cat-icon"><i class="<?= e($cat['icon']) ?>"></i></span><?= e($cat['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-section" id="dynamicFieldsSection" style="<?= empty($ad['category_slug']) ? 'display:none' : '' ?>">
      <h3>Item details</h3>
      <?php foreach ($categoryFieldsConfig as $slug => $fields): ?>
        <div class="dynamic-fields" data-category="<?= e($slug) ?>" style="<?= (($ad['category_slug'] ?? '') === $slug) ? '' : 'display:none' ?>">
          <div class="field-row dyn-field-row">
            <?php foreach ($fields as $fdef):
              $key = $fdef['key'];
              $val = $existingDetails[$key] ?? '';
            ?>
              <div class="field<?= $fdef['type'] === 'combo' ? ' combo-field' : '' ?>">
                <label><?= e($fdef['label']) ?><?= !empty($fdef['required']) ? ' *' : '' ?></label>
                <?php if ($fdef['type'] === 'select'): ?>
                  <select
                    name="details_<?= e($slug) ?>[<?= e($key) ?>]"
                    class="dyn-input"
                    data-key="<?= e($key) ?>"
                    <?= isset($fdef['cascade']) ? 'data-cascade="' . e($fdef['cascade']) . '"' : '' ?>
                    <?= !empty($fdef['required']) ? 'data-required="1"' : '' ?>
                  >
                    <option value="">Select</option>
                    <?php foreach ($fdef['options'] as $opt): ?>
                      <option value="<?= e($opt) ?>" <?= $val === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($fdef['type'] === 'combo'): ?>
                  <input
                    type="text"
                    name="details_<?= e($slug) ?>[<?= e($key) ?>]"
                    class="dyn-input"
                    data-key="<?= e($key) ?>"
                    <?= isset($fdef['cascade']) ? 'data-cascade="' . e($fdef['cascade']) . '"' : '' ?>
                    <?= !empty($fdef['required']) ? 'data-required="1"' : '' ?>
                    autocomplete="off"
                    placeholder="Type to search…"
                    value="<?= e($val) ?>"
                  >
                <?php elseif ($fdef['type'] === 'number'): ?>
                  <input type="number" name="details_<?= e($slug) ?>[<?= e($key) ?>]" class="dyn-input" data-key="<?= e($key) ?>" value="<?= e($val) ?>" <?= !empty($fdef['required']) ? 'data-required="1"' : '' ?>>
                <?php else: ?>
                  <input type="text" name="details_<?= e($slug) ?>[<?= e($key) ?>]" class="dyn-input" data-key="<?= e($key) ?>" value="<?= e($val) ?>" <?= !empty($fdef['required']) ? 'data-required="1"' : '' ?>>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="form-section">
      <h3>Photos <span style="font-weight:400;color:var(--text-muted);font-size:12.5px;">(up to 5)</span></h3>
      <div class="image-drop" id="dropZone">
        <div class="emoji">📷</div>
        <div>Drag photos here, or click to choose files</div>
      </div>
      <input type="file" id="fileInput" name="images[]" accept="image/*" multiple hidden>
      <div class="image-previews" id="imagePreviews">
        <?php foreach ($existingImages as $img): ?>
          <div class="image-preview" data-existing="<?= e($img) ?>">
            <img src="uploads/<?= e($img) ?>">
            <span class="existing-badge">saved</span>
            <button type="button" class="remove-img" onclick="removeExisting(this)">✕</button>
            <input type="hidden" name="keep_images[]" value="<?= e($img) ?>">
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-section">
      <h3>Details</h3>
      <div class="field">
        <label>Title</label>
        <input type="text" name="title" required maxlength="150" placeholder="e.g. iPhone 13 Pro, 128GB, mint condition" value="<?= e($ad['title'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Description</label>
        <textarea name="description" placeholder="Describe the item — condition, age, why you're selling, anything a buyer would want to know."><?= e($ad['description'] ?? '') ?></textarea>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Price (Rs.)</label>
          <input type="text" inputmode="numeric" id="priceInput" name="price" placeholder="Leave blank for 'Contact for price'" value="<?= e($ad['price'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Condition</label>
          <select name="condition">
            <option value="used" <?= (($ad['condition'] ?? 'used') === 'used') ? 'selected' : '' ?>>Used</option>
            <option value="new" <?= (($ad['condition'] ?? '') === 'new') ? 'selected' : '' ?>>Brand new</option>
          </select>
        </div>
      </div>
      <div class="field checkbox-field">
        <input type="checkbox" name="negotiable" id="fNegotiable" <?= !empty($ad['negotiable']) ? 'checked' : '' ?>>
        <label for="fNegotiable" style="margin:0;">Price is negotiable</label>
      </div>

      <div class="field-row">
        <div class="field location-field">
          <label>Location</label>
          <input type="text" id="locationInput" name="location" required autocomplete="off" placeholder="e.g. Colombo 05" value="<?= e($ad['location'] ?? '') ?>">
          <div class="location-suggestions" id="locationSuggestions"></div>
        </div>
        <div class="field">
          <label>District</label>
          <input type="text" id="districtInput" name="district" readonly placeholder="Auto-filled from location" value="<?= e($ad['district'] ?? '') ?>">
        </div>
      </div>

      <div class="field">
        <label>Contact Phone Number *</label>
        <input type="tel" name="phone" required placeholder="07XXXXXXXX" value="<?= e($ad['phone'] ?? '') ?>">
        <p class="field-hint">Buyers will see this number, with a call and WhatsApp button, on your ad.</p>
      </div>
    </div>

    <button class="btn-submit" type="submit"><?= $editId ? 'Save changes' : 'Post ad' ?></button>
  </form>
</div>

<script>
const CATEGORY_FIELDS   = <?= json_encode($categoryFieldsConfig) ?>;
const VEHICLE_MODELS    = <?= json_encode(vehicle_data()) ?>;
const MOBILE_MODELS     = <?= json_encode(mobile_data()) ?>;
const LOCATION_DATA     = <?= json_encode(location_data(), JSON_UNESCAPED_UNICODE) ?>;
const EXISTING_DETAILS  = <?= json_encode($existingDetails) ?>;
const CASCADE_SOURCES   = { vehicles: VEHICLE_MODELS, mobile: MOBILE_MODELS };

function selectCatBtn(radio) {
  document.querySelectorAll('.cat-select-btn').forEach(b => b.classList.remove('selected'));
  radio.closest('.cat-select-btn').classList.add('selected');
  showCategoryFields(radio.value);
}

function showCategoryFields(slug) {
  const section = document.getElementById('dynamicFieldsSection');
  const blocks = document.querySelectorAll('.dynamic-fields');
  let found = false;
  blocks.forEach(b => {
    if (b.dataset.category === slug) {
      b.style.display = '';
      found = true;
      // enable required attrs only on the visible block
      b.querySelectorAll('.dyn-input').forEach(inp => {
        if (inp.dataset.required === '1') inp.setAttribute('required', 'required');
      });
    } else {
      b.style.display = 'none';
      b.querySelectorAll('.dyn-input').forEach(inp => inp.removeAttribute('required'));
    }
  });
  section.style.display = found ? '' : 'none';
}

// Searchable "combo" dropdown for brand/model style fields (nicer + more reliable on
// mobile than the native <datalist>, which some mobile browsers render poorly).
function setupComboField(input, getOptions, onSelect) {
  const wrapper = input.closest('.field');
  if (!wrapper) return;
  let box = wrapper.querySelector('.combo-suggestions');
  if (!box) {
    box = document.createElement('div');
    box.className = 'combo-suggestions';
    wrapper.appendChild(box);
  }

  function render() {
    const q = input.value.trim().toLowerCase();
    const options = getOptions();
    const matches = (q === '' ? options : options.filter(o => o.toLowerCase().includes(q))).slice(0, 8);
    box.innerHTML = '';
    if (matches.length === 0) { box.style.display = 'none'; return; }
    matches.forEach(opt => {
      const item = document.createElement('div');
      item.className = 'combo-suggestion-item';
      item.textContent = opt;
      item.addEventListener('mousedown', (e) => {
        e.preventDefault();
        input.value = opt;
        box.style.display = 'none';
        if (onSelect) onSelect(opt);
        input.dispatchEvent(new Event('change'));
      });
      box.appendChild(item);
    });
    box.style.display = 'block';
  }

  input.addEventListener('focus', render);
  input.addEventListener('input', render);
  document.addEventListener('click', (e) => {
    if (!wrapper.contains(e.target)) box.style.display = 'none';
  });
}

document.querySelectorAll('.dynamic-fields').forEach(block => {
  const cat = block.dataset.category;
  const fieldDefs = CATEGORY_FIELDS[cat] || [];

  fieldDefs.forEach(fdef => {
    if (fdef.type !== 'combo') return;
    const input = block.querySelector('[data-key="' + fdef.key + '"]');
    if (!input) return;

    if (fdef.cascade) {
      // Model-style field: its suggestion list depends on the current brand value
      setupComboField(input, () => {
        const brandInput = block.querySelector('[data-key="' + fdef.cascade + '"]');
        const dataSource = CASCADE_SOURCES[cat];
        const brand = brandInput ? brandInput.value.trim() : '';
        return (dataSource && dataSource[brand]) ? dataSource[brand] : [];
      });
    } else {
      setupComboField(input, () => fdef.options || []);
    }
  });
});

// Location autocomplete -> auto-select district
const locationInput = document.getElementById('locationInput');
const districtInput = document.getElementById('districtInput');
const suggestionsBox = document.getElementById('locationSuggestions');
const cityNames = Object.keys(LOCATION_DATA);

locationInput.addEventListener('input', () => {
  const q = locationInput.value.trim().toLowerCase();
  suggestionsBox.innerHTML = '';
  if (q.length < 1) { suggestionsBox.style.display = 'none'; return; }
  const matches = cityNames.filter(c => c.toLowerCase().startsWith(q)).slice(0, 8);
  if (matches.length === 0) { suggestionsBox.style.display = 'none'; return; }
  matches.forEach(city => {
    const item = document.createElement('div');
    item.className = 'location-suggestion-item';
    item.textContent = city + ' — ' + LOCATION_DATA[city] + ' District';
    item.addEventListener('click', () => {
      locationInput.value = city;
      districtInput.value = LOCATION_DATA[city];
      suggestionsBox.innerHTML = '';
      suggestionsBox.style.display = 'none';
    });
    suggestionsBox.appendChild(item);
  });
  suggestionsBox.style.display = 'block';
});

document.addEventListener('click', (e) => {
  if (!e.target.closest('.location-field')) {
    suggestionsBox.style.display = 'none';
  }
});

// Price field — format with thousands commas as the user types
function formatPriceValue(raw) {
  const digits = (raw || '').replace(/[^\d]/g, '');
  if (digits === '') return '';
  return Number(digits).toLocaleString('en-US');
}
const priceInput = document.getElementById('priceInput');
if (priceInput) {
  priceInput.value = formatPriceValue(priceInput.value);
  priceInput.addEventListener('input', () => {
    const digitsBeforeCursor = priceInput.value.slice(0, priceInput.selectionStart).replace(/[^\d]/g, '').length;
    priceInput.value = formatPriceValue(priceInput.value);
    // Restore cursor position by counting digits back in from the left
    let pos = 0, seen = 0;
    while (pos < priceInput.value.length && seen < digitsBeforeCursor) {
      if (/\d/.test(priceInput.value[pos])) seen++;
      pos++;
    }
    priceInput.setSelectionRange(pos, pos);
  });
  priceInput.form.addEventListener('submit', () => {
    priceInput.value = priceInput.value.replace(/,/g, '');
  });
}

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const previews = document.getElementById('imagePreviews');
let stagedFiles = [];

dropZone.addEventListener('click', () => fileInput.click());
['dragover', 'dragleave', 'drop'].forEach(evt => {
  dropZone.addEventListener(evt, (e) => {
    e.preventDefault();
    dropZone.classList.toggle('dragover', evt === 'dragover');
  });
});
dropZone.addEventListener('drop', (e) => addFiles(e.dataTransfer.files));
fileInput.addEventListener('change', (e) => { addFiles(e.target.files); });

function countTotal() {
  return previews.querySelectorAll('.image-preview').length;
}

function addFiles(fileList) {
  const room = 5 - countTotal();
  if (room <= 0) { alert('You can add up to 5 photos.'); return; }
  Array.from(fileList).slice(0, room).forEach(f => {
    if (!f.type.startsWith('image/')) return;
    stagedFiles.push(f);
    const div = document.createElement('div');
    div.className = 'image-preview';
    div.innerHTML = `<img src="${URL.createObjectURL(f)}"><button type="button" class="remove-img">✕</button>`;
    div.querySelector('.remove-img').addEventListener('click', () => {
      stagedFiles = stagedFiles.filter(sf => sf !== f);
      div.remove();
      syncFileInput();
    });
    previews.appendChild(div);
  });
  syncFileInput();
}

function syncFileInput() {
  const dt = new DataTransfer();
  stagedFiles.forEach(f => dt.items.add(f));
  fileInput.files = dt.files;
}

function removeExisting(btn) {
  btn.closest('.image-preview').remove();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
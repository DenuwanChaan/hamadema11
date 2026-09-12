<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) { header('Location: index.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $phone === '' || $password === '') {
        $errors[] = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $phone, $hash);
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $conn->insert_id;
            flash_set("Welcome, " . explode(' ', $name)[0] . "!");
            $next = $_GET['next'] ?? $_POST['next'] ?? 'index.php';
            header('Location: ' . $next);
            exit;
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$page_title = 'Create an account';
include __DIR__ . '/includes/header.php';
?>

<div class="form-page">
  <div class="form-card">
    <h1>Create your account</h1>
    <p class="subtitle">It takes less than a minute — then you can post your first ad.</p>

    <?php if ($errors): ?>
      <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="post" action="register.php<?= isset($_GET['next']) ? '?next=' . urlencode($_GET['next']) : '' ?>">
      <div class="field">
        <label>Full name</label>
        <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Phone number</label>
        <input type="tel" name="phone" placeholder="07XXXXXXXX" required value="<?= e($_POST['phone'] ?? '') ?>">
        <p class="field-hint">Shown to buyers when they contact you about an ad.</p>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required minlength="6">
        <p class="field-hint">At least 6 characters.</p>
      </div>
      <button class="btn-submit" type="submit">Create account</button>
    </form>
    <p class="form-footer-note">Already have an account? <a href="login.php">Log in</a></p>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

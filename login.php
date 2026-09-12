<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) { header('Location: index.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $errors[] = 'Email or password is incorrect.';
    } else {
        $_SESSION['user_id'] = $user['id'];
        $next = $_GET['next'] ?? $_POST['next'] ?? 'index.php';
        header('Location: ' . $next);
        exit;
    }
}

$page_title = 'Log in';
include __DIR__ . '/includes/header.php';
?>

<div class="form-page">
  <div class="form-card">
    <h1>Welcome back</h1>
    <p class="subtitle">Log in to post ads and view seller contact details.</p>

    <?php if ($errors): ?>
      <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php<?= isset($_GET['next']) ? '?next=' . urlencode($_GET['next']) : '' ?>">
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button class="btn-submit" type="submit">Log in</button>
    </form>
    <p class="form-footer-note">New to Hamadema? <a href="register.php">Create an account</a></p>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

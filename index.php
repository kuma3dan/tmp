<?php
declare(strict_types=1);
$errorMessage = isset($_GET['error']) ? (string)$_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/CSS/style.css">
    <title>ログイン</title>
</head>
<body>
    <main>
        <h1>ログイン</h1>
        <form id="loginForm" action="login.php" method="post" novalidate>
            <div class="form-row">
                <label for="username">ユーザーID</label>
                <input type="text" id="username" name="username" autocomplete="username" required>
            </div>
            <div class="form-row">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <div class="form-row">
                <button type="submit" id="loginSubmit">ログイン</button>
            </div>
            <p class="form-help">アカウントをお持ちでない場合は <a href="register.php">新規登録</a></p>
<?php if ($errorMessage !== ''): ?>
            <div id="loginError" class="error" aria-live="polite"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
<?php else: ?>
            <div id="loginError" class="error" aria-live="polite" hidden></div>
<?php endif; ?>
        </form>
    </main>
    <script src="assets/JS/script.js"></script>
</body>
</html>


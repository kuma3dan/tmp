<?php
declare(strict_types=1);

require_once __DIR__ . '/DBC.php';
require_once __DIR__ . '/auth.php';

function badRequest(string $message, array $context = []): void {
    $detail = '[login.php/badRequest] ' . $message . ' context=' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log($detail);
    $redirect = 'index.php?error=' . rawurlencode($message);
    header('Location: ' . $redirect);
    exit;
}

function toIntString(string $value): string {
    return preg_replace('/\D+/', '', $value) ?? '';
}

function verifyPasswordFlexible(string $inputPassword, string $storedValue): bool {
    $looksHashed = str_starts_with($storedValue, '$2y$') || str_starts_with($storedValue, '$argon2i$') || str_starts_with($storedValue, '$argon2id$');
    if ($looksHashed) {
        return password_verify($inputPassword, $storedValue);
    }
    return hash_equals($storedValue, $inputPassword);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    badRequest('不正なリクエストメソッドです。', ['method' => $_SERVER['REQUEST_METHOD'] ?? '']);
}

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    badRequest('ユーザーIDとパスワードを入力してください。', ['username' => $username === '' ? 'empty' : 'provided', 'password' => $password === '' ? 'empty' : 'provided']);
}

$stdNoDigits = toIntString($username);
if ($stdNoDigits === '') {
    badRequest('ユーザーIDの形式が正しくありません（数字のみ）。', ['username' => $username]);
}

try {
    $sql = 'SELECT id, std_no, std_name, std_pass FROM _kiis_student WHERE std_no = :std_no LIMIT 1';
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(':std_no', (int)$stdNoDigits, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user) {
        badRequest('ユーザーIDまたはパスワードが正しくありません。', ['std_no' => $stdNoDigits, 'found' => false]);
    }

    $storedPass = (string)$user['std_pass'];
    if (!verifyPasswordFlexible($password, $storedPass)) {
        badRequest('ユーザーIDまたはパスワードが正しくありません。', ['std_no' => $stdNoDigits, 'found' => true, 'password' => 'mismatch']);
    }

    loginUser([
        'id' => (int)$user['id'],
        'std_no' => (int)$user['std_no'],
        'std_name' => (string)$user['std_name'],
    ]);

    header('Location: search.php');
    exit;
} catch (Throwable $e) {
    $message = '[login.php] 認証処理中にエラー: class=' . get_class($e) .
        ', message=' . $e->getMessage() .
        ', args=' . json_encode(['username' => $username], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    error_log($message);
    badRequest('システムエラーが発生しました。時間をおいて再度お試しください。');
}


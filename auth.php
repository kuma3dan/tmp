<?php
declare(strict_types=1);

function startSessionIfNotStarted(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    startSessionIfNotStarted();
    return isset($_SESSION['userId'], $_SESSION['stdNo'], $_SESSION['stdName']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $redirect = 'index.php';
        header('Location: ' . $redirect);
        exit;
    }
}

function loginUser(array $user): void {
    startSessionIfNotStarted();
    session_regenerate_id(true);
    $_SESSION['userId'] = (int)$user['id'];
    $_SESSION['stdNo'] = (int)$user['std_no'];
    $_SESSION['stdName'] = (string)$user['std_name'];
    $_SESSION['loginAt'] = (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);
}

function logoutUser(): void {
    startSessionIfNotStarted();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function getLoggedInUser(): array {
    startSessionIfNotStarted();
    if (!isLoggedIn()) {
        return [];
    }
    return [
        'userId' => (int)$_SESSION['userId'],
        'stdNo' => (int)$_SESSION['stdNo'],
        'stdName' => (string)$_SESSION['stdName'],
        'loginAt' => (string)$_SESSION['loginAt'],
    ];
}


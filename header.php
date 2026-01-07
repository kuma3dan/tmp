<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app_config.php';
startSessionIfNotStarted();
$user = getLoggedInUser();
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
function isActive(string $file, string $current): bool {
    return strtolower($file) === strtolower($current);
}

// 予約件数/上限（太宰府）
$quotaText = '';
try {
    if (!empty($user)) {
        if (!isset($dbh)) { require_once __DIR__ . '/DBC.php'; }
        $stmt = $dbh->prepare('SELECT COUNT(*) FROM reservation WHERE user_id = :uid AND reservariton_status = 0');
        $stmt->execute([':uid' => (int)$user['userId']]);
        $count = (int)$stmt->fetchColumn();
        $limit = getMaxReservationsDefault($dbh);
        $quotaText = sprintf('予約 %d/%d', $count, $limit);
    }
} catch (Throwable $e) {
    $quotaText = '';
}
?>
<nav class="site-nav" data-open="false">
    <div class="nav-inner">
        <div class="nav-left">
            <a class="nav-brand" href="search.php">Biblio</a>
            <button class="nav-toggle" type="button" aria-label="メニュー" aria-expanded="false" aria-controls="navLinks">☰</button>
            <ul class="nav-links" id="navLinks">
                <li>
                    <a class="nav-link<?php echo isActive('search.php', $current) ? ' active' : ''; ?>" href="search.php" <?php echo isActive('search.php', $current) ? 'aria-current="page"' : ''; ?>>検索</a>
                </li>
                <li>
                    <a class="nav-link<?php echo isActive('reservation_list.php', $current) ? ' active' : ''; ?>" href="reservation_list.php" <?php echo isActive('reservation_list.php', $current) ? 'aria-current="page"' : ''; ?>>予約一覧</a>
                </li>
                <li>
                    <a class="nav-link<?php echo isActive('H_reservation.php', $current) ? ' active' : ''; ?>" href="H_reservation.php" <?php echo isActive('H_reservation.php', $current) ? 'aria-current="page"' : ''; ?>>博多キャンパス</a>
                </li>
            </ul>
        </div>
        <div class="nav-right">
            <?php if (!empty($user)): ?>
                <?php if ($quotaText !== ''): ?>
                <span class="nav-user"><?php echo htmlspecialchars($quotaText, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <span class="nav-user"><?php echo htmlspecialchars((string)$user['stdNo']); ?> <?php echo htmlspecialchars((string)$user['stdName']); ?></span>
                <a class="nav-logout" href="logout.php">ログアウト</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<script>
(function() {
    var nav = document.querySelector('.site-nav');
    var toggle = document.querySelector('.nav-toggle');
    if (!nav || !toggle) return;
    toggle.addEventListener('click', function() {
        var open = nav.getAttribute('data-open') === 'true';
        var next = !open;
        nav.setAttribute('data-open', next ? 'true' : 'false');
        toggle.setAttribute('aria-expanded', next ? 'true' : 'false');
    });
})();
</script>


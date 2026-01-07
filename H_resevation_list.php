<?php
require_once 'DBC.php';
require_once 'auth.php';
requireLogin();
$user = getLoggedInUser();
$userId = (int)$user['userId'];
$stdNo = $user['stdNo'];
$stdName = $user['stdName'];

$errorMessage = '';
$results = [];
$totalCount = 0;
$page = 1;
$pageSize = 20;

function ensureReservationTableExists(PDO $dbh) {
	$sql = "CREATE TABLE IF NOT EXISTS h_reservation (
		id INT(11) NOT NULL AUTO_INCREMENT,
		h_book_id INT(11) NOT NULL,
		isbn VARCHAR(20) NOT NULL,
		reserver_name VARCHAR(50) NOT NULL,
		copies INT(11) NOT NULL DEFAULT 1,
		note VARCHAR(255) DEFAULT NULL,
		status INT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
	$dbh->exec($sql);
}

try {
	$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, intval($_GET['page'])) : 1;

	$countSql = "SELECT COUNT(*) FROM h_reservation WHERE user_id = :user_id AND reservariton_status = 0";
	$countStmt = $dbh->prepare($countSql);
	$countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
	$countStmt->execute();
	$totalCount = intval($countStmt->fetchColumn());

	$offset = ($page - 1) * $pageSize;

	$listSql = "SELECT
		r.id,
		r.book_id,
		r.reservation_date,
		r.return_date,
		r.reservariton_status,
		r.`date`,
		b.title,
		b.isbn
	FROM h_reservation r
	LEFT JOIN H_book b ON b.id = r.book_id
	WHERE r.user_id = :user_id
	  AND r.reservariton_status = 0
	ORDER BY r.`date` DESC, r.id DESC
	LIMIT :limit OFFSET :offset";
	$stmt = $dbh->prepare($listSql);
	$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
	$stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
	$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
	$stmt->execute();
	$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $exception) {
	$errorMessage = sprintf('エラー: %s', htmlspecialchars($exception->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>貸し出し中の本</title>
	<link rel="stylesheet" href="assets/CSS/style.css">
</head>
<body>
	<?php include 'header_hakata.php'; ?>
	<div class="container">


		<?php if (!empty($errorMessage)): ?>
			<div class="message error-message">
				<strong>エラー:</strong><br>
				<?php echo $errorMessage; ?>
			</div>
		<?php endif; ?>

		<div class="list-container">
			<h2>貸し出し中の本</h2>
			<p>件数: <?php echo number_format($totalCount); ?> 件</p>
			<?php if ($totalCount > 0): ?>
				<table class="table">
					<thead>
						<tr>
							<th>申請日</th>
							<th>タイトル</th>
							<th>ISBN</th>
							<th>貸出日</th>
							<th>返却予定日</th>
							<!-- <th>状態</th> -->
						</tr>
					</thead>
					<tbody>
						<?php foreach ($results as $row): ?>
							<tr>
								<td><?php echo htmlspecialchars($row['date']); ?></td>
								<td><?php echo htmlspecialchars($row['title']); ?></td>
								<td><?php echo htmlspecialchars($row['isbn']); ?></td>
								<td><?php echo htmlspecialchars($row['reservation_date']); ?></td>
								<td><?php echo htmlspecialchars($row['return_date']); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php
					$totalPages = (int)ceil($totalCount / $pageSize);
					if ($totalPages < 1) { $totalPages = 1; }
					$queryBase = $_GET;
					echo '<div class="pagination">';
					if ($page > 1) {
						$queryBase['page'] = $page - 1;
						echo '<a class="page-link" href="H_resevation_list.php?' . htmlspecialchars(http_build_query($queryBase), ENT_QUOTES, 'UTF-8') . '">前へ</a>';
					}
					echo '<span class="page-info">' . $page . ' / ' . $totalPages . '</span>';
					if ($page < $totalPages) {
						$queryBase['page'] = $page + 1;
						echo '<a class="page-link" href="H_resevation_list.php?' . htmlspecialchars(http_build_query($queryBase), ENT_QUOTES, 'UTF-8') . '">次へ</a>';
					}
					echo '</div>';
				?>
			<?php else: ?>
				<p>貸出はありません。</p>
			<?php endif; ?>
			<div class="form-actions" style="margin-top:16px;">
				<a href="search.php" class="reset-button" style="text-decoration:none;display:inline-block;text-align:center;">検索へ</a>
				<a href="reservation.php" class="reset-button" style="text-decoration:none;display:inline-block;text-align:center;">予約へ</a>
			</div>
		</div>
	</div>
</body>
</html>



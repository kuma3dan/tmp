<?php
require_once 'DBC.php';
require_once 'auth.php';
requireLogin();
$user = getLoggedInUser();
$stdNo = $user['stdNo'];
$stdName = $user['stdName'];

$errorMessage = '';
$results = [];
$totalCount = 0;
$page = 1;
$pageSize = 20;

function normalizeIsbn($isbn) {
	return preg_replace('/[^0-9]/', '', $isbn);
}

function isbn10To13($isbn10Digits) {
	$digits = preg_replace('/[^0-9]/', '', $isbn10Digits);
	if (strlen($digits) !== 10) {
		return $digits;
	}
	$body9 = substr($digits, 0, 9);
	$ean12 = '978' . $body9;
	$sum = 0;
	for ($i = 0; $i < 12; $i++) {
		$d = intval($ean12[$i]);
		$sum += ($i % 2 === 0) ? $d : ($d * 3);
	}
	$check = (10 - ($sum % 10)) % 10;
	return $ean12 . strval($check);
}

try {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $isbn = isset($_GET['isbn']) ? trim($_GET['isbn']) : '';
    $status = isset($_GET['status']) && in_array($_GET['status'], ['0', '1'], true) ? $_GET['status'] : '';
    $page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, intval($_GET['page'])) : 1;

	$where = [];
	$params = [];

	if ($q !== '') {
		$where[] = '(title LIKE :kw1 OR author LIKE :kw2 OR publisher LIKE :kw3)';
		$params[':kw1'] = "%$q%";
		$params[':kw2'] = "%$q%";
		$params[':kw3'] = "%$q%";
	}

	if ($isbn !== '') {
		$normalized = normalizeIsbn($isbn);
		$where[] = "REPLACE(isbn, '-', '') = :isbn";
		$params[':isbn'] = $normalized;
	}

	if ($status !== '') {
		$where[] = 'status = :status';
		$params[':status'] = intval($status);
	}

	$whereSql = count($where) > 0 ? ('WHERE ' . implode(' AND ', $where)) : '';

	$countSql = "SELECT COUNT(*) AS c FROM H_book $whereSql";
	$countStmt = $dbh->prepare($countSql);
	foreach ($params as $key => $val) {
		$countStmt->bindValue($key, $val);
	}
	$countStmt->execute();
	$totalCount = intval($countStmt->fetchColumn());

	$offset = ($page - 1) * $pageSize;

	$listSql = "SELECT id, title, isbn, author, publisher, publication_date, copies, status FROM H_book $whereSql ORDER BY publication_date DESC, title ASC LIMIT :limit OFFSET :offset";
	$listStmt = $dbh->prepare($listSql);
	foreach ($params as $key => $val) {
		$listStmt->bindValue($key, $val);
	}
	$listStmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
	$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
	$listStmt->execute();
	$results = $listStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $exception) {
	$errorMessage = sprintf('検索エラー: %s', $exception->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>書籍検索</title>
	<link rel="stylesheet" href="assets/CSS/style.css">
</head>
<body>
	<?php include 'header_hakata.php'; ?>
	<div class="container">
		<!-- <header class="page-header">
			<h1>Search</h1>
			<p class="subtitle">登録済み書籍の検索</p>
		</header> -->

		<?php if (!empty($errorMessage)): ?>
			<div class="message error-message">
				<strong>エラー:</strong><br>
				<?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
			</div>
		<?php endif; ?>

		<div class="form-container">
			<h2>検索条件</h2>
			<form method="get" action="search.php">
				<div class="form-group">
					<label for="q">キーワード</label>
					<input type="text" id="q" name="q" placeholder="タイトル・著者・出版社" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q'], ENT_QUOTES, 'UTF-8') : ''; ?>">
				</div>
				<div class="form-group">
					<label for="isbn">ISBN</label>
					<input type="text" id="isbn" name="isbn" placeholder="ハイフンあり・なし両方可" value="<?php echo isset($_GET['isbn']) ? htmlspecialchars($_GET['isbn'], ENT_QUOTES, 'UTF-8') : ''; ?>">
				</div>
				<div class="form-group">
					<label for="status">状態</label>
					<select id="status" name="status">
						<option value="" <?php echo (!isset($_GET['status']) || $_GET['status'] === '') ? 'selected' : ''; ?>>すべて</option>
						<option value="0" <?php echo (isset($_GET['status']) && $_GET['status'] === '0') ? 'selected' : ''; ?>>貸出許可</option>
						<option value="1" <?php echo (isset($_GET['status']) && $_GET['status'] === '1') ? 'selected' : ''; ?>>禁帯出</option>
					</select>
				</div>
				<div class="form-actions">
					<button type="submit" class="submit-button">検索</button>
					<a href="search.php" class="reset-button" style="text-decoration:none;display:inline-block;text-align:center;">クリア</a>
				</div>
			</form>
		</div>

		<div class="list-container">
			<h2>検索結果</h2>
			<p>件数: <?php echo number_format($totalCount); ?> 件</p>
			<?php if ($totalCount > 0): ?>
				<table class="table">
					<thead>
						<tr>
							
							<th>画像</th>
							<th>タイトル</th>
							<th>状態</th>
							<th>操作</th>
							<th>ISBN</th>
							<th>著者</th>
							<th>出版社</th>
							<th>発刊日</th>
							<th>本数</th>
							
						</tr>
					</thead>
					<tbody>
						<?php foreach ($results as $row): ?>
							<tr>
								

								<td>
									<?php
										$digits = normalizeIsbn($row['isbn']);
										if (strlen($digits) === 10) {
											$digits = isbn10To13($digits);
										}
										$coverUrl = 'https://cover.openbd.jp/' . $digits . '.jpg';
										$googleUrl = 'https://books.google.com/books/content?vid=ISBN' . $digits . '&printsec=frontcover&img=1&zoom=1';
									?>
									<img
										src="<?php echo htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8'); ?>"
										alt="書影"
										style="width:64px;height:auto;object-fit:contain;border:1px solid #eee;border-radius:4px;background:#fff;"
										loading="lazy"
										onerror="this.onerror=null; this.src='<?php echo htmlspecialchars($googleUrl, ENT_QUOTES, 'UTF-8'); ?>'; this.onerror=function(){ this.style.display='none'; };"
									>
								</td>
								<td><?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?php echo intval($row['status']) === 1 ? '禁帯出' : '貸出許可'; ?></td>
								<td>
									<?php if (intval($row['status']) !== 1): ?>
										<a href="H_reservation.php?isbn=<?php echo urlencode($row['isbn']); ?>" class="reserve-button">貸出</a>
									<?php else: ?>
										<span style="color:#999;">—</span>
									<?php endif; ?>
								</td>
								<td><?php echo htmlspecialchars($row['isbn'], ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?php echo htmlspecialchars($row['author'], ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?php echo htmlspecialchars($row['publisher'], ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?php echo htmlspecialchars($row['publication_date'], ENT_QUOTES, 'UTF-8'); ?></td>
								<td style="text-align:right;"><?php echo intval($row['copies']); ?></td>
								
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
						echo '<a class="page-link" href="search.php?' . htmlspecialchars(http_build_query($queryBase), ENT_QUOTES, 'UTF-8') . '">前へ</a>';
					}
					echo '<span class="page-info">' . $page . ' / ' . $totalPages . '</span>';
					if ($page < $totalPages) {
						$queryBase['page'] = $page + 1;
						echo '<a class="page-link" href="search.php?' . htmlspecialchars(http_build_query($queryBase), ENT_QUOTES, 'UTF-8') . '">次へ</a>';
					}
					echo '</div>';
				?>
			<?php else: ?>
				<p>該当する書籍は見つかりませんでした。</p>
			<?php endif; ?>
		</div>
	</div>
</body>
</html>



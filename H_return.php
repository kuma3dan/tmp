<?php
declare(strict_types=1);

require_once __DIR__ . '/DBC.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$user = getLoggedInUser();
$userId = (int)$user['userId'];
$stdNo = $user['stdNo'];
$stdName = $user['stdName'];

$errorMessage = '';
$successMessage = '';
$foundBook = null;
$messageHideMs = 5000;

function normalizeIsbnDigits($isbn) {
	return preg_replace('/[^0-9]/', '', (string)$isbn);
}

function findBookByIsbnDigits(PDO $dbh, $isbn) {
	$digits = normalizeIsbnDigits($isbn);
	$sql = "SELECT id, title, isbn, author, publisher, publication_date, copies, status
		FROM H_book
		WHERE REPLACE(REPLACE(isbn, '-', ''), ' ', '') = :isbn
		LIMIT 1";
	$stmt = $dbh->prepare($sql);
	$stmt->execute([':isbn' => $digits]);
	return $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$isbn = isset($_POST['isbn']) ? trim($_POST['isbn']) : '';

		$errors = [];
		if ($isbn === '') {
			$errors[] = 'ISBNは必須です。';
		} else {
			$digits = normalizeIsbnDigits($isbn);
			if (!(strlen($digits) === 10 || strlen($digits) === 13)) {
				$errors[] = sprintf('ISBN形式が不正です。入力: %s (数字のみ: %s)', htmlspecialchars($isbn), htmlspecialchars($digits));
			}
		}

		if (count($errors) === 0) {
			$book = findBookByIsbnDigits($dbh, $isbn);
			if (!$book) {
				throw new Exception(sprintf('返却対象の書籍が見つかりません。関数: findBookByIsbnDigits, 引数: isbn=%s', htmlspecialchars($isbn)));
			}

			$dbh->beginTransaction();
			try {
				// ユーザの未返却貸出をロックして取得（最も古いものを優先）
				$selectSql = "SELECT id, book_id, user_id, reservation_date, return_date, reservariton_status
					FROM h_reservation
					WHERE book_id = :book_id
					  AND user_id = :user_id
					  AND reservariton_status = 0
					ORDER BY reservation_date ASC, id ASC
					LIMIT 1
					FOR UPDATE";
				$selectStmt = $dbh->prepare($selectSql);
				$selectStmt->execute([
					':book_id' => (int)$book['id'],
					':user_id' => $userId
				]);
				$active = $selectStmt->fetch(PDO::FETCH_ASSOC);
				if (!$active) {
					throw new Exception(sprintf(
						'未返却の予約が見つかりません。関数: return, 引数: book_id=%d, user_id=%d',
						(int)$book['id'],
						$userId
					));
				}

				$updateSql = "UPDATE h_reservation
					SET reservariton_status = 1
					WHERE id = :id";
				$updateStmt = $dbh->prepare($updateSql);
				$updateStmt->execute([':id' => (int)$active['id']]);

				$dbh->commit();

                $successMessage = '返却が完了しました。';
				$_POST = [];
				$foundBook = $book;
			} catch (Throwable $t) {
				if ($dbh->inTransaction()) {
					$dbh->rollBack();
				}
				throw $t;
			}
		} else {
			$errorMessage = implode('<br>', $errors);
		}
	} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['isbn']) && trim($_GET['isbn']) !== '') {
		$foundBook = findBookByIsbnDigits($dbh, $_GET['isbn']);
		if (!$foundBook) {
			$errorMessage = sprintf('指定のISBNは登録されていません。ISBN: %s', htmlspecialchars($_GET['isbn']));
		}
	}
} catch (Exception $exception) {
	$errorMessage = sprintf('エラー: %s', htmlspecialchars($exception->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>返却</title>
	<link rel="stylesheet" href="assets/CSS/style.css">
</head>
<body>
	<?php include __DIR__ . '/header_hakata.php'; ?>
	<div class="container">
		<?php if (!empty($errorMessage)): ?>
			<div class="message error-message">
				<strong>エラー:</strong><br>
				<?php echo $errorMessage; ?>
			</div>
		<?php endif; ?>

		<?php if (!empty($successMessage)): ?>
			<div class="message success-message">
				<strong>成功:</strong><br>
				<?php echo $successMessage; ?>
			</div>
		<?php endif; ?>

		<div class="form-container">
			<h2>返却</h2>
			<form method="POST" action="">
				<div class="form-group">
					<label for="isbn">ISBN <span class="required">*</span></label>
					<input
						type="text"
						id="isbn"
						name="isbn"
						placeholder="バーコードをスキャン or 手入力（ハイフンあり・なし両方可）"
						value="<?php echo isset($_GET['isbn']) ? htmlspecialchars($_GET['isbn']) : (isset($_POST['isbn']) ? htmlspecialchars($_POST['isbn']) : ''); ?>"
						autocomplete="off"
						required
					>
					<div id="isbnStatus" class="helper-text"></div>
				</div>

				

				<div class="form-actions">
					<button type="submit" class="submit-button">返却する</button>
					<a href="H_reservation.php" class="reset-button" style="text-decoration:none;display:inline-block;text-align:center;">貸出へ</a>
				</div>
			</form>
		</div>
	</div>
</body>
</html>


<script>
document.addEventListener('DOMContentLoaded', function() {
    var hideMs = <?php echo (int)$messageHideMs; ?>;
    var messages = document.querySelectorAll('.message');
    if (messages && messages.length > 0) {
        setTimeout(function() {
            messages.forEach(function(el) {
                el.style.transition = 'opacity 0.5s ease-out';
                el.style.opacity = '0';
                setTimeout(function() {
                    el.style.display = 'none';
                }, 500);
            });
        }, hideMs);
    }
});
</script>

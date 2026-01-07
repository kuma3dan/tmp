<?php
require_once 'DBC.php';
require_once 'auth.php';
require_once 'app_config.php';
requireLogin();
$user = getLoggedInUser();
$userId = (int)$user['userId'];
$stdNo = $user['stdNo'];
$stdName = $user['stdName'];

$errorMessage = '';
$successMessage = '';
$bookInfo = null;

function ensureReservationTableExists(PDO $dbh) {
	$sql = "CREATE TABLE IF NOT EXISTS reservation (
		id INT(11) NOT NULL AUTO_INCREMENT,
		book_id INT(11) NOT NULL,
		isbn VARCHAR(20) NOT NULL,
		reserver_name VARCHAR(50) NOT NULL,
		copies INT(11) NOT NULL DEFAULT 1,
		reservation_start_date DATE NOT NULL,
		status INT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
	$dbh->exec($sql);
}

function ensureReservationSchema(PDO $dbh) {
	// reservation_start_date が無ければ追加
	$checkStartDateSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservation' AND COLUMN_NAME = 'reservation_start_date'";
	$existsStartDate = $dbh->query($checkStartDateSql)->fetchColumn();
	if (intval($existsStartDate) === 0) {
		$dbh->exec("ALTER TABLE reservation ADD COLUMN reservation_start_date DATE NOT NULL AFTER copies");
	}

	// note カラムが存在すれば削除
	$checkNoteSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservation' AND COLUMN_NAME = 'note'";
	$existsNote = $dbh->query($checkNoteSql)->fetchColumn();
	if (intval($existsNote) > 0) {
		$dbh->exec("ALTER TABLE reservation DROP COLUMN note");
	}

	// copies が無ければ追加
	$checkCopiesSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservation' AND COLUMN_NAME = 'copies'";
	$existsCopies = $dbh->query($checkCopiesSql)->fetchColumn();
	if (intval($existsCopies) === 0) {
		$dbh->exec("ALTER TABLE reservation ADD COLUMN copies INT(11) NOT NULL DEFAULT 1 AFTER reserver_name");
	}

	// isbn が無ければ追加
	$checkIsbnSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservation' AND COLUMN_NAME = 'isbn'";
	$existsIsbn = $dbh->query($checkIsbnSql)->fetchColumn();
	if (intval($existsIsbn) === 0) {
		$dbh->exec("ALTER TABLE reservation ADD COLUMN isbn VARCHAR(20) NOT NULL AFTER book_id");
	}

	// reserver_name が無ければ追加
	$checkReserverSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservation' AND COLUMN_NAME = 'reserver_name'";
	$existsReserver = $dbh->query($checkReserverSql)->fetchColumn();
	if (intval($existsReserver) === 0) {
		$dbh->exec("ALTER TABLE reservation ADD COLUMN reserver_name VARCHAR(50) NOT NULL AFTER isbn");
	}

	// status が無ければ追加
	$checkStatusSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservation' AND COLUMN_NAME = 'status'";
	$existsStatus = $dbh->query($checkStatusSql)->fetchColumn();
	if (intval($existsStatus) === 0) {
		$dbh->exec("ALTER TABLE reservation ADD COLUMN status INT(1) NOT NULL DEFAULT 0 AFTER reservation_start_date");
	}

	// created_at が無ければ追加
	$checkCreatedAtSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservation' AND COLUMN_NAME = 'created_at'";
	$existsCreatedAt = $dbh->query($checkCreatedAtSql)->fetchColumn();
	if (intval($existsCreatedAt) === 0) {
		$dbh->exec("ALTER TABLE reservation ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status");
	}
}

function normalizeIsbn($isbn) {
	return preg_replace('/[^0-9]/', '', $isbn);
}

function findBookByIsbn(PDO $dbh, $isbn) {
	$normalized = normalizeIsbn($isbn);
	$sql = "SELECT id, title, isbn, author, publisher, publication_date, copies, status FROM book WHERE REPLACE(isbn, '-', '') = :isbn ORDER BY id ASC LIMIT 1";
	$stmt = $dbh->prepare($sql);
	$stmt->execute([':isbn' => $normalized]);
	return $stmt->fetch(PDO::FETCH_ASSOC);
}

function validateReservation($post) {
	$errors = [];
	if (empty($post['isbn'])) {
		$errors[] = 'ISBNは必須です。';
	} else {
		$digits = preg_replace('/[^0-9]/', '', $post['isbn']);
		if (!(strlen($digits) === 10 || strlen($digits) === 13)) {
			$errors[] = sprintf('ISBN形式が不正です。入力: %s (数字のみ: %s)', htmlspecialchars($post['isbn']), htmlspecialchars($digits));
		}
	}
	if (!isset($post['reserveCopies']) || !is_numeric($post['reserveCopies']) || intval($post['reserveCopies']) < 1) {
		$errors[] = sprintf('予約冊数は1以上の数値で入力してください。入力: %s', htmlspecialchars(isset($post['reserveCopies']) ? $post['reserveCopies'] : ''));
	}
	if (empty($post['reservationStartDate'])) {
		$errors[] = '予約開始日は必須です。';
	} else {
		$startDate = DateTime::createFromFormat('Y-m-d', $post['reservationStartDate']);
		$formatOk = $startDate && $startDate->format('Y-m-d') === $post['reservationStartDate'];
		if (!$formatOk) {
			$errors[] = sprintf('予約開始日の形式が不正です。入力: %s (期待形式: YYYY-MM-DD)', htmlspecialchars($post['reservationStartDate']));
		} else {
			$today = new DateTime('today');
			if ($startDate < $today) {
				$errors[] = sprintf('予約開始日は本日以降の日付を指定してください。入力: %s', htmlspecialchars($post['reservationStartDate']));
			}
		}
	}
	if (isset($post['userId']) && $post['userId'] !== '') {
		if (!is_numeric($post['userId']) || intval($post['userId']) < 1) {
			$errors[] = sprintf('予約者IDは1以上の数値で入力してください。入力: %s', htmlspecialchars($post['userId']));
		}
	}
	return $errors;
}

try {
	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		$isbnParam = isset($_GET['isbn']) ? trim($_GET['isbn']) : '';
		if ($isbnParam !== '') {
			$bookInfo = findBookByIsbn($dbh, $isbnParam);
			if (!$bookInfo) {
				$errorMessage = sprintf('指定のISBNは登録されていません。ISBN: %s', htmlspecialchars($isbnParam));
			}
		}
	} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$validationErrors = validateReservation($_POST);
		if (count($validationErrors) > 0) {
			$errorMessage = implode('<br>', $validationErrors);
		} else {
			// 予約開始日の許容範囲（コンフィグ）チェック
			$startOffsetMin = getStartOffsetMinDefault($dbh);
			$startOffsetMax = getStartOffsetMaxDefault($dbh);
			$minDate = (new DateTime('today'))->modify('+' . $startOffsetMin . ' days')->format('Y-m-d');
			$maxDate = (new DateTime('today'))->modify('+' . $startOffsetMax . ' days')->format('Y-m-d');
			$startDateStr = trim($_POST['reservationStartDate']);
			if ($startDateStr < $minDate || $startDateStr > $maxDate) {
				$errorMessage = sprintf('予約開始日は %s から %s の間で指定してください。', htmlspecialchars($minDate), htmlspecialchars($maxDate));
			}
		}
		if ($errorMessage === '') {
			$book = findBookByIsbn($dbh, $_POST['isbn']);
			if (!$book) {
				throw new Exception(sprintf('予約対象の書籍が見つかりません。関数: reservation, 引数: isbn=%s', htmlspecialchars($_POST['isbn'])));
			}
			if (isset($book['status']) && intval($book['status']) === 1) {
				throw new Exception(sprintf(
					'禁帯出のため予約できません。関数: reservation, 引数: isbn=%s, book_id=%d, status=%d',
					htmlspecialchars($_POST['isbn']),
					intval($book['id']),
					intval($book['status'])
				));
			}

			// ダブルブッキング防止（在庫数とユーザ重複）
			$newStart = trim($_POST['reservationStartDate']);
			$loanDays = getLoanDaysDefault($dbh);
			$newEnd = (new DateTime($newStart))->modify('+' . $loanDays . ' days')->format('Y-m-d');

			$dbh->beginTransaction();
			try {
				// ユーザの予約上限チェック
				$maxReservations = getMaxReservationsDefault($dbh);
				$userActiveCountSql = "SELECT COUNT(*) FROM reservation WHERE user_id = :user_id AND reservariton_status = 0 FOR UPDATE";
				$userActiveStmt = $dbh->prepare($userActiveCountSql);
				$userActiveStmt->execute([':user_id' => $userId]);
				$userActiveCount = (int)$userActiveStmt->fetchColumn();
				if ($userActiveCount >= $maxReservations) {
					throw new Exception(sprintf(
						'予約上限に達しています。関数: reservation, 引数: user_id=%d, 上限=%d, 現在=%d',
						$userId, $maxReservations, $userActiveCount
					));
				}

				// 書籍ロック（在庫確認のため）
				$lockBookSql = "SELECT id, copies, status FROM book WHERE id = :book_id FOR UPDATE";
				$lockBookStmt = $dbh->prepare($lockBookSql);
				$lockBookStmt->execute([':book_id' => intval($book['id'])]);
				$lockedBook = $lockBookStmt->fetch(PDO::FETCH_ASSOC);
				if (!$lockedBook) {
					throw new Exception(sprintf('書籍情報のロックに失敗しました。関数: reservation, 引数: book_id=%d', intval($book['id'])));
				}
				$copies = isset($lockedBook['copies']) ? intval($lockedBook['copies']) : 0;
				if ($copies <= 0) {
					throw new Exception(sprintf('在庫がないため予約できません。関数: reservation, 引数: book_id=%d, copies=%d', intval($book['id']), $copies));
				}

				// ユーザの重複予約チェック（同じ書籍・期間重複）
				$userDupSql = "SELECT id FROM reservation
					WHERE book_id = :book_id
					  AND user_id = :user_id
					  AND reservariton_status = 0
					  AND reservation_date < :new_end
					  AND return_date > :new_start
					LIMIT 1 FOR UPDATE";
				$userDupStmt = $dbh->prepare($userDupSql);
				$userDupStmt->execute([
					':book_id' => intval($book['id']),
					':user_id' => $userId,
					':new_start' => $newStart,
					':new_end' => $newEnd,
				]);
				$userDup = $userDupStmt->fetch(PDO::FETCH_ASSOC);
				if ($userDup) {
					throw new Exception(sprintf('同一書籍の重複予約はできません。関数: reservation, 引数: book_id=%d, user_id=%d, 期間=%s〜%s', intval($book['id']), $userId, $newStart, $newEnd));
				}

				// 書籍全体の重複数チェック（在庫超過防止）
				$countSql = "SELECT COUNT(*) FROM reservation
					WHERE book_id = :book_id
					  AND reservariton_status = 0
					  AND reservation_date < :new_end
					  AND return_date > :new_start
					FOR UPDATE";
				$countStmt = $dbh->prepare($countSql);
				$countStmt->execute([
					':book_id' => intval($book['id']),
					':new_start' => $newStart,
					':new_end' => $newEnd,
				]);
				$overlapCount = intval($countStmt->fetchColumn());
				if ($overlapCount >= $copies) {
					throw new Exception(sprintf('在庫数を超えるため予約できません。関数: reservation, 引数: book_id=%d, copies=%d, 重複数=%d, 期間=%s〜%s', intval($book['id']), $copies, $overlapCount, $newStart, $newEnd));
				}

				// 予約登録
				$sql = "INSERT INTO reservation (book_id, user_id, reservation_date, return_date, reservariton_status, date)
					VALUES (:book_id, :user_id, :reservation_date, :return_date, :status, CURDATE())";
				$stmt = $dbh->prepare($sql);
				$params = [
					':book_id' => intval($book['id']),
					':user_id' => $userId,
					':reservation_date' => $newStart,
					':return_date' => $newEnd,
					':status' => 0
				];
				$stmt->execute($params);

				$dbh->commit();
			} catch (Throwable $t) {
				if ($dbh->inTransaction()) {
					$dbh->rollBack();
				}
				throw $t;
			}

			$returnDateForMessage = $newEnd;
			$successMessage = sprintf(
				'予約を受け付けました。予約ID: %d, 書籍ID: %d, ISBN: %s, 開始日: %s, 返却日: %s, 予約者: %s（%s）',
				intval($dbh->lastInsertId()),
				intval($book['id']),
				htmlspecialchars($book['isbn']),
				htmlspecialchars($_POST['reservationStartDate']),
				htmlspecialchars($returnDateForMessage),
				htmlspecialchars($stdName),
				htmlspecialchars((string)$stdNo)
			);
			$_POST = [];
			$bookInfo = $book;
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
	<title>予約</title>
	<link rel="stylesheet" href="assets/CSS/style.css">
</head>
<body>
	<?php include 'header.php'; ?>
	<div class="container">
		<!-- <header class="page-header">
			<h1>Reservation</h1>
			<p class="subtitle">書籍の予約</p>
		</header> -->

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
			<h2>予約フォーム</h2>
			<form method="POST" action="">

				<?php if ($bookInfo): ?>
					<div class="form-group">
						<label>書籍情報</label>
						<div class="readonly-block">
							<div>タイトル: <?php echo htmlspecialchars($bookInfo['title']); ?></div>
							<!-- <div>著者: <?php echo htmlspecialchars($bookInfo['author']); ?></div>
							<div>出版社: <?php echo htmlspecialchars($bookInfo['publisher']); ?></div>
							<div>発刊日: <?php echo htmlspecialchars($bookInfo['publication_date']); ?></div> -->
							<div>ステータス: <?php echo intval($bookInfo['status']) === 1 ? '禁帯出' : '貸出許可'; ?></div>
						</div>
					</div>
				<?php endif; ?>

				<div class="form-group">
					<label for="isbn">ISBN <span class="required">*</span></label>
					<input type="text" id="isbn" name="isbn" placeholder="ハイフンあり・なし両方可" value="<?php echo isset($_GET['isbn']) ? htmlspecialchars($_GET['isbn']) : (isset($_POST['isbn']) ? htmlspecialchars($_POST['isbn']) : ''); ?>" required>
					<div id="isbnStatus" class="helper-text"></div>
				</div>

				<div class="form-group">
					<label>予約者</label>
					<div class="readonly-block">
						<div><?php echo htmlspecialchars($stdName); ?>（<?php echo htmlspecialchars((string)$stdNo); ?>）</div>
					</div>
				</div>

				<div class="form-group">
					<label for="reserveCopies">予約冊数 <span class="required">*</span></label>
					<input type="number" id="reserveCopies" name="reserveCopies" min="1" value="<?php echo isset($_POST['reserveCopies']) ? htmlspecialchars($_POST['reserveCopies']) : '1'; ?>" required>
				</div>

		<div class="form-group">
					<label for="reservationStartDate">予約開始日 <span class="required">*</span></label>
					<input
						type="date"
						id="reservationStartDate"
						name="reservationStartDate"
						value="<?php echo isset($_POST['reservationStartDate']) ? htmlspecialchars($_POST['reservationStartDate']) : date('Y-m-d'); ?>"
				min="<?php
					$__min = (new DateTime('today'))->modify('+' . getStartOffsetMinDefault($dbh) . ' days')->format('Y-m-d');
					echo $__min;
				?>"
				max="<?php
					$__max = (new DateTime('today'))->modify('+' . getStartOffsetMaxDefault($dbh) . ' days')->format('Y-m-d');
					echo $__max;
				?>"
						required
					>
				</div>

				<div class="form-actions">
					<button type="submit" class="submit-button">予約する</button>
					<a href="search.php" class="reset-button" style="text-decoration:none;display:inline-block;text-align:center;">検索に戻る</a>
					<a href="reservation_list.php" class="reset-button" style="text-decoration:none;display:inline-block;text-align:center;">予約一覧</a>
				</div>
			</form>
		</div>
	</div>
</body>
</html>


<script>
document.addEventListener('DOMContentLoaded', function() {
	const isbnInput = document.getElementById('isbn');
	const statusEl = document.getElementById('isbnStatus');
	const submitBtn = document.querySelector('.submit-button');

	function normalizeIsbn(isbn) {
		return (isbn || '').replace(/[^0-9]/g, '');
	}

	function isIsbnFormatValid(isbn) {
		const digits = normalizeIsbn(isbn);
		return digits.length === 10 || digits.length === 13;
	}

	function setSubmitEnabled(enabled) {
		if (!submitBtn) return;
		submitBtn.disabled = !enabled;
	}

	function setStatus(message, type) {
		if (!statusEl) return;
		statusEl.textContent = message;
		statusEl.className = 'helper-text ' + (type ? ('helper-text-' + type) : '');
	}

	async function lookupIsbn(isbn) {
		const digits = normalizeIsbn(isbn);
		try {
			const resp = await fetch('api_lookup.php?isbn=' + encodeURIComponent(digits), { headers: { 'Accept': 'application/json' } });
			if (!resp.ok) {
				throw new Error('関数: lookupIsbn, 引数: isbn=' + digits + ', httpStatus=' + resp.status);
			}
			const data = await resp.json();
			return data;
		} catch (e) {
			setStatus('ISBN検証中にエラーが発生しました。関数: lookupIsbn, 引数: isbn=' + digits + ', 詳細: ' + e.message, 'error');
			setSubmitEnabled(false);
			return null;
		}
	}

	async function validateIsbnInteractive() {
		const value = isbnInput.value.trim();
		if (value === '') {
			setStatus('', '');
			setSubmitEnabled(false);
			return;
		}
		if (!isIsbnFormatValid(value)) {
			setStatus('ISBN形式が不正です（10桁または13桁の数字）。', 'error');
			setSubmitEnabled(false);
			return;
		}
		const result = await lookupIsbn(value);
		if (!result) return;
		if (result.success && result.exists && result.book) {
			if (parseInt(result.book.status, 10) === 1) {
				setStatus('禁帯出のため予約できません。', 'error');
				setSubmitEnabled(false);
				return;
			}
			setStatus('', '');
			setSubmitEnabled(true);
			return;
		}
		if (result.success && !result.exists) {
			setStatus('指定のISBNは登録されていません。', 'error');
			setSubmitEnabled(false);
			return;
		}
		setStatus('ISBN検証に失敗しました。', 'error');
		setSubmitEnabled(false);
	}

	if (isbnInput) {
		isbnInput.addEventListener('blur', validateIsbnInteractive);
		isbnInput.addEventListener('input', function() {
			const v = isbnInput.value.trim();
			if (isIsbnFormatValid(v)) {
				validateIsbnInteractive();
			} else {
				setStatus('', '');
				setSubmitEnabled(false);
			}
		});
		<?php if (isset($_GET['isbn']) && trim($_GET['isbn']) !== ''): ?>
		validateIsbnInteractive();
		<?php endif; ?>
	}
});
</script>

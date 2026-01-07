<?php
require_once __DIR__ . '/DBC.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app_config.php';
requireLogin();
$user = getLoggedInUser();
$userId = isset($user['userId']) ? (int)$user['userId'] : 0;
$stdNo = $user['stdNo'];
$stdName = $user['stdName'];

$errorMessage = '';
$successMessage = '';
$foundBook = null;

function normalizeIsbn($isbn) {
	return preg_replace('/[^0-9]/', '', (string)$isbn);
}

function isIsbnDigitsValid($isbn) {
	$digits = normalizeIsbn($isbn);
	return strlen($digits) === 10 || strlen($digits) === 13;
}

function findBookByIsbn(PDO $dbh, $isbn) {
	$digits = normalizeIsbn($isbn);
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
		$reservationDate = isset($_POST['reservationDate']) ? trim($_POST['reservationDate']) : date('Y-m-d');

		$errors = [];
		if ($isbn === '') {
			$errors[] = 'ISBNは必須です。';
		} else {
			$digits = normalizeIsbn($isbn);
			if (!isIsbnDigitsValid($digits)) {
				$errors[] = sprintf('ISBN形式が不正です。入力: %s (数字のみ: %s)', htmlspecialchars($isbn), htmlspecialchars($digits));
			}
		}

		if ($reservationDate === '') {
			$errors[] = '貸出日は必須です。';
		} else {
			$dt = DateTime::createFromFormat('Y-m-d', $reservationDate);
			$validFormat = $dt && $dt->format('Y-m-d') === $reservationDate;
			if (!$validFormat) {
				$errors[] = sprintf('貸出日の形式が不正です。入力: %s (期待形式: YYYY-MM-DD)', htmlspecialchars($reservationDate));
			} else {
				$minOffset = getStartOffsetMinHakata($dbh);
				$maxOffset = getStartOffsetMaxHakata($dbh);
				$minDate = (new DateTime('today'))->modify('+' . $minOffset . ' days');
				$maxDate = (new DateTime('today'))->modify('+' . $maxOffset . ' days');
				if ($dt < $minDate || $dt > $maxDate) {
					$errors[] = sprintf('貸出日は %s から %s の間で指定してください。', $minDate->format('Y-m-d'), $maxDate->format('Y-m-d'));
				}
			}
		}

		if (count($errors) === 0) {
			$book = findBookByIsbn($dbh, $isbn);
			if (!$book) {
				throw new Exception(sprintf('貸出対象の書籍が見つかりません。関数: findBookByIsbn, 引数: isbn=%s', htmlspecialchars($isbn)));
			}
			if (isset($book['status']) && intval($book['status']) === 1) {
				throw new Exception(sprintf(
					'禁帯出のため貸出できません。関数: H_reservation, 引数: isbn=%s, book_id=%d, status=%d',
					htmlspecialchars($isbn),
					intval($book['id']),
					intval($book['status'])
				));
			}

			// 上限チェック（博多: 貸出可能本数）
			$maxLoans = getMaxLoansHakata($dbh);
			$dbh->beginTransaction();
			try {
				$userActiveCountSql = "SELECT COUNT(*) FROM h_reservation WHERE user_id = :user_id AND reservariton_status = 0 FOR UPDATE";
				$userActiveStmt = $dbh->prepare($userActiveCountSql);
				$userActiveStmt->execute([':user_id' => $userId]);
				$userActiveCount = (int)$userActiveStmt->fetchColumn();
				if ($userActiveCount >= $maxLoans) {
					throw new Exception(sprintf(
						'貸出上限に達しています。関数: H_reservation, 引数: user_id=%d, 上限=%d, 現在=%d',
						$userId, $maxLoans, $userActiveCount
					));
				}

				$loanDays = getLoanDaysHakata($dbh);
				$returnDate = (new DateTime($reservationDate))->modify('+' . $loanDays . ' days')->format('Y-m-d');

				$sql = "INSERT INTO h_reservation (book_id, user_id, reservation_date, return_date, reservariton_status, date)
					VALUES (:book_id, :user_id, :reservation_date, :return_date, :status, CURDATE())";
				$stmt = $dbh->prepare($sql);
				$params = [
					':book_id' => intval($book['id']),
					':user_id' => $userId,
					':reservation_date' => $reservationDate,
					':return_date' => $returnDate,
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

			$returnDateForMessage = $returnDate;
			$successMessage = sprintf('貸出を受け付けました。（返却日: %s）', htmlspecialchars($returnDateForMessage));
			$foundBook = $book;
			$_POST = [];
		} else {
			$errorMessage = implode('<br>', $errors);
		}
	} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['isbn']) && trim($_GET['isbn']) !== '') {
		$foundBook = findBookByIsbn($dbh, $_GET['isbn']);
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
	<title>バーコード貸出</title>
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
			<h2>バーコードから貸出</h2>
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

				<div class="form-group">
					<label for="reservationDate">貸出日 <span class="required">*</span></label>
					<input
						type="date"
						id="reservationDate"
						name="reservationDate"
						value="<?php echo isset($_POST['reservationDate']) ? htmlspecialchars($_POST['reservationDate']) : date('Y-m-d'); ?>"
					min="<?php
						$__minH = (new DateTime('today'))->modify('+' . getStartOffsetMinHakata($dbh) . ' days')->format('Y-m-d');
						echo $__minH;
					?>"
					max="<?php
						$__maxH = (new DateTime('today'))->modify('+' . getStartOffsetMaxHakata($dbh) . ' days')->format('Y-m-d');
						echo $__maxH;
					?>"
						required
					>
				</div>

				<?php if ($foundBook): ?>
					<div class="form-group">
						<label>書籍情報</label>
						<div class="readonly-block">
							<div>タイトル: <?php echo htmlspecialchars($foundBook['title']); ?></div>
							<div>ステータス: <?php echo intval($foundBook['status']) === 1 ? '禁帯出' : '貸出許可'; ?></div>
						</div>
					</div>
				<?php endif; ?>

				<div class="form-actions">
					<button type="submit" class="submit-button">貸出する</button>
					<!-- <a href="search.php" class="reset-button" style="text-decoration:none;display:inline-block;text-align:center;">検索に戻る</a>
					<a href="reservation.php" class="reset-button" style="text-decoration:none;display:inline-block;text-align:center;">詳細予約フォーム</a> -->
				</div>
			</form>
		</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		const isbnInput = document.getElementById('isbn');
		const statusEl = document.getElementById('isbnStatus');
		const submitBtn = document.querySelector('.submit-button');

		function normalizeIsbn(isbn) {
			return (isbn || '').replace(/[^0-9]/g, '');
		}

		function isIsbnFormatValid(isbn) {
			const d = normalizeIsbn(isbn);
			return d.length === 10 || d.length === 13;
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
				setStatus('ISBN検証中にエラーが発生しました。関数: lookupIsbn, 詳細: ' + e.message, 'error');
				setSubmitEnabled(false);
				return null;
			}
		}

		async function validateIsbnInteractive() {
			const value = (isbnInput.value || '').trim();
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
					setStatus('禁帯出のため貸出できません。', 'error');
					setSubmitEnabled(false);
					return;
				}
				setStatus('貸出可能です。タイトル: ' + (result.book.title || ''), 'ok');
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
			isbnInput.focus();
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
			isbnInput.addEventListener('keydown', function(ev) {
				if (ev.key === 'Enter') {
					ev.preventDefault();
					validateIsbnInteractive().then(function() {
						if (!submitBtn.disabled) {
							submitBtn.click();
						}
					});
				}
			});
			<?php if (isset($_GET['isbn']) && trim($_GET['isbn']) !== ''): ?>
			validateIsbnInteractive();
			<?php endif; ?>
		}
	});
	</script>
</body>
</html>



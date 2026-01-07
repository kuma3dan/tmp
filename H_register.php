<?php
//ファイル読み込みと初期化
require_once 'DBC.php';
require_once 'auth.php';
requireLogin();
$user = getLoggedInUser();
$stdNo = $user['stdNo'];
$stdName = $user['stdName'];
$errorMessage = '';
$successMessage = '';
$editMode = false;
$existingBookId = null;

function checkDuplicateIsbn($isbn) {
    global $dbh;
    
    try {
        $sql = "SELECT * FROM H_book WHERE isbn = :isbn LIMIT 1";
        $statement = $dbh->prepare($sql);
        $statement->execute([':isbn' => $isbn]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        
        return $result;
    } catch (PDOException $exception) {
        throw new Exception(
            sprintf(
                "データベースエラー - 関数: checkDuplicateIsbn, ISBN: %s, エラーメッセージ: %s",
                $isbn,
                $exception->getMessage()
            )
        );
    }
}

function saveBookToDatabase($bookData) {
    global $dbh;
    
    try {
        $sql = "INSERT INTO H_book (title, isbn, author, publisher, publication_date, copies, status) 
                VALUES (:title, :isbn, :author, :publisher, :publication_date, :copies, :status)";
        
        $statement = $dbh->prepare($sql);
        
        $parameters = [
            ':title' => $bookData['title'],
            ':isbn' => $bookData['isbn'],
            ':author' => $bookData['author'],
            ':publisher' => $bookData['publisher'],
            ':publication_date' => $bookData['publicationDate'],
            ':copies' => $bookData['copies'],
            ':status' => $bookData['status']
        ];
        
        $result = $statement->execute($parameters);
        
        if ($result) {
            return true;
        } else {
            $errorInfo = $statement->errorInfo();
            throw new Exception(
                sprintf(
                    "書籍保存エラー - ISBN: %s, タイトル: %s, SQLエラーコード: %s, エラーメッセージ: %s",
                    $bookData['isbn'],
                    $bookData['title'],
                    $errorInfo[0],
                    $errorInfo[2]
                )
            );
        }
    } catch (PDOException $exception) {
        throw new Exception(
            sprintf(
                "データベースエラー - 関数: saveBookToDatabase, ISBN: %s, エラーメッセージ: %s",
                $bookData['isbn'],
                $exception->getMessage()
            )
        );
    }
}

function updateBookInDatabase($bookId, $bookData) {
    global $dbh;
    
    try {
        $sql = "UPDATE H_book 
                SET title = :title, 
                    isbn = :isbn, 
                    author = :author, 
                    publisher = :publisher, 
                    publication_date = :publication_date, 
                    copies = :copies, 
                    status = :status 
                WHERE id = :id";
        
        $statement = $dbh->prepare($sql);
        
        $parameters = [
            ':id' => $bookId,
            ':title' => $bookData['title'],
            ':isbn' => $bookData['isbn'],
            ':author' => $bookData['author'],
            ':publisher' => $bookData['publisher'],
            ':publication_date' => $bookData['publicationDate'],
            ':copies' => $bookData['copies'],
            ':status' => $bookData['status']
        ];
        
        $result = $statement->execute($parameters);
        
        if ($result) {
            return true;
        } else {
            $errorInfo = $statement->errorInfo();
            throw new Exception(
                sprintf(
                    "書籍更新エラー - ID: %s, ISBN: %s, タイトル: %s, SQLエラーコード: %s, エラーメッセージ: %s",
                    $bookId,
                    $bookData['isbn'],
                    $bookData['title'],
                    $errorInfo[0],
                    $errorInfo[2]
                )
            );
        }
    } catch (PDOException $exception) {
        throw new Exception(
            sprintf(
                "データベースエラー - 関数: updateBookInDatabase, ID: %s, ISBN: %s, エラーメッセージ: %s",
                $bookId,
                $bookData['isbn'],
                $exception->getMessage()
            )
        );
    }
}

function validateBookData($postData) {
    $errors = [];
    if (empty($postData['isbn'])) {
        $errors[] = "ISBN番号は必須項目です。";
    } else {
        $isbnDigitsOnly = preg_replace('/[^0-9]/', '', $postData['isbn']);
        $isbnLength = strlen($isbnDigitsOnly);
        if ($isbnLength !== 10 && $isbnLength !== 13) {
            $errors[] = sprintf(
                "ISBN番号の形式が正しくありません。入力値: %s (数字のみに変換後: %s, 文字数: %d桁。10桁または13桁が必要です)",
                htmlspecialchars($postData['isbn']),
                htmlspecialchars($isbnDigitsOnly),
                $isbnLength
            );
        }
    }
    
    if (empty($postData['title'])) {
        $errors[] = "タイトルは必須項目です。";
    } elseif (mb_strlen($postData['title']) > 50) {
        $errors[] = sprintf(
            "タイトルは50文字以内で入力してください。現在の文字数: %d文字",
            mb_strlen($postData['title'])
        );
    }
    
    if (!empty($postData['author']) && mb_strlen($postData['author']) > 50) {
        $errors[] = sprintf(
            "著者名は50文字以内で入力してください。現在の文字数: %d文字",
            mb_strlen($postData['author'])
        );
    }
    
    if (!empty($postData['publisher']) && mb_strlen($postData['publisher']) > 50) {
        $errors[] = sprintf(
            "出版社名は50文字以内で入力してください。現在の文字数: %d文字",
            mb_strlen($postData['publisher'])
        );
    }
    
    if (!empty($postData['publicationDate'])) {
        $datePattern = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/';
        if (!preg_match($datePattern, $postData['publicationDate'])) {
            $errors[] = sprintf(
                "発刊日の形式が正しくありません。入力値: %s (YYYY-MM-DD形式が必要です)",
                htmlspecialchars($postData['publicationDate'])
            );
        }
    }
    
    if ($postData['copies'] === '' || $postData['copies'] === null) {
        $errors[] = "本数は必須項目です。";
    } elseif (!is_numeric($postData['copies']) || intval($postData['copies']) < 0) {
        $errors[] = sprintf(
            "本数は0以上の数値で入力してください。入力値: %s",
            htmlspecialchars($postData['copies'])
        );
    }
    
    if (!isset($postData['status'])) {
        $errors[] = "ステータスは必須項目です。";
    } elseif (!in_array($postData['status'], ['0', '1'], true)) {
        $errors[] = sprintf(
            "ステータスの値が不正です。入力値: %s (0または1が必要です)",
            htmlspecialchars($postData['status'])
        );
    }
    
    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $validationErrors = validateBookData($_POST);
        
        if (count($validationErrors) > 0) {
            $errorMessage = implode('<br>', $validationErrors);
        } else {
            $bookData = [
                'title' => trim($_POST['title']),
                'isbn' => trim($_POST['isbn']),
                'author' => trim($_POST['author']),
                'publisher' => trim($_POST['publisher']),
                'publicationDate' => !empty($_POST['publicationDate']) ? $_POST['publicationDate'] : null,
                'copies' => intval($_POST['copies']),
                'status' => intval($_POST['status'])
            ];
            
            $isEditMode = isset($_POST['edit_mode']) && $_POST['edit_mode'] === '1';
            $bookId = isset($_POST['book_id']) ? intval($_POST['book_id']) : null;
            
            if ($isEditMode && $bookId) {
                if (updateBookInDatabase($bookId, $bookData)) {
                    $successMessage = sprintf(
                        "書籍情報を正常に更新しました。<br>ISBN: %s<br>タイトル: %s<br>本数: %d冊",
                        htmlspecialchars($bookData['isbn']),
                        htmlspecialchars($bookData['title']),
                        $bookData['copies']
                    );
                    $_POST = [];
                }
            } else {
                if (saveBookToDatabase($bookData)) {
                    $successMessage = sprintf(
                        "書籍情報を正常に保存しました。<br>ISBN: %s<br>タイトル: %s<br>本数: %d冊",
                        htmlspecialchars($bookData['isbn']),
                        htmlspecialchars($bookData['title']),
                        $bookData['copies']
                    );
                    $_POST = [];
                }
            }
        }
    } catch (Exception $exception) {
        $errorMessage = sprintf(
            "エラーが発生しました。<br>詳細: %s",
            htmlspecialchars($exception->getMessage())
        );
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>書籍登録</title>
    <link rel="stylesheet" href="assets/CSS/style.css">
</head>
<body>
    <?php include 'header_hakata.php'; ?>
    <div class="container">
        <!-- <header class="page-header">
            <h1>Title</h1>
            <p class="subtitle">SubTitle</p>
        </header> -->

        <div class="form-container">
            <h2>書籍情報入力</h2>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="message <?php echo $editMode ? 'warning-message' : 'error-message'; ?>">
                    <strong><?php echo $editMode ? '編集モード:' : 'エラー:'; ?></strong><br>
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($successMessage)): ?>
                <div class="message success-message">
                    <strong>成功:</strong><br>
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <div id="apiStatus" class="api-status" style="display: none;"></div>
            
            <form id="bookForm" method="POST" action="">
                <?php if ($editMode && $existingBookId): ?>
                    <input type="hidden" name="edit_mode" value="1">
                    <input type="hidden" name="book_id" value="<?php echo htmlspecialchars($existingBookId); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="isbn">
                        ISBN番号 <span class="required">*</span>
                        <span class="hint">ハイフンあり・なし両方可</span>
                    </label>
                    <input 
                        type="text" 
                        id="isbn" 
                        name="isbn" 
                        placeholder="例: 978-4-7980-7322-4" 
                        value="<?php echo isset($_POST['isbn']) ? htmlspecialchars($_POST['isbn']) : ''; ?>"
                        required
                    >
                    <button type="button" id="fetchButtonH" class="fetch-button">書籍情報を取得</button>
                </div>

                <div class="form-group">
                    <label for="title">
                        タイトル <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="title" 
                        name="title" 
                        placeholder="書籍タイトル" 
                        value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                        maxlength="50"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="author">著者</label>
                    <input 
                        type="text" 
                        id="author" 
                        name="author" 
                        placeholder="著者名" 
                        value="<?php echo isset($_POST['author']) ? htmlspecialchars($_POST['author']) : ''; ?>"
                        maxlength="50"
                    >
                </div>

                <div class="form-group">
                    <label for="publisher">出版社</label>
                    <input 
                        type="text" 
                        id="publisher" 
                        name="publisher" 
                        placeholder="出版社名" 
                        value="<?php echo isset($_POST['publisher']) ? htmlspecialchars($_POST['publisher']) : ''; ?>"
                        maxlength="50"
                    >
                </div>

                <div class="form-group">
                    <label for="publicationDate">発刊日</label>
                    <input 
                        type="date" 
                        id="publicationDate" 
                        name="publicationDate" 
                        value="<?php echo isset($_POST['publicationDate']) ? htmlspecialchars($_POST['publicationDate']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="copies">
                        本数 <span class="required">*</span>
                    </label>
                    <input 
                        type="number" 
                        id="copies" 
                        name="copies" 
                        placeholder="0" 
                        value="<?php echo isset($_POST['copies']) ? htmlspecialchars($_POST['copies']) : '0'; ?>"
                        min="0"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="status">
                        ステータス <span class="required">*</span>
                    </label>
                    <select id="status" name="status" required>
                        <option value="0" <?php echo (isset($_POST['status']) && $_POST['status'] == '0') ? 'selected' : ''; ?>>
                            通常(貸出可能)
                        </option>
                        <option value="1" <?php echo (isset($_POST['status']) && $_POST['status'] == '1') ? 'selected' : ''; ?>>
                            禁帯出(館内のみ)
                        </option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="submit-button">
                        <?php echo $editMode ? '更新する' : '登録する'; ?>
                    </button>
                    <button type="reset" class="reset-button">クリア</button>
                    <?php if ($editMode): ?>
                        <a href="register.php" class="reset-button" style="text-align: center; text-decoration: none; display: inline-block; line-height: 1.5;">新規登録に戻る</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/JS/script.js"></script>
    <script src="assets/JS/script_hakata.js"></script>
</body>
</html>


<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'DBC.php';

function sendJsonResponse($success, $exists, $book = null, $message = '') {
    $response = [
        'success' => $success,
        'exists' => $exists,
        'message' => $message
    ];
    
    if ($book !== null) {
        $response['book'] = $book;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($_GET['isbn']) || empty($_GET['isbn'])) {
        sendJsonResponse(false, false, null, 'ISBN番号が指定されていません。');
    }
    
    $isbn = trim($_GET['isbn']);
    $normalizedIsbn = preg_replace('/\D/', '', $isbn);
    if ($normalizedIsbn === '') {
        sendJsonResponse(false, false, null, 'ISBN番号の形式が不正です。');
    }
    
    $sql = "SELECT id, title, isbn, author, publisher, publication_date, copies, status 
            FROM H_book 
            WHERE REPLACE(REPLACE(isbn, '-', ''), ' ', '') = :normalized_isbn 
            LIMIT 1";
    
    $statement = $dbh->prepare($sql);
    $statement->execute([':normalized_isbn' => $normalizedIsbn]);
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        sendJsonResponse(true, true, $result, 'この書籍は既に登録されています。');
    } else {
        sendJsonResponse(true, false, null, '登録されていません。');
    }
    
} catch (PDOException $exception) {
    error_log(sprintf(
        "データベースエラー - ファイル: H_api_lookup.php, ISBN: %s, エラー: %s",
        isset($isbn) ? $isbn : 'N/A',
        $exception->getMessage()
    ));
    sendJsonResponse(false, false, null, 'データベースエラーが発生しました。');
} catch (Exception $exception) {
    error_log(sprintf(
        "エラー - ファイル: H_api_lookup.php, エラー: %s",
        $exception->getMessage()
    ));
    sendJsonResponse(false, false, null, 'エラーが発生しました。');
}
?>



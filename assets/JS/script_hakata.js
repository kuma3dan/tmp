document.addEventListener('DOMContentLoaded', function() {
    initializeBookFormHakata();
    initializeMessageAutoHide();
});

function initializeBookFormHakata() {
    const fetchButtonH = document.getElementById('fetchButtonH');
    const fallbackFetchButton = document.getElementById('fetchButton'); // 念のため
    const isbnInput = document.getElementById('isbn');

    const wire = (btn) => {
        if (!btn) return;
        btn.addEventListener('click', handleFetchButtonClickHakata);
    };

    wire(fetchButtonH);
    wire(fallbackFetchButton);

    if (isbnInput) {
        isbnInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                handleFetchButtonClickHakata();
            }
        });
    }
}

async function handleFetchButtonClickHakata() {
    const isbnInput = document.getElementById('isbn');
    const isbnValue = isbnInput.value.trim();
    
    if (!isbnValue) {
        showApiStatus('ISBN番号を入力してください。', 'error');
        return;
    }
    
    if (!validateIsbnFormat(isbnValue)) {
        showApiStatus('ISBN番号は10桁または13桁の数字で入力してください（ハイフンあり・なし両方可）。', 'error');
        return;
    }

    const digits = normalizeIsbn(isbnValue);

    const exists = await lookupBookInDatabaseHakata(digits);
    if (!exists) {
        if (typeof window.fetchBookInformation === 'function') {
            await window.fetchBookInformation(digits);
        } else {
            showApiStatus('外部API取得関数が見つかりません。script.js の読み込みを確認してください。', 'error');
        }
    }
}

function validateIsbnFormat(isbn) {
    const isbnDigitsOnly = isbn.replace(/[^0-9]/g, '');
    return isbnDigitsOnly.length === 10 || isbnDigitsOnly.length === 13;
}

function normalizeIsbn(isbn) {
    return isbn.replace(/[^0-9]/g, '');
}

async function lookupBookInDatabaseHakata(isbn) {
    const normalizedIsbn = normalizeIsbn(isbn);
    const apiUrl = `H_api_lookup.php?isbn=${encodeURIComponent(normalizedIsbn)}`;
    try {
        const resp = await fetch(apiUrl, { headers: { 'Accept': 'application/json' } });
        if (!resp.ok) {
            throw new Error(`HTTPエラー - ステータスコード: ${resp.status}`);
        }
        const data = await resp.json();
        if (data && data.success && data.exists && data.book) {
            fillFormWithExistingBook(data.book);
            enableEditMode(data.book.id);
            showApiStatus('このISBN番号は既に登録されています。内容を変更して「更新する」ボタンを押してください。', 'warning');
            return true;
        }
        if (data && data.success && !data.exists) {
            showApiStatus('登録されていません。外部APIから情報取得を試みます。', 'loading');
            return false;
        }
        throw new Error(`APIレスポンスが不正です: ${JSON.stringify(data)}`);
    } catch (error) {
        console.error('lookupBookInDatabaseHakata エラー:', error);
        showApiStatus('DB検索に失敗しました。外部APIから情報取得を試みます。', 'error');
        return false;
    }
}

/* 共通関数は script.js 側のグローバルを直接使用する */



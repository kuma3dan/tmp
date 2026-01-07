document.addEventListener('DOMContentLoaded', function() {
    initializeBookForm();
    initializeMessageAutoHide();
});

function initializeBookForm() {
    const fetchButton = document.getElementById('fetchButton');
    const isbnInput = document.getElementById('isbn');
    
    if (fetchButton) {
        fetchButton.addEventListener('click', handleFetchButtonClick);
    }
    
    if (isbnInput) {
        isbnInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                handleFetchButtonClick();
            }
        });
    }
}

async function handleFetchButtonClick() {
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
    
    const exists = await lookupBookInDatabase(isbnValue);
    if (!exists) {
        await fetchBookInformation(isbnValue);
    }
}

function validateIsbnFormat(isbn) {
    const isbnDigitsOnly = isbn.replace(/[^0-9]/g, '');
    return isbnDigitsOnly.length === 10 || isbnDigitsOnly.length === 13;
}

function normalizeIsbn(isbn) {
    return isbn.replace(/[^0-9]/g, '');
}

function initializeMessageAutoHide() {
    const messageElements = document.querySelectorAll('.message');
    
    messageElements.forEach(function(messageElement) {
        setTimeout(function() {
            messageElement.style.transition = 'opacity 0.5s ease-out';
            messageElement.style.opacity = '0';
            setTimeout(function() {
                messageElement.style.display = 'none';
            }, 300);
        }, 3000);
    });
}

async function lookupBookInDatabase(isbn) {
    const normalizedIsbn = normalizeIsbn(isbn);
    const apiUrl = `api_lookup.php?isbn=${encodeURIComponent(normalizedIsbn)}`;
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
        console.error('lookupBookInDatabase エラー:', error);
        showApiStatus('DB検索に失敗しました。外部APIから情報取得を試みます。', 'error');
        return false;
    }
}

async function fetchBookInformation(isbn) {
    showApiStatus('書籍情報を取得中...', 'loading');
    disableFetchButton(true);
    
    const normalizedIsbn = normalizeIsbn(isbn);
    
    try {
        const openBdResult = await fetchFromOpenBD(normalizedIsbn);
        
        if (openBdResult) {
            fillFormWithBookData(openBdResult);
            showApiStatus('書籍情報を取得しました', 'success');
        } else {
            const ndlResult = await fetchFromNDL(normalizedIsbn);
            
            if (ndlResult) {
                fillFormWithBookData(ndlResult);
                showApiStatus('書籍情報を取得しました', 'success');
            } else {
                showApiStatus('指定されたISBN番号の書籍が見つかりませんでした。', 'error');
            }
        }
    } catch (error) {
        const errorMessage = `書籍情報の取得に失敗しました。\nエラー詳細: ${error.message}\nISBN: ${isbn}`;
        console.error('fetchBookInformation エラー:', errorMessage);
        showApiStatus('書籍情報の取得に失敗しました。もう一度お試しください。', 'error');
    } finally {
        disableFetchButton(false);
    }
}

async function fetchFromOpenBD(isbn) {
    try {
        const apiUrl = `https://api.openbd.jp/v1/get?isbn=${isbn}`;
        console.log(`openBD APIリクエスト: ${apiUrl}`);
        
        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTPエラー - ステータスコード: ${response.status}, URL: ${apiUrl}`);
        }
        
        const data = await response.json();
        console.log('openBD APIレスポンス:', data);
        
        if (!data || data.length === 0 || !data[0]) {
            console.log(`openBD APIで書籍が見つかりませんでした。ISBN: ${isbn}`);
            return null;
        }
        
        const bookData = data[0];
        const summary = bookData.summary || {};
        const hanmoto = bookData.hanmoto || {};
        const onix = bookData.onix || {};
        const publishingDetail = onix.PublishingDetail || {};
        
        let publicationDate = '';
        
        if (hanmoto.datekoukai) {
            publicationDate = hanmoto.datekoukai;
            console.log('発刊日取得元: hanmoto.datekoukai（出版年月日等）', publicationDate);
        }
        
        if (!publicationDate && publishingDetail.PublishingDate && Array.isArray(publishingDetail.PublishingDate)) {
            for (const pubDate of publishingDetail.PublishingDate) {
                if (pubDate.PublishingDateRole === '01' && pubDate.Date) {
                    publicationDate = pubDate.Date;
                    console.log('発刊日取得元: onix.PublishingDetail.PublishingDate (Role:01)', publicationDate);
                    break;
                }
            }
            if (!publicationDate && publishingDetail.PublishingDate[0] && publishingDetail.PublishingDate[0].Date) {
                publicationDate = publishingDetail.PublishingDate[0].Date;
                console.log('発刊日取得元: onix.PublishingDetail.PublishingDate[0]', publicationDate);
            }
        }
        
        if (!publicationDate && summary.pubdate) {
            publicationDate = summary.pubdate;
            console.log('発刊日取得元: summary.pubdate', publicationDate);
        }
        
        console.log('最終的な発刊日:', publicationDate);
        
        return {
            title: summary.title || '',
            author: summary.author || '',
            publisher: summary.publisher || '',
            publicationDate: formatPublicationDate(publicationDate)
        };
    } catch (error) {
        console.error(`openBD API取得エラー - ISBN: ${isbn}, エラー: ${error.message}`);
        return null;
    }
}

async function fetchFromNDL(isbn) {
    try {
        const apiUrl = `https://iss.ndl.go.jp/api/opensearch?isbn=${isbn}&mediatype=1`;
        console.log(`国立国会図書館APIリクエスト: ${apiUrl}`);
        
        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTPエラー - ステータスコード: ${response.status}, URL: ${apiUrl}`);
        }
        
        const xmlText = await response.text();
        console.log('国立国会図書館APIレスポンス(XML):', xmlText);
        
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(xmlText, 'text/xml');
        
        const parserError = xmlDoc.querySelector('parsererror');
        if (parserError) {
            throw new Error(`XMLパースエラー: ${parserError.textContent}`);
        }
        
        const item = xmlDoc.querySelector('item');
        
        if (!item) {
            console.log(`国立国会図書館APIで書籍が見つかりませんでした。ISBN: ${isbn}`);
            return null;
        }
        
        const title = getXmlElementText(item, 'title');
        const author = getXmlElementText(item, 'dc\\:creator') || getXmlElementText(item, 'creator');
        const publisher = getXmlElementText(item, 'dc\\:publisher') || getXmlElementText(item, 'publisher');
        const pubdate = getXmlElementText(item, 'pubDate');
        
        return {
            title: title,
            author: author,
            publisher: publisher,
            publicationDate: formatPublicationDate(pubdate)
        };
    } catch (error) {
        console.error(`国立国会図書館API取得エラー - ISBN: ${isbn}, エラー: ${error.message}`);
        return null;
    }
}

function getXmlElementText(parentElement, tagName) {
    const element = parentElement.querySelector(tagName);
    return element ? element.textContent.trim() : '';
}

function formatPublicationDate(dateString) {
    if (!dateString) {
        return '';
    }
    
    const yyyymmddPattern = /^(\d{4})(\d{2})(\d{2})$/;
    const yyyymmddMatch = dateString.match(yyyymmddPattern);
    if (yyyymmddMatch) {
        return `${yyyymmddMatch[1]}-${yyyymmddMatch[2]}-${yyyymmddMatch[3]}`;
    }
    
    const standardPattern = /^\d{4}-\d{2}-\d{2}$/;
    if (standardPattern.test(dateString)) {
        return dateString;
    }
    
    const slashPattern = /^(\d{4})\/(\d{2})\/(\d{2})$/;
    const slashMatch = dateString.match(slashPattern);
    if (slashMatch) {
        return `${slashMatch[1]}-${slashMatch[2]}-${slashMatch[3]}`;
    }
    
    const japaneseFullPattern = /^(\d{4})年(\d{1,2})月(\d{1,2})日$/;
    const japaneseFullMatch = dateString.match(japaneseFullPattern);
    if (japaneseFullMatch) {
        const month = japaneseFullMatch[2].padStart(2, '0');
        const day = japaneseFullMatch[3].padStart(2, '0');
        return `${japaneseFullMatch[1]}-${month}-${day}`;
    }
    
    const japaneseYearMonthPattern = /^(\d{4})年(\d{1,2})月$/;
    const japaneseYearMonthMatch = dateString.match(japaneseYearMonthPattern);
    if (japaneseYearMonthMatch) {
        const month = japaneseYearMonthMatch[2].padStart(2, '0');
        return `${japaneseYearMonthMatch[1]}-${month}-01`;
    }
    
    const dotFullPattern = /^(\d{4})\.(\d{1,2})\.(\d{1,2})$/;
    const dotFullMatch = dateString.match(dotFullPattern);
    if (dotFullMatch) {
        const month = dotFullMatch[2].padStart(2, '0');
        const day = dotFullMatch[3].padStart(2, '0');
        return `${dotFullMatch[1]}-${month}-${day}`;
    }
    
    const yearMonthPattern = /^(\d{4})\.(\d{1,2})$/;
    const yearMonthMatch = dateString.match(yearMonthPattern);
    if (yearMonthMatch) {
        const month = yearMonthMatch[2].padStart(2, '0');
        return `${yearMonthMatch[1]}-${month}-01`;
    }
    
    const yearOnlyPattern = /^(\d{4})$/;
    const yearOnlyMatch = dateString.match(yearOnlyPattern);
    if (yearOnlyMatch) {
        return `${yearOnlyMatch[1]}-01-01`;
    }
    
    console.warn(`日付形式の変換に失敗しました。入力値: ${dateString}`);
    return '';
}

function fillFormWithBookData(bookData) {
    console.log('フォーム自動入力開始:', bookData);
    
    const titleInput = document.getElementById('title');
    if (titleInput && bookData.title) {
        titleInput.value = bookData.title;
        console.log(`タイトルを入力: ${bookData.title}`);
    }
    
    const authorInput = document.getElementById('author');
    if (authorInput && bookData.author) {
        authorInput.value = bookData.author;
        console.log(`著者を入力: ${bookData.author}`);
    }
    
    const publisherInput = document.getElementById('publisher');
    if (publisherInput && bookData.publisher) {
        publisherInput.value = bookData.publisher;
        console.log(`出版社を入力: ${bookData.publisher}`);
    }
    
    const publicationDateInput = document.getElementById('publicationDate');
    if (publicationDateInput && bookData.publicationDate) {
        publicationDateInput.value = bookData.publicationDate;
        console.log(`発刊日を入力: ${bookData.publicationDate}`);
    }
    
    console.log('フォーム自動入力完了');
}

function enableEditMode(bookId) {
    let editModeInput = document.getElementById('edit_mode');
    if (!editModeInput) {
        editModeInput = document.createElement('input');
        editModeInput.type = 'hidden';
        editModeInput.id = 'edit_mode';
        editModeInput.name = 'edit_mode';
        const form = document.getElementById('bookForm');
        if (form) {
            form.appendChild(editModeInput);
        }
    }
    editModeInput.value = '1';
    
    let bookIdInput = document.getElementById('book_id');
    if (!bookIdInput) {
        bookIdInput = document.createElement('input');
        bookIdInput.type = 'hidden';
        bookIdInput.id = 'book_id';
        bookIdInput.name = 'book_id';
        const form = document.getElementById('bookForm');
        if (form) {
            form.appendChild(bookIdInput);
        }
    }
    bookIdInput.value = String(bookId);
    
    const submitButton = document.querySelector('.submit-button');
    if (submitButton) {
        submitButton.textContent = '更新する';
    }
    
    console.log('編集モード有効化 - Book ID:', bookId);
}

function fillFormWithExistingBook(book) {
    const titleInput = document.getElementById('title');
    if (titleInput) {
        titleInput.value = book.title || '';
    }

    const authorInput = document.getElementById('author');
    if (authorInput) {
        authorInput.value = book.author || '';
    }

    const publisherInput = document.getElementById('publisher');
    if (publisherInput) {
        publisherInput.value = book.publisher || '';
    }

    const publicationDateInput = document.getElementById('publicationDate');
    if (publicationDateInput) {
        publicationDateInput.value = book.publication_date || '';
    }

    const copiesInput = document.getElementById('copies');
    if (copiesInput) {
        copiesInput.value = String(book.copies != null ? book.copies : '1');
    }

    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.value = String(book.status != null ? book.status : '0');
    }
    
    console.log('既存書籍データでフォームを入力:', book);
}

function showApiStatus(message, type) {
    const statusElement = document.getElementById('apiStatus');
    
    if (!statusElement) {
        console.warn('apiStatus要素が見つかりません。');
        return;
    }
    
    statusElement.className = 'api-status';
    statusElement.textContent = message;
    statusElement.classList.add(`api-status-${type}`);
    statusElement.style.display = 'block';
    statusElement.style.opacity = '1';
    statusElement.style.transition = 'opacity 0.5s ease-out';
    
    console.log(`API状態表示 - タイプ: ${type}, メッセージ: ${message}`);
    
    if (type === 'success' || type === 'error' || type === 'warning') {
        setTimeout(function() {
            statusElement.style.opacity = '0';
            setTimeout(function() {
                statusElement.style.display = 'none';
            }, 300);
        }, 3000);
    }
}

function disableFetchButton(disabled) {
    const fetchButton = document.getElementById('fetchButton');
    
    if (!fetchButton) {
        console.warn('fetchButton要素が見つかりません。');
        return;
    }
    
    fetchButton.disabled = disabled;
    
    if (disabled) {
        fetchButton.classList.add('disabled');
        fetchButton.textContent = '取得中...';
    } else {
        fetchButton.classList.remove('disabled');
        fetchButton.textContent = '書籍情報を取得';
    }
    
    console.log(`取得ボタン状態変更: ${disabled ? '無効' : '有効'}`);
}

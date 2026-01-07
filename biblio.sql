-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: 127.0.0.1
-- 生成日時: 2026-01-07 02:59:00
-- サーバのバージョン： 10.4.32-MariaDB
-- PHP のバージョン: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `biblio`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `book`
--

CREATE TABLE `book` (
  `id` int(11) NOT NULL,
  `title` varchar(50) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `author` varchar(50) DEFAULT NULL COMMENT '著者',
  `publisher` varchar(50) DEFAULT NULL COMMENT '出版社',
  `publication_date` date DEFAULT NULL COMMENT '発刊日',
  `copies` int(1) DEFAULT NULL COMMENT '本数',
  `status` int(1) DEFAULT 0 COMMENT '通常＝0\r\n禁帯＝1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `book`
--

INSERT INTO `book` (`id`, `title`, `isbn`, `author`, `publisher`, `publication_date`, `copies`, `status`) VALUES
(1, 'PHPクックブック : モダンPHPによるWebアプリケーション実用レシピ集', '978-4-8144-0062-1', 'Mann,EricA 広川,類 桑村,潤,1963-', 'オーム社', '2024-02-08', 1, 0),
(8, 'めもりーちゃんのPHPでプログラミング入門', '978-4-297-14587-3', 'めもり－ 田中ひさてる', '技術評論社', '2024-09-26', 1, 0),
(9, 'TECHNICAL MASTER はじめてのPHP エンジニア入門編', '978-4-7980-7322-4', '宇谷有史 島袋隆広 高橋邦彦 藤田泰生 佐野元気 岩原真生 矢田直 富所亮', '秀和システム', '2024-10-27', 0, 0),
(10, 'はじめてのWebデザイン&プログラミング : HTML、CSS、JavaScript、PHPの基本', '978-4-627-85721-6', '村上,祐治', '森北出版', '2023-05-02', 1, 0);

-- --------------------------------------------------------

--
-- テーブルの構造 `h_book`
--

CREATE TABLE `h_book` (
  `id` int(11) NOT NULL,
  `title` varchar(50) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `author` varchar(50) DEFAULT NULL COMMENT '著者',
  `publisher` varchar(50) DEFAULT NULL COMMENT '出版社',
  `publication_date` date DEFAULT NULL COMMENT '発刊日',
  `copies` int(1) DEFAULT NULL COMMENT '本数',
  `status` int(1) DEFAULT 0 COMMENT '通常＝0\r\n禁帯＝1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `h_book`
--

INSERT INTO `h_book` (`id`, `title`, `isbn`, `author`, `publisher`, `publication_date`, `copies`, `status`) VALUES
(1, 'TECHNICAL MASTER はじめてのPHP エンジニア入門編', '978-4-7980-7322-4', '宇谷有史 島袋隆広 高橋邦彦 藤田泰生 佐野元気 岩原真生 矢田直 富所亮', '秀和システム', '2024-10-27', 2, 0),
(2, 'はじめてのWebデザイン&プログラミング : HTML、CSS、JavaScript、PHPの基本', '978-4-627-85721-6', '村上,祐治', '森北出版', '2023-05-02', 3, 0);

-- --------------------------------------------------------

--
-- テーブルの構造 `h_reservation`
--

CREATE TABLE `h_reservation` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT '_kiis_studentから取得',
  `book_id` int(11) DEFAULT NULL COMMENT 'bookから取得',
  `date` date DEFAULT NULL COMMENT '登録日',
  `reservation_date` date DEFAULT NULL COMMENT '予約開始日',
  `return_date` date DEFAULT NULL COMMENT '返却日',
  `reservariton_status` int(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `h_reservation`
--

INSERT INTO `h_reservation` (`id`, `user_id`, `book_id`, `date`, `reservation_date`, `return_date`, `reservariton_status`) VALUES
(1, NULL, 1, '2026-01-04', '2026-01-04', '2026-01-11', 0),
(2, NULL, 2, '2026-01-04', '2026-01-04', '2026-01-11', 0),
(3, 2, 1, '2026-01-07', '2026-01-07', '2026-01-14', 0);

-- --------------------------------------------------------

--
-- テーブルの構造 `page_config`
--

CREATE TABLE `page_config` (
  `id` int(11) NOT NULL,
  `conf_name` char(50) DEFAULT NULL,
  `status` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `reservation`
--

CREATE TABLE `reservation` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT '_kiis_studentから取得',
  `book_id` int(11) DEFAULT NULL COMMENT 'bookから取得',
  `date` date DEFAULT NULL COMMENT '登録日',
  `reservation_date` date DEFAULT NULL COMMENT '予約開始日',
  `return_date` date DEFAULT NULL COMMENT '返却日',
  `reservariton_status` int(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `reservation`
--

INSERT INTO `reservation` (`id`, `user_id`, `book_id`, `date`, `reservation_date`, `return_date`, `reservariton_status`) VALUES
(10, NULL, 1, '2025-11-10', '2025-11-10', '2025-11-17', 0),
(11, NULL, 8, '2025-11-10', '2025-11-10', '2025-11-17', 0),
(12, NULL, 8, '2025-11-10', '2025-11-10', '2025-11-17', 0),
(13, NULL, 8, '2025-11-10', '2025-11-10', '2025-11-17', 0),
(14, 2, 8, '2025-12-22', '2025-12-22', '2025-12-29', 0),
(15, 2, 1, '2025-12-22', '2025-12-22', '2025-12-29', 0),
(16, 2, 10, '2025-12-22', '2025-12-22', '2025-12-29', 0);

-- --------------------------------------------------------

--
-- テーブルの構造 `_kiis_student`
--

CREATE TABLE `_kiis_student` (
  `id` int(11) NOT NULL,
  `std_no` int(4) NOT NULL,
  `std_name` varchar(30) NOT NULL,
  `std_pass` varchar(256) NOT NULL,
  `birth` int(8) NOT NULL,
  `biko1` varchar(256) NOT NULL,
  `biko2` varchar(256) NOT NULL,
  `biko3` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- テーブルのデータのダンプ `_kiis_student`
--

INSERT INTO `_kiis_student` (`id`, `std_no`, `std_name`, `std_pass`, `birth`, `biko1`, `biko2`, `biko3`) VALUES
(2, 2231599, 'TestUser', 'test', 0, '', '', '');

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `book`
--
ALTER TABLE `book`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `isbn` (`isbn`);

--
-- テーブルのインデックス `h_book`
--
ALTER TABLE `h_book`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `isbn` (`isbn`);

--
-- テーブルのインデックス `h_reservation`
--
ALTER TABLE `h_reservation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `user_id` (`user_id`);

--
-- テーブルのインデックス `page_config`
--
ALTER TABLE `page_config`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `reservation`
--
ALTER TABLE `reservation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `user_id` (`user_id`);

--
-- テーブルのインデックス `_kiis_student`
--
ALTER TABLE `_kiis_student`
  ADD PRIMARY KEY (`id`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `book`
--
ALTER TABLE `book`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- テーブルの AUTO_INCREMENT `h_book`
--
ALTER TABLE `h_book`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- テーブルの AUTO_INCREMENT `h_reservation`
--
ALTER TABLE `h_reservation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- テーブルの AUTO_INCREMENT `page_config`
--
ALTER TABLE `page_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `reservation`
--
ALTER TABLE `reservation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- テーブルの AUTO_INCREMENT `_kiis_student`
--
ALTER TABLE `_kiis_student`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `reservation`
--
ALTER TABLE `reservation`
  ADD CONSTRAINT `reservation_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `book` (`id`),
  ADD CONSTRAINT `reservation_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `_kiis_student` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

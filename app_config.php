<?php
declare(strict_types=1);

function fetchConfigInt(PDO $dbh, string $table, array $names): ?int {
	foreach ($names as $name) {
		try {
			$sql = "SELECT status FROM {$table} WHERE conf_name = :name LIMIT 1";
			$stmt = $dbh->prepare($sql);
			$stmt->execute([':name' => $name]);
			$val = $stmt->fetchColumn();
			if ($val !== false && $val !== null) {
				return (int)$val;
			}
		} catch (Throwable $e) {
			// テーブル不存在などはスキップして次候補へ
			continue;
		}
	}
	return null;
}

function getLoanDaysDefault(PDO $dbh): int {
	$val = fetchConfigInt($dbh, 'page_config', ['loan_days', 'default_loan_days']);
	return $val !== null && $val > 0 ? $val : 7;
}

function getLoanDaysHakata(PDO $dbh): int {
	$val = fetchConfigInt($dbh, 'page_config', ['loan_days_hakata', 'h_loan_days', 'loan_days_h']);
	if ($val !== null && $val > 0) {
		return $val;
	}
	$val2 = fetchConfigInt($dbh, 'H_page_config', ['loan_days']);
	return $val2 !== null && $val2 > 0 ? $val2 : 7;
}

function getMaxReservationsDefault(PDO $dbh): int {
	$val = fetchConfigInt($dbh, 'page_config', ['max_reservations', 'reservation_limit', 'max_reservations_default']);
	return $val !== null && $val > 0 ? $val : 5;
}

function getMaxLoansHakata(PDO $dbh): int {
	$val = fetchConfigInt($dbh, 'H_page_config', ['max_loans', 'loan_limit', 'max_borrow']);
	if ($val !== null && $val > 0) {
		return $val;
	}
	$val2 = fetchConfigInt($dbh, 'page_config', ['max_loans_hakata']);
	return $val2 !== null && $val2 > 0 ? $val2 : 5;
}

function getStartOffsetMinDefault(PDO $dbh): int {
	$val = fetchConfigInt($dbh, 'page_config', ['start_offset_min', 'reservation_start_min', 'min_start_offset']);
	return $val !== null && $val >= 0 ? $val : 0;
}

function getStartOffsetMaxDefault(PDO $dbh): int {
	$val = fetchConfigInt($dbh, 'page_config', ['start_offset_max', 'reservation_start_max', 'max_start_offset']);
	return $val !== null && $val >= 0 ? $val : 14;
}

function getStartOffsetMinHakata(PDO $dbh): int {
	$val = fetchConfigInt($dbh, 'H_page_config', ['start_offset_min', 'loan_start_min']);
	if ($val !== null && $val >= 0) {
		return $val;
	}
	$val2 = fetchConfigInt($dbh, 'page_config', ['start_offset_min_hakata']);
	return $val2 !== null && $val2 >= 0 ? $val2 : 0;
}

function getStartOffsetMaxHakata(PDO $dbh): int {
	$val = fetchConfigInt($dbh, 'H_page_config', ['start_offset_max', 'loan_start_max']);
	if ($val !== null && $val >= 0) {
		return $val;
	}
	$val2 = fetchConfigInt($dbh, 'page_config', ['start_offset_max_hakata']);
	return $val2 !== null && $val2 >= 0 ? $val2 : 14;
}



<?php

/**
 * financial_ledger helper — append-only, hash-chained.
 *
 * Every row's signature_hash is a SHA-256 of the previous row's hash plus
 * this row's own data. Altering or deleting a past row breaks the chain for
 * everything after it, which is the point: this table is a tamper-evident
 * trail, not just a log. Never UPDATE or DELETE a financial_ledger row.
 */

declare(strict_types=1);

/**
 * Looks up a ledger_transaction_types / ledger_account_types id by name.
 * Small tables, cheap to query directly rather than hardcoding ids that
 * could drift from the seed data.
 */
function ledger_lookup_id(PDO $db, string $table, string $name): int
{
    $stmt = $db->prepare("SELECT id FROM {$table} WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();

    if ($id === false) {
        throw new RuntimeException("Unknown {$table} name: {$name}");
    }

    return (int) $id;
}

/**
 * Appends one entry to financial_ledger for $cafeId, chained to that cafe's
 * most recent entry. $amount must be a decimal string, e.g. '12.50'.
 */
function record_ledger_entry(
    PDO $db,
    int $cafeId,
    string $transactionTypeName,
    string $accountTypeName,
    string $amount,
    string $referenceTable,
    int $referenceId,
    int $recordedBy
): void {
    $transactionTypeId = ledger_lookup_id($db, 'ledger_transaction_types', $transactionTypeName);
    $accountTypeId = ledger_lookup_id($db, 'ledger_account_types', $accountTypeName);

    $stmt = $db->prepare(
        'SELECT signature_hash FROM financial_ledger
         WHERE cafe_id = :cafe_id
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':cafe_id' => $cafeId]);
    $previousHash = $stmt->fetchColumn();
    if ($previousHash === false) {
        // First entry for this cafe — seed a per-cafe genesis hash rather
        // than a shared constant, so chains for different cafes never collide.
        $previousHash = hash('sha256', 'genesis:' . $cafeId);
    }

    $createdAt = date('Y-m-d H:i:s');
    $payload = implode('|', [
        $previousHash, $cafeId, $transactionTypeId, $accountTypeId,
        $amount, $referenceTable, $referenceId, $recordedBy, $createdAt,
    ]);
    $signatureHash = hash('sha256', $payload);

    $insert = $db->prepare(
        'INSERT INTO financial_ledger
            (cafe_id, transaction_type_id, account_type_id, amount, reference_table, reference_id, recorded_by, previous_hash, signature_hash, created_at)
         VALUES
            (:cafe_id, :transaction_type_id, :account_type_id, :amount, :reference_table, :reference_id, :recorded_by, :previous_hash, :signature_hash, :created_at)'
    );
    $insert->execute([
        ':cafe_id' => $cafeId,
        ':transaction_type_id' => $transactionTypeId,
        ':account_type_id' => $accountTypeId,
        ':amount' => $amount,
        ':reference_table' => $referenceTable,
        ':reference_id' => $referenceId,
        ':recorded_by' => $recordedBy,
        ':previous_hash' => $previousHash,
        ':signature_hash' => $signatureHash,
        ':created_at' => $createdAt,
    ]);
}

# ORENTPHP Accounting Replication Plan (Bank Accounts + Ledger + Reservation Auto-Posting)

## 1. Summary
- Replicate the **core MYNFUTURE finance engine** into ORENTPHP: bank accounts, ledger, transfers, statements, audit-safe posting.
- Implement **cash-basis auto posting** from reservation events.
- Keep vouchers as a **separate non-cash liability ledger** (not bank cash).
- Deliver a structure that can be reused in any PHP project by keeping domain triggers separate from accounting services.

## 2. Scope (Locked)
- In scope:
  - Bank account management.
  - Ledger transaction system (income/expense/manual/transfer/adjustment).
  - Reservation auto-income posting.
  - Account statement view with running balance.
  - Reconciliation checks and activity logs.
- Out of scope for phase 1:
  - Agent/college payables module parity.
  - Full finance report parity from MYNFUTURE.
  - Accrual accounting and receivable ledgers.

## 3. Target Architecture
- Layer A: `Accounting Core`
  - Generic tables and posting service.
  - Idempotent event posting.
  - Balance updates in DB transactions.
- Layer B: `Domain Adapters`
  - Reservation adapter posts to accounting core on delivery/return payment events.
  - Voucher adapter writes voucher liability entries only.
- Layer C: `Finance UI`
  - Bank Accounts page.
  - Ledger page.
  - Statement page.
  - Transfer workflow.

## 4. Data Model and Interfaces (Public Contract)

### 4.1 Tables
- `bank_accounts`
  - `id, bank_name, account_name, account_number, balance, currency, is_active, created_at, updated_at`
- `ledger_entries`
  - `id, txn_no, txn_type, category, description, amount, currency, payment_mode, reference_no, posted_at, bank_account_id, source_module, source_event, source_id, status, created_by, created_at, updated_at`
- `ledger_transfers`
  - `id, transfer_no, from_account_id, to_account_id, amount, currency, posted_at, created_by, created_at`
- `payment_method_account_map`
  - `method_key, bank_account_id, is_default`
- `voucher_liability_entries`
  - `id, client_id, action, amount, reference_type, reference_id, note, created_by, created_at`

### 4.2 Required constraints/indexes
- `ledger_entries` unique idempotency key: `(source_module, source_event, source_id, reference_no)`
- Positive money validation: `amount > 0`
- FK: `ledger_entries.bank_account_id -> bank_accounts.id`
- Indexed filters: `posted_at, txn_type, bank_account_id, source_module, source_event`

### 4.3 Type enums
- `txn_type`: `income | expense | transfer_in | transfer_out | adjustment`
- `status`: `posted | reversed`
- `source_module`: `reservation | manual | expense | transfer | voucher | challan`

### 4.4 Service interfaces (must exist)
- `postIncome(array $ctx): int`
- `postExpense(array $ctx): int`
- `postTransfer(int $fromId, int $toId, float $amount, array $ctx): array`
- `syncEventCashEntry(string $module, string $event, int $sourceId, float $newAmount, array $ctx): array`
- `reverseManualEntry(int $entryId, string $reason, int $userId): int`
- `reconcileAccount(int $bankAccountId): array`

## 5. Reservation-to-Ledger Mapping (Decision Complete)

| Reservation Event | Condition | Ledger Action | Category | Source Key |
|---|---|---|---|---|
| Delivery saved | `delivery_paid_amount > 0` | `income` | `Reservation Delivery Collection` | `reservation:delivery:{reservation_id}` |
| Return saved | `return_paid_amount > 0` | `income` | `Reservation Return Collection` | `reservation:return:{reservation_id}` |
| Return saved | `return_paid_amount < 0` | `expense` (refund) | `Reservation Refund` | `reservation:return_refund:{reservation_id}` |
| Delivery/Return edited later | Amount changed | `adjustment` delta entry | `Reservation Payment Adjustment` | `reservation:{event}_adjust:{reservation_id}` |

Rules:
- Posting is **cash-basis** only.
- Reservation completion alone does not post income unless cash is received.
- Voucher debit/credit never touches bank cash ledger.
- Use payment-method-to-bank mapping to resolve `bank_account_id`.

## 6. Voucher Accounting Rule
- Keep current voucher behavior as liability movements.
- Write each voucher debit/credit to `voucher_liability_entries`.
- Do not create `ledger_entries` for voucher actions.
- Show voucher totals separately in finance dashboards.

## 7. UI/Workflow Features to Build
- Bank Accounts:
  - Create/edit/activate/deactivate account.
  - View live balance.
- Ledger:
  - Filters: date range, type, account, source, payment mode.
  - Manual add/edit/delete for manual entries only.
  - System-generated entries read-only.
- Transfers:
  - From account, to account, amount, note.
  - Creates paired `transfer_out` and `transfer_in`.
- Statement:
  - Per account running balance with opening/closing totals.
- Permissions:
  - `view_finances`, `manage_bank_accounts`, `manage_ledger_manual_entries`, `manage_transfers`.

## 8. Implementation Steps
1. Create migrations for tables, constraints, indexes, and seed default accounts.
2. Implement accounting service with transaction-safe posting and idempotency checks.
3. Integrate reservation delivery flow with `syncEventCashEntry`.
4. Integrate reservation return flow with `syncEventCashEntry`.
5. Integrate voucher helper hooks to write voucher liability entries.
6. Build finance APIs for manual entries, transfers, statement retrieval.
7. Build Finance UI pages and add sidebar navigation under Finance.
8. Add reconciliation endpoint/script and activity logging for all finance mutations.
9. Backfill historical reservation cash events with idempotent script.
10. Switch dashboard finance widgets to read from ledger totals.

## 9. Backfill Plan
- Script scans all reservations with non-zero `delivery_paid_amount` or `return_paid_amount`.
- For each event, generate deterministic source keys and upsert through `syncEventCashEntry`.
- Dry-run mode prints counts only.
- Execution mode writes entries and logs created/skipped/adjusted counts.
- Post-backfill reconciliation required before production use.

## 10. Test Cases and Acceptance Criteria

### 10.1 Core accounting
- Posting income increases target bank balance exactly once.
- Posting expense decreases target bank balance exactly once.
- Duplicate source event does not duplicate ledger entry.

### 10.2 Transfers
- Transfer creates two entries and net system cash remains unchanged.
- Transfer fails atomically when destination/source is invalid.

### 10.3 Reservation integration
- Delivery payment posts income with correct source key and payment mode mapping.
- Return payment posts income/refund correctly.
- Editing paid amount posts only delta adjustment.

### 10.4 Voucher separation
- Voucher debit/credit updates liability ledger.
- Voucher actions do not alter any bank account balance.

### 10.5 Statement/reconciliation
- Statement running balance equals derived sum from entries.
- Reconciliation reports zero mismatch for all active accounts after test suite.

### 10.6 Security/audit
- Unauthorized users cannot mutate finance data.
- Every create/update/delete/transfer/reversal writes activity log entry.

## 11. Rollout Strategy
- Stage 1: schema + service behind feature flag.
- Stage 2: reservation auto-posting enabled for new transactions.
- Stage 3: run historical backfill and reconciliation.
- Stage 4: enable ledger-driven dashboard numbers and retire old direct sums.

## 12. Assumptions and Defaults (Chosen)
- Scope is **Core Ledger** only for first release.
- Accounting basis is **Cash Basis**.
- Voucher treatment is **Liability Ledger (non-cash)**.
- Default currency is app default (single-currency in phase 1).
- Default bank mapping:
  - `cash -> Cash Box`
  - `upi/bank_transfer/card -> Main Bank`
  - unmapped methods fallback to `Cash Box` and log warning.
- Payables/commission modules are deferred to phase 2.

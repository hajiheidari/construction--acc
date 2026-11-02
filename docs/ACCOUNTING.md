# Accounting model and invariants

This is a modular monolith. `AccountingService` owns all posting; `ProjectService` owns contract rules; `Reports` derives financial totals from the journal. No external service is required for core accounting.

## Source model

The logical invoice, receipt, payment, transfer, offset and opening-batch domains share a `documents` header with a typed, validated payload. This deliberately replaces duplicate workflow headers/status/idempotency logic. Invoice lines, journal lines, owner allocations, check events, import batches, contracts, rules, phases and funding commitments have dedicated tables. Document type plus validated data defines the subtype. This is a pragmatic initial model; subtype fields in JSON should migrate to dedicated tables as querying and validation requirements grow.

Every journal references its source document, project and fiscal year. Every allocation references its invoice/opening document, rule version, journal and accounting date. Audit events preserve approvals, reversals and rule differences. The account chart is a fixed server-controlled map in `AccountingService::ACCOUNTS`; users configure expense categories separately. There is no consolidated reporting.

## Posting and concurrency

1. Verify organization membership AND project membership; only managers/accountants can post. Contract/user/period administration additionally requires manager access.
2. Transactionally lock the project row. All posting/settings mutations use that same project serialization boundary.
3. Recheck document state, fiscal period, invoice payable, owner rules, bank balance and exact amounts.
4. Allocate the final number within project/year/type. Unique constraints cover source idempotency keys, journal posting keys and final numbers.
5. Insert draft journal and lines, verify equality, transition journal to posted, write allocations/audit and approve source atomically.
6. Repeated approval returns the existing approved source. Concurrent approvals cannot duplicate journals.

MySQL and SQLite triggers prohibit changing/deleting posted journals and their lines, and reject posting an empty/unbalanced journal. Further triggers protect posted source data and invoice items, and make rule/check/allocation/audit history append-only. Direct database administration can still bypass application authority; database credentials must remain private.

## Precision

Money uses signed 64-bit integer rials. A single amount/posting total is capped at 9,000,000,000,000 rials to preserve headroom for integer intermediates. No floating point is accepted for financial input. Quantities are decimal strings with up to six decimal places; BCMath multiplies exact quantities by integer unit prices, rounds half up to the rial and subtracts integer discount. Percentage rules use basis points, 10000 = 100%. Fee rounding is half up. Allocation uses largest remainder with ascending party ID as deterministic tie breaker. Allocations sum exactly to costs plus fees. Toman display never changes storage.

## Entries

| Operation | Debit | Credit |
|---|---|---|
| Invoice approval | Project cost | Supplier payable |
| Eligible management fee | Fee cost | Fee payable |
| Bank owner contribution | Bank | Accepted contribution, owner dimension |
| Supplier payment | Supplier payable, invoice dimension | Bank |
| Executor personal payment | Supplier payable, invoice dimension | Executor reimbursement |
| Direct owner payment | Supplier payable, invoice dimension | Accepted contribution, paying owner |
| Approved offset | Supplier payable to the same owner | Accepted owner contribution |
| Executor settlement | Fee payable and/or reimbursement, explicitly split | Bank |
| Bank transfer | Destination bank | Source bank |
| Outgoing check delivery | Supplier payable | Outstanding check liability |
| Outgoing check clearance | Outstanding check liability | Bank |
| Outgoing return/cancellation before clearance | Outstanding check liability | Original supplier payable |
| Incoming owner check receipt | Incoming check asset | Pending contribution control |
| Incoming clearance | Bank + pending control | Incoming check asset + accepted contribution |
| Incoming return/cancellation before clearance | Pending contribution control | Incoming check asset |

Payments never re-recognize expense or fee. Fees are never included in their own base. Offsets/direct owner payments never invent bank movements. Negative bank balances and over-settlements are rejected. Incoming checks are reported separately from collected contributions.

## Opening and amendments

Choose either detailed history or a consolidated opening at project creation. Consolidated mode disallows detailed entries before commencement and allows only one approved opening at commencement. Imports are stored for review; erroneous/duplicate files cannot post. Opening expense/fee costs are allocated using the commencement rule; existing fee balances are not accrued again. Supplier opening lines carry the opening document as their settlement reference. Outstanding checks are separately identified source documents and events, linked to the opening journal. Outgoing opening checks credit check liability and debit opening control; verify opening control against historical records.

The newest approved rule applicable to an invoice date controls new invoices. A prospective amendment adjusts invoices on/after its effective date; a retroactive amendment starts at commencement. Preview computes fee and owner-allocation differences against cumulative existing postings, then posts differences only. Ownership-only changes have append-only allocation differences and audit history referencing the original journal. No fictitious zero-money journal is created. Repeated identical latest rule approval is idempotent. Historical opening cost does not carry item-level eligibility; retroactive fee recalculation of that opening is currently unsupported and must be resolved before relying on amendments for such projects.

Corrections reverse all journal effects and allocation effects of an invoice/payment/offset on an open-period date. A settled invoice cannot reverse until settlements reverse. Opening approval is locked against ordinary reversal because it can have dependent check history. Cleared checks cannot be returned by the current workflow; see limitations in DEVELOPMENT_STATUS.md.

Funding commitments are contractual metadata, not journal receivables. Funding difference is allocated cost minus accepted contribution and is not automatically an overdue legal debt.

# CODEX MASTER PROMPT
## Construction Project Accounting — Production-Ready MVP

### 0. Your mission

You are the lead software architect, senior Laravel engineer, financial systems engineer, database architect, QA engineer, and deployment engineer.

Build a **production-ready Persian construction project accounting web application** based on the requirements below.

**The founder's immediate priority is to start recording real construction project accounts as quickly as possible.**

Do not spend excessive time on planning or repeatedly asking questions. Implement the application incrementally, test each milestone, and continue until M0–M3 are complete.

Do not stop after creating documentation, scaffolding, migrations, or a basic dashboard.

Deliver a genuinely usable accounting application.

**Important constraints:**

- Work in the connected GitHub repository.
- Inspect the repository before making changes.
- If it is empty, initialize the application.
- Maintain `AGENTS.md`, `README.md`, `DEVELOPMENT_STATUS.md`, and `DEPLOYMENT.md`.
- Use feature branches and pull requests.
- Never overwrite existing work without inspecting it.
- Never purchase services or use paid APIs without founder approval.
- Do not deploy to the founder's production hosting without explicit approval.
- Do not create fake financial records in production.
- Use synthetic data for development and tests.
- If a genuine blocker occurs, document it and continue with independent tasks.
- Do not claim a milestone is complete until its acceptance tests pass.

---

# 1. Technology and hosting

The application will ultimately run on an Iranian shared hosting account with:

- DirectAdmin control panel
- PHP selectable up to version 8.5
- MySQL database, exact version currently unknown
- Cron Jobs available
- No SSH or terminal access
- No persistent application workers guaranteed
- No dependency on international internet for core accounting

### Preferred stack

- Laravel, latest stable version compatible with hosting
- PHP 8.4 preferred if fully supported; verify compatibility
- MySQL with InnoDB
- Livewire
- Tailwind CSS
- Responsive RTL Persian UI
- Jalali date input/display
- Gregorian canonical dates in database
- Tehran timezone for business operations
- Rial integer storage
- Optional Toman display

Use a modular monolith.

Do not introduce microservices, Redis, Docker, Node.js runtime, or persistent queue workers as production requirements.

Node.js and Composer may be used during development/build.

Production deployment must use prebuilt artifacts.

Verify framework requirements against the actual hosting environment before production deployment.

### Deployment architecture

Prepare a deployment package that includes:

- Production PHP dependencies
- Compiled frontend assets
- Correct Laravel public directory structure
- Environment configuration template
- Secure installation/migration mechanism
- Cron configuration instructions
- Backup and rollback instructions
- Health-check mechanism

No public installer, unsecured migration endpoint, or publicly accessible environment file is allowed.

A privileged one-time deployment mechanism is acceptable only if authenticated, short-lived, disabled after use, and carefully audited.

Do not assume SSH access.

---

# 2. Product overview

Build a simple accounting system specifically for construction projects.

Each organization can have multiple projects.

Each project maintains independent accounting, ownership, contracts, financial reports, and bank accounts.

Do not implement consolidated financial reporting across projects in the MVP.

The application architecture must support multiple independent customer organizations in the future.

### Roles

1. Manager / Executor — مجری
2. Accountant — حسابدار
3. Owner — مالک

Manager and accountant can record and approve financial documents according to permissions.

Owners have read-only access to all financial information of projects they belong to.

Owners cannot edit or approve documents.

Managers can manage users, project settings, contracts, and permissions.

All access restrictions must be enforced server-side.

A user may belong to multiple projects, but reports remain project-specific.

---

# 3. Core accounting requirements

## 3.1 Double-entry accounting

Implement a genuine double-entry accounting engine.

All posted journal entries must balance.

Posted entries must be immutable.

Corrections must use reversal or adjustment entries.

Never silently delete or rewrite posted financial transactions.

All accounting postings must be transactional and idempotent.

Maintain audit logs for financial operations.

Every financial operation must have a traceable source document.

## 3.2 Monetary precision

- Store money as signed 64-bit integer Iranian rials.
- Use exact decimal arithmetic for percentages, quantities, and calculations.
- Never use floating-point arithmetic for financial values.
- Define deterministic rounding rules.
- Ensure owner allocations sum exactly to the distributable cost.
- Prevent integer overflow and invalid negative balances where prohibited.

## 3.3 Financial dates

- Display Persian Jalali dates.
- Store canonical dates consistently.
- Default document date to the current Tehran date.
- Allow authorized users to edit dates.
- Maintain financial years and closed periods.
- Prevent unauthorized backdated postings into closed periods.

## 3.4 Document numbers

- Generate document numbers automatically.
- Allow authorized editing before final posting.
- Enforce uniqueness by project, fiscal year, and document type.
- Keep immutable internal IDs.
- Allocate final numbers server-side.
- Prevent duplicates during concurrent submissions and offline synchronization.

---

# 4. Projects, owners, and contracts

Each project includes:

- Project name and code
- Address
- Start date
- Status
- Total budget
- Owners and ownership percentages
- Executor
- Executor fee percentage
- Project bank accounts
- Optional construction phases
- Optional owner funding schedules

The total project budget includes all eligible costs and executor management fees.

## 4.1 Ownership percentages

Support multiple owners.

Ownership percentages must total 100%.

Maintain versioned ownership agreements.

Support two amendment modes:

### Retroactive

Apply new percentages from project inception.

Calculate differences between previous and new owner allocations.

Post auditable adjustment entries.

Do not overwrite historical journal entries.

### Prospective

Apply new percentages from a specified effective date.

Keep previous allocations unchanged.

Use the effective accounting date of the underlying expense.

### Contract requirements

Maintain a Contracts & Documents module containing:

- Ownership agreements
- Executor agreements
- Supplier/contractor agreements
- Amendments
- Meeting minutes
- Supporting documents

Store contract number, date, parties, description, attachments, effective dates, versions, and audit history.

Financial percentage changes must reference an agreement or amendment.

Provide a preview of financial adjustments before approval.

---

# 5. Executor management fee

The executor receives a percentage of eligible approved project costs.

The fee itself must never be included in its own calculation base.

### Required behavior

- Accrue the fee when an eligible invoice is approved.
- Accrue even when the invoice remains unpaid.
- Do not accrue again when the invoice is paid.
- Include accrued management fees in total project cost.
- Allocate total project cost, including fees, among owners.
- Adjust accrued fees when an invoice is reversed or corrected.
- Track executor fee payable independently from personal reimbursements.

### Changing fee percentages

Support:

1. Prospective changes from an effective date — default.
2. Retroactive changes from project inception.

For retroactive changes, calculate the difference and post adjustments.

Never recreate or duplicate the original fee.

### Executor personal payments

Normally, project expenses are paid from project bank accounts.

Exceptionally, the executor may pay personally.

Provide a payment source named:

`پرداخت از وجه شخصی مجری`

Do not require personal bank account details.

Personal payments increase the executor's reimbursement claim.

Executor settlements must support:

- Separate settlement of fees and reimbursements
- Combined settlement with explicit allocations

Combined settlements must never generate new expenses.

---

# 6. Parties and multiple financial roles

A single person or company may simultaneously be:

- Owner
- Supplier
- Contractor
- Executor
- Direct payer

Use one party profile with separate financial subledgers.

Never create duplicate party identities merely because someone has multiple roles.

Maintain project-specific balances.

## 6.1 Owner supplying materials or services

Example:

An owner supplies 200,000,000 rials of steel.

On invoice approval:

- Record the project cost.
- Record supplier payable to that owner.
- Calculate executor fee if eligible.
- Allocate project cost among owners.
- Do NOT automatically record an owner contribution.

## 6.2 Owner contribution offset

The founder explicitly selected this workflow:

**First record supplier/contractor receivable, then perform a separately approved offset against owner contribution.**

Implement:

1. Existing approved supplier/contractor payable
2. Draft offset request
3. Select one or more unpaid invoices
4. Specify partial or full offset amount
5. Preview balances
6. Manager/accountant approval
7. Post balanced accounting entry
8. Reduce supplier payable
9. Increase accepted owner contribution
10. Preserve full audit trail

Do not allow offsets exceeding available payable balances.

Allow partial offsets.

Offset transactions must not affect project bank balances.

Support reversal through a controlled accounting operation.

## 6.3 Owner paying another supplier directly

An owner may directly pay a project supplier.

Support recording:

- Source invoice
- Paying owner
- Supplier
- Amount
- Payment date
- Supporting receipt
- Approval

When recognized as an owner contribution:

- Reduce supplier payable
- Increase accepted owner contribution
- Do not affect project bank balance
- Do not recognize the original expense twice

---

# 7. Invoices and expense categories

Each invoice has one shared:

- Project
- Supplier
- Invoice date
- Invoice reference
- Approval status

Each invoice may have multiple line items.

Each line includes:

- Description
- Expense category
- Optional construction phase
- Optional quantity
- Optional unit
- Optional unit price
- Discount
- Final amount

Expense categories support up to three hierarchical levels.

Examples:

مصالح > آهن‌آلات > میلگرد

دستمزد > گچکار

All three levels are not mandatory.

Category structures are configurable.

Historical category references must remain valid.

### Invoice states

- Draft
- Approved
- Reversed or adjusted

Payment state is derived independently:

- Unpaid
- Partially settled
- Fully settled

On invoice approval:

- Post cost
- Post supplier payable
- Accrue eligible executor fee
- Calculate owner allocations

All operations must succeed or fail atomically.

---

# 8. Receipts, payments, and bank accounts

Support:

- Owner cash/bank contributions
- Supplier payments
- Executor reimbursements
- Executor fee settlements
- Direct owner payments
- Transfers between project bank accounts
- Payment allocations to multiple invoices
- Partial settlements

Separate invoice approval from payment settlement.

A bank transfer is not an expense or income.

Maintain actual bank ledger balances based on posted bank transactions.

Do not fabricate bank movements for offsets or direct owner payments.

### Owner funding reports

Separate:

- Allocated share of approved project costs
- Accepted contributions
- Outstanding funding difference
- Scheduled funding commitments
- Legally due installments where configured
- Uncollected checks

A funding difference is not automatically an overdue legal debt.

---

# 9. Checks

Support both incoming and outgoing checks.

Fields:

- Direction
- Project
- Party
- Bank
- Check number
- Optional Sayad identifier
- Amount
- Issue date
- Due date
- Current status
- Attachments
- Event history

### Outgoing checks

On delivery to supplier:

- Reduce supplier invoice payable
- Recognize outstanding check liability
- Do not reduce bank balance yet

On clearing:

- Reduce check liability
- Reduce project bank balance

On return:

- Record return event
- Restore appropriate supplier claim or payable
- Preserve complete history

### Incoming checks

On receiving an owner's check:

- Record an incoming check asset or pending collection
- Do not increase bank balance
- Do not classify the check as collected owner contribution

On clearing:

- Increase bank balance
- Recognize the accepted owner contribution once
- Clear the pending check account

Handle cancellation, return, endorsement, and replacement with auditable state transitions.

Dashboard must show:

- Due today
- Due within seven days
- Overdue
- Returned

SMS notifications are deferred.

---

# 10. Opening balances and historical data

**This is a critical MVP requirement.**

The founder needs to enter existing project accounts immediately.

Each project must support an accounting commencement date.

Build an Opening Balances Wizard.

Support:

- Project bank opening balances
- Existing owner contributions
- Existing supplier payables
- Existing contractor payables
- Outstanding incoming checks
- Outstanding outgoing checks
- Accrued but unpaid executor fees
- Unreimbursed executor personal payments
- Previously accumulated project costs
- Other justified opening balances

### Historical entry modes

Support:

1. Detailed historical invoices and transactions
2. Consolidated opening balances

Do not allow the same historical amount to be counted twice.

Use balanced opening journal entries with appropriate opening equity/control accounts.

Opening entries must be reviewable before final approval.

Support CSV/Excel import with:

- Downloadable template
- Preview
- Validation
- Duplicate detection
- Error report
- Approval before posting

Never import unvalidated rows directly into posted accounting records.

Provide a reconciliation checklist before opening balances are finalized.

---

# 11. Required database design

Design normalized MySQL tables with appropriate:

- Primary keys
- Foreign keys
- Unique constraints
- Composite tenant/project isolation constraints
- Financial indexes
- Transactional posting
- Optimistic or pessimistic locking
- Audit references
- Idempotency keys

Minimum domains:

### Identity

`organizations`
`users`
`organization_members`
`projects`
`project_members`
`parties`

### Contracts

`contracts`
`contract_amendments`
`project_owners`
`ownership_rule_versions`
`ownership_rule_lines`
`executor_fee_rules`

### Accounting

`ledger_accounts`
`journal_entries`
`journal_lines`
`fiscal_years`
`document_sequences`

### Expenses

`expense_categories`
`construction_phases`
`invoices`
`invoice_items`
`invoice_adjustments`
`fee_accruals`
`owner_cost_allocations`

### Money

`bank_accounts`
`receipts`
`payments`
`payment_allocations`
`bank_transfers`
`owner_contributions`
`funding_schedule_items`

### Checks and offsets

`checks`
`check_events`
`offsets`
`offset_allocations`
`offset_approvals`

### Opening balances

`opening_balance_batches`
`opening_balance_lines`
`historical_import_batches`
`historical_import_rows`

### Supporting infrastructure

`attachments`
`audit_logs`
`sync_operations`
`extraction_jobs`

You may improve or normalize this schema where necessary.

Do not blindly implement tables if a more consistent financial model is needed.

Document architectural decisions and explain any changes to the logical schema.

---

# 12. API architecture

Use versioned APIs:

`/api/v1`

Required modules:

- Authentication
- Projects
- Owners
- Contracts
- Parties
- Categories
- Invoices
- Receipts
- Payments
- Checks
- Offsets
- Opening balances
- Journal entries
- Reports
- Attachments
- Audit history

All financial approval endpoints must:

- Verify authorization
- Validate current state
- Validate amount limits
- Prevent duplicate posting
- Use database transactions
- Create balanced journals
- Return clear errors
- Record audit events

Do not trust client-provided totals or permissions.

---

# 13. User interface

Build a professional, modern, Persian RTL dashboard.

The application must work well on mobile and desktop.

Keep forms simple.

### Main menu

1. داشبورد
2. پروژه‌ها
3. هزینه‌ها و فاکتورها
4. دریافت‌ها و پرداخت‌ها
5. چک‌ها
6. اشخاص و حساب‌ها
7. قراردادها و اسناد
8. گزارش‌ها
9. تنظیمات

### Dashboard

Display:

- Total project cost including management fee
- Total approved base costs
- Executor fee accrued
- Project bank balances
- Outstanding supplier payables
- Owner contributions
- Owner allocated shares
- Executor reimbursement claims
- Executor unpaid fees
- Budget remaining
- Pending approvals
- Upcoming checks

### Quick actions

- ثبت هزینه
- ثبت دریافت
- ثبت پرداخت
- ثبت چک
- ثبت تهاتر

Use progressive disclosure for advanced options.

Owner dashboard is read-only.

### Essential usability

- Searchable tables
- Jalali date pickers
- Persian number formatting
- Rial/Toman display toggle
- Clear validation messages
- Mobile-friendly forms
- Confirmation before irreversible postings
- Financial adjustment previews
- Printable reports

---

# 14. Reports

Implement project-specific reports:

1. Project financial summary
2. Budget versus actual
3. Expense details
4. Expenses by category
5. Expenses by optional construction phase
6. Owner contribution and cost allocation
7. Owner funding difference
8. Supplier and contractor ledgers
9. Executor fee statement
10. Executor reimbursement statement
11. Bank ledger
12. Incoming/outgoing check reports
13. Offset transactions
14. Contract amendments and adjustment history
15. Trial balance and journal audit

Reports must support relevant filters and Excel/PDF exports.

All balances must reconcile with the accounting ledger.

Never calculate financial reports from contradictory independent totals.

---

# 15. Security and reliability

Implement:

- Password hashing
- Login throttling
- Secure sessions
- CSRF protection
- Server-side authorization
- Tenant isolation
- Project-level permissions
- Private attachment storage
- Secure upload validation
- Audit logging
- Database transactions
- Idempotent financial posting
- Safe error handling
- Backup and recovery procedures

Never expose secrets in the repository.

Never use real customer financial data in automated tests.

Use appropriate database indexes for project, date, party, status, and source references.

---

# 16. Automated financial tests

Build comprehensive unit, integration, and feature tests.

At minimum, test:

### Invoice and fees

- Unpaid invoice creates payable and executor fee.
- Partial payment does not duplicate expense.
- Full payment does not duplicate fee.
- Invoice reversal adjusts expense and fee.
- Fee does not accrue on itself.
- Concurrent approval cannot double-post.

### Ownership

- Ownership percentages total 100%.
- Prospective amendment preserves earlier allocations.
- Retroactive amendment creates correct adjustments.
- Owner allocations reconcile to total distributable costs.
- Rounding differences are handled deterministically.

### Offsets

- Owner supplier invoice creates supplier payable, not contribution.
- Approved offset reduces payable and increases contribution.
- Partial offset works.
- Excessive offset is rejected.
- Repeated approval is idempotent.
- Reversal restores correct balances.

### Checks

- Incoming check does not increase bank before clearing.
- Outgoing check reduces supplier payable on delivery.
- Outgoing check does not reduce bank before clearing.
- Clearing reduces bank exactly once.
- Returned check restores appropriate liability.
- Invalid state transitions are rejected.

### Executor

- Personal payment increases reimbursement claim.
- Executor fee remains separate from reimbursement.
- Combined settlement allocates correctly.
- Settlements do not create expenses.

### Opening balances

- Opening journal is balanced.
- Duplicate historical imports are rejected.
- Opening balances reconcile.
- Historical expenses are not counted twice.
- Outstanding checks are represented correctly.

### Security

- Owner cannot modify financial data.
- User cannot access another organization's data.
- User cannot access unauthorized projects.
- Posted journals cannot be directly edited.
- Closed periods reject unauthorized posting.

### Invariants

For every posted journal:

`sum(debits) = sum(credits)`

For every invoice:

`total allocated settlements <= payable amount`

For every offset:

`approved offset <= available supplier payable`

For every project:

`sum(owner allocation percentages) = 100%`

Test financial results with exact rial values.

---

# 17. Development milestones

Implement the following milestones in order.

## M0 — Foundation

Deliver:

- Laravel application
- Authentication
- Organization/project isolation
- Roles and permissions
- Project creation
- Parties and owners
- Basic contracts
- RTL interface
- Database migrations
- Automated tests

## M1 — Accounting and historical entry

Deliver:

- Chart of accounts
- Double-entry ledger
- Fiscal years
- Document numbering
- Opening balance wizard
- Historical import preview
- Bank accounts
- Multi-line invoices
- Supplier payables
- Receipts and payments
- Partial settlements
- Automated accounting tests

**M1 must already allow useful financial data entry.**

## M2 — Construction-specific accounting

Deliver:

- Executor management fee
- Owner cost allocations
- Contract percentage amendments
- Retroactive and prospective adjustments
- Executor personal reimbursements
- Incoming and outgoing checks
- Owner direct payments
- Owner supplier/contractor roles
- Approved offset workflow
- Automated financial tests

## M3 — Reports and operational readiness

Deliver:

- Dashboard
- All essential financial reports
- Excel exports
- PDF exports
- Audit history
- Reconciliation checks
- User acceptance tests
- Deployment package
- Backup/restore documentation
- DirectAdmin installation instructions

Do not declare M3 complete until a complete sample project can be entered and reconciled.

---

# 18. Deferred features

Do not delay the operational MVP for:

- AI invoice OCR
- Persian speech-to-text
- Offline PWA synchronization
- SMS notifications
- Inventory management
- Contractor progress certificates
- Payroll
- Native mobile applications
- Multi-project consolidated reports
- SaaS subscriptions and billing

Keep the architecture extensible for these features.

---

# 19. DirectAdmin deployment deliverables

Prepare:

1. Production-ready release archive
2. Environment template
3. Database setup instructions
4. Safe migration procedure without SSH
5. Public document-root instructions
6. Storage permissions checklist
7. Cron configuration
8. Backup procedure
9. Restore procedure
10. Upgrade procedure
11. Rollback procedure
12. Post-deployment health checks

Do not assume the database version.

Provide a compatibility verification checklist for the actual MySQL server.

Do not deploy to production without founder approval.

---

# 20. Required final deliverables

The repository must contain:

- Complete working application
- Database migrations
- Seeders with synthetic test data
- Automated tests
- API documentation
- Accounting rules documentation
- User guide in Persian
- Deployment instructions in Persian
- Opening balance import templates
- Example project dataset
- Release package
- Milestone status report

Update `DEVELOPMENT_STATUS.md` after each milestone.

Include:

- Completed functionality
- Test results
- Known issues
- Remaining work
- Deployment readiness
- Exact next steps

---

# 21. Execution instructions

Start immediately.

First inspect the repository and hosting assumptions.

Create a concise implementation plan.

Then implement M0, M1, M2, and M3 sequentially.

Do not wait for approval between milestones unless a genuine blocker or external action requires founder input.

Commit completed work regularly.

Run tests after each milestone.

Fix failures before moving forward.

Do not claim production readiness without verified tests and deployment compatibility.

**Primary success criterion:**

The founder can create a construction project, enter opening balances, record historical and new invoices, receive owner contributions, pay suppliers, issue checks, record executor fees, perform approved offsets, and obtain accurate financial reports.

Prioritize a reliable, usable application over unnecessary architecture or cosmetic features.

Begin implementation now.
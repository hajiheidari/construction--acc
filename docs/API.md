# API v1

Base: `/api/v1`. Same-origin, cookie-session authentication; state-changing calls require the Laravel CSRF token. Obtain session/CSRF cookies through `/login`, then POST `/login` with email/password and CSRF. There are no public registration or migration endpoints. Send `Accept: application/json`; JSON requests also set `Content-Type: application/json` and `X-CSRF-TOKEN`. Owners may only read authorized projects. Mutations require manager/accountant, and contract/rule/user/fiscal administration requires manager.

| Method | Path | Purpose |
|---|---|---|
| GET/POST | `/projects` | Accessible projects / create project |
| GET | `/projects/{p}` | Project information |
| POST | `/projects/{p}/settings/{resource}` | parties, banks, categories, phases, contracts, rules, members, fiscal, funding |
| POST | `/projects/{p}/documents` | Validate and create draft |
| GET | `/projects/{p}/documents/{d}` | Source, posting preview and journal |
| POST | `/projects/{p}/documents/{d}/approve` | Atomic idempotent posting |
| POST | `/projects/{p}/documents/{d}/reverse` | Controlled reversal; `{date: YYYY-MM-DD}` |
| POST | `/projects/{p}/documents/{d}/check-events` | `{event: cleared/returned/cancelled, date}` |
| GET | `/projects/{p}/reports` | Ledger-derived summary and ledger, JSON |
| GET | `/projects/{p}/reports?export=csv/xlsx/pdf` | Download selected ledger/report |
| POST | `/projects/{p}/imports` | multipart CSV/Excel preview; returns batch and errors |
| POST | `/projects/{p}/imports/{batch}/approve` | Reviewed opening draft, requires `reconciled=1` |
| GET | `/projects/{p}/imports/template?export=xlsx` | Opening template, omit export for CSV |
| POST | `/projects/{p}/documents/{d}/attachments` | Private PDF/JPG/PNG upload, max 5MB |
| GET | `/projects/{p}/attachments/{a}` | Authorized private download |
| POST | `/projects/{p}/fiscal/{id}/close` | Close fiscal year |

Create invoice draft:

```json
{
  "kind": "invoice",
  "date": "2026-11-03",
  "description": "Synthetic material purchase",
  "reference": "TEST-001",
  "idempotency_key": "client-generated-unique-operation-id",
  "data": {
    "party_id": 1,
    "items": [
      {"description": "Synthetic steel", "amount": "200000000", "discount": "0", "fee_eligible": true}
    ]
  }
}
```

`quantity` is an optional exact decimal string, `unit_price` integer rials, `unit` text, `category_id` and `phase_id` optional. When quantity is provided the server computes gross amount and deducts discount. Client totals and permissions are never trusted. Money inputs are integer JSON values or decimal integer strings; floats are rejected.

Settlement data for payment/personal_payment/offset/outgoing_check:

```json
{"party_id":1,"bank_id":1,"allocations":[{"invoice_id":123,"amount":"50000000"}]}
```

For `direct_payment`, also provide `owner_id`. For offset the supplier must be the owner; bank_id is unused. For personal payment bank_id is unused. A supplier opening balance uses the opening document ID as invoice_id.

Other data:

- contribution: party_id (owner), bank_id, amount.
- settlement: bank_id, fee_amount, reimbursement_amount; explicit allocations prevent expenses being recreated.
- transfer: bank_id, to_bank_id, amount.
- incoming_check: party_id (owner), bank_id, amount, number, bank_name, due_date, optional 16-digit sayad.
- outgoing_check: settlement data plus number, bank_name, due_date and optional sayad.
- opening: reconciled=true, lines with account/debit/credit/party_id/bank_id; optional checks with direction incoming/outgoing, party_id/bank_id/amount/number/bank_name/due_date.

Rules endpoint accepts `{payload: JSON-string, approve: false}` for preview, then the same payload with approve=true. Payload: contract_id, effective_date, posting_date, fee_bps, shares object keyed by party ID and integer basis-point values, mode prospective/retroactive. The UI handles the approval preview.

Dates use canonical Gregorian `YYYY-MM-DD` on the API. The Persian UI converts Jalali input/display. Fiscal numbering is allocated server-side and unique per project/year/type. Error statuses include 401/redirect for unauthenticated sessions, 403 for authorization, 404 for missing scoped resources and 422 with an errors object for validation. Approval repeats business validation under a database lock; a stale preview never authorizes an excessive settlement. Reuse idempotency_key only for the same operation/payload.

The current API shares UI controllers; a few setting/import actions return redirects or rendered pages for non-JSON requests. It is a same-origin application API, not yet a public token-based integration API.

# Construction accounting
Use the existing checkout; each cloud task is isolated. Do not create worktrees unless requested.
Read docs/REQUIREMENTS.md and DEVELOPMENT_STATUS.md. PHP 8.4+ / Laravel 13, MySQL InnoDB in production. No production Node, Docker, Redis, persistent workers, or external APIs.
Store money as integer rials; percentages in basis points. Never use floats for accounting. All posting must go through AccountingService, in a transaction under the project lock, with audit and balanced immutable journals. Scope every query to authorized projects. Owners are read-only. Corrections use reversal/adjustment entries. Test exact amounts, access isolation, and idempotency.
Use only synthetic development/test data. Never deploy to production without approval. Never commit .env, keys, database files, private attachments, vendor, or node_modules. Maintain README.md, DEPLOYMENT.md, DEVELOPMENT_STATUS.md and docs.

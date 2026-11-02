# Validation evidence

Current-instance results are recorded here. Publication and restoration in a new task have not been tested. No production deployment has occurred.

- Initial financial suite: PHP runtime, SQLite: 23 tests, 89 assertions passed.
- Expanded suite: PHP 8.4.27 and MySQL 8.4: 30 tests, 117 assertions passed.
- Real concurrency: two independent PHP processes approving the same invoice produced one journal and exactly 1,000,000 rials base cost.
- Chromium: login, invoice creation, preview, approval, Livewire refresh, desktop and 390px mobile layout passed with no JS exceptions or page overflow.
- Synthetic sample project: trial difference 0 and allocation difference 0.
- Vite production build succeeded with all runtime assets local.

The final repeated setup script, additional CLI installation tests and release validation are recorded after their completion below.

## Final current-instance results

- PHP 8.4.27 + SQLite: **34 tests, 128 assertions passed**.
- PHP 8.4.27 + MySQL 8.4: **34 tests, 128 assertions passed**.
- Concurrent MySQL approval rerun passed after correcting import ordering in the CLI smoke script.
- Reusable `cloud-setup.sh` completed successfully (32 tests at that point); the two added exact-quantity/opening-amendment tests passed in both final suites.
- Reusable `cloud-start.sh` passed from a stopped server and on repeated invocation; login form HTTP request verified.
- Production-only staged vendor dependencies passed fresh migrations, exact invoice/fee/allocation posting, and HTTP-kernel login rendering via scripts/validate-release.php.
- Review archive and SHA256 generated in dist. It is not a production release certification.
- Git diff whitespace checks passed; .env, local database and private audit files are ignored.
- install_script and start_skill persisted to the environment draft; api.github.com also added to the draft. Saving does not apply/publish. Fresh-task restoration is untested.
- GitHub repository read succeeded via existing HTTPS Git authentication. GitHub API operation returned Forbidden; creating a PR remains blocked. No new token was requested.

Failures diagnosed and corrected: MySQL self-FK creation order; tests incorrectly assuming reset auto-increment IDs on MySQL; SQLite invoice-table rebuild losing triggers; incomplete npm optional dependency metadata; mobile grid overflow; CLI import aliases appearing after first use. The corrected SQLite trigger migration creates missing triggers only and preserves local data.

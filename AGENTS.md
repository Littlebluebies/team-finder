# AGENTS.md

## Ownership and Scope

- Backend lives in `backend/`; frontend lives in `frontend/`.
- Only backend owners change migrations or database schema.
- `docs/api.md` is the API source of truth. Change it in the same change set as any API behavior change.
- Keep the main flow working before adding secondary features.

## Backend Authorization Is Mandatory

- Authenticate every protected endpoint at backend with the Bearer token.
- Return 401 for missing, invalid, or expired authentication and 403 for an authenticated user without permission.
- Never treat hidden React controls as authorization.
- Derive user IDs from the authenticated identity, not client-supplied owner/user IDs.
- Recheck post ownership, application state, and slot availability in the backend action that changes data.
- Use transactions for multi-row writes that must succeed or fail together.

## LINE ID Privacy

- Never expose another user's `line_id` unless the requester has the related application for the same post and its status is `accepted`.
- For an owner viewing applicants, include `applicant.line_id` only on accepted applications.
- For an applicant viewing a post owner, include `owner.line_id` only when their own application is accepted.
- Omit the `line_id` key for pending, rejected, or absent applications; do not substitute another user's value from cache.
- A user's own `line_id` is allowed only in contract-defined self responses such as register/login/me.
- Never put LINE IDs in feeds, logs, or cross-user caches.

## Secrets and Authentication

- Never commit `.env`, secret-bearing environment variants, or `backend/config/db-local.php`, including with force-add.
- Commit only `.env.example` and `db-local.example.php` files containing non-secret placeholders.
- Never commit or log real passwords, raw tokens, token hashes, DB credentials, or production secrets.
- If a secret enters Git history, rotate/revoke it immediately; deleting it in a later commit is insufficient.
- Hash passwords with Yii2 Security and never store plaintext passwords.
- Do not return password hashes, raw token hashes, or internal fields in API responses.

## Verification and Git

- Add a repeatable success and failure check for every new endpoint.
- For protected resources, test unauthenticated, non-owner, and owner cases.
- For LINE ID responses, test pending, rejected, and accepted cases when those endpoints are implemented.
- Run migrations against an empty local test database before merging schema changes.
- Do not weaken or delete a failing test to hide a backend bug.
- Run relevant backend smoke tests and frontend lint/build before handoff.
- Ask the user for fresh confirmation before every Git mutation, including init, add, commit, and push.

# Authentication: decisions pending production requirements

Status: **open**. Nothing in this file has been changed in code. It records what the system does today, the risk, and
what must be known before deciding. Update it (and the tests named below) when a decision is made.

---

## 1. Email verification policy (account creation)

**Current behaviour (approved "demo policy")**

- A Laravel account is created on the first request that carries a valid Supabase token
  (`App\Auth\Supabase\SupabaseUserProvisioner`).
- The only identity key is the Supabase user id (`sub`). Accounts are never linked by email.
- The token's email is **not** checked for verification. `users.email_verified_at` stays `null`.
- An email that already belongs to a different Laravel user is refused with `409`
  ("An account with this email already exists."), and nothing is linked.

**Risk**

- If the Supabase project lets people sign up **without confirming their email**, anyone can register with somebody
  else's address before that person does. They get the Laravel account holding that email, and the real owner later gets
  `409` and is locked out (account squatting).
- The `409` also tells anyone who can register a Supabase account whether an email already exists in Laravel
  (email-existence oracle).
- Neither can be exploited if Supabase only issues tokens for confirmed emails. That is a Supabase project setting that
  this repository can not see.

**What is needed to decide**

1. Is "Confirm email" enabled in the production Supabase project, and are other providers (Google, ...) enabled?
2. Which claim in your real tokens says the email is verified? It must be checked against actual tokens before any code
   relies on it (it is not assumed here).
3. Product choice for unverified emails: allow (today), allow with limited access, or refuse until verified.

**Options**

- A. Keep today's behaviour and require "Confirm email" in Supabase. No code change; the guarantee lives in Supabase.
- B. Also refuse account creation unless the token says the email is verified (new refusal reason, 403).
- C. Keep creating accounts but store the verification state and gate sensitive features on it.

**Tests to revisit when decided:** `SupabaseUserProvisionerTest`, `SupabaseGuardTest` (email conflict / no email cases).

---

## 2. Role changes and bans (how fast they take effect)

**Current behaviour**

- The role is read from the verified token's `app_metadata.role` on every request and is never stored in Laravel.
- A role change or a ban made in Supabase therefore takes effect **only when the user's current access token expires and
  a new one is issued**. Until then the old token, with the old role, keeps working. Laravel has no revocation list.
- Added with the security fixes: if `app_metadata.role` is set explicitly to anything other than `user`, `lawyer` or
  `admin` (for example `suspended` or `banned`), the request is refused with `403` before any account is looked up or
  created, and one reason-code-only line is logged (`unsupported_role`). A missing role still means `user`.
  This gives operators a way to disable an account, but it too applies only from the next token.

**Risk**

- A demoted administrator or a banned user keeps access for up to the access-token lifetime (Supabase's default is one
  hour; the real value of this project has not been checked).
- Highest impact on `role:admin` routes.

**What is needed to decide**

1. The access-token lifetime configured in the production Supabase project, and the longest acceptable delay for
   removing an administrator.
2. Whether any action must be revocable immediately (payments, data export, admin changes) as opposed to "within the
   hour".
3. How accounts will be disabled in practice (Supabase ban feature, role value, both).

**Options**

- A. Accept the delay and shorten the access-token lifetime in Supabase.
- B. Re-check the user against Supabase (or a local deny-list) on selected sensitive routes only.
- C. Keep a local, cached "disabled" flag checked on every request, set by an admin action or webhook.

**Tests to revisit when decided:** `UnsupportedRoleTest`, `GuardRequestIsolationTest`.

---

## Operational requirements added with the Stage 3 security fixes

These are not open decisions; they are things a deployment must get right.

| Item | Requirement |
|---|---|
| CORS | Set `FRONTEND_ORIGIN` in production. If it is missing there, **no** origin is allowed. The `http://localhost:5173` default applies only when `APP_ENV` is `local` or `testing`. |
| Rate limits | Defaults are 300 requests/minute per client address and 120 per signed-in client (ten times that in local/testing). Tune with `API_RATE_LIMIT_PER_IP` and `API_RATE_LIMIT_PER_TOKEN`; invalid values fall back to the defaults. |
| Reverse proxy | Behind a proxy or load balancer the client address is the proxy's unless trusted proxies are configured, so everyone would share one per-address allowance. Configure trusted proxies before going behind one. |
| Rate-limit store | The limiter uses the default cache store (`CACHE_STORE`). With several servers use a shared store (database or Redis), not `file` or `array`. |
| Debug | `APP_DEBUG` must be `false` in production: with it on, a 500 response includes the exception class and message. |
| Long-lived workers | The guard no longer keeps a user between requests (`SupabaseGuard`), so Octane, Swoole, RoadRunner and FrankenPHP worker mode are no longer an authentication risk. Other per-request state in future code still needs the same care. |
| Legacy HS256 secret | `SUPABASE_LEGACY_JWT_SECRET` can forge any token, including `admin`. Leave it unset unless a migration needs it, and set `SUPABASE_LEGACY_HS256_UNTIL`. |

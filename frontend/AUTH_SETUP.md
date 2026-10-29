# LawAssist AI: sign-in setup (Supabase)

Sign-in, sign-up, Google sign-in and the protected chat page (`/assistant`) use [Supabase Auth](https://supabase.com/docs/guides/auth).
Nothing works until the steps below are done.

## 1. Install the package and add your keys

```bash
cd frontend
npm install                       # installs @supabase/supabase-js (already listed in package.json)
cp .env.example .env              # (only if you do not have a .env yet) then fill in the two values
```

`.env` needs the project URL and the **publishable** key (Supabase → Project Settings → API):

```
VITE_SUPABASE_URL=https://your-project-ref.supabase.co
VITE_SUPABASE_PUBLISHABLE_KEY=your-publishable-key
```

Never put the `service_role` / secret key here: every `VITE_` value is sent to visitors' browsers.
Do not commit `.env`.

Restart the dev server after changing `.env`.

**Docker:** `node_modules` lives in a Docker volume, so after pulling these changes run
`docker compose up --build -V` (the `-V` renews that volume), then restart as usual.

## 2. Supabase settings (Authentication → URL Configuration)

- **Site URL:** your site address, e.g. `http://localhost:5173` while developing.
- **Redirect URLs:** add both
  - `http://localhost:5173/assistant` (Google sign-in returns here)
  - `http://localhost:5173/login` (the link in the confirmation e-mail returns here)

  Add the same two for your real domain when you deploy.

## 3. E-mail sign-up (Authentication → Providers → Email)

- **Confirm email ON** (the default): after sign-up the person sees "Check your email" and goes to Log in; they can only
  log in after opening the link in the e-mail.
- **Confirm email OFF:** sign-up creates a session at once and goes straight to the chat.

Supabase's built-in e-mail sender is rate-limited and only meant for testing. Set up your own SMTP provider before launch.

## 4. Google sign-in

If *Continue with Google* shows "Google sign-in is not switched on for this site yet" (or, before that message existed, a page with
`{"code":400,"error_code":"validation_failed","msg":"Unsupported provider: provider is not enabled"}`), the **Google provider is still
switched off in your Supabase project**. The site's code is fine; two things must be set up outside the code:

### 4a. Google Cloud (creates the Client ID and Client secret)

1. Open <https://console.cloud.google.com> and sign in. Top bar -> project picker -> **New project** -> name it `LawAssist AI` -> **Create**, then select it.
2. Menu -> **APIs & Services -> OAuth consent screen** (newer console: **Google Auth Platform**) -> **Get started**:
   - App name `LawAssist AI`, your e-mail as support e-mail.
   - Audience / user type: **External**.
   - Contact e-mail: yours. Agree and **Create**.
   - While the app is in **Testing**, only the Google accounts listed under **Audience -> Test users** can sign in: add your own Gmail (and any tester).
   - The default permissions (`openid`, `email`, `profile`) are all this site needs. No extra scopes.
3. Menu -> **APIs & Services -> Credentials -> Create credentials -> OAuth client ID**:
   - Application type: **Web application**, name `LawAssist AI web`.
   - **Authorized JavaScript origins** (optional for this flow): `http://localhost:5173`
   - **Authorized redirect URIs** -> **Add URI**: `https://<your-project-ref>.supabase.co/auth/v1/callback`
     (this is SUPABASE'S address, not localhost. Supabase shows it to you on its Google provider page, so you can copy it from there.)
   - **Create**. Copy the **Client ID** and **Client secret** (keep the secret private: never put it in `.env`, the code or git).
4. Changes at Google can take a few minutes (rarely up to an hour) to apply.

### 4b. Supabase dashboard

1. **Authentication -> Sign In / Providers** (older dashboards: *Authentication -> Providers*) -> **Google**.
2. Switch **Enable Sign in with Google** ON, paste the **Client ID** and **Client secret** from step 4a, leave "Skip nonce check" off, **Save**.
3. **Authentication -> URL Configuration** (see section 2): Site URL `http://localhost:5173`, and Redirect URLs including `http://localhost:5173/assistant`.

### 4c. What the two Supabase URL settings mean

- **Site URL** is the default address Supabase sends people back to. It is also the fallback when the address the site asks for is
  not on the allow-list. Use `http://localhost:5173` while developing and your real domain in production.
- **Redirect URLs** is the allow-list of addresses Supabase may send people back to after sign-in. The site asks for
  `<current site address>/assistant`, so that exact address (including the port) must be listed.
  If it is missing, sign-in "works" but you land on the Site URL instead of `/assistant`.
  Open the site as `http://localhost:5173` and not `http://127.0.0.1:5173`: they count as different addresses (list both if you use both).
  When you deploy, add `https://your-domain/assistant` and `https://your-domain/login` and set the Site URL to `https://your-domain`.

### 4d. Common Google sign-in errors

| What you see | Cause | Fix |
| --- | --- | --- |
| "Google sign-in is not switched on..." / `Unsupported provider: provider is not enabled` | Google provider is OFF in Supabase | 4b step 2 |
| Google page: `Error 400: redirect_uri_mismatch` | The redirect URI in Google Cloud is not exactly Supabase's callback | 4a step 3: use `https://<ref>.supabase.co/auth/v1/callback` |
| Google page: `Error 403: access_denied` / "app not verified" | App is in Testing and your account is not a test user | Add the Gmail under Audience -> Test users (or publish the app) |
| Back on the wrong page (not `/assistant`) | Address not in Supabase Redirect URLs | 4b step 3 |
| `invalid_client` / "Unable to exchange external code" | Wrong Client ID or secret pasted into Supabase | Paste them again from Google Cloud |

## 5. Testing checklist

1. Signed out, open `/assistant` → you land on `/login`.
2. Sign up with e-mail → "Check your email" (or the chat, if confirmation is off) → *Go to Log in*.
3. Open the confirmation link, then log in → you land on `/assistant`.
4. Refresh the chat page → you stay signed in.
5. *Log out* (navbar or chat sidebar) → you land on `/login`; `/assistant` is protected again.
6. *Continue with Google* → Google → back on `/assistant`.

"Remember me" ticked keeps you signed in after closing the browser; unticked keeps you signed in until the tab/window is closed
(a refresh keeps you signed in either way).

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

## 4. Google sign-in (not working until you do this)

1. Google Cloud Console → *APIs & Services → Credentials → Create credentials → OAuth client ID* (type **Web application**).
2. Under *Authorized redirect URIs* add Supabase's callback: `https://<your-project-ref>.supabase.co/auth/v1/callback`.
3. Supabase → Authentication → Providers → **Google**: switch it on and paste the Client ID and Client secret.
4. Make sure the Redirect URLs from step 2 are saved in Supabase.

## 5. Testing checklist

1. Signed out, open `/assistant` → you land on `/login`.
2. Sign up with e-mail → "Check your email" (or the chat, if confirmation is off) → *Go to Log in*.
3. Open the confirmation link, then log in → you land on `/assistant`.
4. Refresh the chat page → you stay signed in.
5. *Log out* (navbar or chat sidebar) → you land on `/login`; `/assistant` is protected again.
6. *Continue with Google* → Google → back on `/assistant`.

"Remember me" ticked keeps you signed in after closing the browser; unticked keeps you signed in until the tab/window is closed
(a refresh keeps you signed in either way).

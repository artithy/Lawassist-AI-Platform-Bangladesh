import { supabase, isAuthConfigured, setRememberMe } from './supabaseClient'

// "Continue with Google" through Supabase OAuth.
//
// This sends the browser to Google, and Google sends it back to /assistant. There Supabase finishes the sign-in
// (the page code in AuthProvider.jsx picks the new session up). It only works after you have:
//   1. created a Google OAuth client in Google Cloud and put its ID + secret into
//      Supabase -> Authentication -> Providers -> Google (and switched that provider ON), and
//   2. added   <your site address>/assistant   to Supabase -> Authentication -> URL Configuration -> Redirect URLs, and
//      added Supabase's callback address (https://<project-ref>.supabase.co/auth/v1/callback) to the Google client.
// (AUTH_SETUP.md lists every step.)
//
// Returns {} when the browser is being sent to Google, or { errorKey } when it could not start.

// signInWithOAuth() only BUILDS the Google address; it never asks Supabase whether Google is switched on.
// If the Google provider is still off in the Supabase dashboard, the browser would land on a raw page that says
//   {"code":400,"error_code":"validation_failed","msg":"Unsupported provider: provider is not enabled"}
// So before leaving the site we ask Supabase once (without following the redirect) and show a friendly message instead.
// If that check cannot be read for any reason (network, browser rules), we simply carry on to Google as normal.
async function googleIsSwitchedOff(authorizeUrl) {
  try {
    const response = await fetch(authorizeUrl, { redirect: 'manual' })
    // A working provider answers with a redirect to Google ("opaqueredirect" in the browser).
    if (response.type === 'opaqueredirect' || response.status < 400) return false
    const body = await response.json().catch(function () {
      return null
    })
    const text = body ? String(body.msg || body.message || body.error_description || '') : ''
    return body ? body.error_code === 'validation_failed' && /provider is not enabled|unsupported provider/i.test(text) : false
  } catch {
    return false
  }
}

export async function signInWithGoogle({ remember = true } = {}) {
  if (!isAuthConfigured) return { errorKey: 'notConfigured' }
  setRememberMe(remember)
  try {
    const { data, error } = await supabase.auth.signInWithOAuth({
      provider: 'google',
      options: { redirectTo: window.location.origin + '/assistant', skipBrowserRedirect: true },
    })
    if (error || !data || !data.url) return { errorKey: 'googleFailed' }

    if (await googleIsSwitchedOff(data.url)) return { errorKey: 'googleNotEnabled' }

    window.location.assign(data.url) // off to Google
    return {}
  } catch {
    return { errorKey: 'googleFailed' }
  }
}

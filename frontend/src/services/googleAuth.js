import { supabase, isAuthConfigured, setRememberMe } from './supabaseClient'

// "Continue with Google" through Supabase OAuth.
//
// This sends the browser to Google, and Google sends it back to /assistant. There Supabase finishes the sign-in
// (the page code in AuthProvider.jsx picks the new session up). It only works after you have:
//   1. created a Google OAuth client in Google Cloud and put its ID + secret into
//      Supabase -> Authentication -> Providers -> Google, and
//   2. added   <your site address>/assistant   to Supabase -> Authentication -> URL Configuration -> Redirect URLs, and
//      added Supabase's callback address (https://<project-ref>.supabase.co/auth/v1/callback) to the Google client.
// (README / the notes that came with this change list every step.)
//
// Returns {} when the browser is being sent to Google, or { errorKey } when it could not start.
export async function signInWithGoogle({ remember = true } = {}) {
  if (!isAuthConfigured) return { errorKey: 'notConfigured' }
  setRememberMe(remember)
  try {
    const { error } = await supabase.auth.signInWithOAuth({
      provider: 'google',
      options: { redirectTo: window.location.origin + '/assistant' },
    })
    if (error) return { errorKey: 'googleFailed' }
    return {}
  } catch {
    return { errorKey: 'googleFailed' }
  }
}

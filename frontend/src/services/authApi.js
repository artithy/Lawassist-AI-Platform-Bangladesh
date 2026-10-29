import { supabase, isAuthConfigured, setRememberMe } from './supabaseClient'

// E-mail + password authentication with Supabase.
//
// Every function here NEVER throws. It returns either
//     { session }   (or { status: ... })  when it worked, or
//     { errorKey }  the name of a message in translations.js (t.auth.errors.<errorKey>)
// so the pages only have to show the right translated message.

// Turn a Supabase error into one of our message names
function errorKeyOf(error, fallback) {
  const message = String((error && error.message) || '').toLowerCase()
  const code = String((error && error.code) || '').toLowerCase()
  const status = error && error.status

  if (code === 'email_not_confirmed' || message.includes('email not confirmed')) return 'emailNotConfirmed'
  if (code === 'invalid_credentials' || message.includes('invalid login credentials')) return 'invalidCredentials'
  if (code === 'user_already_exists' || code === 'email_exists' || message.includes('already registered')) return 'emailTaken'
  if (code === 'weak_password') return 'passwordWeak'
  if (status === 429 || code.includes('rate_limit')) return 'tooManyRequests'
  if (status === 0 || error?.name === 'AuthRetryableFetchError' || message.includes('failed to fetch') || message.includes('network')) return 'network'
  return fallback
}

export async function loginWithEmail({ email, password, remember }) {
  if (!isAuthConfigured) return { errorKey: 'notConfigured' }
  setRememberMe(remember)
  try {
    const { data, error } = await supabase.auth.signInWithPassword({ email, password })
    if (error) return { errorKey: errorKeyOf(error, 'loginFailed') }
    if (!data || !data.session) return { errorKey: 'loginFailed' }
    return { session: data.session }
  } catch (error) {
    return { errorKey: errorKeyOf(error, 'loginFailed') }
  }
}

// status "signedIn":     e-mail confirmation is OFF in Supabase, so a session already exists
// status "confirmEmail": e-mail confirmation is ON, so the person must open the link we e-mailed
export async function registerWithEmail({ name, email, password, language }) {
  if (!isAuthConfigured) return { errorKey: 'notConfigured' }
  setRememberMe(true)
  try {
    const { data, error } = await supabase.auth.signUp({
      email,
      password,
      options: {
        data: { full_name: name, language },
        // where the confirmation link in the e-mail brings the person back to
        // (this address must be listed under Authentication -> URL Configuration in Supabase)
        emailRedirectTo: window.location.origin + '/login',
      },
    })
    if (error) return { errorKey: errorKeyOf(error, 'registerFailed') }
    if (data && data.session) return { status: 'signedIn', session: data.session }
    // With e-mail confirmation on, Supabase answers "success" for an e-mail that already has an account,
    // but the returned user then has no identities. Tell the person instead of pretending a mail was sent.
    if (data && data.user && Array.isArray(data.user.identities) && data.user.identities.length === 0) {
      return { errorKey: 'emailTaken' }
    }
    return { status: 'confirmEmail' }
  } catch (error) {
    return { errorKey: errorKeyOf(error, 'registerFailed') }
  }
}

export async function logout() {
  if (!isAuthConfigured) return {}
  try {
    // "local": ends the session in THIS browser only
    await supabase.auth.signOut({ scope: 'local' })
  } catch {
    // even if the server cannot be reached, the page below clears its own copy of the session
  }
  return {}
}

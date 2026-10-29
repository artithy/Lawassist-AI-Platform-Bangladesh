import { createClient } from '@supabase/supabase-js'

// The ONE place that creates the Supabase client.
//
// Put your project's details in frontend/.env (see frontend/.env.example), then restart `npm run dev`:
//
//     VITE_SUPABASE_URL=https://your-project-ref.supabase.co
//     VITE_SUPABASE_PUBLISHABLE_KEY=your-publishable-key      (the older name VITE_SUPABASE_ANON_KEY also works)
//
// Only the public "publishable" (or older "anon") key belongs here. NEVER put the "service_role" / secret key in
// a VITE_ variable: everything starting with VITE_ is sent to every visitor's browser.
const url = import.meta.env.VITE_SUPABASE_URL
const key = import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY || import.meta.env.VITE_SUPABASE_ANON_KEY

// "Remember me":
//   ticked     -> the session is kept in localStorage (still signed in after closing the browser)
//   not ticked -> the session is kept in sessionStorage (signed in until this tab/window is closed;
//                 a refresh keeps you signed in)
// Only a harmless on/off flag is stored by us. Passwords are never stored anywhere by this site.
const REMEMBER_FLAG = 'lawassist-remember-me'

export function setRememberMe(remember) {
  try {
    localStorage.setItem(REMEMBER_FLAG, remember ? '1' : '0')
  } catch {
    // storage blocked: Supabase then simply keeps the session in memory
  }
}

function currentStore() {
  try {
    return localStorage.getItem(REMEMBER_FLAG) === '0' ? sessionStorage : localStorage
  } catch {
    return null
  }
}

// Supabase asks this object where to keep its session
const sessionStorageAdapter = {
  getItem(name) {
    try {
      const fromLocal = localStorage.getItem(name)
      return fromLocal !== null ? fromLocal : sessionStorage.getItem(name)
    } catch {
      return null
    }
  },
  setItem(name, value) {
    const store = currentStore()
    if (!store) return
    try {
      store.setItem(name, value)
      // never keep two copies
      ;(store === localStorage ? sessionStorage : localStorage).removeItem(name)
    } catch {
      // ignore
    }
  },
  removeItem(name) {
    try {
      localStorage.removeItem(name)
      sessionStorage.removeItem(name)
    } catch {
      // ignore
    }
  },
}

function makeClient() {
  if (!url || !key) {
    console.warn(
      'LawAssist AI: sign-in is switched off because VITE_SUPABASE_URL and VITE_SUPABASE_PUBLISHABLE_KEY are not set. ' +
        'Copy frontend/.env.example to frontend/.env, fill them in and restart the dev server.',
    )
    return null
  }
  try {
    return createClient(url, key, {
      auth: {
        persistSession: true, // keep the session, so a page refresh keeps you signed in
        autoRefreshToken: true, // renew the session before it expires
        detectSessionInUrl: true, // finish Google sign-in / e-mail confirmation links
        flowType: 'pkce', // the safer redirect flow for browser apps
        storage: sessionStorageAdapter,
      },
    })
  } catch (error) {
    console.error('LawAssist AI: the Supabase client could not be created. Check VITE_SUPABASE_URL.', error)
    return null
  }
}

export const supabase = makeClient()

// true when the project details are present
export const isAuthConfigured = supabase !== null

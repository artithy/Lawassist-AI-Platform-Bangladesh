// "Where was this person trying to go?" - used by the login flow.
//
// When a signed-out visitor opens a members-only page (for example a lawyer profile), ProtectedRoute sends them to
// /login and passes the page they wanted in the router state ("from"). After login they are sent back there.
//
// Router state is lost when the browser leaves the site, which is what happens with "Continue with Google".
// So, just before that redirect, the wanted PATH (never a password or token) is kept in sessionStorage
// for a few minutes, and App.jsx uses it once when the person comes back signed in.

const KEY = 'lawassist.returnTo'
const MAX_AGE_MS = 10 * 60 * 1000

// Only same-site paths are accepted ("/lawyers/tanvir"), never "https://..." or "//other-site.com"
export function isSafePath(path) {
  return typeof path === 'string' && path.startsWith('/') && !path.startsWith('//') && !path.includes('\\')
}

// "/lawyers/tanvir?x=1#top" from a router location
export function pathOf(location) {
  return location.pathname + (location.search || '') + (location.hash || '')
}

// The wanted page from the router state, or '' when there is none (or it looks unsafe)
export function fromState(state) {
  return state && isSafePath(state.from) ? state.from : ''
}

export function saveReturnTo(path) {
  try {
    if (isSafePath(path)) sessionStorage.setItem(KEY, JSON.stringify({ path: path, at: Date.now() }))
    else sessionStorage.removeItem(KEY)
  } catch {
    // storage blocked: the person just lands on the default page after Google
  }
}

export function clearReturnTo() {
  try {
    sessionStorage.removeItem(KEY)
  } catch {
    // ignore
  }
}

// Read the saved path once (it is removed), or '' when nothing valid is saved
export function takeReturnTo() {
  try {
    const raw = sessionStorage.getItem(KEY)
    sessionStorage.removeItem(KEY)
    if (!raw) return ''
    const saved = JSON.parse(raw)
    if (saved && isSafePath(saved.path) && Date.now() - saved.at < MAX_AGE_MS) return saved.path
  } catch {
    // ignore
  }
  return ''
}

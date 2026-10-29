// The ONE place that talks to a sign-up / sign-in backend.
//
// This project does not have an authentication backend yet. Until you connect one, the Register and Login
// pages run in DEMO MODE: they check what was typed, but nothing is created, saved or sent anywhere.
//
// To connect a real backend, put its address in frontend/.env and restart `npm run dev`:
//
//     VITE_AUTH_API_URL=http://localhost:8000/api/auth
//
// The pages then call:
//     POST {VITE_AUTH_API_URL}/register   body { name, email, password, language }
//     POST {VITE_AUTH_API_URL}/login      body { email, password, remember }   (remember = the "Remember me" tick-box)
// and expect a JSON answer  { "user": { "name": "...", "email": "..." } }  on success.
// Error statuses that are understood:  409 on /register = e-mail already used,  401 on /login = wrong e-mail or password.
// Anything else is shown as a general error. (This shape is a suggestion - change it here if your backend differs.)
//
// Security notes for the real backend:
//   - passwords must be hashed on the server (e.g. bcrypt or argon2) and are only ever sent over HTTPS;
//   - prefer a session in an httpOnly cookie (this file sends cookies with `credentials: 'include'`) over keeping
//     a token in localStorage, where any script on the page can read it;
//   - this front end never stores the password anywhere.

const BASE_URL = (import.meta.env.VITE_AUTH_API_URL || '').replace(/\/+$/, '')

// true only when an address has been set
export const isAuthConfigured = BASE_URL !== ''

// An error with a short "code" the pages can turn into a translated message
function makeError(code, message) {
  const error = new Error(message)
  error.code = code
  return error
}

async function post(path, body, statusCodes) {
  if (!isAuthConfigured) {
    throw makeError('NOT_CONFIGURED', 'The authentication backend is not configured.')
  }

  let response
  try {
    response = await fetch(BASE_URL + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(body),
    })
  } catch {
    throw makeError('NETWORK', 'The server could not be reached.')
  }

  if (!response.ok) {
    throw makeError(statusCodes[response.status] || 'FAILED', 'The server answered with status ' + response.status)
  }

  const data = await response.json().catch(function () {
    return null
  })
  if (!data || !data.user || typeof data.user.email !== 'string') {
    throw makeError('FAILED', 'The server sent an answer in an unexpected format.')
  }
  return { user: { name: typeof data.user.name === 'string' ? data.user.name : '', email: data.user.email } }
}

export function registerUser({ name, email, password, language }) {
  return post('/register', { name, email, password, language }, { 409: 'EMAIL_TAKEN' })
}

export function loginUser({ email, password, remember }) {
  return post('/login', { email, password, remember: Boolean(remember) }, { 401: 'INVALID_CREDENTIALS' })
}

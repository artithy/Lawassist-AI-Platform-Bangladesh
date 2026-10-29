// Checks for the sign-up and log-in forms.
// Each function returns '' when the value is fine, or the NAME of the error message in translations.js
// (t.auth.errors.<name>), so the same checks work in English and Bangla.

export function checkName(value) {
  const name = value.trim()
  if (name === '') return 'nameRequired'
  if (name.length < 2) return 'nameShort'
  return ''
}

export function checkEmail(value) {
  const email = value.trim()
  if (email === '') return 'emailRequired'
  // something@something.something, no spaces
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) return 'emailInvalid'
  return ''
}

// Sign-up: at least 8 characters, with a letter and a number
export function checkNewPassword(value) {
  if (value === '') return 'passwordRequired'
  if (value.length < 8) return 'passwordShort'
  if (!/\p{L}/u.test(value) || !/\d/.test(value)) return 'passwordWeak'
  return ''
}

// Log-in: only check that something was typed (the server decides if it is right)
export function checkAnyPassword(value) {
  return value === '' ? 'passwordRequired' : ''
}

export function checkConfirm(password, confirm) {
  if (confirm === '') return 'confirmRequired'
  if (confirm !== password) return 'confirmMismatch'
  return ''
}

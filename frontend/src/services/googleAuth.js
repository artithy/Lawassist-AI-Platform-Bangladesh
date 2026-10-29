// Where REAL "Continue with Google" will live.
//
// Right now Google sign-in is NOT connected, so the Login page shows a small dialog that says so and offers a
// clearly labelled demo mode instead. Nothing here talks to Google.
//
// To connect real Google sign-in later (for example with Google Identity Services and your backend):
//   1. create an OAuth client in Google Cloud and keep its client id in frontend/.env (VITE_GOOGLE_CLIENT_ID);
//   2. implement signInWithGoogle() below so it returns  { user: { name, email } }  after your backend has
//      verified the Google token (never trust a token that was only checked in the browser);
//   3. set isGoogleSignInConnected to true. The Login page then calls signInWithGoogle() instead of opening the dialog.

export const isGoogleSignInConnected = false

export async function signInWithGoogle() {
  const error = new Error('Google sign-in is not connected yet.')
  error.code = 'NOT_CONNECTED'
  throw error
}

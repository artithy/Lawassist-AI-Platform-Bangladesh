import { useCallback, useEffect, useMemo, useState } from 'react'
import { AuthContext } from './AuthContext'
import { supabase, isAuthConfigured } from '../services/supabaseClient'
import { loginWithEmail, registerWithEmail, logout } from '../services/authApi'
import { signInWithGoogle } from '../services/googleAuth'

// Keeps the Supabase session in one place and shares it with the whole app (see main.jsx).
//   - On every page load / refresh it asks Supabase for the saved session (and finishes a Google or
//     e-mail-confirmation redirect, if the address contains one). Until that is done, `loading` is true.
//   - It then listens for sign-in, sign-out and automatic session renewal.
function AuthProvider({ children }) {
  const [session, setSession] = useState(null)
  const [loading, setLoading] = useState(isAuthConfigured)

  useEffect(function () {
    if (!isAuthConfigured) return undefined
    let active = true

    supabase.auth
      .getSession()
      .then(function (result) {
        if (active) setSession(result.data.session)
      })
      .catch(function () {
        if (active) setSession(null)
      })
      .finally(function () {
        if (active) setLoading(false)
      })

    const { data } = supabase.auth.onAuthStateChange(function (_event, newSession) {
      // (Supabase asks us not to call other Supabase functions inside this callback, so we only store the session.)
      if (active) setSession(newSession)
    })

    return function () {
      active = false
      data.subscription.unsubscribe()
    }
  }, [])

  const signIn = useCallback(async function (details) {
    const result = await loginWithEmail(details)
    if (result.session) setSession(result.session)
    return result
  }, [])

  const signUp = useCallback(async function (details) {
    const result = await registerWithEmail(details)
    if (result.session) setSession(result.session)
    return result
  }, [])

  const signOut = useCallback(async function () {
    await logout()
    setSession(null)
  }, [])

  const value = useMemo(
    function () {
      return {
        session,
        user: session ? session.user : null,
        loading,
        isConfigured: isAuthConfigured,
        signIn,
        signUp,
        signInWithGoogle,
        signOut,
      }
    },
    [session, loading, signIn, signUp, signOut],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export default AuthProvider

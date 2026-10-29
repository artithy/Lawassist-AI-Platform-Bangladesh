import { useContext } from 'react'
import { AuthContext } from './AuthContext'

// In any component:   const { session, user, loading, signOut } = useAuth()
//   session  the Supabase session, or null when nobody is signed in
//   user     session.user (id, email, user_metadata.full_name ...), or null
//   loading  true until the saved session has been looked up after a page load / refresh
export function useAuth() {
  const value = useContext(AuthContext)
  if (!value) throw new Error('useAuth must be used inside <AuthProvider>.')
  return value
}

import { createContext } from 'react'

// Shared by AuthProvider (which fills it) and useAuth (which reads it)
export const AuthContext = createContext(null)

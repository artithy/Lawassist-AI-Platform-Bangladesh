import { Navigate } from 'react-router-dom'

// There is no separate dashboard: signing in or signing up leads straight to the AI Legal Chat (/assistant).
// This page only exists so an old /dashboard link or bookmark still lands somewhere useful.
function Dashboard() {
  return <Navigate to="/assistant" replace />
}

export default Dashboard

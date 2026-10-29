import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import { useLanguage } from '../i18n/useLanguage'
import { pathOf } from '../auth/returnTo'

// Wrap a page in this to make it members-only:
//     <Route path="/assistant" element={<ProtectedRoute><AiAssistant /></ProtectedRoute>} />
// - while the saved session is being looked up (a refresh, or coming back from Google) it shows a short message,
//   so a signed-in person is never bounced to the login page by mistake;
// - nobody signed in  -> sent to /login. The page they wanted ("from", e.g. /lawyers/tanvir) is carried along, so they
//   are brought back to it after login; a question typed on the home page is carried too, so it is not lost.
function ProtectedRoute({ children }) {
  const { session, loading } = useAuth()
  const location = useLocation()
  const { t } = useLanguage()

  if (loading) {
    return (
      <main className="grid min-h-[50vh] flex-1 place-items-center bg-[#0f1317] px-5">
        <p role="status" className="flex items-center gap-3 text-[#b4bcc4]">
          <span aria-hidden="true" className="size-2 rounded-full bg-[#e3b261]/70" />
          {t.assistant.checkingSession}
        </p>
      </main>
    )
  }

  if (!session) {
    const question = location.state && location.state.question ? location.state.question : null
    return <Navigate to="/login" replace state={{ from: pathOf(location), question: question }} />
  }

  return children
}

export default ProtectedRoute

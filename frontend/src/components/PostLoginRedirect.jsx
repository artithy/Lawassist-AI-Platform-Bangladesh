import { useEffect } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import { takeReturnTo } from '../auth/returnTo'

// "Continue with Google" takes the browser to Google and back to /assistant, and the page the visitor
// originally wanted (for example a lawyer profile) is lost on the way. Login.jsx therefore saves that path
// just before leaving (auth/returnTo.js). Here, on the way back, a signed-in visitor who lands on /assistant
// is sent on to the saved page - once; the saved path is removed when read, and it expires after 10 minutes.
// (E-mail login does not need this: Login.jsx sends the visitor straight back to the page they wanted.)
function PostLoginRedirect() {
  const { session, loading } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()

  useEffect(
    function () {
      if (loading || !session || location.pathname !== '/assistant') return
      const wanted = takeReturnTo()
      if (wanted && wanted !== '/assistant') navigate(wanted, { replace: true })
    },
    [loading, session, location.pathname, navigate],
  )

  return null
}

export default PostLoginRedirect

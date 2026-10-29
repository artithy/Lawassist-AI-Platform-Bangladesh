import { useEffect } from 'react'
import { useLocation, useNavigationType } from 'react-router-dom'

// When you follow a link to another page, the browser keeps the old scroll position
// (for example the bottom of the page, after clicking a footer link). This puts the new page at the top.
// It does nothing for:
//   - links with a #section (the Home page scrolls to the section itself), and
//   - the browser's back / forward buttons (the browser restores the old position).
function ScrollToTop() {
  const location = useLocation()
  const navigationType = useNavigationType()

  useEffect(
    function () {
      if (location.hash === '' && navigationType !== 'POP') {
        window.scrollTo(0, 0)
      }
    },
    [location.key, location.hash, navigationType],
  )

  return null
}

export default ScrollToTop

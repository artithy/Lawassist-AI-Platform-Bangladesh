import { useEffect } from 'react'
import { useLocation, useNavigationType } from 'react-router-dom'

// The ONE place that decides where the page scrolls after every navigation:
//   - address with a #section (e.g. /#areas or /about#how-it-works) -> scroll to that section.
//       Clicking a link scrolls smoothly; a direct visit, refresh or back/forward jumps there at once.
//       The section is looked up again for a few frames, because after a page change it may not be drawn yet.
//       (Sections have "scroll-mt-16", so their heading stays below the sticky navbar.)
//   - a normal link to another page / Home -> start at the top
//   - the browser's back / forward buttons (without #) -> the browser puts the old scroll position back
function ScrollToTop() {
  const location = useLocation()
  const navigationType = useNavigationType()

  useEffect(
    function () {
      if (location.hash === '') {
        if (navigationType !== 'POP') window.scrollTo(0, 0)
        return undefined
      }

      let id = location.hash.slice(1)
      try {
        id = decodeURIComponent(id)
      } catch {
        // keep the raw text
      }
      const smooth = navigationType === 'PUSH' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches

      function scrollToSection() {
        const section = document.getElementById(id)
        if (!section) return false
        section.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' })
        return true
      }

      if (scrollToSection()) return undefined
      let frames = 0
      let frame = requestAnimationFrame(function retry() {
        frames += 1
        if (!scrollToSection() && frames < 30) frame = requestAnimationFrame(retry)
      })
      return function () {
        cancelAnimationFrame(frame)
      }
    },
    [location.key, location.pathname, location.hash, navigationType],
  )

  return null
}

export default ScrollToTop

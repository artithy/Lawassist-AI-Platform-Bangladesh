import { useEffect, useRef, useState } from 'react'

// "Fade / rise in when it scrolls into view".
//   const [ref, shown] = useReveal()   ->   put ref on the element; "shown" turns true (once) when it is on screen.
// It is already true right away when the visitor asked their system to reduce motion, or the browser has no
// IntersectionObserver, so nothing is ever left invisible.
function prefersReducedMotion() {
  return typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

export default function useReveal() {
  const ref = useRef(null)
  const [shown, setShown] = useState(function () {
    return typeof IntersectionObserver === 'undefined' || prefersReducedMotion()
  })

  useEffect(
    function () {
      if (shown || !ref.current) return undefined
      const observer = new IntersectionObserver(
        function (entries) {
          if (entries.some(function (entry) { return entry.isIntersecting })) {
            setShown(true)
            observer.disconnect()
          }
        },
        { rootMargin: '0px 0px -10% 0px', threshold: 0.05 },
      )
      observer.observe(ref.current)
      return function () {
        observer.disconnect()
      }
    },
    [shown],
  )

  return [ref, shown]
}

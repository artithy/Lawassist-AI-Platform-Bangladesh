import { useEffect, useRef } from 'react'
import { useLanguage } from '../i18n/useLanguage'

// The multi-coloured "G" (Google's logo, drawn as plain SVG)
export function GoogleLogo({ size = 20 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 48 48" aria-hidden="true" className="shrink-0">
      <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
      <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
      <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
      <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
    </svg>
  )
}

// The small window that appears when someone clicks "Continue with Google".
// It says plainly that real Google sign-in is not connected, and offers a clearly labelled demo mode.
//   open        true/false
//   onClose     called for Cancel, the Escape key, or a click on the dark background
//   onContinue  called only when the visitor chooses "Continue in Demo Mode"
function GoogleSignInDemo({ open, onClose, onContinue }) {
  const { language, t } = useLanguage()
  const d = t.auth.login.googleDialog
  const isBn = language === 'bn'
  const dialogRef = useRef(null)
  const continueRef = useRef(null)

  // Always call the newest onClose without restarting the effect below
  const onCloseRef = useRef(onClose)
  useEffect(function () {
    onCloseRef.current = onClose
  })

  useEffect(
    function () {
      if (!open) return undefined

      const opener = document.activeElement // so we can give the focus back when the window closes
      const oldOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden' // the page behind does not scroll
      continueRef.current.focus()

      function onKeyDown(event) {
        if (event.key === 'Escape') {
          onCloseRef.current()
          return
        }
        // Keep Tab inside the window
        if (event.key === 'Tab') {
          const items = dialogRef.current.querySelectorAll('button')
          const first = items[0]
          const last = items[items.length - 1]
          if (event.shiftKey && document.activeElement === first) {
            event.preventDefault()
            last.focus()
          } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault()
            first.focus()
          }
        }
      }
      document.addEventListener('keydown', onKeyDown)

      return function () {
        document.removeEventListener('keydown', onKeyDown)
        document.body.style.overflow = oldOverflow
        if (opener && opener.focus) opener.focus()
      }
    },
    [open],
  )

  if (!open) return null

  return (
    <div
      className="fixed inset-0 z-[60] flex items-center justify-center bg-[#05070a]/75 p-4 backdrop-blur-sm"
      onMouseDown={function (event) {
        if (event.target === event.currentTarget) onClose()
      }}
    >
      <div ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby="google-demo-title" aria-describedby="google-demo-text" className="w-full max-w-[440px] rounded-[22px] border border-gold/35 bg-[#12151a] p-6 shadow-[0_40px_80px_-20px_rgba(0,0,0,0.9)] motion-safe:animate-fade-up sm:p-8">
        <div className="flex items-center gap-3">
          <span className="flex size-11 items-center justify-center rounded-full border border-silver/20 bg-ivory">
            <GoogleLogo size={22} />
          </span>
          <span className={'rounded-full border border-gold/50 bg-gold/10 px-2.5 py-0.5 font-medium text-gold-light ' + (isBn ? 'text-[13px]' : 'text-[11px] tracking-wide')}>{d.badge}</span>
        </div>
        <h2 id="google-demo-title" className={'mt-5 font-display font-medium text-ivory ' + (isBn ? 'text-[26px] leading-[1.4]' : 'text-[30px] leading-[1.1]')}>{d.title}</h2>
        <p id="google-demo-text" className={'mt-3 text-mist ' + (isBn ? 'text-[16px] leading-[1.85]' : 'text-[14.5px] leading-[1.65]')}>{d.text}</p>

        <div className="mt-7 flex flex-col gap-3 sm:flex-row-reverse">
          <button ref={continueRef} type="button" onClick={onContinue} className={'h-12 flex-1 cursor-pointer rounded-xl bg-gold px-5 font-semibold text-deep shadow-lg shadow-gold/20 transition hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-light ' + (isBn ? 'text-[16px]' : 'text-[15px]')}>
            {d.continue}
          </button>
          <button type="button" onClick={onClose} className={'h-12 cursor-pointer rounded-xl border border-silver/25 px-5 font-medium text-ivory transition-colors hover:border-gold/60 hover:bg-graphite/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold ' + (isBn ? 'text-[16px]' : 'text-[15px]')}>
            {d.cancel}
          </button>
        </div>
      </div>
    </div>
  )
}

export default GoogleSignInDemo

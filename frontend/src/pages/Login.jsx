import { useEffect, useRef, useState } from 'react'
import { Link, Navigate, useLocation } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import { useAuth } from '../auth/useAuth'
import { Field, GoogleButton, Icon, Notice, OrDivider, PasswordField, SubmitButton } from '../components/AuthUi'
import { checkAnyPassword, checkEmail } from '../services/authValidation'
import { clearReturnTo, fromState, saveReturnTo } from '../auth/returnTo'
import courthouse from '../assets/areas-courthouse.webp'

// The log-in page: /login
//
//   Sign In               -> signs in with e-mail + password (Supabase). Success leads to the AI Legal Chat.
//   Continue with Google  -> sends the browser to Google; Supabase brings it back to /assistant when it worked.
//   Already signed in     -> this page sends you straight on to the chat.
//
// Nothing typed here is stored by this site. "Remember me" only decides whether the session survives closing the browser.
function Login() {
  const { language, t } = useLanguage()
  const a = t.auth
  const l = a.login
  const isBn = language === 'bn'
  const location = useLocation()
  const { session, signIn, signInWithGoogle } = useAuth()
  // The page the visitor wanted before being sent here (set by ProtectedRoute), e.g. a lawyer profile
  const wantedPage = fromState(location.state)

  const [form, setForm] = useState({ email: '', password: '' })
  const [remember, setRemember] = useState(false)
  const [touched, setTouched] = useState({})
  const [serverError, setServerError] = useState('') // name of a message in a.errors
  const [busy, setBusy] = useState(false)
  const [status, setStatus] = useState('') // '' | 'forgot'
  const statusRef = useRef(null)

  const keys = { email: checkEmail(form.email), password: checkAnyPassword(form.password) }
  const hasErrors = Object.values(keys).some(Boolean)
  function errorOf(field) {
    return touched[field] && keys[field] ? a.errors[keys[field]] : ''
  }
  function setValue(field, value) {
    setForm({ ...form, [field]: value })
    setServerError('')
    setStatus('')
  }
  function markTouched(field) {
    setTouched(function (old) {
      return { ...old, [field]: true }
    })
  }

  // Arriving here again means any older "continue after Google" request is finished or abandoned
  useEffect(function () {
    clearReturnTo()
  }, [])

  // When a status message appears, move the focus to it (so screen readers read it, and it scrolls into view)
  useEffect(
    function () {
      if (status && statusRef.current) statusRef.current.focus()
    },
    [status],
  )

  // Already signed in (or just signed in): go back to the page the visitor wanted (a lawyer profile, for example),
  // or to the AI Legal Chat when they came to log in on purpose.
  // A question typed on the home page (kept by ProtectedRoute) is passed on to the chat, so it is not lost.
  if (session) {
    const question = location.state && location.state.question
    const goesToChat = !wantedPage || wantedPage === '/assistant'
    return <Navigate to={wantedPage || '/assistant'} replace state={goesToChat && question ? { question: question } : null} />
  }

  async function handleSubmit(event) {
    event.preventDefault()
    if (busy) return
    setServerError('')
    setStatus('')

    if (hasErrors) {
      setTouched({ email: true, password: true })
      const element = document.getElementById(keys.email ? 'login-email' : 'login-password')
      if (element) element.focus()
      return
    }

    setBusy(true)
    const result = await signIn({ email: form.email.trim(), password: form.password, remember: remember })
    if (result.errorKey) {
      setServerError(result.errorKey)
      setBusy(false)
    }
    // on success the session is set and this page redirects (see "if (session)" above)
  }

  async function handleGoogle() {
    setStatus('')
    setServerError('')
    setBusy(true)
    // Google takes the browser away from this site, so the wanted page is kept for the way back (see auth/returnTo.js)
    saveReturnTo(wantedPage)
    const result = await signInWithGoogle({ remember: remember })
    // on success the browser is already on its way to Google; only an error comes back here
    if (result.errorKey) {
      clearReturnTo()
      setServerError(result.errorKey)
      setBusy(false)
    }
  }

  const points = l.points

  return (
    <main className="relative overflow-hidden bg-[#0b0e12]">
      <div className="mx-auto grid min-h-[calc(100dvh-4rem)] max-w-[1440px] grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1.08fr)_minmax(0,1fr)]">
        {/* ================= Left: branding and picture ================= */}
        <section className="relative isolate overflow-hidden px-5 pb-10 pt-10 sm:px-10 lg:px-[7%] lg:pb-16 lg:pt-20">
          {/* Picture: a pointed arch with the outline of Bangladesh, between courthouse columns */}
          <img src={courthouse} alt="" aria-hidden="true" draggable="false" className="pointer-events-none absolute bottom-0 right-[-30%] -z-10 w-[150%] max-w-none select-none opacity-60 sm:right-[-12%] sm:w-[105%] sm:opacity-80 lg:bottom-[2%] lg:right-[-78%] lg:w-[125%] lg:opacity-100" />
          {/* Soft darkening so the words stay readable, and a warm glow */}
          <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10 bg-linear-to-r from-[#0b0e12] via-[#0b0e12]/70 to-transparent" />
          <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-1/3 bg-linear-to-t from-[#0b0e12] to-transparent" />
          <div aria-hidden="true" className="pointer-events-none absolute -left-24 top-0 -z-10 size-[28rem] rounded-full bg-gold/[0.08] blur-3xl" />

          <div className="flex items-center gap-3.5 text-gold-light">
            <span className="relative flex size-11 items-center justify-center">
              <span aria-hidden="true" className="absolute left-0 top-0 size-2.5 border-l border-t border-gold/70" />
              <span aria-hidden="true" className="absolute right-0 top-0 size-2.5 border-r border-t border-gold/70" />
              <span aria-hidden="true" className="absolute bottom-0 left-0 size-2.5 border-b border-l border-gold/70" />
              <span aria-hidden="true" className="absolute bottom-0 right-0 size-2.5 border-b border-r border-gold/70" />
              <Icon size={26}>
                <path d="M12 3 3.5 7.5h17L12 3Z" />
                <path d="M3.5 21h17" />
                <path d="M5.5 18.5h13" />
                <path d="M6.5 10.5v6M10 10.5v6M14 10.5v6M17.5 10.5v6" />
              </Icon>
            </span>
            <span className="font-display text-[26px] font-medium leading-none text-ivory">LawAssist AI</span>
          </div>

          <h1 className={'mt-10 font-display font-medium sm:mt-14 lg:mt-24 ' + (isBn ? 'text-[clamp(2.2rem,4.6vw,3.8rem)] leading-[1.35]' : 'text-[clamp(2.7rem,5.4vw,5rem)] leading-[1.02]')}>
            <span className="block text-ivory">{l.line1}</span>{" "}
            <span className="block text-gold-light">{l.line2}</span>
          </h1>

          {/* Short gold divider: line - diamond - line */}
          <div aria-hidden="true" className="mt-6 flex items-center gap-2.5">
            <span className="h-px w-14 bg-linear-to-r from-gold/80 to-gold/10" />
            <span className="block size-1.5 rotate-45 bg-gold" />
            <span className="h-px w-6 bg-gold/30" />
          </div>

          <p className={'mt-5 max-w-[30rem] text-silver ' + (isBn ? 'text-[17px] leading-[1.9]' : 'text-[16px] leading-[1.7] sm:text-[17px]')}>{l.description}</p>

          <ul className="mt-8 hidden space-y-3 sm:block">
            {points.map(function (point) {
              return (
                <li key={point} className={'flex items-center gap-3 text-ivory/90 ' + (isBn ? 'text-[16px]' : 'text-[14.5px]')}>
                  <span aria-hidden="true" className="block size-1.5 shrink-0 rotate-45 bg-gold" />
                  {point}
                </li>
              )
            })}
          </ul>
        </section>

        {/* ================= Right: the login card ================= */}
        <section className="relative flex items-center justify-center px-5 pb-14 pt-4 sm:px-10 lg:py-16">
          <div className="w-full max-w-[460px] rounded-[24px] border border-silver/15 bg-[#12151a]/95 p-6 shadow-[0_40px_80px_-30px_rgba(0,0,0,0.95)] backdrop-blur-sm motion-safe:animate-fade-up sm:p-9">
            {/* a thin gold line along the top edge */}
            <div aria-hidden="true" className="-mt-6 mb-6 h-px bg-linear-to-r from-transparent via-gold/70 to-transparent sm:-mt-9 sm:mb-8" />

            <div className="flex items-center gap-3">
              <span className="flex size-10 items-center justify-center rounded-full border border-gold/40 bg-gold/10 text-gold-light">
                <Icon size={21}>
                  <path d="M12 3 3.5 7.5h17L12 3Z" />
                  <path d="M3.5 21h17" />
                  <path d="M6.5 10.5v6M10 10.5v6M14 10.5v6M17.5 10.5v6" />
                </Icon>
              </span>
              <span className="font-display text-[22px] font-medium text-ivory">LawAssist AI</span>
            </div>

            <h2 className={'mt-5 font-display font-medium text-ivory ' + (isBn ? 'text-[30px] leading-[1.35]' : 'text-[36px] leading-[1.05]')}>{l.welcome}</h2>
            <p className={'mt-1.5 text-mist ' + (isBn ? 'text-[16px]' : 'text-[14.5px]')}>{l.subtitle}</p>

            <form onSubmit={handleSubmit} noValidate className="mt-6 space-y-5">
              {/* Status message ("forgot password") and errors */}
              {status && (
                <div ref={statusRef} tabIndex={-1} role="status" className="outline-none">
                  <Notice>{l.forgotInfo}</Notice>
                </div>
              )}
              {wantedPage.startsWith('/lawyers/') && !serverError && !status && (
                <Notice>{l.profileGate}</Notice>
              )}
              {serverError && (
                <Notice tone="error" role="alert">
                  {a.errors[serverError]}
                </Notice>
              )}

              <Field id="login-email" type="email" label={a.fields.email.label} placeholder={a.fields.email.placeholder} autoComplete="email" value={form.email} disabled={busy} onChange={function (e) { setValue('email', e.target.value) }} onBlur={function () { markTouched('email') }} error={errorOf('email')} />
              <PasswordField id="login-password" label={a.fields.loginPassword.label} placeholder={a.fields.loginPassword.placeholder} autoComplete="current-password" value={form.password} disabled={busy} onChange={function (e) { setValue('password', e.target.value) }} onBlur={function () { markTouched('password') }} error={errorOf('password')} />

              <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <label className={'flex cursor-pointer items-center gap-2.5 text-ivory ' + (isBn ? 'text-[15.5px]' : 'text-[14px]')}>
                  <input type="checkbox" id="login-remember" checked={remember} onChange={function (e) { setRemember(e.target.checked) }} className="size-[17px] cursor-pointer accent-[#b99a5b]" />
                  {l.remember}
                </label>
                {/* The message only says that password reset is not available yet */}
                <button type="button" onClick={function () { setStatus('forgot') }} className={'cursor-pointer text-gold-light underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-gold ' + (isBn ? 'text-[15.5px]' : 'text-[14px]')}>
                  {l.forgot}
                </button>
              </div>

              <SubmitButton busy={busy}>{busy ? l.submitting : l.submit}</SubmitButton>
            </form>

            <OrDivider label={l.or} />

            <GoogleButton onClick={handleGoogle} disabled={busy}>
              {l.google}
            </GoogleButton>

            <p className={'mt-7 text-center text-mist ' + (isBn ? 'text-[16px]' : 'text-[14px]')}>
              {l.noAccount}{' '}
              <Link to="/register" state={location.state} className="font-medium text-gold-light underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-gold">
                {l.registerLink}
              </Link>
            </p>
          </div>
        </section>
      </div>

    </main>
  )
}

export default Login

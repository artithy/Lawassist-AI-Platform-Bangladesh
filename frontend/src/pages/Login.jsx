import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import { Field, Icon, Notice, PasswordField, SubmitButton } from '../components/AuthUi'
import GoogleSignInDemo, { GoogleLogo } from '../components/GoogleSignInDemo'
import { checkAnyPassword, checkEmail } from '../services/authValidation'
import { isAuthConfigured, loginUser } from '../services/authApi'
import { isGoogleSignInConnected, signInWithGoogle } from '../services/googleAuth'
import courthouse from '../assets/areas-courthouse.webp'

// The log-in page: /login
//
//   Sign In button         -> checks the form. With no backend it says so and offers demo mode (nobody is signed in).
//   Continue with Google   -> opens a small dialog; real Google sign-in is not connected (see services/googleAuth.js).
//   Continue in Demo Mode  -> goes to /assistant. That is the ONLY way this page sends you there without a real sign-in.
//
// Nothing typed here is stored in the browser. "Remember me" is only passed to a real backend, if one is connected.
function Login() {
  const { language, t } = useLanguage()
  const a = t.auth
  const l = a.login
  const isBn = language === 'bn'
  const navigate = useNavigate()

  const [form, setForm] = useState({ email: '', password: '' })
  const [remember, setRemember] = useState(false)
  const [touched, setTouched] = useState({})
  const [serverError, setServerError] = useState('') // name of a message in a.errors
  const [busy, setBusy] = useState(false)
  const [status, setStatus] = useState('') // '' | 'notConnected' | 'forgot'
  const [dialogOpen, setDialogOpen] = useState(false)
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

  // When a status message appears, move the focus to it (so screen readers read it, and it scrolls into view)
  useEffect(
    function () {
      if (status && statusRef.current) statusRef.current.focus()
    },
    [status],
  )

  function goToDemo() {
    // "demo" tells the chat page to show "Demo visitor - not signed in" instead of a profile
    navigate('/assistant', { state: { demo: true } })
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

    // No backend: say so, throw the password away, offer demo mode. Nobody is signed in.
    if (!isAuthConfigured) {
      setForm({ ...form, password: '' })
      setTouched({})
      setStatus('notConnected')
      return
    }

    setBusy(true)
    try {
      const result = await loginUser({ email: form.email.trim(), password: form.password, remember: remember })
      navigate('/assistant', { state: { user: result.user } })
    } catch (error) {
      setServerError(error.code === 'INVALID_CREDENTIALS' ? 'invalidCredentials' : 'loginFailed')
    } finally {
      setBusy(false)
    }
  }

  async function handleGoogle() {
    setStatus('')
    setServerError('')
    if (!isGoogleSignInConnected) {
      setDialogOpen(true) // the honest "not connected yet" dialog
      return
    }
    try {
      const result = await signInWithGoogle()
      navigate('/assistant', { state: { user: result.user } })
    } catch {
      setServerError('loginFailed')
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

            {!isAuthConfigured && (
              <p className={'mt-4 inline-flex items-center gap-2 rounded-full border border-gold/35 bg-gold/[0.07] px-3 py-1 text-gold-light ' + (isBn ? 'text-[13.5px]' : 'text-[12px]')}>
                <span aria-hidden="true" className="size-1.5 rounded-full bg-gold" />
                {l.demoPill}
              </p>
            )}

            <form onSubmit={handleSubmit} noValidate className="mt-6 space-y-5">
              {/* Status messages (not connected / forgot password) and errors */}
              {status && (
                <div ref={statusRef} tabIndex={-1} role="status" className="outline-none">
                  {status === 'notConnected' ? (
                    <Notice title={l.notConnectedTitle}>
                      <p>{l.notConnectedText}</p>
                      <button type="button" onClick={goToDemo} className={'mt-3 inline-flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-gold/70 px-4 font-medium text-ivory transition-colors hover:bg-gold hover:text-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold ' + (isBn ? 'text-[15px]' : 'text-[13.5px]')}>
                        {l.demoAction}
                        <Icon size={15}>
                          <path d="M5 12h14" />
                          <path d="m13 6 6 6-6 6" />
                        </Icon>
                      </button>
                    </Notice>
                  ) : (
                    <Notice>{l.forgotInfo}</Notice>
                  )}
                </div>
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
                {/* UI only: there is no password reset yet */}
                <button type="button" onClick={function () { setStatus('forgot') }} className={'cursor-pointer text-gold-light underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-gold ' + (isBn ? 'text-[15.5px]' : 'text-[14px]')}>
                  {l.forgot}
                </button>
              </div>

              <SubmitButton busy={busy}>{busy ? l.submitting : l.submit}</SubmitButton>
            </form>

            {/* OR divider */}
            <div className="my-6 flex items-center gap-4" role="separator" aria-label={l.or}>
              <span className="h-px flex-1 bg-silver/15" />
              <span className={'text-mist ' + (isBn ? 'text-[14px]' : 'text-[12px] tracking-[0.25em]')}>{l.or}</span>
              <span className="h-px flex-1 bg-silver/15" />
            </div>

            <button type="button" onClick={handleGoogle} disabled={busy} className={'flex h-12 w-full cursor-pointer items-center justify-center gap-3 rounded-xl border border-ivory/80 bg-ivory font-semibold text-deep shadow-lg shadow-black/30 transition hover:-translate-y-px hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-light disabled:cursor-not-allowed disabled:opacity-60 ' + (isBn ? 'text-[16.5px]' : 'text-[15px]')}>
              <GoogleLogo />
              {l.google}
            </button>

            <p className={'mt-7 text-center text-mist ' + (isBn ? 'text-[16px]' : 'text-[14px]')}>
              {l.noAccount}{' '}
              <Link to="/register" className="font-medium text-gold-light underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-gold">
                {l.registerLink}
              </Link>
            </p>
          </div>
        </section>
      </div>

      <GoogleSignInDemo
        open={dialogOpen}
        onClose={function () {
          setDialogOpen(false)
        }}
        onContinue={function () {
          setDialogOpen(false)
          goToDemo()
        }}
      />
    </main>
  )
}

export default Login

import { useEffect, useState } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import { useAuth } from '../auth/useAuth'
import { Field, GoogleButton, Icon, Notice, OrDivider, PasswordField } from '../components/AuthUi'
import { clearReturnTo, fromState, saveReturnTo } from '../auth/returnTo'
import { checkConfirm, checkEmail, checkName, checkNewPassword } from '../services/authValidation'
import courthouse from '../assets/areas-courthouse.webp'

const emptyForm = { name: '', email: '', password: '', confirm: '' }

// The sign-up page: /register  (every "Get Started" button leads here)
function Register() {
  const { language, t } = useLanguage()
  const a = t.auth
  const r = a.register
  const isBn = language === 'bn'
  const navigate = useNavigate()
  const location = useLocation()
  // The page the visitor wanted before being sent to log in (e.g. a lawyer profile), kept through Log in <-> Sign up
  const wantedPage = fromState(location.state)
  useEffect(function () {
    clearReturnTo()
  }, [])
  const { session, signUp, signInWithGoogle } = useAuth()

  const [form, setForm] = useState(emptyForm)
  const [agreed, setAgreed] = useState(false)
  const [touched, setTouched] = useState({}) // which boxes the visitor has already left
  const [serverError, setServerError] = useState('') // name of a message in a.errors
  const [emailTaken, setEmailTaken] = useState(false)
  const [busy, setBusy] = useState(false)
  const [verifyEmail, setVerifyEmail] = useState('') // set when Supabase asks the person to confirm their e-mail
  const [showSummary, setShowSummary] = useState(false)

  // The error KEY of every box (empty text = fine). Words come later, from the current language.
  const keys = {
    name: checkName(form.name),
    email: checkEmail(form.email),
    password: checkNewPassword(form.password),
    confirm: checkConfirm(form.password, form.confirm),
    terms: agreed ? '' : 'termsRequired',
  }
  const hasErrors = Object.values(keys).some(Boolean)

  // Show a box's error only after the visitor left that box (or pressed the button)
  function errorOf(field) {
    if (field === 'email' && emailTaken && keys.email === '') return a.errors.emailTaken
    return touched[field] && keys[field] ? a.errors[keys[field]] : ''
  }

  function setValue(field, value) {
    setForm({ ...form, [field]: value })
    if (field === 'email') setEmailTaken(false)
    setServerError('')
  }
  function markTouched(field) {
    setTouched(function (old) {
      return { ...old, [field]: true }
    })
  }

  async function handleSubmit(event) {
    event.preventDefault()
    if (busy) return
    setServerError('')

    if (hasErrors) {
      setTouched({ name: true, email: true, password: true, confirm: true, terms: true })
      setShowSummary(true)
      // move the cursor to the first box that needs attention
      const first = ['name', 'email', 'password', 'confirm', 'terms'].find(function (field) {
        return keys[field]
      })
      const element = document.getElementById('register-' + first)
      if (element) element.focus()
      return
    }
    setShowSummary(false)

    setBusy(true)
    const result = await signUp({ name: form.name.trim(), email: form.email.trim(), password: form.password, language: language })
    setBusy(false)

    if (result.errorKey === 'emailTaken') {
      setEmailTaken(true)
      setTouched({ ...touched, email: true })
      // the boxes are switched off while waiting, so move the cursor a moment later
      setTimeout(function () {
        const element = document.getElementById('register-email')
        if (element) element.focus()
      }, 50)
    } else if (result.errorKey) {
      setServerError(result.errorKey)
    } else if (result.status === 'confirmEmail') {
      // e-mail confirmation is on: ask the person to check their inbox. The password is thrown away right now.
      setVerifyEmail(form.email.trim())
      setForm(emptyForm)
      setAgreed(false)
      setTouched({})
    }
    // status "signedIn": the session is set and this page redirects to the chat (see "if (session)" below)
  }

  async function handleGoogle() {
    setServerError('')
    setBusy(true)
    saveReturnTo(wantedPage)
    const result = await signInWithGoogle({ remember: true })
    if (result.errorKey) {
      clearReturnTo()
      setServerError(result.errorKey)
      setBusy(false)
    }
  }

  // Signed in already (or signed up with e-mail confirmation switched off): go to the AI Legal Chat
  if (session) return <Navigate to={wantedPage || '/assistant'} replace />

  // The three benefits on the left: a small gold icon for each (same order as r.benefits in translations.js)
  const benefitIcons = [
    <>
      <path d="M4 5h16v11H9l-5 4V5Z" />
      <path d="M8 9h8M8 12.5h5" />
    </>,
    <>
      <path d="M12 3v18M5 7h14" />
      <path d="M5 7 2.5 14a3 3 0 0 0 5 0L5 7ZM19 7l-2.5 7a3 3 0 0 0 5 0L19 7Z" />
      <path d="M8 21h8" />
    </>,
    <>
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </>,
  ]

  return (
    <main className="relative overflow-hidden bg-[#0b0e12]">
      <div className="mx-auto grid min-h-[calc(100dvh-4rem)] max-w-[1440px] grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
        {/* ================= Left: welcome, benefits and picture ================= */}
        <section className="relative isolate overflow-hidden px-5 pb-8 pt-10 sm:px-10 lg:px-[7%] lg:pb-16 lg:pt-20">
          {/* Picture: the same pointed arch with the outline of Bangladesh as on the login page, kept soft */}
          <img src={courthouse} alt="" aria-hidden="true" draggable="false" className="pointer-events-none absolute bottom-0 right-[-30%] -z-10 w-[150%] max-w-none select-none opacity-40 sm:right-[-12%] sm:w-[105%] sm:opacity-55 lg:bottom-[2%] lg:right-[-78%] lg:w-[125%] lg:opacity-85" />
          <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10 bg-linear-to-r from-[#0b0e12] via-[#0b0e12]/75 to-transparent" />
          <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-1/3 bg-linear-to-t from-[#0b0e12] to-transparent" />
          <div aria-hidden="true" className="pointer-events-none absolute inset-y-0 right-0 -z-10 hidden w-24 bg-linear-to-l from-[#0b0e12] to-transparent lg:block" />
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

          <h1 className={'mt-8 font-display font-medium sm:mt-12 lg:mt-16 ' + (isBn ? 'text-[clamp(2.1rem,4.4vw,3.6rem)] leading-[1.35]' : 'text-[clamp(2.6rem,5.2vw,4.8rem)] leading-[1.02]')}>
            <span className="block text-ivory">{r.line1}</span>{' '}
            <span className="block text-gold-light">{r.line2}</span>
          </h1>

          {/* Short gold divider: line - diamond - line */}
          <div aria-hidden="true" className="mt-6 flex items-center gap-2.5">
            <span className="h-px w-14 bg-linear-to-r from-gold/80 to-gold/10" />
            <span className="block size-1.5 rotate-45 bg-gold" />
            <span className="h-px w-6 bg-gold/30" />
          </div>

          <p className={'mt-5 max-w-[30rem] text-silver ' + (isBn ? 'text-[17px] leading-[1.9]' : 'text-[16px] leading-[1.7] sm:text-[17px]')}>{r.description}</p>

          <ul className="mt-8 max-w-[30rem] space-y-3.5 lg:mt-10">
            {r.benefits.map(function (benefit, index) {
              return (
                <li key={benefit.title} className="flex items-start gap-4 rounded-2xl border border-silver/10 bg-[#12151a]/70 p-4 backdrop-blur-sm">
                  <span className="flex size-11 shrink-0 items-center justify-center rounded-xl border border-gold/35 bg-gold/10 text-gold-light">
                    <Icon size={21}>{benefitIcons[index]}</Icon>
                  </span>
                  <span className="min-w-0">
                    <span className={'block font-medium text-ivory ' + (isBn ? 'text-[17px] leading-[1.5]' : 'text-[15.5px]')}>{benefit.title}</span>
                    <span className={'mt-0.5 block text-mist ' + (isBn ? 'text-[15px] leading-[1.7]' : 'text-[13.5px] leading-[1.55]')}>{benefit.text}</span>
                  </span>
                </li>
              )
            })}
          </ul>
        </section>

        {/* ================= Right: the sign-up card ================= */}
        <section className="relative flex items-center justify-center px-5 pb-14 pt-2 sm:px-10 lg:py-14">
          <div className="w-full max-w-[500px] rounded-[24px] border border-silver/15 bg-[#12151a]/95 p-6 shadow-[0_40px_80px_-30px_rgba(0,0,0,0.95)] backdrop-blur-sm motion-safe:animate-fade-up sm:p-9">
            {/* a thin gold line along the top edge */}
            <div aria-hidden="true" className="-mt-6 mb-6 h-px bg-linear-to-r from-transparent via-gold/70 to-transparent sm:-mt-9 sm:mb-8" />

            {verifyEmail ? (
              <div role="status">
                <span className="flex size-14 items-center justify-center rounded-full border border-gold/40 bg-gold/10 text-gold-light">
                  <Icon size={26}>
                    <rect x="3" y="5" width="18" height="14" rx="2" />
                    <path d="m3 7 9 6 9-6" />
                  </Icon>
                </span>
                <h2 className={'mt-5 font-display font-medium text-ivory ' + (isBn ? 'text-[28px] leading-[1.4]' : 'text-[34px] leading-[1.1]')}>{r.verifyTitle}</h2>
                <p className={'mt-3 text-silver ' + (isBn ? 'text-[17px] leading-[1.85]' : 'text-[15.5px] leading-[1.65]')}>
                  {r.verifyText.split('{email}')[0]}
                  <strong className="break-all font-medium text-ivory">{verifyEmail}</strong>
                  {r.verifyText.split('{email}')[1]}
                </p>
                <p className={'mt-2 text-mist ' + (isBn ? 'text-[15.5px] leading-[1.8]' : 'text-[14px]')}>{r.verifyHint}</p>
                <button type="button" onClick={function () { navigate('/login', { state: location.state }) }} className={'mt-7 inline-flex h-12 cursor-pointer items-center justify-center gap-2.5 rounded-xl bg-gold px-6 font-semibold text-deep transition hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-light ' + (isBn ? 'text-[16px]' : 'text-[15px]')}>
                  {r.verifyButton}
                  <Icon size={16}>
                    <path d="M5 12h14" />
                    <path d="m13 6 6 6-6 6" />
                  </Icon>
                </button>
              </div>
            ) : (
              <>
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

                <h2 className={'mt-5 font-display font-medium text-ivory ' + (isBn ? 'text-[30px] leading-[1.35]' : 'text-[36px] leading-[1.05]')}>{r.title}</h2>
                <p className={'mt-1.5 text-mist ' + (isBn ? 'text-[16px]' : 'text-[14.5px]')}>{r.intro}</p>

                <form onSubmit={handleSubmit} noValidate className="mt-6 space-y-5">
                  {showSummary && hasErrors && (
                    <Notice tone="error" role="alert">
                      {a.errors.fixThese}
                    </Notice>
                  )}
                  {serverError && (
                    <Notice tone="error" role="alert">
                      {a.errors[serverError]}
                    </Notice>
                  )}

                  <Field id="register-name" label={a.fields.name.label} placeholder={a.fields.name.placeholder} autoComplete="name" value={form.name} disabled={busy} onChange={function (e) { setValue('name', e.target.value) }} onBlur={function () { markTouched('name') }} error={errorOf('name')} />
                  <Field id="register-email" type="email" label={a.fields.email.label} placeholder={a.fields.email.placeholder} autoComplete="email" value={form.email} disabled={busy} onChange={function (e) { setValue('email', e.target.value) }} onBlur={function () { markTouched('email') }} error={errorOf('email')} />
                  <PasswordField id="register-password" label={a.fields.password.label} placeholder={a.fields.password.placeholder} autoComplete="new-password" value={form.password} disabled={busy} hint={a.passwordHint} onChange={function (e) { setValue('password', e.target.value) }} onBlur={function () { markTouched('password') }} error={errorOf('password')} />
                  <PasswordField id="register-confirm" label={a.fields.confirm.label} placeholder={a.fields.confirm.placeholder} autoComplete="new-password" value={form.confirm} disabled={busy} onChange={function (e) { setValue('confirm', e.target.value) }} onBlur={function () { markTouched('confirm') }} error={errorOf('confirm')} />

                  {/* Terms and Privacy Policy */}
                  <div>
                    <label className="flex cursor-pointer items-start gap-3">
                      <input
                        id="register-terms"
                        type="checkbox"
                        checked={agreed}
                        disabled={busy}
                        onChange={function (e) {
                          setAgreed(e.target.checked)
                          markTouched('terms')
                          setServerError('')
                        }}
                        aria-invalid={errorOf('terms') ? 'true' : 'false'}
                        aria-describedby={'register-terms-note' + (errorOf('terms') ? ' register-terms-error' : '')}
                        className="mt-1 size-[18px] shrink-0 cursor-pointer accent-[#b99a5b]"
                      />
                      <span className={'text-ivory ' + (isBn ? 'text-[16px] leading-[1.7]' : 'text-[14px] leading-[1.55]')}>{r.terms}</span>
                    </label>
                    <p id="register-terms-note" className={'mt-1 pl-[30px] text-mist ' + (isBn ? 'text-[14.5px]' : 'text-[12.5px]')}>{r.termsNote}</p>
                    {errorOf('terms') && (
                      <p id="register-terms-error" role="alert" className={'mt-1.5 pl-[30px] text-red-300 ' + (isBn ? 'text-[15px]' : 'text-[13px]')}>
                        {errorOf('terms')}
                      </p>
                    )}
                  </div>

                  {/* The premium gold "Create Account" button */}
                  <button
                    type="submit"
                    disabled={busy}
                    className={'group flex h-[52px] w-full cursor-pointer items-center justify-center gap-2.5 rounded-xl bg-linear-to-b from-[#e6c985] via-[#cfa95f] to-[#b38f4b] font-semibold text-deep shadow-[0_14px_30px_-12px_rgba(207,169,95,0.75)] ring-1 ring-inset ring-white/25 transition hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-light disabled:cursor-not-allowed disabled:opacity-60 ' + (isBn ? 'text-[17px]' : 'text-[15.5px] tracking-wide')}
                  >
                    {busy && <span aria-hidden="true" className="size-4 rounded-full border-2 border-deep/30 border-t-deep motion-safe:animate-spin" />}
                    {busy ? r.submitting : r.submit}
                    {!busy && (
                      <Icon size={17} className="transition-transform group-hover:translate-x-0.5">
                        <path d="M5 12h14" />
                        <path d="m13 6 6 6-6 6" />
                      </Icon>
                    )}
                  </button>

                  <OrDivider label={a.login.or} />
                  <GoogleButton onClick={handleGoogle} disabled={busy}>
                    {r.google}
                  </GoogleButton>
                </form>

                <p className={'mt-6 text-center text-mist ' + (isBn ? 'text-[16px]' : 'text-[14px]')}>
                  {r.haveAccount}{' '}
                  <Link to="/login" state={location.state} className="font-medium text-gold-light underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-gold">
                    {r.loginLink}
                  </Link>
                </p>
              </>
            )}
          </div>
        </section>
      </div>
    </main>
  )
}

export default Register

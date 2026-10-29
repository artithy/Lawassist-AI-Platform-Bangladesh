import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import { Eyebrow, Field, Icon, Notice, PasswordField, SubmitButton } from '../components/AuthUi'
import { checkConfirm, checkEmail, checkName, checkNewPassword } from '../services/authValidation'
import { isAuthConfigured, registerUser } from '../services/authApi'

const emptyForm = { name: '', email: '', password: '', confirm: '' }

// The sign-up page: /register  (every "Get Started" button leads here)
function Register() {
  const { language, t } = useLanguage()
  const a = t.auth
  const r = a.register
  const isBn = language === 'bn'
  const navigate = useNavigate()

  const [form, setForm] = useState(emptyForm)
  const [agreed, setAgreed] = useState(false)
  const [touched, setTouched] = useState({}) // which boxes the visitor has already left
  const [serverError, setServerError] = useState('') // name of a message in a.errors
  const [emailTaken, setEmailTaken] = useState(false)
  const [busy, setBusy] = useState(false)
  const [demoDone, setDemoDone] = useState(false) // demo mode: the details were checked and then discarded
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

    // DEMO MODE: no backend, so nothing is created. The password is thrown away right away.
    if (!isAuthConfigured) {
      setForm({ ...form, password: '', confirm: '' })
      setAgreed(false)
      setTouched({})
      setDemoDone(true)
      return
    }

    setBusy(true)
    try {
      const result = await registerUser({ name: form.name.trim(), email: form.email.trim(), password: form.password, language })
      navigate('/dashboard', { state: { user: result.user } })
    } catch (error) {
      if (error.code === 'EMAIL_TAKEN') {
        setEmailTaken(true)
        setTouched({ ...touched, email: true })
        // the boxes are switched off while waiting, so move the cursor a moment later
        setTimeout(function () {
          const element = document.getElementById('register-email')
          if (element) element.focus()
        }, 50)
      } else {
        setServerError('registerFailed')
      }
    } finally {
      setBusy(false)
    }
  }

  const sideItemText = isBn ? 'text-[17px] leading-[1.7]' : 'text-[15px] leading-[1.6]'

  return (
    <main className="bg-[#0e1115] bg-[radial-gradient(ellipse_at_20%_0%,rgba(185,154,91,0.09),transparent_60%)]">
      <div className="mx-auto grid max-w-[1180px] grid-cols-[minmax(0,1fr)] gap-10 px-5 pb-16 pt-10 sm:px-8 sm:pt-14 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:gap-16 lg:pt-20">
        {/* ================= Left: welcome text ================= */}
        <div>
          <Eyebrow>{r.eyebrow}</Eyebrow>
          <h1 className={'mt-5 font-display font-medium text-ivory ' + (isBn ? 'text-[clamp(1.9rem,4.4vw,3.1rem)] leading-[1.4]' : 'text-[clamp(2.2rem,4.6vw,3.8rem)] leading-[1.06]')}>{r.title}</h1>
          <p className={'mt-4 max-w-[30rem] text-silver ' + (isBn ? 'text-[17px] leading-[1.85]' : 'text-[16px] leading-[1.7]')}>{r.intro}</p>

          <div className="mt-8 hidden border-t border-silver/10 pt-8 lg:block">
            <h2 className={'font-display font-medium text-ivory ' + (isBn ? 'text-[24px] leading-[1.45]' : 'text-[26px] leading-[1.15]')}>{r.sideTitle}</h2>
            <p className={'mt-2 max-w-[28rem] text-mist ' + (isBn ? 'text-[16px] leading-[1.85]' : 'text-[14.5px] leading-[1.65]')}>{r.sideText}</p>
            <p className={'mt-6 font-medium text-gold ' + (isBn ? 'text-[15px]' : 'text-[12px] tracking-[0.22em]')}>{r.sideListTitle}</p>
            <ul className="mt-3 space-y-2.5">
              {r.sideList.map(function (item) {
                return (
                  <li key={item} className={'flex items-start gap-3 text-silver ' + sideItemText}>
                    <span aria-hidden="true" className="mt-2.5 block size-1.5 shrink-0 rotate-45 bg-gold" />
                    {item}
                  </li>
                )
              })}
            </ul>
          </div>
        </div>

        {/* ================= Right: the form card ================= */}
        <div className="rounded-[22px] border border-silver/15 bg-[#12151a]/95 p-5 shadow-[0_30px_60px_-30px_rgba(0,0,0,0.9)] sm:p-8">
          {/* Honest mode label */}
          {!isAuthConfigured && !demoDone && (
            <div className="mb-6">
              <Notice title={a.demoLabel}>{r.demoText}</Notice>
            </div>
          )}

          {demoDone ? (
            <div role="status">
              <Notice title={r.resultTitle}>{r.resultText}</Notice>
              <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                <Link to="/dashboard" className={'inline-flex h-12 items-center justify-center gap-2.5 rounded-xl bg-gold px-6 font-semibold text-deep transition hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-light ' + (isBn ? 'text-[16px]' : 'text-[15px]')}>
                  {r.preview}
                  <Icon size={16}>
                    <path d="M5 12h14" />
                    <path d="m13 6 6 6-6 6" />
                  </Icon>
                </Link>
                <button type="button" onClick={function () { setDemoDone(false) }} className={'h-12 cursor-pointer rounded-xl border border-gold/70 px-6 font-medium text-ivory transition-colors hover:bg-gold hover:text-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold ' + (isBn ? 'text-[16px]' : 'text-[15px]')}>
                  {r.edit}
                </button>
              </div>
            </div>
          ) : (
            <form onSubmit={handleSubmit} noValidate className="space-y-5">
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

              <SubmitButton busy={busy}>{busy ? r.submitting : r.submit}</SubmitButton>
            </form>
          )}

          <p className={'mt-6 text-center text-mist ' + (isBn ? 'text-[16px]' : 'text-[14px]')}>
            {r.haveAccount}{' '}
            <Link to="/login" className="font-medium text-gold-light underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-gold">
              {r.loginLink}
            </Link>
          </p>
        </div>
      </div>
    </main>
  )
}

export default Register

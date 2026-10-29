import { useState } from 'react'
import { useLanguage } from '../i18n/useLanguage'

// Small pieces shared by the Register and Login pages.

export function Icon({ children, size = 18, className = '' }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className={'shrink-0 ' + className} aria-hidden="true">
      {children}
    </svg>
  )
}

// A message box. tone: "info" (gold), "error" (red)
export function Notice({ tone = 'info', title, children, role }) {
  const { language } = useLanguage()
  const isBn = language === 'bn'
  const colors = tone === 'error' ? 'border-red-400/50 bg-red-400/[0.07]' : 'border-gold/35 bg-gold/[0.07]'
  return (
    <div role={role} className={'flex items-start gap-3 rounded-2xl border px-4 py-3.5 ' + colors}>
      <Icon size={20} className={'mt-0.5 ' + (tone === 'error' ? 'text-red-300' : 'text-gold-light')}>
        <circle cx="12" cy="12" r="9.5" />
        <path d="M12 11v5.5M12 7.5v.01" />
      </Icon>
      <div className="min-w-0">
        {title && <p className={'font-medium text-ivory ' + (isBn ? 'text-[17px]' : 'text-[15px]')}>{title}</p>}
        <div className={'text-mist ' + (title ? 'mt-0.5 ' : '') + (isBn ? 'text-[16px] leading-[1.8]' : 'text-[14px] leading-[1.6]')}>{children}</div>
      </div>
    </div>
  )
}

// One text box with its label, optional hint and error message.
// "error" is already-translated text ('' when the value is fine).
export function Field({ id, label, type = 'text', value, onChange, onBlur, error, hint, placeholder, autoComplete, disabled, trailing }) {
  const { language } = useLanguage()
  const isBn = language === 'bn'
  const describedBy = [error ? id + '-error' : '', hint ? id + '-hint' : ''].filter(Boolean).join(' ') || undefined

  return (
    <div>
      <label htmlFor={id} className={'mb-1.5 block font-medium text-ivory ' + (isBn ? 'text-[16px]' : 'text-[14px]')}>
        {label}
      </label>
      <div className={'flex items-center rounded-xl border bg-deep/70 transition-colors focus-within:border-gold ' + (error ? 'border-red-400' : 'border-silver/20')}>
        <input
          id={id}
          name={id}
          type={type}
          value={value}
          onChange={onChange}
          onBlur={onBlur}
          placeholder={placeholder}
          autoComplete={autoComplete}
          disabled={disabled}
          aria-invalid={error ? 'true' : 'false'}
          aria-describedby={describedBy}
          className={'h-12 min-w-0 flex-1 bg-transparent px-4 text-ivory outline-none placeholder:text-mist/60 disabled:opacity-60 ' + (isBn ? 'text-[17px]' : 'text-[15px]')}
        />
        {trailing}
      </div>
      {hint && !error && (
        <p id={id + '-hint'} className={'mt-1.5 text-mist ' + (isBn ? 'text-[14.5px]' : 'text-[12.5px]')}>
          {hint}
        </p>
      )}
      {error && (
        <p id={id + '-error'} role="alert" className={'mt-1.5 text-red-300 ' + (isBn ? 'text-[15px]' : 'text-[13px]')}>
          {error}
        </p>
      )}
    </div>
  )
}

// A password box with a Show / Hide button
export function PasswordField(props) {
  const { t, language } = useLanguage()
  const [visible, setVisible] = useState(false)
  const isBn = language === 'bn'

  const toggle = (
    <button
      type="button"
      onClick={function () {
        setVisible(!visible)
      }}
      aria-label={visible ? t.auth.hidePassword : t.auth.showPassword}
      aria-pressed={visible}
      className={'mr-2 cursor-pointer rounded-lg px-2.5 py-1.5 text-gold-light transition-colors hover:bg-graphite/70 focus-visible:outline-2 focus-visible:outline-gold ' + (isBn ? 'text-[14px]' : 'text-[12.5px]')}
    >
      {visible ? t.auth.hide : t.auth.show}
    </button>
  )

  return <Field {...props} type={visible ? 'text' : 'password'} trailing={toggle} />
}

// The big gold button (shows the wait text while busy)
export function SubmitButton({ busy, children }) {
  const { language } = useLanguage()
  return (
    <button
      type="submit"
      disabled={busy}
      className={'flex h-12 w-full cursor-pointer items-center justify-center gap-2.5 rounded-xl bg-gold font-semibold text-deep shadow-lg shadow-gold/20 transition hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-light disabled:cursor-not-allowed disabled:opacity-60 ' + (language === 'bn' ? 'text-[17px]' : 'text-[15px]')}
    >
      {busy && <span aria-hidden="true" className="size-4 rounded-full border-2 border-deep/30 border-t-deep motion-safe:animate-spin" />}
      {children}
    </button>
  )
}

// Eyebrow: small gold heading with a line
export function Eyebrow({ children }) {
  const { language } = useLanguage()
  return (
    <div className={'flex items-center gap-4 font-medium text-gold ' + (language === 'bn' ? 'text-[15px]' : 'text-[12px] tracking-[0.3em] sm:text-[13px]')}>
      <span className="h-px w-8 bg-gold/70 sm:w-[70px]" />
      <span>{children}</span>
    </div>
  )
}

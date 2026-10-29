import { useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import LanguageSwitch from './LanguageSwitch'
import { useLanguage } from '../i18n/useLanguage'

// A small arrow icon, used on the submit button and on the examples.
function ArrowIcon({ size = 16 }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M5 12h14" />
      <path d="m13 6 6 6-6 6" />
    </svg>
  )
}

// The question box on the home page.
function LegalQuestionCard() {
  const { t } = useLanguage()
  const navigate = useNavigate()
  const inputRef = useRef(null)

  // What the visitor has typed, and the red message if they sent it empty
  const [question, setQuestion] = useState('')
  const [error, setError] = useState('')

  function handleChange(event) {
    setQuestion(event.target.value)
    setError('') // hide the message as soon as they start typing
  }

  // Clicking an example puts its text into the input
  function fillExample(text) {
    setQuestion(text)
    setError('')
    inputRef.current.focus()
  }

  function handleSubmit(event) {
    event.preventDefault() // stop the browser from reloading the page

    const cleanQuestion = question.trim()
    if (cleanQuestion === '') {
      setError(t.question.emptyError)
      inputRef.current.focus()
      return
    }

    // Go to the assistant page and carry the question along
    navigate('/assistant', { state: { question: cleanQuestion } })
  }

  let fieldBorder = 'border-silver/15 focus-within:border-gold'
  if (error) {
    fieldBorder = 'border-red-400'
  }

  return (
    <div className="mt-5 w-full max-w-[510px] 2xl:max-w-[716px] rounded-2xl border border-silver/20 bg-charcoal/85 p-3.5 shadow-2xl shadow-black/30 backdrop-blur-md">
      <form onSubmit={handleSubmit} noValidate>
        {/* Input + gold arrow button */}
        <div className={'flex items-center gap-3 rounded-xl border bg-deep/70 py-1.5 pl-4 pr-1.5 transition-colors ' + fieldBorder}>
          <input
            ref={inputRef}
            type="text"
            value={question}
            onChange={handleChange}
            placeholder={t.question.placeholder}
            aria-label={t.question.placeholder}
            aria-invalid={error ? 'true' : 'false'}
            className="h-9 min-w-0 flex-1 bg-transparent text-[13px] 2xl:h-11 2xl:text-[15px] text-ivory outline-none placeholder:text-mist/70"
          />
          <button
            type="submit"
            aria-label={t.question.send}
            className="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg bg-gold text-deep transition hover:brightness-105"
          >
            <ArrowIcon />
          </button>
        </div>

        {/* Validation message */}
        {error && (
          <p role="alert" className="mt-2 px-1 text-[12.5px] text-red-300">
            {error}
          </p>
        )}
      </form>

      {/* "Try an example" + small language switch */}
      <div className="mt-3 flex items-center justify-between gap-3 px-0.5">
        <span className="text-xs font-medium text-ivory 2xl:text-sm">{t.question.tryExample}</span>
        <LanguageSwitch small />
      </div>

      {/* Three example questions */}
      <div className="mt-2.5 grid gap-2 sm:grid-cols-3">
        {t.question.examples.map(function (example) {
          return (
            <button
              key={example}
              type="button"
              onClick={function () {
                fillExample(example)
              }}
              className="flex min-h-12 cursor-pointer items-center justify-between gap-2 rounded-lg border border-silver/15 bg-graphite/70 px-2.5 py-2 text-left text-[11px] leading-snug 2xl:min-h-[84px] 2xl:text-[13px] text-ivory transition hover:border-gold hover:bg-graphite"
            >
              <span>{example}</span>
              <span className="shrink-0 text-gold-light">
                <ArrowIcon size={12} />
              </span>
            </button>
          )
        })}
      </div>

      {/* Disclaimer */}
      <p className="mt-2.5 flex items-start gap-2 px-0.5 text-[10.5px] leading-snug text-mist 2xl:text-[13px]">
        <svg
          width="12"
          height="12"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          className="mt-px shrink-0"
          aria-hidden="true"
        >
          <circle cx="12" cy="12" r="9.5" />
          <path d="M12 11v5.5M12 7.5v.01" />
        </svg>
        {t.question.disclaimer}
      </p>
    </div>
  )
}

export default LegalQuestionCard

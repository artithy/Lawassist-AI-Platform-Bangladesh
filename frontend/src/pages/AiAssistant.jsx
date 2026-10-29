import { useEffect, useRef, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import { askAssistant, isAssistantConfigured } from '../services/assistantApi'

// ---------- Small icons (plain SVG, same style as the rest of the site) ----------
function Icon({ children, size = 18, className = '' }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className={'shrink-0 ' + className} aria-hidden="true">
      {children}
    </svg>
  )
}

// One icon for each of the four topic cards (same order as t.assistant.topics)
const topicIcons = [
  <>
    <path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6Z" />
    <path d="M9 4v14M15 6v14" />
  </>,
  <>
    <circle cx="9" cy="7.5" r="3.3" />
    <path d="M2.8 20c0-3.4 2.8-5.9 6.2-5.9s6.2 2.5 6.2 5.9" />
    <path d="M16.4 4.6a3.1 3.1 0 0 1 0 5.8" />
    <path d="M18.3 14.5c2 .7 3.4 2.6 3.4 5.2" />
  </>,
  <>
    <rect x="3" y="7" width="18" height="13" rx="2" />
    <path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18" />
  </>,
  <>
    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
    <path d="M3 6h18" />
    <path d="M16 10a4 4 0 0 1-8 0" />
  </>,
]

// Only real web links are made clickable (so a backend can never inject a "javascript:" link)
function isWebLink(url) {
  return typeof url === 'string' && /^https?:\/\//i.test(url)
}

// The AI Legal Assistant: /assistant
// A sidebar (sample chats, search, profile) + the conversation + the message box.
// There is no AI behind it yet: every message gets the placeholder "AI responses will be connected soon."
// (services/assistantApi.js can connect a real backend later; until then nothing is sent anywhere.)
function AiAssistant() {
  const { language, t } = useLanguage()
  const a = t.assistant
  const isBn = language === 'bn'

  const location = useLocation()
  const navigate = useNavigate()
  // After a REAL log-in the user arrives here in location.state.user. Otherwise this is a guest / demo visit.
  const user = location.state && location.state.user

  // messages: { id, role: 'user' | 'assistant' | 'notice' | 'error', text, sources, question }
  const [messages, setMessages] = useState([])
  const [draft, setDraft] = useState('')
  const [emptyWarning, setEmptyWarning] = useState(false)
  const [loading, setLoading] = useState(false)
  const [sidebarOpen, setSidebarOpen] = useState(false) // only matters on small screens
  const [query, setQuery] = useState('') // the "search conversations" box

  const nextId = useRef(1)
  const listRef = useRef(null)
  const inputRef = useRef(null)
  const startedFromHome = useRef(false)

  function addMessage(message) {
    const full = { sources: [], ...message, id: nextId.current++ }
    setMessages(function (old) {
      return old.concat(full)
    })
  }

  // Ask the assistant. "alreadyShown" is true when the question is already on screen (the "Try again" button).
  async function ask(question, alreadyShown) {
    if (!alreadyShown) addMessage({ role: 'user', text: question })

    // No backend yet: show the transparent placeholder. Never invent an answer.
    if (!isAssistantConfigured) {
      addMessage({ role: 'notice', text: a.notConnectedReply })
      return
    }

    setLoading(true)
    try {
      const reply = await askAssistant(question, language)
      addMessage({ role: 'assistant', text: reply.answer, sources: reply.sources })
    } catch {
      addMessage({ role: 'error', text: a.errorReply, question: question })
    } finally {
      setLoading(false)
    }
  }

  function handleSubmit(event) {
    event.preventDefault()
    if (loading) return
    const question = draft.trim()
    if (question === '') {
      setEmptyWarning(true)
      inputRef.current.focus()
      return
    }
    setDraft('')
    ask(question, false)
    inputRef.current.focus()
  }

  // Enter sends, Shift+Enter starts a new line. (While typing Bangla with a phonetic keyboard, Enter must not send.)
  function handleKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
      handleSubmit(event)
    }
  }

  // Put a suggested question into the message box (the visitor can edit it, then send)
  function suggest(question) {
    setDraft(question)
    setEmptyWarning(false)
    setSidebarOpen(false)
    inputRef.current.focus()
  }

  function newChat() {
    setMessages([])
    setDraft('')
    setEmptyWarning(false)
    setSidebarOpen(false)
    inputRef.current.focus()
  }

  function retry(message) {
    setMessages(function (old) {
      return old.filter(function (m) {
        return m.id !== message.id
      })
    })
    ask(message.question, true)
  }

  // A question typed on the home page arrives here in location.state (see LegalQuestionCard).
  // We ask it once, then clear it so refreshing the page does not ask it again.
  useEffect(
    function () {
      const fromHome = location.state && location.state.question
      if (fromHome && !startedFromHome.current) {
        startedFromHome.current = true
        navigate('/assistant', { replace: true, state: user ? { user: user } : null })
        ask(fromHome, false)
      }
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  )

  // Escape closes the sidebar on small screens
  useEffect(
    function () {
      if (!sidebarOpen) return undefined
      function onKey(event) {
        if (event.key === 'Escape') setSidebarOpen(false)
      }
      document.addEventListener('keydown', onKey)
      return function () {
        document.removeEventListener('keydown', onKey)
      }
    },
    [sidebarOpen],
  )

  // Grow the message box with its content (up to a limit)
  useEffect(
    function () {
      const box = inputRef.current
      if (!box) return
      box.style.height = 'auto'
      box.style.height = Math.min(box.scrollHeight, 168) + 'px'
    },
    [draft],
  )

  // Keep the newest message in view (inside the chat window only, the page itself does not jump)
  useEffect(
    function () {
      const list = listRef.current
      if (list) list.scrollTop = messages.length === 0 && !loading ? 0 : list.scrollHeight
    },
    [messages, loading],
  )

  const words = query.trim().toLowerCase()
  const samples = a.samples.filter(function (item) {
    return words === '' || (item.title + ' ' + item.question).toLowerCase().includes(words)
  })

  const bodyText = isBn ? 'text-[17px] leading-[1.85]' : 'text-[15px] leading-[1.7]'
  const smallText = isBn ? 'text-[15px]' : 'text-[13px]'

  // The name shown in the profile box
  const profileName = user && user.name ? user.name : user && user.email ? user.email : a.guestName
  const profileLetter = user ? (profileName.trim().charAt(0) || '?').toUpperCase() : null

  return (
    <main className="bg-[#0b0e12]">
      <h1 className="sr-only">{a.title}</h1>

      {/* The chat "app": as tall as the screen under the navbar. The footer follows below it. */}
      <div className="relative mx-auto flex h-[calc(100dvh-4rem)] min-h-[620px] max-w-[1600px] overflow-hidden border-b border-silver/10">
        {/* Dark layer behind the sidebar on small screens */}
        {sidebarOpen && <div aria-hidden="true" onClick={function () { setSidebarOpen(false) }} className="absolute inset-0 z-20 bg-black/60 lg:hidden" />}

        {/* ================= Sidebar ================= */}
        <aside
          id="chat-sidebar"
          aria-label={a.sidebarLabel}
          className={'absolute inset-y-0 left-0 z-30 flex w-[300px] max-w-[88%] flex-col border-r border-silver/10 bg-[#0e1115] transition-[transform,visibility] duration-300 lg:static lg:visible lg:w-[300px] lg:max-w-none lg:translate-x-0 ' + (sidebarOpen ? 'visible translate-x-0' : 'invisible -translate-x-full')}
        >
          <div className="flex items-center justify-between gap-3 px-4 pb-3 pt-4">
            <Link to="/" className="flex items-center gap-2.5 text-gold-light focus-visible:outline-2 focus-visible:outline-gold">
              <span className="flex size-9 items-center justify-center rounded-lg border border-gold/40 bg-gold/10">
                <Icon size={20}>
                  <path d="M12 3 3.5 7.5h17L12 3Z" />
                  <path d="M3.5 21h17" />
                  <path d="M6.5 10.5v6M10 10.5v6M14 10.5v6M17.5 10.5v6" />
                </Icon>
              </span>
              <span className="font-display text-[22px] font-medium leading-none text-ivory">LawAssist AI</span>
            </Link>
            <button type="button" onClick={function () { setSidebarOpen(false) }} aria-label={a.closeMenu} className="flex size-9 cursor-pointer items-center justify-center rounded-lg text-silver transition-colors hover:bg-graphite/60 hover:text-ivory focus-visible:outline-2 focus-visible:outline-gold lg:hidden">
              <Icon size={20}>
                <path d="M6 6l12 12M18 6 6 18" />
              </Icon>
            </button>
          </div>

          <div className="space-y-3 px-4">
            <button type="button" onClick={newChat} className={'flex h-11 w-full cursor-pointer items-center justify-center gap-2.5 rounded-xl border border-gold/70 bg-gold/10 font-medium text-ivory transition-colors hover:bg-gold hover:text-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold ' + (isBn ? 'text-[16px]' : 'text-[14.5px]')}>
              <Icon size={18}>
                <path d="M12 5v14M5 12h14" />
              </Icon>
              {a.newChat}
            </button>

            <label className="block">
              <span className="sr-only">{a.searchLabel}</span>
              <span className="flex items-center gap-2.5 rounded-xl border border-silver/15 bg-deep/60 px-3 transition-colors focus-within:border-gold">
                <Icon size={17} className="text-silver">
                  <circle cx="11" cy="11" r="7" />
                  <path d="m20 20-3.5-3.5" />
                </Icon>
                <input type="search" value={query} onChange={function (e) { setQuery(e.target.value) }} placeholder={a.searchPlaceholder} className={'h-10 min-w-0 flex-1 bg-transparent text-ivory outline-none placeholder:text-mist/70 ' + (isBn ? 'text-[15.5px]' : 'text-[14px]')} />
              </span>
            </label>
          </div>

          {/* Recent chats: SAMPLE items only (no real history is saved yet) */}
          <div className="mt-5 flex min-h-0 flex-1 flex-col px-4">
            <div className="mb-2 flex items-center justify-between gap-2">
              <h2 className={'font-medium text-gold ' + (isBn ? 'text-[14.5px]' : 'text-[11.5px] tracking-[0.2em]')}>{isBn ? a.recent : a.recent.toUpperCase()}</h2>
              <span className={'rounded-full border border-gold/45 bg-gold/10 px-2 py-0.5 text-gold-light ' + (isBn ? 'text-[12.5px]' : 'text-[10.5px]')}>{a.sampleTag}</span>
            </div>
            <ul className="-mx-1 flex-1 space-y-0.5 overflow-y-auto px-1">
              {samples.map(function (item) {
                return (
                  <li key={item.title}>
                    <button type="button" onClick={function () { suggest(item.question) }} className={'flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-silver transition-colors hover:bg-graphite/50 hover:text-ivory focus-visible:outline-2 focus-visible:outline-gold ' + (isBn ? 'text-[15.5px]' : 'text-[14px]')}>
                      <Icon size={16} className="text-mist">
                        <path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.6A8 8 0 1 1 21 12Z" />
                      </Icon>
                      <span className="truncate">{item.title}</span>
                    </button>
                  </li>
                )
              })}
              {samples.length === 0 && <li className={'px-2.5 py-3 text-mist ' + smallText}>{a.noMatch}</li>}
            </ul>
            <p className={'py-3 text-mist/90 ' + (isBn ? 'text-[13.5px] leading-[1.7]' : 'text-[11.5px] leading-snug')}>{a.sampleNote}</p>
          </div>

          {/* Profile */}
          <div className="flex items-center gap-3 border-t border-silver/10 px-4 py-3.5">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full border border-gold/40 bg-gold/10 text-gold-light">
              {profileLetter ? (
                <span className="font-display text-[20px]">{profileLetter}</span>
              ) : (
                <Icon size={20}>
                  <circle cx="12" cy="8" r="3.8" />
                  <path d="M4.5 20c0-4 3.4-6.5 7.5-6.5s7.5 2.5 7.5 6.5" />
                </Icon>
              )}
            </span>
            <div className="min-w-0 flex-1">
              <p className={'truncate text-ivory ' + (isBn ? 'text-[16px]' : 'text-[14px]')}>{profileName}</p>
              <p className={'truncate text-mist ' + (isBn ? 'text-[14px]' : 'text-[12px]')}>{user ? a.signedInStatus : a.guestStatus}</p>
            </div>
            {!user && (
              <Link to="/login" className={'shrink-0 rounded-lg border border-silver/25 px-3 py-1.5 text-gold-light transition-colors hover:border-gold/70 focus-visible:outline-2 focus-visible:outline-gold ' + (isBn ? 'text-[14px]' : 'text-[12.5px]')}>
                {a.loginLink}
              </Link>
            )}
          </div>
        </aside>

        {/* ================= Chat ================= */}
        <section aria-label={a.conversation} className="relative flex min-w-0 flex-1 flex-col bg-[radial-gradient(ellipse_at_50%_0%,rgba(185,154,91,0.07),transparent_55%)]">
          {/* Top bar */}
          <div className="flex items-center justify-between gap-3 border-b border-silver/10 px-4 py-2.5 sm:px-6">
            <div className="flex min-w-0 items-center gap-3">
              <button type="button" onClick={function () { setSidebarOpen(true) }} aria-label={a.openMenu} aria-expanded={sidebarOpen} aria-controls="chat-sidebar" className="flex size-10 cursor-pointer items-center justify-center rounded-lg border border-silver/20 text-silver transition-colors hover:border-gold/60 hover:text-ivory focus-visible:outline-2 focus-visible:outline-gold lg:hidden">
                <Icon size={20}>
                  <path d="M4 7h16M4 12h16M4 17h16" />
                </Icon>
              </button>
              <p className={'truncate font-display font-medium text-ivory ' + (isBn ? 'text-[19px]' : 'text-[21px]')}>{a.title}</p>
            </div>
            {!isAssistantConfigured && (
              <span className={'hidden shrink-0 items-center gap-2 rounded-full border border-gold/35 bg-gold/[0.07] px-3 py-1 text-gold-light sm:inline-flex ' + (isBn ? 'text-[13px]' : 'text-[11.5px]')}>
                <span aria-hidden="true" className="size-1.5 rounded-full bg-gold" />
                {a.demoPill}
              </span>
            )}
          </div>

          {/* Messages / welcome */}
          <div ref={listRef} role="log" aria-live="polite" aria-label={a.conversation} className="min-h-0 flex-1 overflow-y-auto px-4 py-6 sm:px-6">
            {messages.length === 0 && !loading ? (
              <div className="mx-auto flex min-h-full max-w-[760px] flex-col items-center justify-center py-4 text-center">
                <span className="flex size-14 items-center justify-center rounded-full border border-gold/40 bg-gold/10 text-gold-light">
                  <Icon size={26}>
                    <path d="M12 3v18M5 7h14" />
                    <path d="M5 7 2.5 14a3 3 0 0 0 5 0L5 7ZM19 7l-2.5 7a3 3 0 0 0 5 0L19 7Z" />
                    <path d="M8 21h8" />
                  </Icon>
                </span>
                <h2 className={'mt-5 font-display font-medium text-ivory ' + (isBn ? 'text-[clamp(1.8rem,3.2vw,2.5rem)] leading-[1.4]' : 'text-[clamp(1.9rem,3.2vw,2.6rem)] leading-[1.15]')}>{a.welcomeTitle}</h2>
                <p className={'mt-3 max-w-[34rem] text-silver ' + (isBn ? 'text-[17px] leading-[1.85]' : 'text-[16px] leading-[1.65]')}>{a.welcomeSubtitle}</p>

                <p className={'mb-3 mt-9 font-medium text-gold ' + (isBn ? 'text-[15px]' : 'text-[11.5px] tracking-[0.22em]')}>{isBn ? a.topicsLabel : a.topicsLabel.toUpperCase()}</p>
                <ul className="grid w-full gap-3 text-left sm:grid-cols-2">
                  {a.topics.map(function (topic, index) {
                    return (
                      <li key={topic.title}>
                        <button type="button" onClick={function () { suggest(topic.question) }} className="group flex h-full w-full cursor-pointer items-start gap-3.5 text-left rounded-2xl border border-silver/15 bg-[#12151a]/90 p-4 transition-[border-color,transform,background-color] duration-300 hover:border-gold/55 hover:bg-[#161a20] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold motion-safe:hover:-translate-y-0.5">
                          <span className="flex size-11 shrink-0 items-center justify-center rounded-xl border border-gold/35 bg-gold/10 text-gold-light transition-colors group-hover:bg-gold/20">
                            <Icon size={22}>{topicIcons[index]}</Icon>
                          </span>
                          <span className="min-w-0">
                            <span className={'block font-medium text-ivory ' + (isBn ? 'text-[17px] leading-[1.5]' : 'text-[15.5px]')}>{topic.title}</span>
                            <span className={'mt-0.5 block text-mist ' + (isBn ? 'text-[14.5px] leading-[1.6]' : 'text-[13px] leading-snug')}>{topic.text}</span>
                          </span>
                        </button>
                      </li>
                    )
                  })}
                </ul>
              </div>
            ) : (
              <div className="mx-auto max-w-[760px] space-y-5">
                {messages.map(function (message) {
                  if (message.role === 'user') {
                    return (
                      <div key={message.id} className="flex justify-end">
                        <div className="max-w-[88%] sm:max-w-[75%]">
                          <p className={'mb-1 text-right text-gold ' + smallText}>{a.you}</p>
                          <p className={'whitespace-pre-wrap break-words rounded-2xl rounded-tr-md border border-gold/30 bg-gold/10 px-4 py-3 text-ivory ' + bodyText}>{message.text}</p>
                        </div>
                      </div>
                    )
                  }

                  // "notice" and "error" come from the SITE (not from an AI), so they look different from answers
                  if (message.role === 'notice' || message.role === 'error') {
                    return (
                      <div key={message.id} role={message.role === 'error' ? 'alert' : undefined} className={'flex items-start gap-3 rounded-2xl border border-dashed px-4 py-3 ' + (message.role === 'error' ? 'border-red-400/50 bg-red-400/[0.06]' : 'border-silver/25 bg-[#0e1115]/70')}>
                        <Icon size={18} className={'mt-1 ' + (message.role === 'error' ? 'text-red-300' : 'text-silver')}>
                          <circle cx="12" cy="12" r="9.5" />
                          <path d="M12 11v5.5M12 7.5v.01" />
                        </Icon>
                        <div className="min-w-0">
                          {message.role === 'notice' && <p className={'mb-0.5 text-gold ' + (isBn ? 'text-[13.5px]' : 'text-[11.5px] tracking-wide')}>{a.placeholderLabel}</p>}
                          <p className={'text-mist ' + bodyText}>{message.text}</p>
                          {message.role === 'error' && (
                            <button type="button" onClick={function () { retry(message) }} disabled={loading} className={'mt-2 cursor-pointer rounded-lg border border-gold/70 px-3.5 py-1.5 font-medium text-ivory transition-colors hover:bg-gold hover:text-deep disabled:cursor-not-allowed disabled:opacity-50 ' + (isBn ? 'text-[15px]' : 'text-[13px]')}>
                              {a.retry}
                            </button>
                          )}
                        </div>
                      </div>
                    )
                  }

                  // a real answer from a connected backend
                  return (
                    <div key={message.id} className="flex justify-start">
                      <div className="max-w-[92%] sm:max-w-[80%]">
                        <p className={'mb-1 text-gold-light ' + smallText}>{a.assistantName}</p>
                        <div className="rounded-2xl rounded-tl-md border border-silver/15 bg-graphite/50 px-4 py-3">
                          <p className={'whitespace-pre-wrap break-words text-ivory ' + bodyText}>{message.text}</p>
                          {message.sources.length > 0 && (
                            <div className="mt-3 border-t border-silver/15 pt-2.5">
                              <p className={'mb-1 font-medium text-gold ' + smallText}>{a.sources}</p>
                              <ul className={'space-y-0.5 text-silver ' + smallText}>
                                {message.sources.map(function (source, index) {
                                  const title = typeof source === 'string' ? source : source && (source.title || source.url)
                                  const url = source && typeof source === 'object' ? source.url : null
                                  return (
                                    <li key={index}>
                                      {isWebLink(url) ? (
                                        <a href={url} target="_blank" rel="noreferrer" className="text-gold-light underline-offset-4 hover:underline">{title}</a>
                                      ) : (
                                        title
                                      )}
                                    </li>
                                  )
                                })}
                              </ul>
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  )
                })}

                {loading && (
                  <div role="status" className={'flex items-center gap-3 text-mist ' + smallText}>
                    <span aria-hidden="true" className="flex gap-1">
                      <span className="size-1.5 rounded-full bg-gold motion-safe:animate-pulse" />
                      <span className="size-1.5 rounded-full bg-gold motion-safe:animate-pulse [animation-delay:150ms]" />
                      <span className="size-1.5 rounded-full bg-gold motion-safe:animate-pulse [animation-delay:300ms]" />
                    </span>
                    {a.sending}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Message box: stays at the bottom of the chat */}
          <form onSubmit={handleSubmit} noValidate className="border-t border-silver/10 bg-[#0b0e12]/80 px-4 pb-3 pt-3.5 backdrop-blur-sm sm:px-6">
            <div className="mx-auto max-w-[760px]">
              <div className={'flex items-end gap-3 rounded-2xl border bg-deep/70 p-2 pl-4 shadow-[0_10px_30px_-18px_rgba(0,0,0,0.9)] transition-colors focus-within:border-gold ' + (emptyWarning ? 'border-red-400' : 'border-silver/20')}>
                <textarea
                  ref={inputRef}
                  rows={1}
                  value={draft}
                  onChange={function (event) {
                    setDraft(event.target.value)
                    setEmptyWarning(false)
                  }}
                  onKeyDown={handleKeyDown}
                  placeholder={a.placeholder}
                  aria-label={a.placeholder}
                  aria-invalid={emptyWarning ? 'true' : 'false'}
                  maxLength={2000}
                  className={'max-h-[168px] min-h-11 min-w-0 flex-1 resize-none bg-transparent py-2.5 text-ivory outline-none placeholder:text-mist/70 ' + (isBn ? 'text-[17px] leading-[1.6]' : 'text-[15px] leading-[1.5]')}
                />
                <button type="submit" disabled={loading} aria-label={a.send} className="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-gold text-deep transition hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-light disabled:cursor-not-allowed disabled:opacity-50">
                  <Icon size={19}>
                    <path d="M12 19V5M5 12l7-7 7 7" />
                  </Icon>
                </button>
              </div>

              {emptyWarning && <p role="alert" className={'mt-2 px-1 text-red-300 ' + smallText}>{t.question.emptyError}</p>}

              <p className={'mt-2.5 flex items-start justify-center gap-2 text-center text-mist ' + (isBn ? 'text-[14px] leading-[1.7]' : 'text-[12px] leading-snug')}>
                <Icon size={14} className="mt-0.5 text-gold">
                  <circle cx="12" cy="12" r="9.5" />
                  <path d="M12 11v5.5M12 7.5v.01" />
                </Icon>
                <span>
                  {a.disclaimer}
                  <span className="hidden text-mist/70 sm:inline"> · {a.hint}</span>
                </span>
              </p>
            </div>
          </form>
        </section>
      </div>
    </main>
  )
}

export default AiAssistant

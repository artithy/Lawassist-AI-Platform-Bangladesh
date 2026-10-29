import { useEffect, useRef, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import { useAuth } from '../auth/useAuth'
import { askAssistant, isAssistantConfigured } from '../services/assistantApi'
import artwork from '../assets/assistant-bg.webp'

// ---------- Small icons (plain SVG, same style as the rest of the site) ----------
function Icon({ children, size = 18, className = '' }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className={'shrink-0 ' + className} aria-hidden="true">
      {children}
    </svg>
  )
}

// Only real web links are made clickable (so a backend can never inject a "javascript:" link)
function isWebLink(url) {
  return typeof url === 'string' && /^https?:\/\//i.test(url)
}

// The AI Legal Assistant: /assistant  (members only: App.jsx wraps it in <ProtectedRoute>)
// A sidebar (chats from this visit, search, profile + log out) + the conversation + the message box.
// There is no AI behind it yet: every message gets the placeholder "AI responses will be connected soon."
// (services/assistantApi.js can connect a real backend later; until then nothing is sent anywhere.)
function AiAssistant() {
  const { language, t } = useLanguage()
  const a = t.assistant
  const isBn = language === 'bn'

  const location = useLocation()
  const navigate = useNavigate()
  const { user, signOut } = useAuth()
  const [loggingOut, setLoggingOut] = useState(false)

  // The chats of this visit. Each chat: { id, title, messages }, newest first.
  // A message: { id, role: 'user' | 'assistant' | 'notice' | 'error', text, sources, question }
  // (They live only in memory: nothing is saved, so they are gone after a refresh.)
  const [chats, setChats] = useState([])
  const [activeId, setActiveId] = useState(null) // null = a new, empty chat (the empty chat screen)
  const activeChat = chats.find(function (chat) {
    return chat.id === activeId
  })
  const messages = activeChat ? activeChat.messages : []
  const [draft, setDraft] = useState('')
  const [emptyWarning, setEmptyWarning] = useState(false)
  const [loading, setLoading] = useState(false)
  const [sidebarOpen, setSidebarOpen] = useState(false) // only matters on small screens
  const [query, setQuery] = useState('') // the "search conversations" box

  const nextId = useRef(1)
  const nextChatId = useRef(1)
  const listRef = useRef(null)
  const inputRef = useRef(null)
  const startedFromHome = useRef(false)

  // Add a message to a chat. With chatId = null a new chat is started (its title is the first question).
  function addMessage(chatId, message) {
    const full = { sources: [], ...message, id: nextId.current++ }
    let targetId = chatId
    if (targetId === null) {
      targetId = nextChatId.current++
      const title = full.text.replace(/\s+/g, ' ').trim()
      setChats(function (old) {
        return [{ id: targetId, title: title.length > 44 ? title.slice(0, 44) + '…' : title, messages: [full] }].concat(old)
      })
      setActiveId(targetId)
    } else {
      setChats(function (old) {
        return old.map(function (chat) {
          return chat.id === targetId ? { ...chat, messages: chat.messages.concat(full) } : chat
        })
      })
    }
    return targetId
  }

  // Ask the assistant. "alreadyShown" is true when the question is already on screen (the "Try again" button).
  async function ask(question, alreadyShown, chatId) {
    let id = chatId
    if (!alreadyShown) id = addMessage(chatId, { role: 'user', text: question })

    // No AI is connected yet: say so plainly. Never invent an answer.
    if (!isAssistantConfigured) {
      addMessage(id, { role: 'notice', text: a.notConnectedReply })
      return
    }

    setLoading(true)
    try {
      const reply = await askAssistant(question, language)
      addMessage(id, { role: 'assistant', text: reply.answer, sources: reply.sources })
    } catch {
      addMessage(id, { role: 'error', text: a.errorReply, question: question })
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
    ask(question, false, activeId)
    inputRef.current.focus()
  }

  // Enter sends, Shift+Enter starts a new line. (While typing Bangla with a phonetic keyboard, Enter must not send.)
  function handleKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
      handleSubmit(event)
    }
  }

  function newChat() {
    setActiveId(null) // the chat stays in the sidebar list; the screen goes back to the empty chat screen
    setDraft('')
    setEmptyWarning(false)
    setSidebarOpen(false)
    inputRef.current.focus()
  }

  // Open an earlier chat of this visit
  function openChat(chat) {
    setActiveId(chat.id)
    setEmptyWarning(false)
    setSidebarOpen(false)
  }

  function retry(message) {
    setChats(function (old) {
      return old.map(function (chat) {
        return chat.id === activeId ? { ...chat, messages: chat.messages.filter(function (m) { return m.id !== message.id }) } : chat
      })
    })
    ask(message.question, true, activeId)
  }

  async function handleLogout() {
    if (loggingOut) return
    setLoggingOut(true)
    await signOut()
    navigate('/login', { replace: true })
  }

  // A question typed on the home page arrives here in location.state (see LegalQuestionCard).
  // We ask it once, then clear it so refreshing the page does not ask it again.
  useEffect(
    function () {
      const fromHome = location.state && location.state.question
      if (fromHome && !startedFromHome.current) {
        startedFromHome.current = true
        navigate('/assistant', { replace: true, state: null })
        ask(fromHome, false, null)
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
  const shownChats = chats.filter(function (chat) {
    return words === '' || chat.title.toLowerCase().includes(words)
  })

  // Text sizes: the base size is for laptops, the 2xl: size (screens 1536px and wider) is a little larger.
  const bodyText = isBn ? 'text-[17px] leading-[1.85] 2xl:text-[18px]' : 'text-[15px] leading-[1.7] 2xl:text-[17px]'
  const smallText = isBn ? 'text-[15px] 2xl:text-[16px]' : 'text-[13px] 2xl:text-[14px]'
  const hasMessages = messages.length > 0 || loading

  // The name shown in the profile box: the name given at sign-up (or by Google), else the e-mail address
  const meta = (user && user.user_metadata) || {}
  const email = (user && user.email) || ''
  const profileName = meta.full_name || meta.name || email || a.account
  const profileLetter = (profileName.trim().charAt(0) || '?').toUpperCase()

  const focusRing = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#e3b261]'

  return (
    <main className="flex min-h-0 flex-1 flex-col bg-[#0f1317] text-[#ece8df]">
      <h1 className="sr-only">{a.title}</h1>

      {/* The chat "app": fills the screen under the navbar, all the way to the bottom (there is no footer on this page). */}
      <div className="relative mx-auto flex min-h-0 w-full max-w-[1920px] flex-1 overflow-hidden">
        {/* Dark layer behind the sidebar on small screens */}
        {sidebarOpen && <div aria-hidden="true" onClick={function () { setSidebarOpen(false) }} className="absolute inset-0 z-20 bg-black/60 lg:hidden" />}

        {/* ================= Sidebar ================= */}
        <aside
          id="chat-sidebar"
          aria-label={a.sidebarLabel}
          className={'absolute inset-y-0 left-0 z-30 flex w-[300px] max-w-[88%] flex-col border-r border-[#33383d] bg-[#0f1317] transition-[transform,visibility] duration-300 lg:static lg:visible lg:w-[300px] lg:max-w-none lg:translate-x-0 2xl:w-[357px] ' + (sidebarOpen ? 'visible translate-x-0' : 'invisible -translate-x-full')}
        >
          <div className="flex items-center justify-between gap-3 px-4 pb-3 pt-4 2xl:px-[17px] 2xl:pb-4 2xl:pt-[21px]">
            <Link to="/" className={'flex items-center gap-2.5 text-[#e3b261] 2xl:gap-3 ' + focusRing}>
              <span className="flex size-9 items-center justify-center rounded-[9px] border border-[#8a683e] bg-[#1b1f20] 2xl:size-[43px] 2xl:rounded-[10px]">
                <Icon size={20} className="2xl:size-6">
                  <path d="M12 3 3.5 7.5h17L12 3Z" />
                  <path d="M3.5 21h17" />
                  <path d="M6.5 10.5v6M10 10.5v6M14 10.5v6M17.5 10.5v6" />
                </Icon>
              </span>
              <span className="font-display text-[22px] font-medium leading-none text-[#f5f2ea] 2xl:text-[26px]">LawAssist AI</span>
            </Link>
            <button type="button" onClick={function () { setSidebarOpen(false) }} aria-label={a.closeMenu} className={'flex size-9 cursor-pointer items-center justify-center rounded-lg text-[#b8bdc5] transition-colors hover:bg-[#1b2026] hover:text-white lg:hidden ' + focusRing}>
              <Icon size={20}>
                <path d="M6 6l12 12M18 6 6 18" />
              </Icon>
            </button>
          </div>

          <div className="space-y-3 px-4 2xl:space-y-4 2xl:px-[17px]">
            <button type="button" onClick={newChat} className={'flex h-11 w-full cursor-pointer items-center justify-center gap-2.5 rounded-xl border border-[#a07a42] bg-[#1b1f20] font-medium text-[#f5f2ea] transition-colors hover:bg-[#262a2b] 2xl:h-[52px] 2xl:gap-3 2xl:rounded-[14px] ' + focusRing + ' ' + (isBn ? 'text-[16px] 2xl:text-[18px]' : 'text-[14.5px] 2xl:text-[17px]')}>
              <Icon size={18} className="2xl:size-[21px]">
                <path d="M12 5v14M5 12h14" />
              </Icon>
              {a.newChat}
            </button>

            <label className="block">
              <span className="sr-only">{a.searchLabel}</span>
              <span className="flex items-center gap-2.5 rounded-xl border border-[#2a3138] bg-[#11171d] px-3 transition-colors focus-within:border-[#a07a42] 2xl:gap-3 2xl:rounded-[12px] 2xl:px-[17px]">
                <Icon size={17} className="text-[#f5f2ea] 2xl:size-5">
                  <circle cx="11" cy="11" r="7" />
                  <path d="m20 20-3.5-3.5" />
                </Icon>
                <input type="search" value={query} onChange={function (e) { setQuery(e.target.value) }} placeholder={a.searchPlaceholder} className={'h-10 min-w-0 flex-1 bg-transparent text-[#f5f2ea] outline-none placeholder:text-[#8f9aa6] 2xl:h-12 ' + (isBn ? 'text-[15.5px] 2xl:text-[17px]' : 'text-[14px] 2xl:text-[17px]')} />
              </span>
            </label>
          </div>

          {/* Recent chats: the chats of this visit (not saved) */}
          <div className="mt-5 flex min-h-0 flex-1 flex-col px-4 2xl:mt-[28px] 2xl:px-[17px]">
            <h2 className={'mb-2 font-medium text-[#f5f2ea] 2xl:mb-3 ' + (isBn ? 'text-[14.5px] 2xl:text-[16px]' : 'text-[11.5px] tracking-[0.2em] 2xl:text-[14px]')}>{isBn ? a.recent : a.recent.toUpperCase()}</h2>
            <ul className="-mx-1 flex-1 space-y-0.5 overflow-y-auto px-1 [color-scheme:dark]">
              {shownChats.map(function (chat) {
                const isActive = chat.id === activeId
                return (
                  <li key={chat.id}>
                    <button type="button" onClick={function () { openChat(chat) }} aria-current={isActive ? 'true' : undefined} className={'flex w-full cursor-pointer items-center gap-2.5 rounded-lg border px-2.5 py-2 text-left transition-colors hover:bg-[#1b2026] hover:text-white 2xl:py-2.5 ' + focusRing + ' ' + (isActive ? 'border-[#8a683e]/60 bg-[#1b2026] text-white ' : 'border-transparent text-[#b4bcc4] ') + (isBn ? 'text-[15.5px] 2xl:text-[17px]' : 'text-[14px] 2xl:text-[16px]')}>
                      <Icon size={16} className="text-[#e3b261]">
                        <path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.6A8 8 0 1 1 21 12Z" />
                      </Icon>
                      <span className="truncate">{chat.title}</span>
                    </button>
                  </li>
                )
              })}
              {shownChats.length === 0 && <li className={'px-2.5 py-3 text-[#a6b0b8] 2xl:leading-6 ' + (isBn ? 'text-[15px] 2xl:text-[16px]' : 'text-[14px] 2xl:text-[16px]')}>{chats.length === 0 ? a.noChats : a.noMatch}</li>}
            </ul>
            <p className={'py-3 text-[#a6b0b8] ' + (isBn ? 'text-[13.5px] leading-[1.7] 2xl:text-[15px]' : 'text-[11.5px] leading-snug 2xl:text-[14px] 2xl:leading-[22px]')}>{a.historyNote}</p>
          </div>

          {/* Profile + log out */}
          <div className="flex items-center gap-3 border-y border-[#272e32] px-4 py-3.5 2xl:px-[17px] 2xl:py-[17px]">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full border border-[#c9994f] bg-[#242018] text-[#f0bf6a] 2xl:size-12">
              <span className="font-display text-[20px] 2xl:text-[24px]">{profileLetter}</span>
            </span>
            <div className="min-w-0 flex-1">
              <p title={profileName} className={'truncate text-[#f5f2ea] ' + (isBn ? 'text-[16px] 2xl:text-[17px]' : 'text-[14px] 2xl:text-[16px]')}>{profileName}</p>
              {email && email !== profileName && <p className={'truncate text-[#a9b0b6] ' + (isBn ? 'text-[14px] 2xl:text-[15px]' : 'text-[12px] 2xl:text-[14px]')}>{email}</p>}
            </div>
            <button type="button" onClick={handleLogout} disabled={loggingOut} className={'shrink-0 cursor-pointer rounded-[10px] border border-[#60482e] px-3 py-1.5 font-medium text-[#e8b45e] transition-colors hover:border-[#c9994f] hover:bg-[#1b1f20] disabled:cursor-not-allowed disabled:opacity-60 2xl:px-4 2xl:py-[9px] ' + focusRing + ' ' + (isBn ? 'text-[14px] 2xl:text-[15px]' : 'text-[12.5px] 2xl:text-[15px]')}>
              {loggingOut ? a.loggingOut : a.logout}
            </button>
          </div>
          <div aria-hidden="true" className="h-7 shrink-0 2xl:h-[35px]" />
        </aside>

        {/* ================= Chat ================= */}
        <section aria-label={a.conversation} className="relative isolate flex min-w-0 flex-1 flex-col overflow-hidden bg-[#14191d]">
          {/* The artwork: Bangladesh map + the Shaheed Minar by a golden-lit lake. It stays behind the conversation. */}
          <img src={artwork} alt="" aria-hidden="true" draggable="false" className="pointer-events-none absolute inset-0 -z-10 h-full w-full select-none object-cover object-[80%_50%] lg:object-[82%_50%]" />
          {/* Once the chat has started, the picture is dimmed a little so the conversation reads clearly */}
          <div aria-hidden="true" className={'pointer-events-none absolute inset-0 -z-10 bg-[#0d1217] transition-opacity duration-500 ' + (hasMessages ? 'opacity-50' : 'opacity-30 lg:opacity-0')} />

          {/* Top bar: only on small screens (it holds the button that opens the sidebar) */}
          <div className="flex items-center gap-3 border-b border-[#2a3036] bg-[#0f1317]/70 px-4 py-2.5 backdrop-blur-sm sm:px-6 lg:hidden">
            <button type="button" onClick={function () { setSidebarOpen(true) }} aria-label={a.openMenu} aria-expanded={sidebarOpen} aria-controls="chat-sidebar" className={'flex size-10 cursor-pointer items-center justify-center rounded-lg border border-[#33383d] text-[#e3b261] transition-colors hover:border-[#a07a42] ' + focusRing}>
              <Icon size={20}>
                <path d="M4 7h16M4 12h16M4 17h16" />
              </Icon>
            </button>
            <p className={'truncate font-display font-medium text-[#f5f2ea] ' + (isBn ? 'text-[19px]' : 'text-[21px]')}>{a.title}</p>
          </div>

          {/* Messages / welcome */}
          <div ref={listRef} role="log" aria-live="polite" aria-label={a.conversation} className="min-h-0 flex-1 overflow-y-auto px-4 py-6 [color-scheme:dark] sm:px-6">
            {messages.length === 0 && !loading ? (
              // Empty chat: the welcome (icon, heading, one line) in the middle of the picture
              <div className="mx-auto flex min-h-full max-w-[760px] flex-col items-center justify-center pb-4 pt-10 text-center 2xl:pt-[62px]">
                <span className="flex size-[62px] items-center justify-center rounded-full border border-[#927043] bg-[#1c1c18]/70 text-[#e8b45e] 2xl:size-[74px]">
                  <Icon size={28} className="2xl:size-[34px]">
                    <path d="M12 3v18M5 7h14" />
                    <path d="M5 7 2.5 14a3 3 0 0 0 5 0L5 7ZM19 7l-2.5 7a3 3 0 0 0 5 0L19 7Z" />
                    <path d="M8 21h8" />
                  </Icon>
                </span>
                <h2 className={'mt-4 font-display font-medium text-[#f7f4ec] [text-shadow:0_2px_20px_rgba(0,0,0,0.65)] 2xl:mt-[22px] ' + (isBn ? 'text-[clamp(1.7rem,3vw,2.3rem)] leading-[1.4] 2xl:text-[44px]' : 'text-[clamp(2rem,3vw,2.3rem)] leading-[1.15] 2xl:text-[44px]')}>{a.welcomeTitle}</h2>
                <p className={'mt-1.5 max-w-[34rem] text-[#dcdfe2] [text-shadow:0_1px_12px_rgba(0,0,0,0.7)] ' + (isBn ? 'text-[16px] leading-[1.8] 2xl:text-[18px]' : 'text-[15px] leading-[1.6] 2xl:text-[18px]')}>{a.welcomeSubtitle}</p>
              </div>
            ) : (
              <div className="mx-auto max-w-[760px] space-y-5 2xl:max-w-[915px]">
                {messages.map(function (message) {
                  if (message.role === 'user') {
                    return (
                      <div key={message.id} className="flex justify-end">
                        <div className="max-w-[88%] sm:max-w-[75%]">
                          <p className={'mb-1 text-right text-[#e8b45e] ' + smallText}>{a.you}</p>
                          <p className={'whitespace-pre-wrap break-words rounded-2xl rounded-tr-md border border-[#8c7044]/60 bg-[#dca858]/[0.12] px-4 py-3 text-[#f5f2ea] ' + bodyText}>{message.text}</p>
                        </div>
                      </div>
                    )
                  }

                  // "notice" and "error" come from the SITE (not from an AI), so they look different from answers
                  if (message.role === 'notice' || message.role === 'error') {
                    return (
                      <div key={message.id} role={message.role === 'error' ? 'alert' : undefined} className={'flex items-start gap-3 rounded-2xl border border-dashed px-4 py-3 ' + (message.role === 'error' ? 'border-red-400/50 bg-red-950/50' : 'border-[#5a6168] bg-[#0f1317]/80')}>
                        <Icon size={18} className={'mt-1 ' + (message.role === 'error' ? 'text-red-300' : 'text-[#e3b261]')}>
                          <circle cx="12" cy="12" r="9.5" />
                          <path d="M12 11v5.5M12 7.5v.01" />
                        </Icon>
                        <div className="min-w-0">
                          {message.role === 'notice' && <p className={'mb-0.5 text-[#e8b45e] ' + (isBn ? 'text-[13.5px]' : 'text-[11.5px] tracking-wide')}>{a.placeholderLabel}</p>}
                          <p className={(message.role === 'error' ? 'text-red-200 ' : 'text-[#b4bcc4] ') + bodyText}>{message.text}</p>
                          {message.role === 'error' && (
                            <button type="button" onClick={function () { retry(message) }} disabled={loading} className={'mt-2 cursor-pointer rounded-lg border border-[#a07a42] px-3.5 py-1.5 font-medium text-[#f5f2ea] transition-colors hover:bg-[#dca858] hover:text-[#1a1c1f] disabled:cursor-not-allowed disabled:opacity-50 ' + focusRing + ' ' + (isBn ? 'text-[15px]' : 'text-[13px]')}>
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
                        <p className={'mb-1 text-[#e8b45e] ' + smallText}>{a.assistantName}</p>
                        <div className="rounded-2xl rounded-tl-md border border-[#2f353b] bg-[#12171c]/90 px-4 py-3">
                          <p className={'whitespace-pre-wrap break-words text-[#ece8df] ' + bodyText}>{message.text}</p>
                          {message.sources.length > 0 && (
                            <div className="mt-3 border-t border-[#2f353b] pt-2.5">
                              <p className={'mb-1 font-medium text-[#e8b45e] ' + smallText}>{a.sources}</p>
                              <ul className={'space-y-0.5 text-[#b4bcc4] ' + smallText}>
                                {message.sources.map(function (source, index) {
                                  const title = typeof source === 'string' ? source : source && (source.title || source.url)
                                  const url = source && typeof source === 'object' ? source.url : null
                                  return (
                                    <li key={index}>
                                      {isWebLink(url) ? (
                                        <a href={url} target="_blank" rel="noreferrer" className="text-[#e8b45e] underline-offset-4 hover:underline">{title}</a>
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
                  <div role="status" className={'flex items-center gap-3 text-[#b4bcc4] ' + smallText}>
                    <span aria-hidden="true" className="flex gap-1">
                      <span className="size-1.5 rounded-full bg-[#e3b261]/80" />
                      <span className="size-1.5 rounded-full bg-[#e3b261]/50" />
                      <span className="size-1.5 rounded-full bg-[#e3b261]/25" />
                    </span>
                    {a.sending}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Message box: stays at the bottom of the chat */}
          <form onSubmit={handleSubmit} noValidate className="px-4 pb-8 pt-3.5 sm:px-6 lg:pb-12 2xl:pb-[59px]">
            <div className="mx-auto max-w-[760px] 2xl:max-w-[915px]">
              <div className={'flex items-end gap-3 rounded-[14px] border bg-[#0d141b]/85 p-2 pl-4 shadow-[0_10px_34px_-14px_rgba(0,0,0,0.8)] backdrop-blur-sm transition-colors focus-within:border-[#e3b261] 2xl:gap-5 2xl:p-[9px] 2xl:pl-[19px] ' + (emptyWarning ? 'border-red-400' : 'border-[#8c7044]')}>
                <span aria-hidden="true" className="flex h-10 shrink-0 items-center text-[#f0bf6a] 2xl:h-[50px]">
                  <Icon size={19} className="2xl:size-[22px]">
                    <path d="m21.4 11.1-8.5 8.5a5.5 5.5 0 0 1-7.8-7.8l8.5-8.5a3.7 3.7 0 0 1 5.2 5.2l-8.5 8.5a1.8 1.8 0 0 1-2.6-2.6l7.8-7.8" />
                  </Icon>
                </span>
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
                  className={'max-h-[168px] min-h-10 min-w-0 flex-1 resize-none bg-transparent py-2 text-[#f5f2ea] caret-[#e3b261] outline-none placeholder:text-[#aab2b9] 2xl:min-h-[50px] 2xl:py-[13px] ' + (isBn ? 'text-[16px] leading-[1.6] 2xl:text-[18px]' : 'text-[15px] leading-[1.5] 2xl:text-[17px] 2xl:leading-6')}
                />
                <button type="submit" disabled={loading} aria-label={a.send} className={'flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-[10px] bg-[#dca858] text-[#1a1c1f] transition-colors hover:bg-[#e8b96a] disabled:cursor-not-allowed disabled:opacity-50 2xl:size-[51px] ' + focusRing}>
                  <Icon size={19} className="2xl:size-6">
                    <path d="M12 19V5M5 12l7-7 7 7" />
                  </Icon>
                </button>
              </div>

              {emptyWarning && <p role="alert" className={'mt-2 px-1 text-red-300 ' + smallText}>{t.question.emptyError}</p>}

              <p className={'mt-3 flex items-start justify-center gap-2 text-center text-[#aeb4ba] [text-shadow:0_1px_8px_rgba(0,0,0,0.8)] 2xl:mt-[14px] 2xl:gap-2.5 ' + (isBn ? 'text-[14px] leading-[1.7] 2xl:text-[15px]' : 'text-[12px] leading-snug 2xl:text-[14.5px] 2xl:leading-[22px]')}>
                <Icon size={14} className="mt-0.5 text-[#e8b45e] 2xl:mt-[3px] 2xl:size-4">
                  <circle cx="12" cy="12" r="9.5" />
                  <path d="M12 11v5.5M12 7.5v.01" />
                </Icon>
                <span>{a.disclaimer}</span>
              </p>
            </div>
          </form>
        </section>
      </div>
    </main>
  )
}

export default AiAssistant

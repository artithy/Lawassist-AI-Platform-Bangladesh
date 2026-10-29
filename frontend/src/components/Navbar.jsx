import { useEffect, useRef, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import LanguageSwitch from './LanguageSwitch'
import { useLanguage } from '../i18n/useLanguage'
import { useAuth } from '../auth/useAuth'

// Each link has a path (URL) and the NAME of its text in translations.js.
// The words themselves are not written here - they come from the dictionary.
// "Explore Laws" goes to the "Areas of Law" section on the Home page ("/" + "#areas").
// "section" is the id of that link's section on the Home page (used by the scroll highlight below);
// the link itself always opens its own page (About -> /about).
const navLinks = [
  { key: 'home', path: '/', textKey: 'home', section: 'home' },
  { key: 'exploreLaws', path: '/', hash: '#areas', textKey: 'exploreLaws', section: 'areas' },
  { key: 'aiAssistant', path: '/assistant', textKey: 'aiAssistant', section: 'assistant' },
  { key: 'findLawyer', path: '/lawyers', textKey: 'findLawyer', section: 'lawyers' },
  { key: 'about', path: '/about', textKey: 'about', section: 'about' },
]

// The Home page sections the navbar follows while you scroll, in the order they appear on the page
const homeSections = navLinks
  .filter(function (link) {
    return link.section
  })
  .map(function (link) {
    return link.section
  })

// Which ONE navbar link is active?  (A single key, so two links can never be active together.)
//   Home page ("/")  -> the section on screen (see useHomeSection below)
//   other pages      -> the link of that page; the pages inside it count too (/laws, /lawyers/tanvir)
//   login, register, unknown address -> none
function getActiveKey(pathname, homeSection) {
  if (pathname === '/') {
    const link = navLinks.find(function (item) {
      return item.section === homeSection
    })
    return link ? link.key : 'home'
  }
  if (pathname === '/laws' || pathname.startsWith('/laws/')) return 'exploreLaws'
  const link = navLinks.find(function (item) {
    return item.path !== '/' && (pathname === item.path || pathname.startsWith(item.path + '/'))
  })
  return link ? link.key : null
}

// Scroll highlight for the Home page: which section is on screen right now?
// An IntersectionObserver watches a thin band a third of the way down the window; the section
// crossing that band is the current one. Between sections (and over the closing banner) the last one stays.
function useHomeSection(location) {
  const hashId = location.hash.slice(1)
  const startId = homeSections.includes(hashId) ? hashId : 'home'

  // Every new visit / click starts from the address (/#areas -> Explore Laws), then the scroll takes over.
  const [state, setState] = useState({ key: location.key, section: startId })
  let section = state.section
  if (state.key !== location.key) {
    setState({ key: location.key, section: startId })
    section = startId
  }

  const onHome = location.pathname === '/'
  const lockUntil = useRef(0)
  const keyRef = useRef(location.key)
  useEffect(
    function () {
      keyRef.current = location.key
      // A click on a link scrolls smoothly past other sections. Ignore the scroll for a moment so the
      // highlight does not flicker through them; the final position is applied when the scrolling is over.
      lockUntil.current = location.hash ? Date.now() + 1000 : 0
    },
    [location.key, location.hash],
  )

  useEffect(
    function () {
      if (!onHome || typeof IntersectionObserver === 'undefined') return undefined
      const inBand = new Set()
      let timer = 0

      function apply() {
        // the last section (in page order) that touches the band
        const current = homeSections.filter(function (id) {
          return inBand.has(id)
        })
        if (current.length === 0) return
        const id = current[current.length - 1]
        setState(function (old) {
          return old.section === id && old.key === keyRef.current ? old : { key: keyRef.current, section: id }
        })
      }

      const observer = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) inBand.add(entry.target.id)
            else inBand.delete(entry.target.id)
          })
          const wait = lockUntil.current - Date.now()
          if (wait > 0) {
            clearTimeout(timer)
            timer = setTimeout(apply, wait + 50)
          } else {
            apply()
          }
        },
        { rootMargin: '-34% 0px -64% 0px' },
      )
      homeSections.forEach(function (id) {
        const element = document.getElementById(id)
        if (element) observer.observe(element)
      })
      return function () {
        clearTimeout(timer)
        observer.disconnect()
      }
    },
    [onHome],
  )

  return section
}

// The current page gets an ivory text colour and a small gold line underneath.
function getNavLinkClass(isActive, dark) {
  // dark = the AI Legal Assistant page: a slightly larger text on wide screens (see Navbar below)
  const base =
    'relative block whitespace-nowrap py-3 text-[15px] font-medium transition-colors lg:py-2 lg:text-[13px] ' +
    (dark ? 'lg:text-[13.5px] 2xl:text-[16px] ' : '2xl:text-[14.5px] ')

  if (isActive) {
    return (
      base +
      'text-ivory lg:after:absolute lg:after:inset-x-0 lg:after:-bottom-1 lg:after:h-0.5 lg:after:rounded-full lg:after:bg-gold ' + (dark ? '2xl:after:-bottom-[9px]' : '')
    )
  }
  return base + 'text-silver hover:text-ivory'
}

function Navbar() {
  const { language, t } = useLanguage()
  const location = useLocation()
  const navigate = useNavigate()
  const { session, signOut } = useAuth()
  const homeSection = useHomeSection(location)
  const activeKey = getActiveKey(location.pathname, homeSection)

  // The AI Legal Assistant page has its own dark look (charcoal/black with gold). Every other page keeps the normal bar.
  const dark = location.pathname === '/assistant'

  // English capitals look good with wide letter spacing. Bangla letters join each other,
  // so spacing would break them apart - for Bangla we use no spacing and a slightly bigger size.
  let taglineClass = 'mt-1 text-[8px] tracking-[0.2em] text-silver'
  if (dark) {
    taglineClass = 'mt-1 text-[8px] tracking-[0.2em] text-silver 2xl:mt-[7px] 2xl:text-[10px]'
  }
  if (language === 'bn') {
    taglineClass = 'mt-1 text-[10px] text-silver'
  }

  // Is the mobile menu open? (true or false)
  const [menuOpen, setMenuOpen] = useState(false)

  function toggleMenu() {
    setMenuOpen(!menuOpen)
  }

  function closeMenu() {
    setMenuOpen(false)
  }

  // Log out, then go to the login page
  async function handleLogout() {
    closeMenu()
    await signOut()
    navigate('/login', { replace: true, state: null })
  }

  // Mobile: a dropdown panel under the bar.  Desktop (lg:): a normal row.
  let menuClass =
    'absolute left-0 right-0 top-full flex-col gap-5 border-b border-silver/15 px-5 pb-6 pt-3 ' + (dark ? 'bg-[#10161a] ' : 'bg-charcoal ') +
    'lg:static lg:ml-4 xl:ml-8 lg:flex lg:flex-1 lg:flex-row lg:items-center lg:justify-between lg:border-b-0 lg:bg-transparent lg:p-0'

  if (menuOpen) {
    menuClass = menuClass + ' flex'
  } else {
    menuClass = menuClass + ' hidden'
  }

  return (
    // The bar is see-through, so the Supreme Court picture shows behind it on the home page.
    <header className={'sticky top-0 z-50 border-b ' + (dark ? 'border-[#31373c] bg-[#10161a]' : 'border-silver/5 bg-charcoal/40 backdrop-blur-md')}>
      <div className={'mx-auto flex h-16 max-w-[1920px] items-center px-5 lg:px-5 xl:px-[5.5%] ' + (dark ? 'lg:h-[73px] 2xl:h-[88px] 2xl:pt-1.5' : '')}>
        {/* ----- Brand: courthouse icon + name + small subtitle ----- */}
        <Link to="/" className="flex shrink-0 items-center gap-2.5" onClick={closeMenu}>
          <svg
            width="30"
            height="30"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={dark ? 'text-[#e3b261]' : 'text-gold-light'}
            aria-hidden="true"
          >
            <path d="M12 3 3.5 7.5h17L12 3Z" />
            <path d="M3.5 21h17" />
            <path d="M5.5 18.5h13" />
            <path d="M6.5 10.5v6M10 10.5v6M14 10.5v6M17.5 10.5v6" />
          </svg>
          <span className="flex flex-col leading-none">
            <span className="font-display text-[22px] font-medium text-ivory 2xl:text-[26px]">LawAssist AI</span>
            <span className={taglineClass}>{t.nav.tagline}</span>
          </span>
        </Link>

        {/* ----- Hamburger button (hidden on desktop with "lg:hidden") ----- */}
        <button
          type="button"
          className="ml-auto cursor-pointer rounded-lg border border-silver/30 bg-deep/50 px-3 py-1.5 text-xl text-gold-light lg:hidden"
          onClick={toggleMenu}
          aria-label={t.nav.menu}
          aria-expanded={menuOpen}
        >
          {menuOpen ? '✕' : '☰'}
        </button>

        {/* ----- Links + language + buttons ----- */}
        <div className={menuClass}>
          <ul className={'flex flex-col lg:flex-1 lg:flex-row lg:justify-center lg:gap-4 xl:gap-9 ' + (dark ? '2xl:gap-[44px]' : '')}>
            {navLinks.map(function (link) {
              const isActive = link.key === activeKey
              return (
                <li key={link.key}>
                  <Link
                    to={link.path + (link.hash || '')}
                    className={getNavLinkClass(isActive, dark)}
                    aria-current={isActive ? (location.pathname === '/' ? 'location' : 'page') : undefined}
                    onClick={closeMenu}
                  >
                    {t.nav[link.textKey]}
                  </Link>
                </li>
              )
            })}
          </ul>

          <div className="flex flex-col items-stretch gap-4 lg:flex-row lg:items-center lg:gap-2.5 xl:gap-5">
            <div className="flex justify-center">
              <LanguageSwitch large={dark} />
            </div>

            {session ? (
              <button
                type="button"
                onClick={handleLogout}
                className={'cursor-pointer whitespace-nowrap rounded-full border border-gold/60 px-4 py-2.5 text-center text-[13px] font-semibold text-ivory transition-colors hover:bg-gold hover:text-deep xl:px-5 ' + (dark ? 'lg:py-3 lg:text-[13.5px] 2xl:px-[26px] 2xl:py-[13px] 2xl:text-[16px] 2xl:font-medium' : '2xl:px-6 2xl:py-3 2xl:text-[14px]')}
              >
                {t.nav.logout}
              </button>
            ) : (
              <>
                <Link
                  to="/login"
                  className="p-1 text-center text-[13px] font-medium text-ivory transition-colors hover:text-gold-light"
                  onClick={closeMenu}
                >
                  {t.nav.login}
                </Link>

                <Link
                  to="/register"
                  className="whitespace-nowrap rounded-full bg-gold px-4 py-2.5 xl:px-5 2xl:px-6 2xl:py-3 2xl:text-[14px] text-center text-[13px] font-semibold text-deep shadow-lg shadow-gold/30 transition hover:-translate-y-px hover:brightness-105"
                  onClick={closeMenu}
                >
                  {t.nav.getStarted}
                </Link>
              </>
            )}
          </div>
        </div>
      </div>
    </header>
  )
}

export default Navbar

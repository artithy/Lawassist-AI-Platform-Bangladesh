import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import LanguageSwitch from './LanguageSwitch'
import { useLanguage } from '../i18n/useLanguage'

// Each link has a path (URL) and the NAME of its text in translations.js.
// The words themselves are not written here - they come from the dictionary.
// "Explore Laws" goes to the "Areas of Law" section on the Home page ("/" + "#areas").
// The Home page then scrolls down to that section by itself (see Home.jsx).
const navLinks = [
  { path: '/', textKey: 'home' },
  { path: '/', hash: '#areas', textKey: 'exploreLaws' },
  { path: '/assistant', textKey: 'aiAssistant' },
  { path: '/lawyers', textKey: 'findLawyer' },
  { path: '/about', textKey: 'about' },
]

// Is this link the current page? ("Home" and "Explore Laws" share the "/" page,
// so we also look at the #areas part of the address to tell them apart.)
function isLinkActive(link, location) {
  if (link.path === '/') {
    const onAreas = location.hash === '#areas'
    return location.pathname === '/' && (link.hash ? onAreas : !onAreas)
  }
  return location.pathname === link.path || location.pathname.startsWith(link.path + '/')
}

// The current page gets an ivory text colour and a small gold line underneath.
function getNavLinkClass(isActive) {
  const base =
    'relative block whitespace-nowrap py-3 text-[15px] font-medium transition-colors lg:py-2 lg:text-[13px] 2xl:text-[14.5px] '

  if (isActive) {
    return (
      base +
      'text-ivory lg:after:absolute lg:after:inset-x-0 lg:after:-bottom-1 lg:after:h-0.5 lg:after:rounded-full lg:after:bg-gold'
    )
  }
  return base + 'text-silver hover:text-ivory'
}

function Navbar() {
  const { language, t } = useLanguage()
  const location = useLocation()

  // English capitals look good with wide letter spacing. Bangla letters join each other,
  // so spacing would break them apart - for Bangla we use no spacing and a slightly bigger size.
  let taglineClass = 'mt-1 text-[8px] tracking-[0.2em] text-silver'
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

  // Mobile: a dropdown panel under the bar.  Desktop (lg:): a normal row.
  let menuClass =
    'absolute left-0 right-0 top-full flex-col gap-5 border-b border-silver/15 bg-charcoal px-5 pb-6 pt-3 ' +
    'lg:static lg:ml-4 xl:ml-8 lg:flex lg:flex-1 lg:flex-row lg:items-center lg:justify-between lg:border-b-0 lg:bg-transparent lg:p-0'

  if (menuOpen) {
    menuClass = menuClass + ' flex'
  } else {
    menuClass = menuClass + ' hidden'
  }

  return (
    // The bar is see-through, so the Supreme Court picture shows behind it on the home page.
    <header className="sticky top-0 z-50 border-b border-silver/5 bg-charcoal/40 backdrop-blur-md">
      <div className="mx-auto flex h-16 max-w-[1920px] items-center px-5 lg:px-5 xl:px-[5.5%]">
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
            className="text-gold-light"
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
          <ul className="flex flex-col lg:flex-1 lg:flex-row lg:justify-center lg:gap-4 xl:gap-9">
            {navLinks.map(function (link) {
              const isActive = isLinkActive(link, location)
              return (
                <li key={link.path + (link.hash || '')}>
                  <Link
                    to={link.path + (link.hash || '')}
                    className={getNavLinkClass(isActive)}
                    aria-current={isActive ? 'page' : undefined}
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
              <LanguageSwitch />
            </div>

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
          </div>
        </div>
      </div>
    </header>
  )
}

export default Navbar

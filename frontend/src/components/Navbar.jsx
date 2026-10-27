import { useState } from 'react'
import { Link, NavLink } from 'react-router-dom'

// All the navigation links live in ONE list.
// Each link has a path (URL) and a label in both languages.
const navLinks = [
  { path: '/', english: 'Home', bangla: 'হোম' },
  { path: '/laws', english: 'Explore Laws', bangla: 'আইন অনুসন্ধান' },
  { path: '/assistant', english: 'AI Legal Assistant', bangla: 'এআই আইনি সহকারী' },
  { path: '/lawyers', english: 'Find a Lawyer', bangla: 'আইনজীবী খুঁজুন' },
  { path: '/about', english: 'About', bangla: 'আমাদের সম্পর্কে' },
]

// Tailwind classes are just text, so we can store them in variables.
// NOTE: "lg:" means "only on screens 1024px wide or more" (desktop).
// Without "lg:", the class applies to every screen size (mobile first).
// "xl:" means 1280px or more. We use it to add a little more spacing on big screens.

// NavLink calls this function and tells us if the link is the current page.
function getNavLinkClass({ isActive }) {
  const base =
    'block whitespace-nowrap py-3 text-base font-medium transition-colors lg:py-0 lg:text-sm'

  if (isActive) {
    return base + ' text-gold-light'
  }
  return base + ' text-muted hover:text-cream'
}

function getLanguageButtonClass(isSelected) {
  const base =
    'cursor-pointer whitespace-nowrap rounded-full px-3 py-2 text-[0.9rem] xl:px-4 font-medium transition-colors'

  if (isSelected) {
    return base + ' bg-gold font-semibold text-ink'
  }
  return base + ' text-muted hover:text-cream'
}

function Navbar() {
  // State 1: which language is selected? ('english' or 'bangla')
  const [language, setLanguage] = useState('english')

  // State 2: is the mobile menu open? (true or false)
  const [menuOpen, setMenuOpen] = useState(false)

  function toggleMenu() {
    setMenuOpen(!menuOpen)
  }

  function closeMenu() {
    setMenuOpen(false)
  }

  // Give this function an English word and a Bangla word.
  // It returns the one that matches the selected language.
  function getLabel(english, bangla) {
    if (language === 'english') {
      return english
    }
    return bangla
  }

  // Classes for the menu panel.
  // Mobile: a dropdown panel under the navbar.
  // Desktop (lg:): a normal row inside the navbar.
  let menuClass =
    'absolute left-0 right-0 top-full flex-col gap-5 border-b border-gold/20 bg-navy px-5 pb-6 pt-3 ' +
    'lg:static lg:ml-6 xl:ml-10 lg:flex lg:flex-1 lg:flex-row lg:items-center lg:justify-between lg:border-b-0 lg:bg-transparent lg:p-0'

  // On mobile, show the menu only when it is open.
  if (menuOpen) {
    menuClass = menuClass + ' flex'
  } else {
    menuClass = menuClass + ' hidden'
  }

  return (
    <header className="sticky top-0 z-50 border-b border-gold/20 bg-navy">
      <div className="mx-auto flex h-[75px] max-w-[1440px] items-center px-5 lg:px-6 xl:px-10">
        {/* ----- Brand (logo + name). Clicking it goes Home ----- */}
        <Link to="/" className="flex items-center gap-3" onClick={closeMenu}>
          <span className="flex size-[34px] items-center justify-center rounded-full border border-gold text-gold">
            {/* Simple justice-scale icon drawn with SVG */}
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.6"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <path d="M12 3v18" />
              <path d="M7 21h10" />
              <path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2" />
              <path d="m2 16 3-8 3 8c-.9.65-1.9 1-3 1s-2.1-.35-3-1Z" />
              <path d="m16 16 3-8 3 8c-.9.65-1.9 1-3 1s-2.1-.35-3-1Z" />
            </svg>
          </span>
          <span className="whitespace-nowrap font-serif text-2xl font-medium text-cream">
            LawAssist <span className="text-gold">AI</span>
          </span>
        </Link>

        {/* ----- Hamburger button (hidden on desktop with "lg:hidden") ----- */}
        <button
          className="ml-auto cursor-pointer rounded-lg border border-gold/35 px-3 py-1.5 text-xl text-gold lg:hidden"
          onClick={toggleMenu}
          aria-label="Toggle menu"
          aria-expanded={menuOpen}
        >
          {menuOpen ? '✕' : '☰'}
        </button>

        {/* ----- Links + buttons. On mobile this drops down when open ----- */}
        <div className={menuClass}>
          <ul className="flex flex-col lg:flex-1 lg:flex-row lg:justify-center lg:gap-3 xl:gap-6">
            {navLinks.map(function (link) {
              return (
                <li key={link.path}>
                  {/* NavLink knows if this link is the current page (isActive) */}
                  <NavLink
                    to={link.path}
                    end={link.path === '/'}
                    className={getNavLinkClass}
                    onClick={closeMenu}
                  >
                    {getLabel(link.english, link.bangla)}
                  </NavLink>
                </li>
              )
            })}
          </ul>

          <div className="flex flex-col items-stretch gap-4 lg:flex-row lg:items-center lg:gap-3 xl:gap-6">
            {/* Language toggle */}
            <div className="flex items-center justify-center rounded-full border border-gold/35 p-1">
              <button
                className={getLanguageButtonClass(language === 'bangla')}
                onClick={function () {
                  setLanguage('bangla')
                }}
              >
                বাংলা
              </button>
              <button
                className={getLanguageButtonClass(language === 'english')}
                onClick={function () {
                  setLanguage('english')
                }}
              >
                English
              </button>
            </div>

            <Link
              to="/login"
              className="p-1 text-center text-sm font-medium text-cream transition-colors hover:text-gold-light"
              onClick={closeMenu}
            >
              {getLabel('Login', 'লগইন')}
            </Link>

            <Link
              to="/register"
              className="whitespace-nowrap rounded-full bg-linear-to-br from-gold-light to-gold px-5 py-3 text-center text-sm font-semibold text-ink shadow-lg shadow-gold/25 transition hover:-translate-y-px hover:shadow-xl hover:shadow-gold/40"
              onClick={closeMenu}
            >
              {getLabel('Get Started', 'শুরু করুন')}
            </Link>
          </div>
        </div>
      </div>
    </header>
  )
}

export default Navbar

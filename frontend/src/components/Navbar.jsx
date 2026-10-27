import { useState } from 'react'
import { Link, NavLink } from 'react-router-dom'
import './Navbar.css'

// All the navigation links live in ONE list.
// Each link has a path (URL) and a label in both languages.
const navLinks = [
  { path: '/', english: 'Home', bangla: 'হোম' },
  { path: '/laws', english: 'Explore Laws', bangla: 'আইন অনুসন্ধান' },
  { path: '/assistant', english: 'AI Legal Assistant', bangla: 'এআই আইনি সহকারী' },
  { path: '/lawyers', english: 'Find a Lawyer', bangla: 'আইনজীবী খুঁজুন' },
  { path: '/about', english: 'About', bangla: 'আমাদের সম্পর্কে' },
]

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

  // The menu has two class names when open: "navbar-menu open"
  let menuClass = 'navbar-menu'
  if (menuOpen) {
    menuClass = 'navbar-menu open'
  }

  return (
    <header className="navbar">
      <div className="navbar-inner">
        {/* ----- Brand (logo + name). Clicking it goes Home ----- */}
        <Link to="/" className="brand" onClick={closeMenu}>
          <span className="brand-icon">
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
          <span className="brand-name">
            LawAssist <span className="brand-ai">AI</span>
          </span>
        </Link>

        {/* ----- Hamburger button (only visible on mobile) ----- */}
        <button
          className="menu-button"
          onClick={toggleMenu}
          aria-label="Toggle menu"
          aria-expanded={menuOpen}
        >
          {menuOpen ? '✕' : '☰'}
        </button>

        {/* ----- Links + buttons. On mobile this slides down when "open" ----- */}
        <div className={menuClass}>
          <ul className="navbar-links">
            {navLinks.map(function (link) {
              return (
                <li key={link.path}>
                  {/* NavLink automatically adds the class "active" to the link of the current page */}
                  <NavLink
                    to={link.path}
                    end={link.path === '/'}
                    className="nav-link"
                    onClick={closeMenu}
                  >
                    {getLabel(link.english, link.bangla)}
                  </NavLink>
                </li>
              )
            })}
          </ul>

          <div className="navbar-actions">
            {/* Language toggle */}
            <div className="language-toggle">
              <button
                className={language === 'bangla' ? 'lang-button active' : 'lang-button'}
                onClick={function () {
                  setLanguage('bangla')
                }}
              >
                বাংলা
              </button>
              <button
                className={language === 'english' ? 'lang-button active' : 'lang-button'}
                onClick={function () {
                  setLanguage('english')
                }}
              >
                English
              </button>
            </div>

            <Link to="/login" className="login-link" onClick={closeMenu}>
              {getLabel('Login', 'লগইন')}
            </Link>

            <Link to="/register" className="get-started-button" onClick={closeMenu}>
              {getLabel('Get Started', 'শুরু করুন')}
            </Link>
          </div>
        </div>
      </div>
    </header>
  )
}

export default Navbar

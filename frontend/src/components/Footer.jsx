import { Link } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'

// The footer, shown at the bottom of every page (it is placed in App.jsx).
// The link names come from translations.js (the same words as the Navbar).
// Every link points to a route that already exists.

// Link columns. "to" is the address, "key" is the name in t.nav.
const exploreLinks = [
  { to: '/', key: 'home' },
  { to: '/#areas', key: 'exploreLaws' }, // the Areas of Law section on the Home page (same as the Navbar)
  { to: '/assistant', key: 'aiAssistant' },
  { to: '/lawyers', key: 'findLawyer' },
]
const brandLinks = [
  { to: '/about', key: 'about' },
  { to: '/login', key: 'login' },
  { to: '/register', key: 'getStarted' },
]

// Shared look of a footer link: muted grey, turns warm gold on hover, moves 2px (not for reduced-motion users)
const linkBase =
  'inline-flex cursor-pointer items-center py-1 transition-[color,transform] duration-200 hover:text-gold-light focus-visible:text-gold-light focus-visible:outline-none motion-safe:hover:translate-x-0.5'

// A faint line drawing of a courthouse (pediment, columns, steps), used as a quiet background detail
function CourthouseOutline() {
  return (
    <svg viewBox="0 0 400 260" fill="none" stroke="currentColor" strokeWidth="1.2" strokeLinejoin="round" className="pointer-events-none absolute -bottom-4 left-[38%] hidden w-[330px] text-gold/[0.07] xl:block" aria-hidden="true">
      <path d="M20 90 200 12l180 78Z" />
      <path d="M20 90h360" />
      <path d="M36 98h328" />
      <path d="M52 106v104M104 106v104M156 106v104M208 106v104M260 106v104M312 106v104M348 106v104" />
      <path d="M40 210h320M28 222h344M16 234h368" />
      <circle cx="200" cy="62" r="10" />
    </svg>
  )
}

function Footer() {
  const { language, setLanguage, t } = useLanguage()
  const f = t.footer
  const isBn = language === 'bn'
  // Bangla letters are drawn smaller than English ones, so they get a bigger size
  const linkSize = isBn ? ' text-[16px]' : ' text-[14px]'
  const linkClass = linkBase + linkSize + ' text-mist' // an ordinary link

  // Column headings: wide letter spacing for English capitals, none for Bangla
  const headingClass = 'mb-3 font-semibold text-gold ' + (isBn ? 'text-[14px]' : 'text-[11px] tracking-[0.2em]')

  return (
    <footer className="relative overflow-hidden border-t border-silver/10 bg-[#0a0c0f] bg-[radial-gradient(ellipse_at_12%_0%,rgba(185,154,91,0.08),transparent_55%)]">
      <CourthouseOutline />

      <div className="relative mx-auto max-w-[1920px] px-5 pb-8 pt-12 sm:px-8 lg:px-[5.5%] lg:pt-14">
        <div className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_auto] lg:gap-16">
          {/* ================= Brand ================= */}
          <div className="max-w-[26rem]">
            <Link to="/" className="inline-flex items-center gap-3.5">
              {/* Courthouse icon in a square with four gold corner marks */}
              <span className="relative flex size-12 items-center justify-center text-gold-light">
                <span aria-hidden="true" className="absolute left-0 top-0 size-2.5 border-l border-t border-gold/70" />
                <span aria-hidden="true" className="absolute right-0 top-0 size-2.5 border-r border-t border-gold/70" />
                <span aria-hidden="true" className="absolute bottom-0 left-0 size-2.5 border-b border-l border-gold/70" />
                <span aria-hidden="true" className="absolute bottom-0 right-0 size-2.5 border-b border-r border-gold/70" />
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M12 3 3.5 7.5h17L12 3Z" />
                  <path d="M3.5 21h17" />
                  <path d="M5.5 18.5h13" />
                  <path d="M6.5 10.5v6M10 10.5v6M14 10.5v6M17.5 10.5v6" />
                </svg>
              </span>
              <span className="font-display text-[26px] font-medium leading-none text-ivory">LawAssist AI</span>
            </Link>

            {/* Small gold divider: line - diamond - line */}
            <div aria-hidden="true" className="mt-5 flex items-center gap-2.5">
              <span className="h-px w-10 bg-linear-to-r from-gold/70 to-gold/10" />
              <span className="block size-1.5 rotate-45 bg-gold" />
              <span className="h-px w-5 bg-gold/30" />
            </div>

            <p className={'mt-4 text-mist ' + (isBn ? 'text-[15px] leading-[1.8]' : 'text-[13.5px] leading-[1.7]')}>{f.description}</p>

            {/* Understated language note */}
            <p className="mt-4 inline-flex items-center gap-2 rounded-full border border-silver/15 px-3 py-1 text-[12px] text-silver">
              <span aria-hidden="true" className="size-1.5 rounded-full bg-gold/80" />
              {f.languages}
            </p>
          </div>

          {/* ================= Link columns ================= */}
          <nav aria-label="Footer" className="grid grid-cols-2 gap-x-10 gap-y-8 sm:grid-cols-3 sm:gap-x-12 xl:gap-x-20">
            <div>
              <h2 className={headingClass}>{f.explore}</h2>
              <ul>
                {exploreLinks.map(function (link) {
                  return (
                    <li key={link.key}>
                      <Link to={link.to} className={linkClass}>
                        {t.nav[link.key]}
                      </Link>
                    </li>
                  )
                })}
              </ul>
            </div>

            <div>
              <h2 className={headingClass}>{f.brand}</h2>
              <ul>
                {brandLinks.map(function (link) {
                  return (
                    <li key={link.key}>
                      <Link to={link.to} className={linkClass}>
                        {t.nav[link.key]}
                      </Link>
                    </li>
                  )
                })}
              </ul>
            </div>

            {/* The language buttons change the language of the whole site, like the switch in the Navbar */}
            <div className="col-span-2 sm:col-span-1">
              <h2 className={headingClass}>{f.language}</h2>
              <ul className="flex gap-6 sm:block">
                {[
                  { code: 'bn', label: 'বাংলা' },
                  { code: 'en', label: 'English' },
                ].map(function (item) {
                  const isOn = language === item.code
                  return (
                    <li key={item.code}>
                      <button
                        type="button"
                        aria-pressed={isOn}
                        onClick={function () {
                          setLanguage(item.code)
                        }}
                        className={linkBase + linkSize + ' gap-2 ' + (isOn ? 'text-ivory' : 'text-mist')}
                      >
                        <span aria-hidden="true" className={'size-1.5 rounded-full transition-colors ' + (isOn ? 'bg-gold' : 'bg-silver/25')} />
                        {item.label}
                      </button>
                    </li>
                  )
                })}
              </ul>
            </div>
          </nav>
        </div>

        {/* ================= Bottom bar ================= */}
        <div className="mt-10 flex flex-col gap-2.5 border-t border-silver/10 pt-5 text-[12px] leading-relaxed text-mist/90 sm:flex-row sm:items-center sm:justify-between sm:gap-8 lg:mt-12" style={isBn ? { fontSize: '13.5px' } : undefined}>
          <p>{f.copyright}</p>
          <p className="sm:max-w-[34rem] sm:text-right">{t.question.disclaimer}</p>
        </div>
      </div>
    </footer>
  )
}

export default Footer

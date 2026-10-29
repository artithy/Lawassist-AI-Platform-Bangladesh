import { useEffect, useRef, useState } from 'react'

// Shared by the home-page lawyer section and the /lawyers directory page.
// ---------- Small gold line icons (plain SVG) ----------
export function Icon({ children, size = 18, className = '' }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className={'shrink-0 ' + className} aria-hidden="true">
      {children}
    </svg>
  )
}
export const iconInstitution = (
  <>
    <path d="M3 21h18" />
    <path d="M5 21V10M9.5 21V10M14.5 21V10M19 21V10" />
    <path d="M12 3 3 8h18Z" />
  </>
)
export const iconLanguage = (
  <>
    <path d="m5 8 6 6" />
    <path d="m4 14 6-6 2-3" />
    <path d="M2 5h12" />
    <path d="M7 2h1" />
    <path d="m22 22-5-10-5 10" />
    <path d="M14 18h6" />
  </>
)
export const iconGavel = (
  <>
    <path d="m14 13-8.4 8.4a2.1 2.1 0 0 1-3-3L11 10" />
    <path d="m16 16 6-6" />
    <path d="m8 8 6-6" />
    <path d="m9 7 8 8" />
    <path d="m21 11-8-8" />
  </>
)
export const iconCalendar = (
  <>
    <rect x="3" y="4" width="18" height="18" rx="2" />
    <path d="M8 2v4M16 2v4M3 10h18" />
  </>
)
export const iconCard = (
  <>
    <rect x="2" y="5" width="20" height="14" rx="2" />
    <path d="M2 10h20" />
  </>
)
export const iconGlobe = (
  <>
    <circle cx="12" cy="12" r="9.5" />
    <path d="M2.5 12h19" />
    <path d="M12 2.5a14 14 0 0 1 0 19 14 14 0 0 1 0-19Z" />
  </>
)
export const iconVideo = (
  <>
    <path d="m16 13 5.2 3.5a.5.5 0 0 0 .8-.4V7.9a.5.5 0 0 0-.8-.4L16 11" />
    <rect x="2" y="6" width="14" height="12" rx="2" />
  </>
)
export const iconShield = (
  <>
    <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
    <path d="m9 12 2 2 4-4" />
  </>
)
export const iconPin = (
  <>
    <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0Z" />
    <circle cx="12" cy="10" r="3" />
  </>
)
export const iconPeople = (
  <>
    <circle cx="9" cy="7.5" r="3.3" />
    <path d="M2.8 20c0-3.4 2.8-5.9 6.2-5.9s6.2 2.5 6.2 5.9" />
    <path d="M16.4 4.6a3.1 3.1 0 0 1 0 5.8" />
    <path d="M18.3 14.5c2 .7 3.4 2.6 3.4 5.2" />
  </>
)
export const trustIcons = [iconShield, iconPin, iconPeople]

// The five filters: the key in "filters", its icon, and how a profile is tested against the chosen value.
export const filterDefs = [
  { key: 'area', icon: iconInstitution, test: (p, v) => p.area === v },
  { key: 'language', icon: iconLanguage, test: (p, v) => p.languages.includes(v) },
  { key: 'court', icon: iconGavel, test: (p, v) => p.court === v },
  { key: 'availability', icon: iconCalendar, test: (p, v) => p.formats.includes(v) },
  { key: 'fee', icon: iconCard, test: (p, v) => p.fee <= Number(v) }, // "up to" a maximum fee
]

// ---------- One filter chip. Click it to open a small list of choices. ----------
export function FilterChip({ icon, label, anyLabel, options, value, onChange, big }) {
  const [open, setOpen] = useState(false)
  const boxRef = useRef(null)

  // Close the list when the visitor clicks somewhere else or presses Escape
  useEffect(
    function () {
      if (!open) return undefined
      function onClickOutside(event) {
        if (boxRef.current && !boxRef.current.contains(event.target)) setOpen(false)
      }
      function onKey(event) {
        if (event.key === 'Escape') setOpen(false)
      }
      document.addEventListener('mousedown', onClickOutside)
      document.addEventListener('keydown', onKey)
      return function () {
        document.removeEventListener('mousedown', onClickOutside)
        document.removeEventListener('keydown', onKey)
      }
    },
    [open],
  )

  const selected = options.find(function (o) {
    return o.value === value
  })

  function choose(next) {
    onChange(next)
    setOpen(false)
  }

  return (
    <div ref={boxRef} className="relative">
      <button
        type="button"
        onClick={function () {
          setOpen(!open)
        }}
        aria-haspopup="listbox"
        aria-expanded={open}
        className={
          'inline-flex cursor-pointer items-center gap-2.5 rounded-full border px-4 py-2.5 text-ivory transition-colors ' + (big ? 'text-[16px] ' : 'text-[14px] ') + ' focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold 2xl:text-[15px] ' +
          (selected ? 'border-gold bg-gold/10' : 'border-silver/25 bg-[#14171c]/70 hover:border-gold/70')
        }
      >
        <Icon className="text-gold-light" size={20}>
          {icon}
        </Icon>
        <span>{selected ? selected.label : label}</span>
      </button>

      {open && (
        <ul role="listbox" aria-label={label} className="absolute left-0 top-full z-30 mt-2 min-w-[230px] rounded-xl border border-silver/20 bg-[#161a20] p-1.5 shadow-2xl shadow-black/60">
          {[{ value: '', label: anyLabel }].concat(options).map(function (o) {
            const isOn = o.value === value
            return (
              <li key={o.value || 'any'} role="option" aria-selected={isOn}>
                <button
                  type="button"
                  onClick={function () {
                    choose(o.value)
                  }}
                  className={'flex w-full cursor-pointer items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-[14px] transition-colors hover:bg-graphite/70 focus-visible:bg-graphite/70 focus-visible:outline-none ' + (isOn ? 'text-gold-light' : 'text-ivory')}
                >
                  <span>{o.label}</span>
                  {isOn && <span aria-hidden="true">✓</span>}
                </button>
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}


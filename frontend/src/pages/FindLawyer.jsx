import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import { profiles } from '../data/demoAdvocates'
import { Icon, FilterChip, filterDefs, iconInstitution, iconGlobe, iconVideo, iconCard, iconShield } from '../components/lawyerUi'

const noFilters = { area: '', language: '', court: '', availability: '', fee: '' }

// A small arrow used on the buttons
const arrow = (
  <>
    <path d="M5 12h14" />
    <path d="m13 6 6 6-6 6" />
  </>
)

// The /lawyers page: a searchable, filterable directory of DEMO profiles.
// /lawyers?advocate=tanvir shows one profile in full (the "View Profile" buttons link there).
function FindLawyer() {
  const { language, t } = useLanguage()
  const h = t.lawyers // names of the filters, the three demo profiles, shared with the home page section
  const p = t.lawyersPage // text that only this page uses
  const isBn = language === 'bn'
  const f = h.filters

  const location = useLocation()
  const advocateId = new URLSearchParams(location.search).get('advocate')

  const [search, setSearch] = useState('')
  const [filters, setFilters] = useState(noFilters)

  // Opening a profile (or coming back) should start at the top of the page
  useEffect(
    function () {
      window.scrollTo(0, 0)
    },
    [advocateId],
  )

  // Words about a profile, in the current language
  function textOf(profile) {
    return h.profiles.find(function (item) {
      return item.id === profile.id
    })
  }
  function formatsOf(profile) {
    return profile.formats.map(function (value) { return f.availability.options[value] }).join(', ')
  }
  function languagesOf(profile) {
    return profile.languages.map(function (value) { return f.language.options[value] }).join(', ')
  }

  const words = search.trim().toLowerCase()
  const hasFilters = words !== '' || Object.values(filters).some(function (v) { return v !== '' })

  // A profile is shown when it matches every chosen filter AND the search words
  const shown = profiles.filter(function (profile) {
    const passesFilters = filterDefs.every(function (def) {
      return filters[def.key] === '' || def.test(profile, filters[def.key])
    })
    if (!passesFilters) return false
    if (words === '') return true
    const haystack = [textOf(profile).name, f.area.options[profile.area], f.court.options[profile.court], languagesOf(profile)].join(' ').toLowerCase()
    return words.split(/\s+/).every(function (word) { return haystack.includes(word) })
  })

  function clearAll() {
    setSearch('')
    setFilters(noFilters)
  }

  const eyebrow = (
    <div className={'flex items-center gap-4 font-medium text-gold ' + (isBn ? 'text-[15px]' : 'text-[12px] tracking-[0.3em] sm:text-[13px]')}>
      <span className="h-px w-8 bg-gold/70 sm:w-[70px]" />
      <span>{p.eyebrow}</span>
    </div>
  )
  const demoNotice = (
    <div role="note" className="mt-6 flex items-start gap-3 rounded-2xl border border-gold/35 bg-gold/[0.07] px-4 py-3.5">
      <Icon size={20} className="mt-0.5 text-gold-light">{iconShield}</Icon>
      <div>
        <p className={'font-medium text-ivory ' + (isBn ? 'text-[17px]' : 'text-[15px]')}>{p.demoTitle}</p>
        <p className={'mt-0.5 text-mist ' + (isBn ? 'text-[16px] leading-[1.8]' : 'text-[14px] leading-[1.6]')}>{p.demoText}</p>
      </div>
    </div>
  )
  const demoBadge = (
    <span className={'inline-flex items-center rounded-full border border-gold/50 bg-gold/10 px-2.5 py-0.5 font-medium text-gold-light ' + (isBn ? 'text-[13px]' : 'text-[11px]')}>{h.demoLabel}</span>
  )
  const backLink = (
    <Link to="/lawyers" className={'inline-flex items-center gap-2 text-gold-light underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold ' + (isBn ? 'text-[16px]' : 'text-[14px]')}>
      <Icon size={16}>
        <path d="M19 12H5" />
        <path d="m11 6-6 6 6 6" />
      </Icon>
      {p.backToAll}
    </Link>
  )

  // ======================= One profile =======================
  if (advocateId) {
    const profile = profiles.find(function (item) { return item.id === advocateId })

    if (!profile) {
      return (
        <main className="bg-[#0e1115]">
          <div className="mx-auto max-w-[760px] px-5 py-20 text-center sm:px-8">
            <h1 className="font-display text-[clamp(1.9rem,4vw,2.8rem)] font-medium text-ivory">{p.notFoundTitle}</h1>
            <p className={'mt-3 text-mist ' + (isBn ? 'text-[17px]' : 'text-[16px]')}>{p.notFoundText}</p>
            <div className="mt-6">{backLink}</div>
          </div>
        </main>
      )
    }

    const text = textOf(profile)
    const rows = [
      { icon: iconInstitution, label: p.area, value: f.area.options[profile.area] },
      { icon: iconInstitution, label: p.court, value: f.court.options[profile.court] },
      { icon: iconGlobe, label: p.languages, value: languagesOf(profile) },
      { icon: iconVideo, label: p.format, value: formatsOf(profile) },
      { icon: iconCard, label: p.fee, value: text.fee + ' · ' + h.perConsultation },
    ]

    return (
      <main className="bg-[#0e1115] bg-[radial-gradient(ellipse_at_50%_0%,rgba(185,154,91,0.07),transparent_60%)]">
        <div className="mx-auto max-w-[1100px] px-5 pb-16 pt-8 sm:px-8 sm:pt-10">
          {backLink}
          <article aria-label={p.detailsLabel} className="mt-6 grid gap-8 rounded-[22px] border border-silver/15 bg-[#12151a]/95 p-5 shadow-[0_30px_60px_-30px_rgba(0,0,0,0.9)] sm:p-8 md:grid-cols-[minmax(0,300px)_minmax(0,1fr)]">
            <img src={profile.photo} alt="" draggable="false" className="aspect-[4/5] w-full max-w-[300px] rounded-2xl border border-silver/15 object-cover" />

            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                <h1 className={'font-display font-medium text-ivory ' + (isBn ? 'text-[clamp(1.8rem,3.4vw,2.6rem)] leading-[1.35]' : 'text-[clamp(2rem,3.6vw,3rem)] leading-[1.1]')}>{text.name}</h1>
                {demoBadge}
              </div>
              <p className={'mt-2 text-gold-light ' + (isBn ? 'text-[20px]' : 'text-[18px]')}>{f.area.options[profile.area]}</p>

              <dl className="mt-6 divide-y divide-silver/10 border-y border-silver/10">
                {rows.map(function (row) {
                  return (
                    <div key={row.label} className="grid gap-1 py-3 sm:grid-cols-[210px_minmax(0,1fr)] sm:gap-4">
                      <dt className={'flex items-center gap-2.5 text-mist ' + (isBn ? 'text-[16px]' : 'text-[14px]')}>
                        <Icon size={17} className="text-silver">{row.icon}</Icon>
                        {row.label}
                      </dt>
                      <dd className={'text-ivory ' + (isBn ? 'text-[17px]' : 'text-[15px]')}>{row.value}</dd>
                    </div>
                  )
                })}
              </dl>

              <div className="mt-6 rounded-2xl border border-dashed border-silver/25 bg-[#0e1115]/70 px-4 py-3.5">
                <p className={'font-medium text-ivory ' + (isBn ? 'text-[16px]' : 'text-[14px]')}>{p.aboutTitle}</p>
                <p className={'mt-1 text-mist ' + (isBn ? 'text-[16px] leading-[1.8]' : 'text-[14px] leading-[1.65]')}>{p.aboutText}</p>
              </div>

              {/* Booking does not exist in the prototype, so the button is visibly switched off */}
              <div className="mt-6 flex flex-wrap items-center gap-4">
                <button type="button" disabled aria-disabled="true" className={'inline-flex cursor-not-allowed items-center gap-2.5 rounded-xl bg-gold/30 px-6 py-3 font-semibold text-ivory/70 ' + (isBn ? 'text-[16px]' : 'text-[15px]')}>
                  {p.requestConsultation}
                </button>
                <span className={'text-mist ' + (isBn ? 'text-[15px]' : 'text-[13px]')}>{p.unavailable}</span>
              </div>
            </div>
          </article>
        </div>
      </main>
    )
  }

  // ======================= The directory =======================
  const countText = h.showing.replace('{shown}', shown.length).replace('{total}', profiles.length)

  return (
    <main className="bg-[#0e1115] bg-[radial-gradient(ellipse_at_50%_0%,rgba(185,154,91,0.07),transparent_60%)]">
      <div className="mx-auto max-w-[1280px] px-5 pb-16 pt-10 sm:px-8 sm:pt-14">
        {eyebrow}
        <h1 className={'mt-4 font-display font-medium text-ivory ' + (isBn ? 'text-[clamp(1.9rem,4vw,3rem)] leading-[1.4]' : 'text-[clamp(2.1rem,4.4vw,3.6rem)] leading-[1.08]')}>{p.title}</h1>
        <p className={'mt-4 max-w-[46rem] text-silver ' + (isBn ? 'text-[17px] leading-[1.85]' : 'text-[16px] leading-[1.7] sm:text-[17px]')}>{p.description}</p>
        {demoNotice}

        {/* ---------- Search and filters ---------- */}
        <div className="mt-8 rounded-[22px] border border-silver/15 bg-[#12151a]/90 p-4 sm:p-5">
          <label className="block">
            <span className="sr-only">{p.searchLabel}</span>
            <span className="flex items-center gap-3 rounded-xl border border-silver/20 bg-deep/70 px-4 transition-colors focus-within:border-gold">
              <Icon size={18} className="text-silver">
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-3.5-3.5" />
              </Icon>
              <input
                type="search"
                value={search}
                onChange={function (event) { setSearch(event.target.value) }}
                placeholder={p.searchPlaceholder}
                className={'h-12 min-w-0 flex-1 bg-transparent text-ivory outline-none placeholder:text-mist/70 ' + (isBn ? 'text-[16px]' : 'text-[15px]')}
              />
            </span>
          </label>

          <div className="mt-4 flex flex-wrap gap-3" role="group" aria-label={p.filtersLabel}>
            {filterDefs.map(function (def) {
              const filter = f[def.key]
              return (
                <FilterChip
                  key={def.key}
                  icon={def.icon}
                  label={filter.label}
                  anyLabel={filter.any}
                  big={isBn}
                  value={filters[def.key]}
                  onChange={function (value) { setFilters({ ...filters, [def.key]: value }) }}
                  options={Object.keys(filter.options).map(function (value) { return { value: value, label: filter.options[value] } })}
                />
              )
            })}
          </div>
        </div>

        <div className={'mb-4 mt-6 flex min-h-6 items-center justify-between gap-3 px-1 text-mist ' + (isBn ? 'text-[15px]' : 'text-[13px]')}>
          <p aria-live="polite">{countText}</p>
          {hasFilters && (
            <button type="button" onClick={clearAll} className="cursor-pointer text-gold-light underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-gold">
              {h.clear}
            </button>
          )}
        </div>

        {/* ---------- Profile cards ---------- */}
        <ul aria-label={h.listLabel} className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
          {shown.map(function (profile) {
            const text = textOf(profile)
            return (
              <li key={profile.id}>
                <article className="flex h-full flex-col overflow-hidden rounded-[20px] border border-silver/15 bg-[#12151a]/90 shadow-[0_18px_40px_-22px_rgba(0,0,0,0.9)] transition-[border-color,transform] duration-300 hover:border-gold/45 motion-safe:hover:-translate-y-0.5">
                  <img src={profile.photo} alt="" draggable="false" className="aspect-square w-full object-cover object-[50%_20%]" />
                  <div className="flex flex-1 flex-col p-5">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                      <h2 className={'font-display font-medium text-ivory ' + (isBn ? 'text-[24px] leading-[1.4]' : 'text-[26px] leading-[1.1]')}>{text.name}</h2>
                      {demoBadge}
                    </div>
                    <p className={'mt-1.5 text-gold-light ' + (isBn ? 'text-[18px]' : 'text-[16px]')}>{f.area.options[profile.area]}</p>

                    <ul className={'mb-5 mt-3 space-y-1.5 text-mist ' + (isBn ? 'text-[15.5px]' : 'text-[14px]')}>
                      <li className="flex items-start gap-2.5">
                        <Icon size={17} className="mt-0.5 text-silver">{iconInstitution}</Icon>
                        <span>{f.court.options[profile.court]}</span>
                      </li>
                      <li className="flex items-start gap-2.5">
                        <Icon size={17} className="mt-0.5 text-silver">{iconGlobe}</Icon>
                        <span>{languagesOf(profile)}</span>
                      </li>
                      <li className="flex items-start gap-2.5">
                        <Icon size={17} className="mt-0.5 text-silver">{iconVideo}</Icon>
                        <span>{formatsOf(profile)}</span>
                      </li>
                    </ul>

                    <div className="mt-auto flex flex-wrap items-end justify-between gap-x-4 gap-y-3 border-t border-silver/10 pt-4">
                      <div>
                        <span className={'text-ivory ' + (isBn ? 'text-[20px]' : 'text-[18px]')}>{text.fee}</span>
                        <p className={'leading-tight text-mist ' + (isBn ? 'text-[14px]' : 'text-[12px]')}>
                          {h.perConsultation} ({h.illustrativeFee})
                        </p>
                      </div>
                      <Link
                        to={'/lawyers?advocate=' + profile.id}
                        className={'inline-flex items-center gap-2 whitespace-nowrap rounded-xl border border-gold/80 px-5 py-2 font-medium text-ivory transition-colors hover:bg-gold hover:text-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold ' + (isBn ? 'text-[16px]' : 'text-[14px]')}
                      >
                        {h.viewProfile}
                        <Icon size={15}>{arrow}</Icon>
                      </Link>
                    </div>
                  </div>
                </article>
              </li>
            )
          })}
        </ul>

        {shown.length === 0 && (
          <p className={'rounded-[20px] border border-dashed border-silver/20 bg-[#12151a]/80 px-6 py-10 text-center text-mist ' + (isBn ? 'text-[17px]' : 'text-[15px]')}>{h.empty}</p>
        )}
      </div>
    </main>
  )
}

export default FindLawyer

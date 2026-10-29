import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import SectionHeading from './SectionHeading'
import { profiles } from '../data/demoAdvocates'
import { Icon, FilterChip, DemoBadge, filterDefs, textOf, formatsOf, languagesOf, iconInstitution, iconGlobe, iconVideo, iconShield } from './lawyerUi'

const noFilters = { area: '', language: '', court: '', availability: '', fee: '' }

// A small arrow used on the buttons
const arrow = (
  <>
    <path d="M5 12h14" />
    <path d="m13 6 6 6-6 6" />
  </>
)

// ---------------------------------------------------------------------------
// THE lawyer directory: intro, sample-profile notice, search, filters and the profile cards.
// It is the ONLY implementation of the listing. Two places show it, and they look and behave the same:
//   - the /lawyers page          (<LawyerDirectory />,           the page's own <main> and <h1>)
//   - the "Find a Lawyer" section of the Home page (<LawyerDirectory embedded />, a <section id="lawyers"> and <h2>)
// The list is public. Only a full profile (/lawyers/:id) needs a login - see App.jsx and ProtectedRoute.
// ---------------------------------------------------------------------------
function LawyerDirectory({ embedded = false }) {
  const { language, t } = useLanguage()
  const h = t.lawyers // names of the filters and the three demo profiles
  const p = t.lawyersPage // text of the directory
  const isBn = language === 'bn'
  const f = h.filters

  const [search, setSearch] = useState('')
  const [filters, setFilters] = useState(noFilters)

  const words = search.trim().toLowerCase()
  const hasFilters = words !== '' || Object.values(filters).some(function (v) { return v !== '' })

  // A profile is shown when it matches every chosen filter AND the search words
  const shown = profiles.filter(function (profile) {
    const passesFilters = filterDefs.every(function (def) {
      return filters[def.key] === '' || def.test(profile, filters[def.key])
    })
    if (!passesFilters) return false
    if (words === '') return true
    const haystack = [textOf(h, profile).name, f.area.options[profile.area], f.court.options[profile.court], languagesOf(h, profile)].join(' ').toLowerCase()
    return words.split(/\s+/).every(function (word) { return haystack.includes(word) })
  })

  function clearAll() {
    setSearch('')
    setFilters(noFilters)
  }

  // The page has its own <h1>; on the Home page (which already has one) the same heading is an <h2>
  const Heading = embedded ? 'h2' : 'h1'
  const Wrapper = embedded ? 'section' : 'main'
  const CardHeading = embedded ? 'h3' : 'h2' // one level below the heading above

  const demoNotice = (
    <div role="note" className="mt-10 flex items-start gap-3 rounded-2xl border border-gold/35 bg-gold/[0.07] px-4 py-3.5">
      <Icon size={20} className="mt-0.5 text-gold-light">{iconShield}</Icon>
      <div>
        <p className={'font-medium text-ivory ' + (isBn ? 'text-[17px]' : 'text-[15px]')}>{p.demoTitle}</p>
        <p className={'mt-0.5 text-mist ' + (isBn ? 'text-[16px] leading-[1.8]' : 'text-[14px] leading-[1.6]')}>{p.demoText}</p>
      </div>
    </div>
  )
  const demoBadge = <DemoBadge label={h.demoLabel} isBn={isBn} />
  const countText = h.showing.replace('{shown}', shown.length).replace('{total}', profiles.length)

  return (
    // "scroll-mt-16" keeps the section just below the sticky navbar when the page scrolls here
    <Wrapper id={embedded ? 'lawyers' : undefined} aria-labelledby="lawyers-heading" className="scroll-mt-16 bg-[#0e1115] bg-[radial-gradient(ellipse_at_50%_0%,rgba(185,154,91,0.07),transparent_60%)]">
      <div className="mx-auto max-w-[1280px] px-5 pb-16 pt-10 sm:px-8 sm:pt-14">
        <SectionHeading as={Heading} id="lawyers-heading" label={p.eyebrow} title={p.title} subtitle={p.description} />
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
            const text = textOf(h, profile)
            return (
              <li key={profile.id}>
                <article className="flex h-full flex-col overflow-hidden rounded-[20px] border border-silver/15 bg-[#12151a]/90 shadow-[0_18px_40px_-22px_rgba(0,0,0,0.9)] transition-[border-color,transform] duration-300 hover:border-gold/45 motion-safe:hover:-translate-y-0.5">
                  <img src={profile.photo} alt="" draggable="false" className="aspect-square w-full object-cover object-[50%_20%]" />
                  <div className="flex flex-1 flex-col p-5">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                      <CardHeading className={'font-display font-medium text-ivory ' + (isBn ? 'text-[24px] leading-[1.4]' : 'text-[26px] leading-[1.1]')}>{text.name}</CardHeading>
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
                        <span>{languagesOf(h, profile)}</span>
                      </li>
                      <li className="flex items-start gap-2.5">
                        <Icon size={17} className="mt-0.5 text-silver">{iconVideo}</Icon>
                        <span>{formatsOf(h, profile)}</span>
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
                        to={'/lawyers/' + profile.id}
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
    </Wrapper>
  )
}

export default LawyerDirectory

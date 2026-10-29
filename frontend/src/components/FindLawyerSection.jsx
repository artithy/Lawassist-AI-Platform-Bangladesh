import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import courthouse from '../assets/lawyers-courthouse.webp'
import { profiles } from '../data/demoAdvocates'
import { Icon, FilterChip, filterDefs, trustIcons, iconInstitution, iconGlobe, iconVideo } from './lawyerUi'

// ---------- The "Find a Lawyer" section ----------
function FindLawyerSection() {
  const { language, t } = useLanguage()
  const h = t.lawyers
  const isBn = language === 'bn'

  // What the visitor picked in each filter ('' means "any")
  const [filters, setFilters] = useState({ area: '', language: '', court: '', availability: '', fee: '' })

  function setFilter(key, value) {
    setFilters({ ...filters, [key]: value })
  }

  const hasFilters = Object.values(filters).some(function (v) {
    return v !== ''
  })

  // Keep only the profiles that match every chosen filter
  const shown = profiles.filter(function (profile) {
    return filterDefs.every(function (def) {
      return filters[def.key] === '' || def.test(profile, filters[def.key])
    })
  })

  const headingSize = isBn ? 'text-[clamp(1.9rem,3.2vw,3.2rem)] leading-[1.4]' : 'text-[clamp(2.1rem,3.4vw,4rem)] leading-[1.08]'
  const countText = h.showing.replace('{shown}', shown.length).replace('{total}', profiles.length)

  return (
    <section id="lawyers" className="relative scroll-mt-16 overflow-hidden border-t border-silver/10 bg-[#0e1115]">
      {/* Background: a warm evening picture of the Supreme Court, dome on the right */}
      <img src={courthouse} alt="" aria-hidden="true" draggable="false" className="pointer-events-none absolute inset-0 size-full select-none object-cover object-right opacity-60 lg:opacity-100" />
      {/* Phone/tablet: darken it so the text stays readable */}
      <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-[#0e1115]/55 lg:hidden" />
      <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 bottom-0 h-32 bg-linear-to-t from-[#0e1115] to-transparent" />

      <div className="relative mx-auto grid max-w-[1920px] items-center gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:gap-10 lg:pl-[5.3%] lg:pr-[12%] lg:py-24 xl:gap-14">
        {/* ================= Left: intro, filters, button, trust points ================= */}
        <div>
          <div className={'flex items-center gap-4 font-medium text-gold ' + (isBn ? 'text-[15px]' : 'text-[12px] tracking-[0.3em] sm:text-[13px]')}>
            <span className="h-px w-8 bg-gold/70 sm:w-[70px]" />
            <span>{h.eyebrow}</span>
            <span className="hidden h-px w-[70px] bg-gold/70 sm:block" />
          </div>

          <h2 className={'mt-6 font-display font-medium ' + headingSize}>
            <span className="block text-ivory">{h.line1}</span>
            <span className="block text-gold-light">{h.line2}</span>
          </h2>
          <p className="mt-5 max-w-[34rem] text-[15px] leading-[1.7] text-silver sm:text-[17px] 2xl:text-[19px]">{h.description}</p>

          {/* Filter chips: they really filter the demo profiles on the right */}
          <div className="mt-8 flex flex-wrap gap-3" role="group" aria-label={h.eyebrow}>
            {filterDefs.map(function (def) {
              const f = h.filters[def.key]
              return (
                <FilterChip
                  key={def.key}
                  icon={def.icon}
                  label={f.label}
                  anyLabel={f.any}
                  big={isBn}
                  value={filters[def.key]}
                  onChange={function (v) {
                    setFilter(def.key, v)
                  }}
                  options={Object.keys(f.options).map(function (value) {
                    return { value: value, label: f.options[value] }
                  })}
                />
              )
            })}
          </div>

          {/* Button */}
          <div className="mt-9">
            <Link
              to="/lawyers"
              className="inline-flex items-center gap-3 rounded-xl bg-gold px-7 py-3.5 text-[15px] font-semibold text-deep shadow-lg shadow-gold/20 transition hover:-translate-y-px hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-light 2xl:px-9 2xl:py-4 2xl:text-[17px]"
            >
              {h.cta}
              <Icon size={18}>
                <path d="M5 12h14" />
                <path d="m13 6 6 6-6 6" />
              </Icon>
            </Link>
          </div>

          {/* Three small points (worded as plans, because the demo has no real verification yet) */}
          <ul className="mt-10 grid max-w-[46rem] gap-5 sm:grid-cols-3 sm:gap-0">
            {h.trust.map(function (item, index) {
              return (
                <li key={item.title} className={'flex items-start gap-3 sm:px-4 sm:first:pl-0 sm:last:pr-0 ' + (index > 0 ? 'sm:border-l sm:border-silver/15' : '')}>
                  <Icon size={30} className="mt-0.5 text-gold">
                    {trustIcons[index]}
                  </Icon>
                  <span className={'leading-snug text-silver ' + (isBn ? 'text-[15px]' : 'text-[14px]')}>
                    <span className="block text-ivory">{item.title}</span>
                    <span className="block text-mist">{item.text}</span>
                  </span>
                </li>
              )
            })}
          </ul>
        </div>

        {/* ================= Right: the demo profile cards ================= */}
        <div>
          <div className={'mb-3 flex min-h-6 items-center justify-between gap-3 px-1 text-mist ' + (isBn ? 'text-[15px]' : 'text-[13px]')}>
            <p aria-live="polite">{countText}</p>
            {hasFilters && (
              <button
                type="button"
                onClick={function () {
                  setFilters({ area: '', language: '', court: '', availability: '', fee: '' })
                }}
                className="cursor-pointer text-gold-light underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-gold"
              >
                {h.clear}
              </button>
            )}
          </div>

          <ul aria-label={h.listLabel} className="flex flex-col gap-3">
            {shown.map(function (profile) {
              const text = h.profiles.find(function (p) {
                return p.id === profile.id
              })
              const f = h.filters
              return (
                <li key={profile.id}>
                  <article className="group grid grid-cols-[92px_minmax(0,1fr)] gap-x-4 gap-y-4 rounded-[20px] border border-silver/15 bg-[#12151a]/90 p-4 shadow-[0_18px_40px_-22px_rgba(0,0,0,0.9)] backdrop-blur-sm transition-[border-color,transform] duration-300 hover:border-gold/45 motion-safe:hover:-translate-y-0.5 sm:grid-cols-[112px_minmax(0,1fr)_auto] sm:gap-x-5 xl:grid-cols-[130px_minmax(0,1fr)_auto] xl:p-[18px]">
                    {/* Portrait (a stylised avatar, not a real person) */}
                    <img src={profile.photo} alt="" draggable="false" className="aspect-[4/5] w-full rounded-xl border border-silver/15 object-cover" />

                    {/* Details */}
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                        <h3 className={'font-display font-medium text-ivory ' + (isBn ? 'text-[clamp(1.2rem,1.7vw,1.6rem)] leading-[1.4]' : 'text-[clamp(1.35rem,1.8vw,1.7rem)] leading-[1.1]')}>{text.name}</h3>
                        <span className={'inline-flex items-center rounded-full border border-gold/50 bg-gold/10 px-2.5 py-0.5 font-medium text-gold-light ' + (isBn ? 'text-[13px]' : 'text-[11px]')}>{h.demoLabel}</span>
                      </div>
                      <p className="mt-1.5 text-[clamp(0.95rem,1.15vw,1.2rem)] text-gold-light">{f.area.options[profile.area]}</p>
                      <ul className={'mt-2 space-y-1.5 text-mist ' + (isBn ? 'text-[15px]' : 'text-[14px]')}>
                        <li className="flex items-center gap-2.5">
                          <Icon size={17} className="text-silver">{iconInstitution}</Icon>
                          <span>{f.court.options[profile.court]}</span>
                        </li>
                        <li className="flex items-center gap-2.5">
                          <Icon size={17} className="text-silver">{iconGlobe}</Icon>
                          <span>{profile.languages.map(function (l) { return f.language.options[l] }).join(isBn ? ', ' : ', ')}</span>
                        </li>
                        <li className="flex items-center gap-2.5">
                          <Icon size={17} className="text-silver">{iconVideo}</Icon>
                          <span>{h.bothFormats}</span>
                        </li>
                      </ul>
                    </div>

                    {/* Fee + button */}
                    <div className="col-span-2 flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-t border-silver/10 pt-4 sm:col-span-1 sm:flex-col sm:items-center sm:justify-center sm:border-t-0 sm:pt-0">
                      <div className="text-center">
                        <span className={'inline-block rounded-full border border-silver/25 bg-[#0e1115]/60 px-5 py-1.5 text-ivory ' + (isBn ? 'text-[17px]' : 'text-[15px]')}>{text.fee}</span>
                        <p className={'mt-1.5 leading-tight text-mist ' + (isBn ? 'text-[14px]' : 'text-[12.5px]')}>
                          {h.perConsultation}
                          <span className={'block text-mist/70 ' + (isBn ? 'text-[13px]' : 'text-[11px]')}>({h.illustrativeFee})</span>
                        </p>
                      </div>
                      <Link
                        to={'/lawyers?advocate=' + profile.id}
                        className={'inline-flex items-center gap-2 whitespace-nowrap rounded-xl border border-gold/80 px-5 py-2 font-medium text-ivory transition-colors hover:bg-gold hover:text-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold ' + (isBn ? 'text-[16px]' : 'text-[14px]')}
                      >
                        {h.viewProfile}
                        <Icon size={15}>
                          <path d="M5 12h14" />
                          <path d="m13 6 6 6-6 6" />
                        </Icon>
                      </Link>
                    </div>
                  </article>
                </li>
              )
            })}
          </ul>

          {shown.length === 0 && (
            <p className="rounded-[20px] border border-dashed border-silver/20 bg-[#12151a]/80 px-6 py-10 text-center text-[15px] text-mist">{h.empty}</p>
          )}
        </div>
      </div>
    </section>
  )
}

export default FindLawyerSection

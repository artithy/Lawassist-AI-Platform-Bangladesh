import { useEffect } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import { profiles } from '../data/demoAdvocates'
import { Icon, DemoBadge, textOf, formatsOf, languagesOf, iconInstitution, iconGlobe, iconVideo, iconCard } from '../components/lawyerUi'

// One advocate in full: /lawyers/:advocateId
// This page is members-only: App.jsx wraps it in <ProtectedRoute>, so a signed-out visitor who clicks "View Profile"
// is sent to /login and brought back here after logging in. The directory list itself stays public.
function LawyerProfile() {
  const { language, t } = useLanguage()
  const h = t.lawyers // names of the filters and the three demo profiles
  const p = t.lawyersPage // text that only the lawyer pages use
  const isBn = language === 'bn'
  const f = h.filters
  const { advocateId } = useParams()
  const profile = profiles.find(function (item) { return item.id === advocateId })

  // Opening a profile starts at the top of the page
  useEffect(
    function () {
      window.scrollTo(0, 0)
    },
    [advocateId],
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

  const text = textOf(h, profile)
  const rows = [
    { icon: iconInstitution, label: p.area, value: f.area.options[profile.area] },
    { icon: iconInstitution, label: p.court, value: f.court.options[profile.court] },
    { icon: iconGlobe, label: p.languages, value: languagesOf(h, profile) },
    { icon: iconVideo, label: p.format, value: formatsOf(h, profile) },
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
              <DemoBadge label={h.demoLabel} isBn={isBn} />
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

export default LawyerProfile

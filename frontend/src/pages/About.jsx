import { Link } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import HowItWorks from '../components/HowItWorks'
import SectionHeading from '../components/SectionHeading'

// Small icons (plain SVG)
function Icon({ children, size = 22, className = '' }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className={'shrink-0 ' + className} aria-hidden="true">
      {children}
    </svg>
  )
}

// One icon for each key feature
const featureIcons = [
  <>
    <path key="a1" d="m5 8 6 6" />
    <path key="a2" d="m4 14 6-6 2-3" />
    <path key="a3" d="M2 5h12" />
    <path key="a4" d="m22 22-5-10-5 10" />
    <path key="a5" d="M14 18h6" />
  </>,
  <>
    <path key="b1" d="M3 21h18" />
    <path key="b2" d="M5 21V10M9.5 21V10M14.5 21V10M19 21V10" />
    <path key="b3" d="M12 3 3 8h18Z" />
  </>,
  <path key="c" d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.6A8 8 0 1 1 21 12Z" />,
  <>
    <circle key="d1" cx="9" cy="7.5" r="3.3" />
    <path key="d2" d="M2.8 20c0-3.4 2.8-5.9 6.2-5.9s6.2 2.5 6.2 5.9" />
    <path key="d3" d="M16.4 4.6a3.1 3.1 0 0 1 0 5.8" />
  </>,
]

// The /about page
function About() {
  const { language, t } = useLanguage()
  const a = t.about
  const isBn = language === 'bn'

  const bodyText = isBn ? 'text-[17px] leading-[1.9]' : 'text-[16px] leading-[1.75]'

  return (
    <main className="bg-[#0e1115]">
      {/* ================= Introduction ================= */}
      <section className="relative overflow-hidden bg-[radial-gradient(ellipse_at_20%_0%,rgba(185,154,91,0.1),transparent_60%)]">
        <div className="mx-auto max-w-[1100px] px-5 pb-14 pt-12 sm:px-8 sm:pt-16">
          <SectionHeading
            as="h1"
            label={a.eyebrow}
            title={
              <>
                <span className="block">{a.line1}</span>
                <span className="block text-gold-light">{a.line2}</span>
              </>
            }
            subtitle={a.lead}
          />
        </div>
      </section>

      {/* ================= Why it exists ================= */}
      <section className="border-t border-silver/10">
        <div className="mx-auto max-w-[1100px] px-5 py-16 sm:px-8">
          <SectionHeading title={a.missionTitle} />
          <p className={'mx-auto mt-7 max-w-[48rem] text-center text-silver ' + bodyText}>{a.missionText}</p>
        </div>
      </section>

      {/* ================= How it works (moved here from the Home page; id="how-it-works") ================= */}
      <HowItWorks />

      {/* ================= Audience and key features ================= */}
      <section className="border-t border-silver/10">
        <div className="mx-auto grid max-w-[1100px] gap-12 px-5 py-14 sm:px-8 lg:grid-cols-2 lg:gap-14">
          <div>
            <SectionHeading align="start" title={a.audienceTitle} />
            <ul className="mt-7 space-y-5">
              {a.audience.map(function (item) {
                return (
                  <li key={item.title} className="flex gap-4">
                    <span aria-hidden="true" className="mt-2.5 block size-2 shrink-0 rotate-45 bg-gold" />
                    <div>
                      <h3 className={'text-ivory ' + (isBn ? 'text-[19px]' : 'text-[17px]')}>{item.title}</h3>
                      <p className={'mt-1 text-mist ' + (isBn ? 'text-[16px] leading-[1.8]' : 'text-[14.5px] leading-[1.65]')}>{item.text}</p>
                    </div>
                  </li>
                )
              })}
            </ul>
          </div>

          <div>
            <SectionHeading align="start" title={a.featuresTitle} />
            <ul className="mt-7 grid gap-3 sm:grid-cols-2">
              {a.features.map(function (item, index) {
                return (
                  <li key={item.title} className="rounded-2xl border border-silver/15 bg-[#12151a]/90 p-4">
                    <Icon size={22} className="text-gold-light">{featureIcons[index]}</Icon>
                    <h3 className={'mt-3 text-ivory ' + (isBn ? 'text-[18px]' : 'text-[16px]')}>{item.title}</h3>
                    <p className={'mt-1 text-mist ' + (isBn ? 'text-[15px] leading-[1.75]' : 'text-[13.5px] leading-[1.6]')}>{item.text}</p>
                  </li>
                )
              })}
            </ul>
          </div>
        </div>
      </section>

      {/* ================= Honest statement + prototype status ================= */}
      <section className="border-t border-silver/10 bg-[#0b0e12]">
        <div className="mx-auto max-w-[1100px] px-5 py-14 sm:px-8">
          <div className="rounded-[22px] border border-gold/35 bg-gold/[0.06] p-6 sm:p-8">
            <SectionHeading title={a.honestTitle} />
            <p className={'mx-auto mt-5 max-w-[52rem] text-center text-silver ' + bodyText}>{a.honestText}</p>

            <h3 className={'mt-8 font-medium text-gold ' + (isBn ? 'text-[16px]' : 'text-[12px] tracking-[0.22em]')}>{a.statusTitle}</h3>
            <div className="mt-4 grid gap-6 sm:grid-cols-2">
              {[
                { label: a.workingLabel, items: a.working, on: true },
                { label: a.plannedLabel, items: a.planned, on: false },
              ].map(function (group) {
                return (
                  <div key={group.label}>
                    <p className={'mb-2 font-medium text-ivory ' + (isBn ? 'text-[17px]' : 'text-[15px]')}>{group.label}</p>
                    <ul className={'space-y-1.5 text-mist ' + (isBn ? 'text-[16px] leading-[1.7]' : 'text-[14.5px] leading-[1.55]')}>
                      {group.items.map(function (item) {
                        return (
                          <li key={item} className="flex items-start gap-2.5">
                            <span aria-hidden="true" className={'mt-2 block size-1.5 shrink-0 rounded-full ' + (group.on ? 'bg-gold' : 'border border-silver/50')} />
                            <span>{item}</span>
                          </li>
                        )
                      })}
                    </ul>
                  </div>
                )
              })}
            </div>
          </div>
        </div>
      </section>

      {/* ================= Call to action ================= */}
      <section className="border-t border-silver/10 bg-[radial-gradient(ellipse_at_50%_100%,rgba(185,154,91,0.09),transparent_65%)]">
        <div className="mx-auto max-w-[1100px] px-5 py-14 text-center sm:px-8">
          <SectionHeading title={a.ctaTitle} subtitle={a.ctaText} />
          <div className="mt-7 flex flex-wrap items-center justify-center gap-3">
            <Link to="/#areas" className={'inline-flex items-center rounded-xl bg-gold px-7 py-3.5 font-semibold text-deep shadow-lg shadow-gold/20 transition hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-light ' + (isBn ? 'text-[17px]' : 'text-[15px]')}>
              {t.nav.exploreLaws}
            </Link>
            <Link to="/assistant" className={'inline-flex items-center rounded-xl border border-gold/80 px-7 py-3.5 font-medium text-ivory transition-colors hover:bg-gold hover:text-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold ' + (isBn ? 'text-[17px]' : 'text-[15px]')}>
              {t.nav.aiAssistant}
            </Link>
          </div>
        </div>
      </section>
    </main>
  )
}

export default About

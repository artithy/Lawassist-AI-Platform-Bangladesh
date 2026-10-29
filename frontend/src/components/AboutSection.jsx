import { Link } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import HowItWorks from './HowItWorks'
import SectionHeading from './SectionHeading'

// The "About" section at the bottom of the Home page, just above the footer (id="about").
// ONE continuous section: a short introduction with a "Learn More" button to the full /about page, then the
// "How it works" steps (the same HowItWorks component the About page uses). Both parts share one background that
// fades smoothly from the page colour down towards the footer, the same centred headings and the same gold details,
// so it reads as a single story: what LawAssist AI is -> how it works.
// The navbar highlights "About" while this section is on screen.
function AboutSection() {
  const { language, t } = useLanguage()
  const a = t.about // the heading words are shared with the About page
  const s = t.aboutSection
  const isBn = language === 'bn'

  return (
    // "scroll-mt-16" keeps the section just below the sticky navbar when the page scrolls here
    <section id="about" aria-labelledby="about-heading" className="relative scroll-mt-16 overflow-hidden bg-linear-to-b from-[#0e1115] via-[#13161b] to-[#0b0e12]">
      {/* One soft warm light at the top, shared by both parts */}
      <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-0 h-[34rem] bg-[radial-gradient(ellipse_at_50%_0%,rgba(185,154,91,0.09),transparent_65%)]" />

      <div className="relative mx-auto max-w-[1320px] px-5 pt-20 sm:px-8 lg:pt-28 2xl:max-w-[1500px]">
        <div className="text-center">
          <SectionHeading
            label={a.eyebrow}
            id="about-heading"
            title={
              <>
                <span className="block">{a.line1}</span>
                <span className="block text-gold-light">{a.line2}</span>
              </>
            }
            subtitle={s.text}
          >
            <Link
              to="/about"
              className={'mt-8 inline-flex items-center gap-2 rounded-xl border border-gold/80 px-6 py-3 font-medium text-ivory transition-colors hover:bg-gold hover:text-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold 2xl:px-8 2xl:py-3.5 ' + (isBn ? 'text-[16px] 2xl:text-[17px]' : 'text-[14px] 2xl:text-[16px]')}
            >
              {s.learnMore}
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <path d="M5 12h14" />
                <path d="m13 6 6 6-6 6" />
              </svg>
            </Link>
          </SectionHeading>

          {/* A thin gold thread leading down into "How it works" */}
          <span aria-hidden="true" className="mx-auto mt-12 block h-16 w-px bg-linear-to-b from-gold/60 via-gold/25 to-transparent" />
        </div>
      </div>

      <HowItWorks joined />
    </section>
  )
}

export default AboutSection

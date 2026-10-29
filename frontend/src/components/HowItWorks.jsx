import { useLanguage } from '../i18n/useLanguage'
import SectionHeading from './SectionHeading'

// One small gold line icon per step (plain SVG, same approach as the other sections).
const icons = [
  // 1. A speech bubble with text (ask in your own words)
  <>
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
    <path d="M13 8H7" />
    <path d="M17 12H7" />
  </>,
  // 2. A document (read a plain-language answer)
  <>
    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
    <path d="M14 2v4a2 2 0 0 0 2 2h4" />
    <path d="M10 9H8" />
    <path d="M16 13H8" />
    <path d="M16 17H8" />
  </>,
  // 3. The scales of justice (check the source)
  <>
    <path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z" />
    <path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z" />
    <path d="M7 21h10" />
    <path d="M12 3v18" />
    <path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2" />
  </>,
]

// A tiny gold diamond, used as a decoration
function Diamond({ className }) {
  return <span aria-hidden="true" className={'block shrink-0 rotate-45 bg-gold ' + className} />
}

// ---------- The "How it works" section ----------
// Used on the Home page (inside AboutSection, joined) and on the About page.
// A centred heading, then three cards that are joined by a thin gold line,
// so they read as one journey: ask -> read -> check the source.
//   Desktop (lg:): three cards in a row, joined by a horizontal line.
//   Phone/tablet : the cards are stacked, joined by a short vertical line.
function HowItWorks({ joined = false }) {
  const { language, t } = useLanguage()
  const h = t.howItWorks
  const isBn = language === 'bn'

  return (
    // The section has NO background of its own: it takes the colour of whatever it sits in, so it reads as part of
    // the same page. "joined" = continues straight on from the About introduction on the Home page (no line, less
    // space above); otherwise (the About page) it gets the same thin top line as the other About sections.
    <section id="how-it-works" aria-labelledby="how-it-works-heading" className={'relative scroll-mt-16 ' + (joined ? '' : 'border-t border-silver/10')}>
      <div className={'relative mx-auto max-w-[1320px] px-5 sm:px-8 2xl:max-w-[1500px] ' + (joined ? 'pb-20 pt-4 lg:pb-28 lg:pt-6' : 'py-16 lg:py-20')}>
        {/* ----- Heading (the shared section heading) ----- */}
        <SectionHeading
          label={h.eyebrow}
          id="how-it-works-heading"
          title={
            <>
              <span className="block">{h.line1}</span>
              <span className="block">{h.line2}</span>
            </>
          }
        />

        {/* ----- Three steps ----- */}
        <ol className="mx-auto mt-14 grid max-w-[560px] grid-cols-1 gap-12 lg:mt-20 lg:max-w-none lg:grid-cols-3 lg:gap-14 2xl:gap-20">
          {h.steps.map(function (step, index) {
            const isLast = index === h.steps.length - 1
            return (
              <li key={step.title} className="relative flex">
                <div className="group relative flex w-full flex-col overflow-hidden rounded-[20px] border border-silver/15 bg-linear-to-b from-[#1c2026] to-[#14171c] p-7 shadow-[0_20px_44px_-24px_rgba(0,0,0,0.9)] transition-[border-color,box-shadow,transform] duration-300 hover:border-gold/50 hover:shadow-[0_24px_50px_-22px_rgba(0,0,0,0.95)] motion-safe:hover:-translate-y-1 lg:p-8 2xl:p-10">
                  {/* Faint warm corner light */}
                  <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_100%_0%,rgba(185,154,91,0.10),transparent_55%)]" />

                  {/* Top row: big gold numeral on the left, icon in an arch frame on the right */}
                  <div className="relative flex items-start justify-between">
                    <span className="font-display text-[clamp(3.4rem,4.6vw,4.8rem)] font-medium leading-[0.9] text-gold">{step.number}</span>
                    <span className="flex h-[58px] w-[52px] items-center justify-center rounded-b-[4px] rounded-t-full border border-gold/35 text-silver transition-[color,border-color,transform] duration-300 group-hover:border-gold/80 group-hover:text-gold-light motion-safe:group-hover:-translate-y-0.5 motion-safe:group-hover:scale-105">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="mt-1" aria-hidden="true">
                        {icons[index]}
                      </svg>
                    </span>
                  </div>

                  {/* A short gold rule that grows a little on hover */}
                  <span aria-hidden="true" className="relative mt-7 block h-px w-9 bg-gold/60 transition-[width] duration-300 motion-safe:group-hover:w-16" />

                  {/* Title + description */}
                  <h3 className={'relative mt-5 font-display font-medium text-ivory ' + (isBn ? 'text-[clamp(1.35rem,1.7vw,1.7rem)] leading-[1.4]' : 'text-[clamp(1.5rem,1.9vw,2rem)] leading-[1.15]')}>
                    {step.title}
                  </h3>
                  <p className={'relative mt-3 text-mist ' + (isBn ? 'text-[16px] leading-[1.75]' : 'text-[15px] leading-[1.7] 2xl:text-[16px]')}>{step.text}</p>
                </div>

                {/* Thin gold line that joins this card to the next one */}
                {!isLast && (
                  <>
                    {/* Desktop: horizontal line in the gap, with a small diamond in the middle */}
                    <span aria-hidden="true" className="absolute left-full top-[52px] z-10 hidden h-px w-14 items-center justify-center bg-linear-to-r from-gold/60 via-gold/30 to-gold/60 lg:flex 2xl:w-20">
                      <Diamond className="size-1.5" />
                    </span>
                    {/* Phone/tablet: vertical line under the card */}
                    <span aria-hidden="true" className="absolute left-1/2 top-full z-10 flex h-12 w-px -translate-x-1/2 items-center justify-center bg-linear-to-b from-gold/60 via-gold/30 to-gold/60 lg:hidden">
                      <Diamond className="size-1.5" />
                    </span>
                  </>
                )}
              </li>
            )
          })}
        </ol>
      </div>
    </section>
  )
}

export default HowItWorks

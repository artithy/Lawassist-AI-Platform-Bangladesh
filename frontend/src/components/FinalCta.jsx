import { Link } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import supremeCourt from '../assets/supreme-court.jpg'

// The closing banner at the bottom of the Home page: one sentence and one button.
// It reuses the project's Supreme Court picture, darkened, as a quiet background.
function FinalCta() {
  const { language, t } = useLanguage()
  const c = t.finalCta
  const isBn = language === 'bn'

  return (
    <section className="bg-[#0e1115] px-5 pb-20 pt-4 sm:px-8 lg:pb-28">
      <div className="relative mx-auto max-w-[1100px] overflow-hidden rounded-[26px] border border-silver/15 bg-deep 2xl:max-w-[1300px]">
        {/* Background picture, dark and a little blurred so the text stays easy to read */}
        <img
          src={supremeCourt}
          alt=""
          aria-hidden="true"
          draggable="false"
          className="pointer-events-none absolute inset-0 size-full select-none object-cover object-[50%_55%] opacity-70 [filter:brightness(0.75)_contrast(1.08)]"
        />
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-linear-to-b from-[#0e1115]/60 via-[#0e1115]/20 to-[#0e1115]/60" />
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(14,17,21,0.45),transparent_65%)]" />

        <div className="relative flex flex-col items-center px-6 py-10 text-center sm:py-11 2xl:py-16">
          <h2 className={'font-display font-medium text-ivory ' + (isBn ? 'text-[clamp(1.7rem,3.2vw,2.8rem)] leading-[1.4]' : 'text-[clamp(1.9rem,3.4vw,3.2rem)] leading-[1.15]')}>
            {c.headingStart}
            <span className="italic text-gold-light">{c.headingHighlight}</span>
            {c.headingEnd}
          </h2>
          <p className={'mt-3 max-w-xl text-silver ' + (isBn ? 'text-[16px] leading-[1.75]' : 'text-[14px] leading-[1.6] sm:text-[15px]')}>{c.text}</p>

          <Link
            to="/assistant"
            className="mt-5 inline-flex items-center gap-2 rounded-xl bg-gold px-6 py-3 text-[14px] font-semibold text-deep shadow-lg shadow-gold/25 transition hover:-translate-y-px hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-light 2xl:px-8 2xl:py-3.5 2xl:text-[16px]"
          >
            {t.hero.askButton}
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d="M5 12h14" />
              <path d="m13 6 6 6-6 6" />
            </svg>
          </Link>
        </div>
      </div>
    </section>
  )
}

export default FinalCta

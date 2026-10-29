import { Link } from 'react-router-dom'
import HeroBackground from '../components/HeroBackground'
import HeroArtwork from '../components/HeroArtwork'
import LegalQuestionCard from '../components/LegalQuestionCard'
import AssistantSection from '../components/AssistantSection'
import AreasOfLaw from '../components/AreasOfLaw'
import LawyerDirectory from '../components/LawyerDirectory'
import FinalCta from '../components/FinalCta'
import AboutSection from '../components/AboutSection'
import { useLanguage } from '../i18n/useLanguage'

function ArrowIcon() {
  return (
    <svg
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M5 12h14" />
      <path d="m13 6 6 6-6 6" />
    </svg>
  )
}

function SearchIcon() {
  return (
    <svg
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      aria-hidden="true"
    >
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </svg>
  )
}

function Home() {
  const { language, t } = useLanguage()

  // (Links like "/#areas" are scrolled to by components/ScrollToTop.jsx, and the navbar highlights the section in view.)
  // Bangla letters have marks above and below the line, so they need more line height
  // than English, otherwise the marks get cut off.
  let headlineSize = 'text-[clamp(2.4rem,3.9vw,4.4rem)] leading-[1.03]'
  if (language === 'bn') {
    headlineSize = 'text-[clamp(2.1rem,3.7vw,3.4rem)] leading-[1.3]'
  }

  return (
    // "-mt-16" slides the hero up underneath the see-through navbar (the navbar is 4rem tall),
    // so the court picture starts at the very top of the page, like the design.
    <>
      <main id="home" className="relative -mt-16 min-h-[720px] overflow-hidden lg:min-h-screen">
        <HeroBackground />

        {/* Text column. On desktop it only uses the left part, the statue has the right part. */}
        <div className="relative z-10 mx-auto max-w-[1920px] px-5 pb-6 pt-24 sm:px-8 lg:px-[8%] lg:pb-8 lg:pt-[84px]">
          <div className="lg:max-w-[560px] 2xl:max-w-[740px]">
            {/* Small label */}
            <div className="inline-flex max-w-full items-center rounded-full border border-silver/15 bg-graphite/40 px-4 py-1.5 text-[11px] leading-snug tracking-wide text-silver backdrop-blur-sm">
              {t.hero.badge}
            </div>

            {/* Headline */}
            <h1 className={'mt-4 font-display font-medium text-ivory ' + headlineSize}>
              <span className="block">{t.hero.line1}</span>
              <span className="block">{t.hero.line2}</span>
              <span className="block">{t.hero.line3}</span>
              <span className="block">
                {t.hero.line4Start}
                <span className="italic text-gold-light">{t.hero.line4Highlight}</span>
                {t.hero.line4End}
              </span>
            </h1>

            {/* Paragraph */}
            <p className="mt-5 max-w-[27.5rem] text-[13px] leading-[1.65] text-silver [text-shadow:0_1px_10px_rgba(34,37,43,0.9)] 2xl:max-w-[34rem] 2xl:text-[16px]">{t.hero.description}</p>

            {/* Buttons */}
            <div className="mt-5 flex flex-wrap gap-3">
              <Link
                to="/assistant"
                className="inline-flex items-center gap-2 rounded-xl bg-gold px-5 py-2.5 text-[12.5px] font-semibold text-deep 2xl:px-7 2xl:py-3.5 2xl:text-[15px] shadow-lg shadow-gold/30 transition hover:-translate-y-px hover:brightness-105"
              >
                <SearchIcon />
                {t.hero.askButton}
                <ArrowIcon />
              </Link>

              {/* Goes to the "Areas of Law" section on the Home page (the effect above scrolls there) */}
              <Link
                to="/#areas"
                className="inline-flex items-center gap-2 rounded-xl border border-silver/30 bg-deep/60 px-5 py-2.5 text-[12.5px] font-semibold text-ivory 2xl:px-7 2xl:py-3.5 2xl:text-[15px] backdrop-blur-sm transition-colors hover:border-gold hover:text-gold-light"
              >
                {t.hero.exploreButton}
                <ArrowIcon />
              </Link>
            </div>

            {/* Question box */}
            <LegalQuestionCard />
          </div>
        </div>

        {/* Lady Justice (right side) */}
        <HeroArtwork />
      </main>

      {/* 2. Explore Laws: the five areas of law */}
      <AreasOfLaw />

      {/* 3. AI Legal Assistant: what it does, and the four promises */}
      <AssistantSection />

      {/* 4. Find a Lawyer: the SAME directory component as the /lawyers page */}
      <LawyerDirectory embedded />

      {/* 5. Closing banner */}
      <FinalCta />

      {/* 6. About: short introduction + "How it works"; "Learn More" opens the /about page. The footer follows. */}
      <AboutSection />
    </>
  )
}

export default Home

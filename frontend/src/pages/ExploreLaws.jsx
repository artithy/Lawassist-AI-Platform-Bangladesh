import { Link } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import SectionHeading from '../components/SectionHeading'

// The /laws page. The law browser is not built yet, so this is a short, honest placeholder
// (the "areas of law" cards on the Home page link here with ?area=...).
function ExploreLaws() {
  const { language, t } = useLanguage()
  const p = t.exploreLawsPage
  const isBn = language === 'bn'

  return (
    <main className="bg-[#0e1115] bg-[radial-gradient(ellipse_at_50%_0%,rgba(185,154,91,0.07),transparent_60%)]">
      <div className="mx-auto max-w-[900px] px-5 py-20 text-center sm:px-8 sm:py-24">
        <SectionHeading as="h1" label={p.eyebrow} title={p.title} subtitle={p.text}>
          <Link
            to="/"
            className={'mt-8 inline-flex items-center rounded-xl border border-gold/80 px-6 py-3 font-medium text-ivory transition-colors hover:bg-gold hover:text-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold ' + (isBn ? 'text-[16px]' : 'text-[14px]')}
          >
            {p.home}
          </Link>
        </SectionHeading>
      </div>
    </main>
  )
}

export default ExploreLaws

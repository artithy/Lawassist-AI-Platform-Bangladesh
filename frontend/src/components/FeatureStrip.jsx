import { useLanguage } from '../i18n/useLanguage'

// Small gold line icons (plain SVG, no icon library needed).
// They are listed in the same order as t.features in translations.js.
const iconPaths = [
  // 1. Language (Bangla & English)
  <>
    <path d="m5 8 6 6" />
    <path d="m4 14 6-6 2-3" />
    <path d="M2 5h12" />
    <path d="M7 2h1" />
    <path d="m22 22-5-10-5 10" />
    <path d="M14 18h6" />
  </>,
  // 2. Document (sources shown)
  <>
    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
    <path d="M14 2v4a2 2 0 0 0 2 2h4" />
    <path d="M10 9H8" />
    <path d="M16 13H8" />
    <path d="M16 17H8" />
  </>,
  // 3. Message (plain language)
  <>
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
    <path d="M13 8H7" />
    <path d="M17 12H7" />
  </>,
  // 4. Shield with a tick (information, not advice)
  <>
    <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
    <path d="m9 12 2 2 4-4" />
  </>,
]

// The dark strip with four short promises, shown right under the hero.
//   Phone: one column  |  Tablet: two columns  |  Desktop (lg:): four columns in one row
function FeatureStrip() {
  const { language, t } = useLanguage()

  // Bangla letters need a little more line height than English
  const textLeading = language === 'bn' ? 'leading-[1.75]' : 'leading-[1.55]'

  return (
    <section className="relative bg-[#13161b]">
      <div className="mx-auto grid max-w-[1920px] grid-cols-1 gap-x-12 gap-y-7 px-6 py-8 sm:grid-cols-2 sm:px-10 lg:grid-cols-4 lg:px-[8.5%] 2xl:gap-x-16">
        {t.features.map(function (feature, index) {
          return (
            <div key={feature.title} className="flex min-w-0 items-start gap-3.5">
              <svg
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="mt-0.5 shrink-0 text-[#e0b24a]"
                aria-hidden="true"
              >
                {iconPaths[index]}
              </svg>
              <div className="min-w-0">
                <h3 className="text-[14px] font-semibold text-ivory 2xl:text-[16px]">{feature.title}</h3>
                <p className={'mt-0.5 text-[13px] text-mist 2xl:text-[15px] ' + textLeading}>{feature.text}</p>
              </div>
            </div>
          )
        })}
      </div>
    </section>
  )
}

export default FeatureStrip

import { Link } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import courthouse from '../assets/areas-courthouse.webp'

// ---------- Small gold line icons (plain SVG, one per card, same order as t.areas.cards) ----------
const icons = [
  // Land & Property: a house
  <>
    <path d="M3 10.5 12 3l9 7.5" />
    <path d="M5.5 9.5V21h13V9.5" />
    <path d="M10 21v-6h4v6" />
  </>,
  // Family Law: people
  <>
    <circle cx="9" cy="7.5" r="3.3" />
    <path d="M2.8 20c0-3.4 2.8-5.9 6.2-5.9s6.2 2.5 6.2 5.9" />
    <path d="M16.4 4.6a3.1 3.1 0 0 1 0 5.8" />
    <path d="M18.3 14.5c2 .7 3.4 2.6 3.4 5.2" />
  </>,
  // Labour & Employment: a hard hat
  <>
    <path d="M4.5 16V14a7.5 7.5 0 0 1 15 0v2" />
    <path d="M2.5 16h19v2.5h-19z" />
    <path d="M10 6.8V16" />
    <path d="M14 6.8V16" />
  </>,
  // Business: a briefcase
  <>
    <path d="M3 9a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z" />
    <path d="M8.5 8V6a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v2" />
    <path d="M3 13.5h18" />
    <path d="M11 13.5h2v2h-2z" />
  </>,
  // Consumer Rights: a shopping cart
  <>
    <path d="M2.8 4h2.7l2.2 10.2a1 1 0 0 0 1 .8h8.6a1 1 0 0 0 1-.8L20.2 8H6.2" />
    <circle cx="9.7" cy="19.3" r="1.3" />
    <circle cx="17" cy="19.3" r="1.3" />
  </>,
]

// ---------- Very faint dark pictures in the top-right corner of each card ----------
// (simple SVG shapes: a house, a family, a building site, offices, shopping bags)
const scenes = [
  <>
    <path d="M18 62 60 30l42 32v40H18z" />
    <path d="M78 22h10v22H78z" />
    <rect x="30" y="68" width="12" height="16" fill="#0b0d10" />
    <rect x="78" y="68" width="12" height="16" fill="#0b0d10" />
    <rect x="54" y="80" width="14" height="22" fill="#0b0d10" />
  </>,
  <>
    <circle cx="40" cy="26" r="9" />
    <path d="M28 102V54c0-9 24-9 24 0v48z" />
    <circle cx="74" cy="34" r="8" />
    <path d="M63 102V62c0-8 22-8 22 0v40z" />
    <circle cx="97" cy="52" r="6" />
    <path d="M89 102V72c0-6 16-6 16 0v30z" />
  </>,
  <>
    <path d="M30 102V40h14v62z" />
    <path d="M62 102V24h12v78z" />
    <path d="M20 24h90v6H20z" />
    <path d="M96 30v26h3V30z" />
    <path d="M86 102V62h24v40z" />
  </>,
  <>
    <path d="M14 102V34h30v68z" />
    <path d="M50 102V16h34v86z" />
    <path d="M90 102V48h26v54z" />
    <g fill="#0b0d10">
      <rect x="20" y="42" width="6" height="6" /><rect x="32" y="42" width="6" height="6" />
      <rect x="58" y="26" width="6" height="6" /><rect x="70" y="26" width="6" height="6" />
      <rect x="58" y="42" width="6" height="6" /><rect x="70" y="42" width="6" height="6" />
      <rect x="98" y="56" width="6" height="6" />
    </g>
  </>,
  <>
    <path d="M22 44h44l6 58H16z" />
    <path d="M32 44v-8a12 12 0 0 1 24 0v8" fill="none" stroke="currentColor" strokeWidth="4" />
    <path d="M70 58h38l5 44H65z" />
  </>,
]

// A small arrow, used in the round "Explore" button
function ArrowIcon() {
  return (
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M5 12h14" />
      <path d="m13 6 6 6-6 6" />
    </svg>
  )
}

// ---------- The "Areas of Law" section ----------
// Dark courthouse picture behind the heading, then five cards. Each card links to /laws?area=<id>.
function AreasOfLaw() {
  const { language, t } = useLanguage()
  const a = t.areas
  const isBn = language === 'bn'

  // Bangla needs a smaller size and more line height than the English serif heading
  const headingSize = isBn
    ? 'text-[clamp(1.9rem,3vw,3.1rem)] leading-[1.35]'
    : 'text-[clamp(2.3rem,3.4vw,4.2rem)] leading-[1.06]'
  // Wide letter spacing looks nice in English capitals, but breaks Bangla letters apart
  const spaced = isBn ? 'text-[15px]' : 'tracking-[0.32em]'

  return (
    // "scroll-mt-16" keeps the heading just below the sticky navbar when the page scrolls here
    <section id="areas" className="relative scroll-mt-16 overflow-hidden bg-[#0e1115]">
      {/* Background: courthouse columns, a pointed arch with the outline of Bangladesh */}
      <img
        src={courthouse}
        alt=""
        aria-hidden="true"
        draggable="false"
        className="pointer-events-none absolute right-[-35%] top-0 w-[110%] max-w-none select-none opacity-35 sm:right-[-12%] sm:w-[80%] sm:opacity-80 lg:right-0 lg:w-[52%] lg:opacity-100"
      />
      {/* Soft dark patches at the edges (like out-of-focus leaves in the design) */}
      <div aria-hidden="true" className="pointer-events-none absolute -left-24 top-[45%] size-80 rounded-full bg-[#2b2a1c]/35 blur-3xl" />
      <div aria-hidden="true" className="pointer-events-none absolute -right-24 bottom-[10%] size-72 rounded-full bg-[#2b2a1c]/30 blur-3xl" />
      {/* Keeps the text readable where it sits on top of the picture */}
      <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-linear-to-r from-[#0e1115]/80 via-transparent to-transparent lg:via-[#0e1115]/10" />
      <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 bottom-0 h-40 bg-linear-to-t from-[#0e1115] to-transparent" />

      <div className="relative mx-auto max-w-[1920px] px-5 pb-16 pt-14 sm:px-8 lg:px-[6%] lg:pb-20 lg:pt-[88px]">
        {/* Small label with gold lines on both sides */}
        <div className={'flex items-center gap-4 font-medium text-gold ' + (isBn ? '' : 'text-[12px] sm:text-[13px] ') + spaced}>
          <span className="h-px w-6 bg-gold/70 sm:w-[26px]" />
          <span>{a.eyebrow}</span>
          <span className="hidden h-px w-[58px] bg-gold/70 sm:block" />
        </div>

        {/* Heading + short description */}
        <div className="mt-6 lg:pl-[2.5%]">
          <h2 className={'font-display font-medium text-ivory ' + headingSize}>
            <span className="block">{a.headingLine1}</span>
            <span className="italic text-gold-light">{a.headingHighlight}</span>
            {a.headingEnd}
          </h2>
          <p className="mt-5 max-w-[44rem] text-[15px] leading-[1.7] text-silver sm:text-[17px] 2xl:text-[20px]">{a.description}</p>
        </div>

        {/* Right side words: Know / Understand / Take the right step (wide screens only) */}
        <div className="absolute right-[3.5%] top-[170px] hidden xl:block">
          <div className="mb-5 h-px w-10 bg-gold/80" />
          {a.tagline.map(function (word) {
            return (
              <p key={word} className={'font-display uppercase text-mist/90 ' + (isBn ? '' : 'text-[13px] ') + ' ' + (isBn ? 'text-[16px] leading-8' : 'leading-6 tracking-[0.3em]')}>
                {word}
              </p>
            )
          })}
        </div>

        {/* Five cards: 1 column on phones, 2 on tablets, 3 on small laptops, 5 on wide screens */}
        <div className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:mt-14 lg:grid-cols-3 xl:grid-cols-5 xl:gap-[22px] 2xl:gap-[26px]">
          {a.cards.map(function (card, index) {
            return (
              <Link
                key={card.id}
                to={'/laws?area=' + card.id}
                className="group relative flex flex-col sm:min-h-[400px] overflow-hidden rounded-[18px] border border-silver/15 bg-linear-to-b from-[#1c2027]/85 via-[#13161b]/90 to-[#0f1216]/95 p-6 shadow-[0_18px_40px_-20px_rgba(0,0,0,0.8)] transition-[border-color,box-shadow,transform] duration-300 hover:border-gold/60 hover:shadow-[0_0_44px_-10px_rgba(185,154,91,0.45)] focus-visible:border-gold focus-visible:outline-none motion-safe:hover:-translate-y-1 xl:min-h-[440px] xl:p-[26px] 2xl:min-h-[470px] 2xl:p-8"
              >
                {/* Warm light in the top-right corner (a little stronger on hover) */}
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_92%_0%,rgba(185,154,91,0.20),transparent_58%)] opacity-70 transition-opacity duration-300 group-hover:opacity-100" />

                {/* Faint dark picture in the corner */}
                <svg
                  viewBox="0 0 120 110"
                  fill="currentColor"
                  aria-hidden="true"
                  className="pointer-events-none absolute right-0 top-0 h-[26%] w-[46%] text-ivory/[0.07] [mask-image:linear-gradient(to_bottom,black_40%,transparent),linear-gradient(to_right,transparent,black_50%)] [mask-composite:intersect]"
                >
                  {scenes[index]}
                </svg>

                {/* Number */}
                <span className="relative font-display text-[13px] tracking-[0.28em] text-gold-light/80">
                  {String(index + 1).padStart(2, '0')}
                </span>

                {/* Icon inside an arch-shaped frame */}
                <div className="relative mt-3 flex h-[78px] w-[82px] items-center justify-center rounded-b-[3px] rounded-t-full border border-gold/70 text-gold-light transition-colors duration-300 group-hover:border-gold-light">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.2" strokeLinecap="round" strokeLinejoin="round" className="mt-1" aria-hidden="true">
                    {icons[index]}
                  </svg>
                </div>

                {/* Title, subtitle in the other language, description */}
                <h3 className={'relative mt-5 font-display font-medium text-ivory ' + (isBn ? 'text-[clamp(1.35rem,1.6vw,1.8rem)] leading-[1.35]' : 'text-[clamp(1.45rem,1.85vw,2.05rem)] leading-[1.12]')}>
                  {card.title}
                </h3>
                {card.subtitle && (
                  <p className="relative mt-2 text-[clamp(0.95rem,1.1vw,1.2rem)] leading-snug text-gold-light">{card.subtitle}</p>
                )}
                <p className={'relative mt-4 leading-[1.7] text-mist ' + (isBn ? 'text-[16px] 2xl:text-[17px]' : 'text-[14.5px] 2xl:text-[16px]')}>{card.text}</p>

                {/* Explore row, always at the bottom of the card */}
                <div className="relative mt-auto flex items-center gap-4 pt-7 text-ivory">
                  <span className="text-[16px] 2xl:text-[18px]">{a.explore}</span>
                  <span className="h-px w-14 bg-gold/60 transition-[width] duration-300 motion-safe:group-hover:w-20" />
                  <span className="flex size-11 shrink-0 items-center justify-center rounded-full border border-gold/80 text-gold-light transition-colors duration-300 group-hover:bg-gold group-hover:text-deep">
                    <ArrowIcon />
                  </span>
                </div>
              </Link>
            )
          })}
        </div>
      </div>
    </section>
  )
}

export default AreasOfLaw

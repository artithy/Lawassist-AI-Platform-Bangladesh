import { useLanguage } from '../i18n/useLanguage'
import useReveal from './useReveal'

// ---------------------------------------------------------------------------
// SectionHeading - the ONE heading design used for every section of every page.
//
// A small floating "glass" label (thin gold border, soft shadow, a gold diamond on each side) names the section,
// so you always see where a section starts. An optional large title and sentence sit centred under it.
// It fades and rises into view once when it scrolls on screen (not for people who reduce motion), and the label
// lifts and glows a little on hover.
//
// Two shapes:
//   <SectionHeading label="HOW IT WORKS" title={<>...</>} subtitle="..." />
//       -> label in the glass box (a small <p>), big display title as the heading (<h2>) under it
//   <SectionHeading title="Why it exists" />        (no label)
//       -> the title itself sits in the glass box and is the heading
//
// Props: as = 'h1' | 'h2' | 'h3' (the heading tag, default h2) - id - align = 'center' (default) | 'start'
//        subtitle - children (extra content under the heading, e.g. a button)
// ---------------------------------------------------------------------------

function Diamond() {
  return <span aria-hidden="true" className="block size-1.5 shrink-0 rotate-45 bg-gold" />
}

// The glass box. "hover:" effects are gentle: border brightens, a faint gold glow, 2px lift (not for reduced motion).
const boxClass =
  'inline-flex max-w-full items-center justify-center gap-3 rounded-full border border-gold/30 bg-linear-to-b from-white/[0.08] to-white/[0.02] shadow-[0_10px_30px_-14px_rgba(0,0,0,0.9),inset_0_1px_0_rgba(255,255,255,0.07)] backdrop-blur-md transition-[border-color,box-shadow,translate] duration-300 hover:border-gold/70 hover:shadow-[0_0_0_1px_rgba(185,154,91,0.22),0_12px_36px_-12px_rgba(185,154,91,0.4)] motion-safe:hover:-translate-y-0.5'

function SectionHeading({ label, title, subtitle, as = 'h2', id, align = 'center', children, className = '' }) {
  const { language } = useLanguage()
  const isBn = language === 'bn'
  const [ref, shown] = useReveal()
  const Tag = as
  const center = align === 'center'

  // Bangla letters join each other, so no letter spacing; a slightly bigger size and more line height instead
  const labelText = isBn ? 'text-[15px] font-medium' : 'text-[12px] font-semibold tracking-[0.28em] sm:text-[13px]'
  const bigTitle = isBn
    ? 'text-[clamp(1.5rem,3vw,2.9rem)] leading-[1.4]'
    : 'text-[clamp(1.7rem,3.5vw,3.6rem)] leading-[1.12]'
  const smallTitle = isBn ? 'text-[clamp(1.3rem,2vw,1.7rem)] leading-[1.45]' : 'text-[clamp(1.35rem,2vw,1.75rem)] leading-[1.2]'

  return (
    <div
      ref={ref}
      data-section-heading=""
      className={
        'flex flex-col transition-[opacity,translate] duration-700 ease-out motion-reduce:translate-y-0 motion-reduce:opacity-100 motion-reduce:transition-none ' +
        (center ? 'items-center text-center ' : 'items-start text-left ') +
        (shown ? 'translate-y-0 opacity-100 ' : 'translate-y-4 opacity-0 ') +
        className
      }
    >
      {label ? (
        <p className={boxClass + ' px-5 py-2 text-gold-light ' + labelText}>
          <Diamond />
          <span>{label}</span>
          <Diamond />
        </p>
      ) : (
        <Tag id={id} className={boxClass + ' px-6 py-2.5 font-display font-medium text-ivory ' + smallTitle}>
          <Diamond />
          <span>{title}</span>
          <Diamond />
        </Tag>
      )}

      {label && title && (
        <Tag id={id} className={'mt-6 font-display font-medium text-ivory ' + bigTitle}>
          {title}
        </Tag>
      )}

      {subtitle && (
        <p className={'mt-5 max-w-[44rem] text-silver ' + (isBn ? 'text-[17px] leading-[1.85]' : 'text-[15px] leading-[1.7] sm:text-[17px]')}>{subtitle}</p>
      )}

      {children}
    </div>
  )
}

export default SectionHeading

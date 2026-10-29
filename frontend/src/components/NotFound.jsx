import { Link } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'

// Shown for any address that is not a page of the site (the "*" route in App.jsx).
function NotFound() {
  const { language, t } = useLanguage()
  const n = t.notFound
  const isBn = language === 'bn'

  return (
    <main className="bg-[#0e1115] bg-[radial-gradient(ellipse_at_50%_0%,rgba(185,154,91,0.07),transparent_60%)]">
      <div className="mx-auto max-w-[760px] px-5 py-24 text-center sm:px-8">
        <h1 className={'font-display font-medium text-ivory ' + (isBn ? 'text-[clamp(1.9rem,4vw,2.8rem)] leading-[1.4]' : 'text-[clamp(2.2rem,4.4vw,3.2rem)] leading-[1.1]')}>{n.title}</h1>
        <p className={'mt-4 text-mist ' + (isBn ? 'text-[17px] leading-[1.85]' : 'text-[16px] leading-[1.7]')}>{n.text}</p>
        <Link
          to="/"
          className={'mt-8 inline-flex items-center rounded-xl bg-gold px-7 py-3 font-semibold text-deep shadow-lg shadow-gold/20 transition hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-light ' + (isBn ? 'text-[16px]' : 'text-[15px]')}
        >
          {n.home}
        </Link>
      </div>
    </main>
  )
}

export default NotFound

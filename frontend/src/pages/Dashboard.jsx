import { Link, useLocation } from 'react-router-dom'
import { useLanguage } from '../i18n/useLanguage'
import { Eyebrow, Icon, Notice } from '../components/AuthUi'

// Where each action goes, and its icon. The words come from translations.js (t.auth.dashboard.actions).
const actions = [
  {
    to: '/assistant',
    icon: <path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.6A8 8 0 1 1 21 12Z" />,
  },
  {
    to: '/#areas',
    icon: (
      <>
        <path d="M3 21h18" />
        <path d="M5 21V10M9.5 21V10M14.5 21V10M19 21V10" />
        <path d="M12 3 3 8h18Z" />
      </>
    ),
  },
  {
    to: '/lawyers',
    icon: (
      <>
        <circle cx="9" cy="7.5" r="3.3" />
        <path d="M2.8 20c0-3.4 2.8-5.9 6.2-5.9s6.2 2.5 6.2 5.9" />
        <path d="M16.4 4.6a3.1 3.1 0 0 1 0 5.8" />
        <path d="M18.3 14.5c2 .7 3.4 2.6 3.4 5.2" />
      </>
    ),
  },
]

// The welcome page: /dashboard
// After a REAL sign-up or log-in, the user's name arrives in location.state.user.
// Without it (a direct visit, or the demo preview) the page says clearly that it is only a preview.
// There are no "recent questions" here on purpose: the prototype does not save any.
function Dashboard() {
  const { language, t } = useLanguage()
  const d = t.auth.dashboard
  const isBn = language === 'bn'
  const location = useLocation()
  const user = location.state && location.state.user

  const firstName = user && user.name ? user.name.trim().split(/\s+/)[0] : ''
  const heading = firstName ? d.welcomeName.replace('{name}', firstName) : d.welcome

  return (
    <main className="bg-[#0e1115] bg-[radial-gradient(ellipse_at_50%_0%,rgba(185,154,91,0.08),transparent_60%)]">
      <div className="mx-auto max-w-[1100px] px-5 pb-16 pt-10 sm:px-8 sm:pt-16">
        <Eyebrow>{d.eyebrow}</Eyebrow>
        <h1 className={'mt-5 break-words font-display font-medium text-ivory ' + (isBn ? 'text-[clamp(2rem,5vw,3.4rem)] leading-[1.4]' : 'text-[clamp(2.3rem,5.4vw,4rem)] leading-[1.06]')}>{heading}</h1>
        <p className={'mt-4 text-silver ' + (isBn ? 'text-[18px] leading-[1.85]' : 'text-[17px] leading-[1.7]')}>{d.intro}</p>

        {!user && (
          <div className="mt-6 max-w-[44rem]">
            <Notice title={d.previewTitle} role="note">
              {d.previewText}
            </Notice>
          </div>
        )}

        <ul className="mt-9 grid gap-4 md:grid-cols-3">
          {actions.map(function (action, index) {
            const text = d.actions[index]
            return (
              <li key={action.to}>
                <Link to={action.to} className="group flex h-full flex-col rounded-[20px] border border-silver/15 bg-[#12151a]/90 p-6 shadow-[0_18px_40px_-22px_rgba(0,0,0,0.9)] transition-[border-color,transform] duration-300 hover:border-gold/55 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold motion-safe:hover:-translate-y-0.5">
                  <span className="flex size-12 items-center justify-center rounded-full border border-gold/40 bg-gold/10 text-gold-light">
                    <Icon size={24}>{action.icon}</Icon>
                  </span>
                  <h2 className={'mt-5 font-display font-medium text-ivory ' + (isBn ? 'text-[23px] leading-[1.4]' : 'text-[26px] leading-[1.15]')}>{text.title}</h2>
                  <p className={'mt-2 flex-1 text-mist ' + (isBn ? 'text-[16px] leading-[1.8]' : 'text-[14.5px] leading-[1.6]')}>{text.text}</p>
                  <span className="mt-5 inline-flex text-gold-light transition-transform motion-safe:group-hover:translate-x-1">
                    <Icon size={20}>
                      <path d="M5 12h14" />
                      <path d="m13 6 6 6-6 6" />
                    </Icon>
                  </span>
                </Link>
              </li>
            )
          })}
        </ul>
      </div>
    </main>
  )
}

export default Dashboard

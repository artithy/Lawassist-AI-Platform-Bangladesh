import { useLanguage } from '../i18n/useLanguage'

// The small বাংলা / English pill.
// It is used in the Navbar AND inside the question card, and both change the
// same shared language - so the whole site switches together.
function LanguageSwitch({ small = false }) {
  const { language, setLanguage, t } = useLanguage()

  function getButtonClass(isSelected) {
    let size = 'px-3 py-1.5 text-xs'
    if (small) {
      size = 'px-2.5 py-1 text-[11px]'
    }

    let color = 'text-silver hover:text-ivory'
    if (isSelected) {
      color = 'bg-gold text-deep'
    }

    return 'cursor-pointer rounded-full font-medium leading-normal transition-colors ' + size + ' ' + color
  }

  return (
    <div
      role="group"
      aria-label={t.nav.languageLabel}
      className="flex items-center rounded-full border border-silver/30 bg-deep/50 p-0.5"
    >
      <button
        type="button"
        className={getButtonClass(language === 'bn')}
        aria-pressed={language === 'bn'}
        onClick={function () {
          setLanguage('bn')
        }}
      >
        বাংলা
      </button>
      <button
        type="button"
        className={getButtonClass(language === 'en')}
        aria-pressed={language === 'en'}
        onClick={function () {
          setLanguage('en')
        }}
      >
        English
      </button>
    </div>
  )
}

export default LanguageSwitch

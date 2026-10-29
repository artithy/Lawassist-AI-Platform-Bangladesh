import { useEffect, useState } from 'react'
import { LanguageContext } from './useLanguage'
import translations from './translations'

const STORAGE_KEY = 'lawassist-language'

// Read the language saved from last time (if any).
function getSavedLanguage() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved === 'en' || saved === 'bn') {
      return saved
    }
  } catch {
    // localStorage can be blocked (private mode). Then we just use English.
  }
  return 'en'
}

// Wrap the whole app in this. Every component inside can read the language.
function LanguageProvider({ children }) {
  const [language, setLanguage] = useState(getSavedLanguage)

  // Runs every time the language changes:
  //  - tells the browser which language the page is in
  //  - remembers the choice, so a refresh keeps it
  useEffect(() => {
    document.documentElement.lang = language
    try {
      localStorage.setItem(STORAGE_KEY, language)
    } catch {
      // ignore: saving is only a convenience
    }
  }, [language])

  const value = {
    language: language,
    setLanguage: setLanguage,
    t: translations[language],
  }

  return <LanguageContext.Provider value={value}>{children}</LanguageContext.Provider>
}

export default LanguageProvider

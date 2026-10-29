import { createContext, useContext } from 'react'

// A "context" is a box that any component can read from, without passing
// props down through every level. We keep the selected language inside it.
export const LanguageContext = createContext(null)

// Components call this to get:
//   language    -> 'en' or 'bn'
//   setLanguage -> function to change it
//   t           -> the text for the selected language (see translations.js)
export function useLanguage() {
  return useContext(LanguageContext)
}

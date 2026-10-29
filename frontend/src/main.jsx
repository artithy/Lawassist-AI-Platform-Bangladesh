import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import './index.css'
import App from './App.jsx'
import LanguageProvider from './i18n/LanguageProvider.jsx'
import AuthProvider from './auth/AuthProvider.jsx'

// BrowserRouter makes routing work for everything inside it.
// LanguageProvider shares the chosen language (English / বাংলা) with every component.
// AuthProvider shares the signed-in user (Supabase session) with every component.
createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <LanguageProvider>
        <AuthProvider>
          <App />
        </AuthProvider>
      </LanguageProvider>
    </BrowserRouter>
  </StrictMode>,
)

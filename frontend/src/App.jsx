import { Routes, Route, useLocation } from 'react-router-dom'
import Navbar from './components/Navbar'
import Footer from './components/Footer'
import ScrollToTop from './components/ScrollToTop'
import Home from './pages/Home'
import ExploreLaws from './pages/ExploreLaws'
import AiAssistant from './pages/AiAssistant'
import FindLawyer from './pages/FindLawyer'
import About from './pages/About'
import Login from './pages/Login'
import Register from './pages/Register'
import Dashboard from './pages/Dashboard'
import ProtectedRoute from './components/ProtectedRoute'

function App() {
  // The chat page (/assistant) is a full-screen app: no footer, and the page itself never scrolls
  // (only the messages inside the chat do). Every other page keeps the normal footer and layout.
  const isChat = useLocation().pathname === '/assistant'

  return (
    <div className={isChat ? 'flex h-dvh flex-col overflow-hidden' : undefined}>
      {/* The Navbar is outside <Routes>, so it shows on every page */}
      <Navbar />
      <ScrollToTop />

      {/* <Routes> shows ONE page at a time, depending on the URL.
          The wrapper's minimum height keeps the footer down at the bottom on short pages. */}
      <div className={isChat ? 'flex min-h-0 flex-1 flex-col' : 'min-h-[55vh]'}>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/laws" element={<ExploreLaws />} />
          {/* The chat needs a signed-in user: anybody else is sent to /login */}
          <Route
            path="/assistant"
            element={
              <ProtectedRoute>
                <AiAssistant />
              </ProtectedRoute>
            }
          />
          <Route path="/lawyers" element={<FindLawyer />} />
          <Route path="/about" element={<About />} />
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          {/* (old address) /dashboard now just forwards to /assistant */}
          <Route path="/dashboard" element={<Dashboard />} />

          {/* "*" matches any URL that is not listed above */}
          <Route
            path="*"
            element={
              <main className="mx-auto max-w-3xl px-6 py-20 text-center">
                <h1 className="mb-4 font-serif text-4xl font-medium text-ivory">Page not found</h1>
                <p className="mb-3 text-lg text-mist">Sorry, this page does not exist.</p>
              </main>
            }
          />
        </Routes>
      </div>

      {/* The Footer is outside <Routes> too, so it shows on every page */}
      {!isChat && <Footer />}
    </div>
  )
}

export default App

import { Routes, Route } from 'react-router-dom'
import Navbar from './components/Navbar'
import Home from './pages/Home'
import ExploreLaws from './pages/ExploreLaws'
import AiAssistant from './pages/AiAssistant'
import FindLawyer from './pages/FindLawyer'
import About from './pages/About'
import Login from './pages/Login'
import Register from './pages/Register'

function App() {
  return (
    <div>
      {/* The Navbar is outside <Routes>, so it shows on every page */}
      <Navbar />

      {/* <Routes> shows ONE page at a time, depending on the URL */}
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/laws" element={<ExploreLaws />} />
        <Route path="/assistant" element={<AiAssistant />} />
        <Route path="/lawyers" element={<FindLawyer />} />
        <Route path="/about" element={<About />} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />

        {/* "*" matches any URL that is not listed above */}
        <Route
          path="*"
          element={
            <main className="mx-auto max-w-3xl px-6 py-20 text-center">
              <h1 className="mb-4 font-serif text-4xl font-medium text-cream">Page not found</h1>
              <p className="mb-3 text-lg text-muted">Sorry, this page does not exist.</p>
            </main>
          }
        />
      </Routes>
    </div>
  )
}

export default App

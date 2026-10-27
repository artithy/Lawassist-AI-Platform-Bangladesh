import { Routes, Route } from 'react-router-dom'
import Navbar from './components/Navbar'
import Home from './pages/Home'
import ExploreLaws from './pages/ExploreLaws'
import AiAssistant from './pages/AiAssistant'
import FindLawyer from './pages/FindLawyer'
import About from './pages/About'
import Login from './pages/Login'
import Register from './pages/Register'
import './App.css'

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
            <main className="page">
              <h1>Page not found</h1>
              <p>Sorry, this page does not exist.</p>
            </main>
          }
        />
      </Routes>
    </div>
  )
}

export default App

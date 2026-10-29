// The ONE place that talks to the AI backend.
//
// This project does not have an AI backend yet, so by default nothing is sent anywhere.
// When you build one, put its address in frontend/.env (then restart `npm run dev`):
//
//     VITE_ASSISTANT_API_URL=http://localhost:8000/api/ask
//
// The page then sends:   POST { "question": "...", "language": "bn" | "en" }
// and expects back:      { "answer": "...", "sources": [ "Law name, section", { "title": "...", "url": "https://..." } ] }
// ("sources" is optional.) This request/response shape is a suggestion - change it here if your backend differs.

const API_URL = import.meta.env.VITE_ASSISTANT_API_URL

// true only when an address has been set
export const isAssistantConfigured = Boolean(API_URL)

export async function askAssistant(question, language) {
  if (!API_URL) {
    const error = new Error('The assistant backend is not configured.')
    error.code = 'NOT_CONFIGURED'
    throw error
  }

  const response = await fetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ question, language }),
  })
  if (!response.ok) {
    throw new Error('The assistant answered with status ' + response.status)
  }

  const data = await response.json()
  if (!data || typeof data.answer !== 'string' || data.answer.trim() === '') {
    throw new Error('The assistant sent an answer in an unexpected format.')
  }
  return { answer: data.answer, sources: Array.isArray(data.sources) ? data.sources : [] }
}

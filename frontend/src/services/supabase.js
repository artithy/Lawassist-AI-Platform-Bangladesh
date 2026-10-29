// Kept so any older "import { supabase } from './supabase'" keeps working.
// There must be only ONE Supabase client in the app (two clients share the same saved session and warn each other),
// so this file simply re-exports the one created in supabaseClient.js, which reads
// VITE_SUPABASE_URL and VITE_SUPABASE_PUBLISHABLE_KEY from your .env file.
export { supabase } from './supabaseClient'

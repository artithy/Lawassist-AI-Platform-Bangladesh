import { Navigate, useLocation } from 'react-router-dom'
import LawyerDirectory from '../components/LawyerDirectory'

// The /lawyers page: the searchable, filterable directory of DEMO profiles (see components/LawyerDirectory.jsx).
// The same directory is also the "Find a Lawyer" section of the Home page, so both look and behave the same.
// A full profile lives at /lawyers/:advocateId (pages/LawyerProfile.jsx, login required).
function FindLawyer() {
  const location = useLocation()

  // Old links looked like /lawyers?advocate=tanvir. They now forward to /lawyers/tanvir (which asks for a login).
  const oldId = new URLSearchParams(location.search).get('advocate')
  if (oldId) return <Navigate to={'/lawyers/' + encodeURIComponent(oldId)} replace />

  return <LawyerDirectory />
}

export default FindLawyer

import { Navigate, Route, Routes } from 'react-router'
import { RequireAuth } from './auth/RequireAuth'
import { AppLayout } from './components/AppLayout'
import { RequestDrawer } from './components/RequestDrawer'
import { LoginPage } from './pages/LoginPage'
import { RequestsPage } from './pages/RequestsPage'

function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/requests"
        element={
          <RequireAuth>
            <AppLayout>
              <RequestsPage />
            </AppLayout>
          </RequireAuth>
        }
      >
        <Route path=":id" element={<RequestDrawer />} />
      </Route>
      <Route path="*" element={<Navigate to="/requests" replace />} />
    </Routes>
  )
}

export default App

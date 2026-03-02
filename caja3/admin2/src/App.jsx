import { AdminLayout } from './components/admin-layout'
import { Dashboard } from './views/Dashboard'

function App() {
    return (
        <AdminLayout title="Panel de Control">
            <Dashboard />
        </AdminLayout>
    )
}

export default App

'use client';

import {
  AlertTriangle,
  Bell,
  CalendarDays,
  CheckCircle2,
  Dumbbell,
  LayoutDashboard,
  LogOut,
  Search,
  Users,
  WalletCards,
} from 'lucide-react';
import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import {
  apiGet,
  clearSession,
  DashboardSummary,
  getStoredToken,
  getStoredUser,
} from '@/lib/api';

const currencyFormatter = new Intl.NumberFormat('es-ES', {
  style: 'currency',
  currency: 'EUR',
  maximumFractionDigits: 0,
});

export default function DashboardPage() {
  const router = useRouter();
  const [dashboard, setDashboard] = useState<DashboardSummary | null>(null);
  const [error, setError] = useState('');
  const user = useMemo(() => getStoredUser(), []);

  useEffect(() => {
    if (!getStoredToken()) {
      router.replace('/login');
      return;
    }

    apiGet<DashboardSummary>('/dashboard')
      .then(setDashboard)
      .catch(() => setError('No se pudo cargar el dashboard.'));
  }, [router]);

  function logout() {
    clearSession();
    router.push('/login');
  }

  return (
    <main className="app-shell">
      <aside className="sidebar">
        <div className="brand-lockup brand-lockup--sidebar">
          <div className="brand-icon">
            <Dumbbell size={24} />
          </div>
          <div>
            <h1>Membora CRM</h1>
            <p>{user?.tenantName ?? 'NexoFit Studio'}</p>
          </div>
        </div>

        <nav className="sidebar-nav" aria-label="Navegación principal">
          <Link className="active" href="/dashboard">
            <LayoutDashboard size={20} />
            Panel
          </Link>
          <Link href="/leads">
            <Search size={20} />
            Leads
          </Link>
          <a href="#">
            <Users size={20} />
            Socios
          </a>
          <a href="#">
            <WalletCards size={20} />
            Membresías
          </a>
          <a href="#">
            <CalendarDays size={20} />
            Clases
          </a>
          <a href="#">
            <CheckCircle2 size={20} />
            Tareas
          </a>
        </nav>

        <button className="logout-button" onClick={logout} type="button">
          <LogOut size={18} />
          Cerrar sesión
        </button>
      </aside>

      <section className="workspace">
        <header className="topbar">
          <div className="search-box">
            <Search size={18} />
            <input placeholder="Buscar socios, leads o clases..." />
          </div>
          <div className="topbar-actions">
            <button className="ghost-icon" type="button">
              <Bell size={20} />
            </button>
            <div className="user-chip">
              <span>{user?.name?.slice(0, 1) ?? 'A'}</span>
              <div>
                <strong>{user?.name ?? 'Laura Martin'}</strong>
                <small>{user?.role ?? 'GYM_ADMIN'}</small>
              </div>
            </div>
          </div>
        </header>

        <div className="content">
          <div className="page-heading">
            <div>
              <h2>Panel de control</h2>
              <p>Resumen operativo de NexoFit Studio.</p>
            </div>
            <Link className="primary-action primary-action--compact" href="/leads">
              Nuevo lead
            </Link>
          </div>

          {error ? <div className="notice notice-error">{error}</div> : null}

          {!dashboard ? (
            <div className="loading-card">Cargando indicadores...</div>
          ) : (
            <>
              <section className="kpi-grid">
                <KpiCard
                  icon={<Users size={22} />}
                  label="Socios activos"
                  value={dashboard.kpis.activeMembers}
                  helper="Socios con estado activo"
                />
                <KpiCard
                  icon={<Search size={22} />}
                  label="Leads abiertos"
                  value={dashboard.kpis.openLeads}
                  helper="Oportunidades en seguimiento"
                />
                <KpiCard
                  icon={<WalletCards size={22} />}
                  label="MRR estimado"
                  value={currencyFormatter.format(dashboard.kpis.estimatedMrr)}
                  helper="Suscripciones activas"
                />
                <KpiCard
                  alert
                  icon={<AlertTriangle size={22} />}
                  label="Alertas abiertas"
                  value={dashboard.kpis.openAlerts}
                  helper={`${dashboard.kpis.overdueTasks} tareas vencidas`}
                />
              </section>

              <section className="dashboard-grid">
                <article className="panel-card panel-card--wide">
                  <header>
                    <h3>Actividad semanal</h3>
                    <span>{dashboard.kpis.weeklyCheckIns} check-ins</span>
                  </header>
                  <div className="bar-chart" aria-label="Gráfico de asistencia semanal">
                    {[45, 64, 58, 82, 72, 50, 36].map((height, index) => (
                      <div key={index}>
                        <span style={{ height: `${height}%` }} />
                        <small>{['L', 'M', 'X', 'J', 'V', 'S', 'D'][index]}</small>
                      </div>
                    ))}
                  </div>
                </article>

                <article className="panel-card">
                  <header>
                    <h3>Tareas y alertas</h3>
                    <span>{dashboard.kpis.openAlerts} abiertas</span>
                  </header>
                  <div className="stack-list">
                    {dashboard.openRiskAlerts.length ? (
                      dashboard.openRiskAlerts.map((alert) => (
                        <div className="alert-row" key={alert.id}>
                          <AlertTriangle size={18} />
                          <div>
                            <strong>{alert.message}</strong>
                            <small>{translateSeverity(alert.severity)}</small>
                          </div>
                        </div>
                      ))
                    ) : (
                      <p className="empty-text">No hay alertas abiertas.</p>
                    )}
                  </div>
                </article>
              </section>

              <section className="panel-card">
                <header>
                  <h3>Leads recientes</h3>
                  <span>{dashboard.recentLeads.length} registros</span>
                </header>
                <div className="table-like">
                  {dashboard.recentLeads.map((lead) => (
                    <div className="table-row" key={lead.id}>
                      <div>
                        <strong>
                          {lead.firstName} {lead.lastName ?? ''}
                        </strong>
                        <small>{lead.email ?? 'Sin email'}</small>
                      </div>
                      <span>{lead.pipelineStage.name}</span>
                      <StatusBadge status={lead.status} />
                    </div>
                  ))}
                </div>
              </section>
            </>
          )}
        </div>
      </section>
    </main>
  );
}

function KpiCard({
  alert,
  helper,
  icon,
  label,
  value,
}: {
  alert?: boolean;
  helper: string;
  icon: React.ReactNode;
  label: string;
  value: number | string;
}) {
  return (
    <article className="kpi-card">
      <div className={alert ? 'kpi-icon kpi-icon--alert' : 'kpi-icon'}>{icon}</div>
      <p>{label}</p>
      <strong>{value}</strong>
      <small>{helper}</small>
    </article>
  );
}

function StatusBadge({ status }: { status: string }) {
  const label = status === 'CONVERTED' ? 'Convertido' : status === 'LOST' ? 'Perdido' : 'Abierto';
  return <span className={`status-badge status-badge--${status.toLowerCase()}`}>{label}</span>;
}

function translateSeverity(severity: string) {
  if (severity === 'HIGH') return 'Alta prioridad';
  if (severity === 'LOW') return 'Baja prioridad';
  return 'Prioridad media';
}

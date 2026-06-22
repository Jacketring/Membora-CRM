'use client';

import {
  ArrowRight,
  Bell,
  CalendarDays,
  CheckCircle2,
  Dumbbell,
  LayoutDashboard,
  LogOut,
  Mail,
  Phone,
  Plus,
  Search,
  Users,
  WalletCards,
} from 'lucide-react';
import Link from 'next/link';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import {
  apiGet,
  apiPatch,
  apiPost,
  clearSession,
  CreateLeadPayload,
  getStoredToken,
  getStoredUser,
  Lead,
  PipelineStage,
} from '@/lib/api';

const leadSources = [
  { label: 'Visita presencial', value: 'WALK_IN' },
  { label: 'Web', value: 'WEBSITE' },
  { label: 'Teléfono', value: 'PHONE' },
  { label: 'Redes sociales', value: 'SOCIAL_MEDIA' },
  { label: 'Recomendación', value: 'REFERRAL' },
  { label: 'Otro', value: 'OTHER' },
];

export default function LeadsPage() {
  const router = useRouter();
  const user = useMemo(() => getStoredUser(), []);
  const [leads, setLeads] = useState<Lead[]>([]);
  const [stages, setStages] = useState<PipelineStage[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [query, setQuery] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<CreateLeadPayload>({
    pipelineStageId: '',
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    source: 'WALK_IN',
    interest: '',
  });

  useEffect(() => {
    if (!getStoredToken()) {
      router.replace('/login');
      return;
    }

    loadData();
  }, [router]);

  async function loadData() {
    setError('');
    setLoading(true);

    try {
      const [loadedStages, loadedLeads] = await Promise.all([
        apiGet<PipelineStage[]>('/pipeline-stages'),
        apiGet<Lead[]>('/leads'),
      ]);

      const orderedStages = loadedStages.sort((a, b) => a.order - b.order);
      setStages(orderedStages);
      setLeads(loadedLeads);

      setForm((current) => ({
        ...current,
        pipelineStageId: current.pipelineStageId || orderedStages[0]?.id || '',
      }));
    } catch {
      setError('No se pudieron cargar los leads.');
    } finally {
      setLoading(false);
    }
  }

  function logout() {
    clearSession();
    router.push('/login');
  }

  const filteredLeads = leads.filter((lead) => {
    const term = query.trim().toLowerCase();

    if (!term) return true;

    return [
      lead.firstName,
      lead.lastName,
      lead.email,
      lead.phone,
      lead.interest,
      lead.pipelineStage.name,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(term));
  });

  const openLeads = leads.filter((lead) => lead.status === 'OPEN').length;
  const convertedLeads = leads.filter((lead) => lead.status === 'CONVERTED').length;
  const lostLeads = leads.filter((lead) => lead.status === 'LOST').length;
  const conversionRate = leads.length ? Math.round((convertedLeads / leads.length) * 100) : 0;

  async function createLead(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!form.pipelineStageId || !form.firstName.trim()) {
      setError('Indica al menos nombre y etapa comercial.');
      return;
    }

    setSaving(true);
    setError('');

    try {
      const payload: CreateLeadPayload = {
        pipelineStageId: form.pipelineStageId,
        firstName: form.firstName.trim(),
        lastName: form.lastName?.trim() || undefined,
        email: form.email?.trim() || undefined,
        phone: form.phone?.trim() || undefined,
        source: form.source || 'WALK_IN',
        interest: form.interest?.trim() || undefined,
      };

      const createdLead = await apiPost<Lead>('/leads', payload);
      setLeads((current) => [createdLead, ...current]);
      setShowForm(false);
      setForm({
        pipelineStageId: stages[0]?.id || '',
        firstName: '',
        lastName: '',
        email: '',
        phone: '',
        source: 'WALK_IN',
        interest: '',
      });
    } catch {
      setError('No se pudo crear el lead.');
    } finally {
      setSaving(false);
    }
  }

  async function moveLead(lead: Lead, pipelineStageId: string) {
    setError('');

    try {
      const updatedLead = await apiPatch<Lead>(`/leads/${lead.id}`, {
        pipelineStageId,
        status: lead.status,
      });

      setLeads((current) => current.map((item) => (item.id === lead.id ? updatedLead : item)));
    } catch {
      setError('No se pudo mover el lead.');
    }
  }

  async function convertLead(lead: Lead) {
    setError('');

    try {
      await apiPost(`/leads/${lead.id}/convert`);
      await loadData();
    } catch {
      setError('No se pudo convertir el lead. Puede que ya esté convertido.');
    }
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
          <Link href="/dashboard">
            <LayoutDashboard size={20} />
            Panel
          </Link>
          <Link className="active" href="/leads">
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
            <input
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Buscar por nombre, email, teléfono o interés..."
              value={query}
            />
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
              <h2>Leads y pipeline</h2>
              <p>Seguimiento comercial de oportunidades para NexoFit Studio.</p>
            </div>
            <button className="primary-action primary-action--compact" onClick={() => setShowForm(true)} type="button">
              <Plus size={18} />
              Nuevo lead
            </button>
          </div>

          {error ? <div className="notice notice-error">{error}</div> : null}

          <section className="kpi-grid kpi-grid--leads">
            <MiniMetric label="Leads abiertos" value={openLeads} />
            <MiniMetric label="Convertidos" value={convertedLeads} />
            <MiniMetric label="Perdidos" value={lostLeads} />
            <MiniMetric label="Conversión" value={`${conversionRate}%`} />
          </section>

          {loading ? (
            <div className="loading-card">Cargando pipeline comercial...</div>
          ) : (
            <>
              <section className="pipeline-board" aria-label="Pipeline comercial">
                {stages.map((stage) => {
                  const stageLeads = filteredLeads.filter((lead) => lead.pipelineStageId === stage.id);

                  return (
                    <article className="pipeline-column" key={stage.id}>
                      <header>
                        <h3>{stage.name}</h3>
                        <span>{stageLeads.length}</span>
                      </header>
                      <div className="pipeline-cards">
                        {stageLeads.length ? (
                          stageLeads.map((lead) => (
                            <LeadCard
                              key={lead.id}
                              lead={lead}
                              onConvert={() => convertLead(lead)}
                              onMove={(nextStageId) => moveLead(lead, nextStageId)}
                              stages={stages}
                            />
                          ))
                        ) : (
                          <p className="empty-text">Sin oportunidades.</p>
                        )}
                      </div>
                    </article>
                  );
                })}
              </section>

              <section className="panel-card">
                <header>
                  <h3>Listado de leads</h3>
                  <span>{filteredLeads.length} registros</span>
                </header>
                <div className="lead-table">
                  {filteredLeads.map((lead) => (
                    <div className="lead-table-row" key={lead.id}>
                      <div>
                        <strong>
                          {lead.firstName} {lead.lastName ?? ''}
                        </strong>
                        <small>{lead.interest ?? 'Sin interés indicado'}</small>
                      </div>
                      <div>
                        <span>{lead.email ?? 'Sin email'}</span>
                        <small>{lead.phone ?? 'Sin teléfono'}</small>
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

      {showForm ? (
        <div className="modal-backdrop" role="presentation">
          <form className="modal-card" onSubmit={createLead}>
            <header>
              <div>
                <h2>Nuevo lead</h2>
                <p>Registra una oportunidad comercial para hacer seguimiento.</p>
              </div>
              <button onClick={() => setShowForm(false)} type="button">
                Cerrar
              </button>
            </header>

            <div className="form-grid">
              <label className="field">
                <span>Nombre</span>
                <input
                  onChange={(event) => setForm((current) => ({ ...current, firstName: event.target.value }))}
                  required
                  value={form.firstName}
                />
              </label>
              <label className="field">
                <span>Apellidos</span>
                <input
                  onChange={(event) => setForm((current) => ({ ...current, lastName: event.target.value }))}
                  value={form.lastName}
                />
              </label>
              <label className="field">
                <span>Email</span>
                <input
                  onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))}
                  type="email"
                  value={form.email}
                />
              </label>
              <label className="field">
                <span>Teléfono</span>
                <input
                  onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))}
                  value={form.phone}
                />
              </label>
              <label className="field">
                <span>Etapa</span>
                <select
                  onChange={(event) => setForm((current) => ({ ...current, pipelineStageId: event.target.value }))}
                  required
                  value={form.pipelineStageId}
                >
                  {stages.map((stage) => (
                    <option key={stage.id} value={stage.id}>
                      {stage.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="field">
                <span>Origen</span>
                <select
                  onChange={(event) => setForm((current) => ({ ...current, source: event.target.value }))}
                  value={form.source}
                >
                  {leadSources.map((source) => (
                    <option key={source.value} value={source.value}>
                      {source.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="field field--wide">
                <span>Interés principal</span>
                <input
                  onChange={(event) => setForm((current) => ({ ...current, interest: event.target.value }))}
                  placeholder="Ej. Prueba de HIIT, plan premium, bono mensual..."
                  value={form.interest}
                />
              </label>
            </div>

            <button className="primary-action" disabled={saving} type="submit">
              {saving ? 'Guardando...' : 'Crear lead'}
              <ArrowRight size={18} />
            </button>
          </form>
        </div>
      ) : null}
    </main>
  );
}

function MiniMetric({ label, value }: { label: string; value: number | string }) {
  return (
    <article className="mini-metric">
      <span>{label}</span>
      <strong>{value}</strong>
    </article>
  );
}

function LeadCard({
  lead,
  onConvert,
  onMove,
  stages,
}: {
  lead: Lead;
  onConvert: () => void;
  onMove: (stageId: string) => void;
  stages: PipelineStage[];
}) {
  return (
    <div className="lead-card">
      <div className="lead-card__head">
        <div>
          <strong>
            {lead.firstName} {lead.lastName ?? ''}
          </strong>
          <small>{translateSource(lead.source)}</small>
        </div>
        <StatusBadge status={lead.status} />
      </div>
      <p>{lead.interest ?? 'Sin interés indicado'}</p>
      <div className="lead-contact">
        <span>
          <Mail size={14} />
          {lead.email ?? 'Sin email'}
        </span>
        <span>
          <Phone size={14} />
          {lead.phone ?? 'Sin teléfono'}
        </span>
      </div>
      <div className="lead-actions">
        <select
          aria-label="Mover lead de etapa"
          onChange={(event) => onMove(event.target.value)}
          value={lead.pipelineStageId}
        >
          {stages.map((stage) => (
            <option key={stage.id} value={stage.id}>
              {stage.name}
            </option>
          ))}
        </select>
        {lead.status === 'OPEN' ? (
          <button onClick={onConvert} type="button">
            Convertir
          </button>
        ) : null}
      </div>
    </div>
  );
}

function StatusBadge({ status }: { status: string }) {
  const label = status === 'CONVERTED' ? 'Convertido' : status === 'LOST' ? 'Perdido' : 'Abierto';
  return <span className={`status-badge status-badge--${status.toLowerCase()}`}>{label}</span>;
}

function translateSource(source: string) {
  const match = leadSources.find((item) => item.value === source);
  return match?.label ?? 'Otro';
}

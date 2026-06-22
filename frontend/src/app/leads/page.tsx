'use client';

import {
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
  apiDelete,
  apiPatch,
  apiPost,
  clearSession,
  CreateLeadPayload,
  getStoredToken,
  getStoredUser,
  Lead,
  PipelineStage,
} from '@/lib/api';
import {
  CreateLeadModal,
  LeadDetailDrawer,
  LeadFilters,
  LeadFiltersState,
  LeadMetrics,
  LeadsTable,
} from './components/lead-components';

const initialFilters: LeadFiltersState = {
  query: '',
  source: '',
  stageId: '',
  status: '',
};

export default function LeadsPage() {
  const router = useRouter();
  const user = useMemo(() => getStoredUser(), []);
  const [leads, setLeads] = useState<Lead[]>([]);
  const [stages, setStages] = useState<PipelineStage[]>([]);
  const [filters, setFilters] = useState<LeadFiltersState>(initialFilters);
  const [selectedLead, setSelectedLead] = useState<Lead | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [showCreateModal, setShowCreateModal] = useState(false);

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

      setStages(loadedStages.sort((a, b) => a.order - b.order));
      setLeads(loadedLeads);
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
    const term = filters.query.trim().toLowerCase();
    const matchesQuery =
      !term ||
      [lead.firstName, lead.lastName, lead.email, lead.phone, lead.interest, lead.pipelineStage.name]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(term));

    return (
      matchesQuery &&
      (!filters.source || lead.source === filters.source) &&
      (!filters.stageId || lead.pipelineStageId === filters.stageId) &&
      (!filters.status || lead.status === filters.status)
    );
  });

  const openLeads = leads.filter((lead) => lead.status === 'OPEN').length;
  const convertedLeads = leads.filter((lead) => lead.status === 'CONVERTED').length;
  const conversionRate = leads.length ? Math.round((convertedLeads / leads.length) * 100) : 0;
  const trialScheduled = leads.filter((lead) => lead.pipelineStage.key === 'TRIAL_SCHEDULED').length;
  const noFollowUp = leads.filter((lead) => lead.status === 'OPEN' && !lead.nextActionAt).length;

  async function createLead(payload: CreateLeadPayload) {
    setSaving(true);
    setError('');

    try {
      const createdLead = await apiPost<Lead>('/leads', payload);
      setLeads((current) => [createdLead, ...current]);
      setShowCreateModal(false);
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
      setSelectedLead((current) => (current?.id === lead.id ? updatedLead : current));
    } catch {
      setError('No se pudo mover el lead.');
    }
  }

  async function convertLead(lead: Lead) {
    setError('');

    try {
      await apiPost(`/leads/${lead.id}/convert`);
      setSelectedLead(null);
      await loadData();
    } catch {
      setError('No se pudo convertir el lead. Puede que ya esté convertido.');
    }
  }

  async function revertConversion(lead: Lead) {
    setError('');

    try {
      const updatedLead = await apiPost<Lead>(`/leads/${lead.id}/revert-conversion`);
      setLeads((current) => current.map((item) => (item.id === lead.id ? updatedLead : item)));
      setSelectedLead((current) => (current?.id === lead.id ? updatedLead : current));
      setError('Conversión revertida correctamente.');
    } catch {
      setError('No se pudo revertir la conversión.');
    }
  }

  async function deleteLead(lead: Lead) {
    const confirmed = window.confirm(
      `¿Eliminar el lead de ${lead.firstName} ${lead.lastName ?? ''}? Esta acción no se puede deshacer.`,
    );

    if (!confirmed) {
      return;
    }

    setError('');

    try {
      await apiDelete<{ deleted: boolean }>(`/leads/${lead.id}`);
      setLeads((current) => current.filter((item) => item.id !== lead.id));
      setSelectedLead((current) => (current?.id === lead.id ? null : current));
      setError('Lead eliminado correctamente.');
    } catch {
      setError('No se pudo eliminar el lead. Si está convertido, revierte la conversión primero.');
    }
  }

  async function markLost(lead: Lead) {
    const lostStage = stages.find((stage) => stage.key === 'LOST');

    setError('');

    try {
      const updatedLead = await apiPatch<Lead>(`/leads/${lead.id}`, {
        pipelineStageId: lostStage?.id ?? lead.pipelineStageId,
        status: 'LOST',
      });

      setLeads((current) => current.map((item) => (item.id === lead.id ? updatedLead : item)));
      setSelectedLead(updatedLead);
    } catch {
      setError('No se pudo marcar el lead como perdido.');
    }
  }

  async function createFollowUpTask(lead: Lead) {
    const dueAt = new Date();
    dueAt.setDate(dueAt.getDate() + 1);

    setError('');

    try {
      await apiPost('/tasks', {
        leadId: lead.id,
        title: `Seguimiento a ${lead.firstName} ${lead.lastName ?? ''}`.trim(),
        description: 'Contactar para continuar el proceso comercial.',
        type: 'SALES',
        status: 'PENDING',
        dueAt: dueAt.toISOString(),
      });
      setError('Tarea de seguimiento creada correctamente.');
    } catch {
      setError('No se pudo crear la tarea de seguimiento.');
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
              onChange={(event) => setFilters((current) => ({ ...current, query: event.target.value }))}
              placeholder="Buscar socios, leads o clases..."
              value={filters.query}
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

        <div className="content leads-content">
          <div className="page-heading leads-heading">
            <div>
              <h2>Leads</h2>
              <p>Gestiona oportunidades comerciales desde el primer contacto hasta el alta como socio.</p>
            </div>
          </div>

          {error ? <div className={error.includes('correctamente') ? 'notice notice-success' : 'notice notice-error'}>{error}</div> : null}

          <LeadMetrics
            conversionRate={conversionRate}
            noFollowUp={noFollowUp}
            openLeads={openLeads}
            trialScheduled={trialScheduled}
          />

          <LeadFilters
            filters={filters}
            onChange={setFilters}
            onCreate={() => setShowCreateModal(true)}
            stages={stages}
          />

          {loading ? (
            <div className="loading-card">Cargando tabla de leads...</div>
          ) : (
            <LeadsTable
              leads={filteredLeads}
              onConvert={convertLead}
              onCreateTask={createFollowUpTask}
              onDelete={deleteLead}
              onMarkLost={markLost}
              onMove={moveLead}
              onOpen={setSelectedLead}
              onRevertConversion={revertConversion}
              stages={stages}
            />
          )}
        </div>
      </section>

      {showCreateModal ? (
        <CreateLeadModal
          initialStageId={stages[0]?.id ?? ''}
          onClose={() => setShowCreateModal(false)}
          onSubmit={createLead}
          saving={saving}
          stages={stages}
        />
      ) : null}

      <LeadDetailDrawer
        lead={selectedLead}
        onClose={() => setSelectedLead(null)}
        onConvert={convertLead}
        onCreateTask={createFollowUpTask}
        onDelete={deleteLead}
        onMarkLost={markLost}
        onMove={moveLead}
        onRevertConversion={revertConversion}
        stages={stages}
      />
    </main>
  );
}

'use client';

import {
  ArrowRight,
  CalendarClock,
  Plus,
  Search,
  MoreHorizontal,
  CircleDot,
  X,
} from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import { CreateLeadPayload, Lead, PipelineStage } from '@/lib/api';

export const leadSources = [
  { label: 'Visita presencial', value: 'WALK_IN' },
  { label: 'Web', value: 'WEBSITE' },
  { label: 'Teléfono', value: 'PHONE' },
  { label: 'Redes sociales', value: 'SOCIAL_MEDIA' },
  { label: 'Recomendación', value: 'REFERRAL' },
  { label: 'Otro', value: 'OTHER' },
];

export interface LeadFiltersState {
  query: string;
  source: string;
  stageId: string;
  status: string;
}

export function LeadMetrics({
  conversionRate,
  noFollowUp,
  openLeads,
  trialScheduled,
}: {
  conversionRate: number;
  noFollowUp: number;
  openLeads: number;
  trialScheduled: number;
}) {
  return (
    <section className="lead-metrics">
      <MetricCard label="Leads abiertos" value={openLeads} tone="blue" />
      <MetricCard label="Pruebas agendadas" value={trialScheduled} tone="green" />
      <MetricCard label="Conversión a socio" value={`${conversionRate}%`} tone="dark" />
      <MetricCard label="Sin seguimiento" value={noFollowUp} tone="orange" />
    </section>
  );
}

function MetricCard({ label, tone, value }: { label: string; tone: string; value: number | string }) {
  return (
    <article className={`lead-metric lead-metric--${tone}`}>
      <span>{label}</span>
      <strong>{value}</strong>
    </article>
  );
}

export function LeadFilters({
  filters,
  onChange,
  onCreate,
  stages,
}: {
  filters: LeadFiltersState;
  onChange: (filters: LeadFiltersState) => void;
  onCreate: () => void;
  stages: PipelineStage[];
}) {
  return (
    <section className="lead-toolbar">
      <div className="lead-search">
        <Search size={18} />
        <input
          onChange={(event) => onChange({ ...filters, query: event.target.value })}
          placeholder="Buscar por nombre, teléfono, email o interés"
          value={filters.query}
        />
      </div>

      <div className="lead-filter-group">
        <select onChange={(event) => onChange({ ...filters, source: event.target.value })} value={filters.source}>
          <option value="">Todos los orígenes</option>
          {leadSources.map((source) => (
            <option key={source.value} value={source.value}>
              {source.label}
            </option>
          ))}
        </select>

        <select onChange={(event) => onChange({ ...filters, stageId: event.target.value })} value={filters.stageId}>
          <option value="">Todas las etapas</option>
          {stages.map((stage) => (
            <option key={stage.id} value={stage.id}>
              {stage.name}
            </option>
          ))}
        </select>

        <select onChange={(event) => onChange({ ...filters, status: event.target.value })} value={filters.status}>
          <option value="">Todos los estados</option>
          <option value="OPEN">Abiertos</option>
          <option value="CONVERTED">Convertidos</option>
          <option value="LOST">Perdidos</option>
        </select>
      </div>

      <button className="primary-action primary-action--compact" onClick={onCreate} type="button">
        <Plus size={18} />
        Nuevo lead
      </button>
    </section>
  );
}

export function LeadsTable({
  leads,
  onConvert,
  onCreateTask,
  onMarkLost,
  onMove,
  onOpen,
  stages,
}: {
  leads: Lead[];
  onConvert: (lead: Lead) => void;
  onCreateTask: (lead: Lead) => void;
  onMarkLost: (lead: Lead) => void;
  onMove: (lead: Lead, stageId: string) => void;
  onOpen: (lead: Lead) => void;
  stages: PipelineStage[];
}) {
  return (
    <section className="leads-table-card">
      <header>
        <div>
          <h3>Listado de leads</h3>
          <span>{leads.length} resultados</span>
        </div>
      </header>

      <div className="leads-table-wrap">
        <table className="leads-table">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Teléfono</th>
              <th>Email</th>
              <th>Origen</th>
              <th>Interés</th>
              <th>Etapa</th>
              <th>Estado</th>
              <th>Próxima acción</th>
              <th>Último contacto</th>
              <th>Fecha de creación</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {leads.length ? (
              leads.map((lead) => (
                <LeadTableRow
                  key={lead.id}
                  lead={lead}
                  onConvert={() => onConvert(lead)}
                  onCreateTask={() => onCreateTask(lead)}
                  onMarkLost={() => onMarkLost(lead)}
                  onMove={(stageId) => onMove(lead, stageId)}
                  onOpen={() => onOpen(lead)}
                  stages={stages}
                />
              ))
            ) : (
              <tr>
                <td className="leads-empty-cell" colSpan={11}>
                  No hay leads que coincidan con los filtros actuales.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}

function LeadTableRow({
  lead,
  onConvert,
  onCreateTask,
  onMarkLost,
  onMove,
  onOpen,
  stages,
}: {
  lead: Lead;
  onConvert: () => void;
  onCreateTask: () => void;
  onMarkLost: () => void;
  onMove: (stageId: string) => void;
  onOpen: () => void;
  stages: PipelineStage[];
}) {
  return (
    <tr className="lead-data-row">
      <td data-label="Nombre">
        <button className="lead-name-button" onClick={onOpen} type="button">
          {lead.firstName} {lead.lastName ?? ''}
        </button>
      </td>
      <td data-label="Teléfono">{lead.phone ?? 'Sin teléfono'}</td>
      <td data-label="Email">{lead.email ?? 'Sin email'}</td>
      <td data-label="Origen">
        <SourceBadge source={lead.source} />
      </td>
      <td data-label="Interés">{lead.interest ?? 'Sin interés indicado'}</td>
      <td data-label="Etapa">
        <select
          aria-label="Cambiar etapa"
          className="stage-select stage-select--table"
          onChange={(event) => onMove(event.target.value)}
          value={lead.pipelineStageId}
        >
          {stages.map((stage) => (
            <option key={stage.id} value={stage.id}>
              {stage.name}
            </option>
          ))}
        </select>
      </td>
      <td data-label="Estado">
        <StatusBadge status={lead.status} />
      </td>
      <td data-label="Próxima acción">
        <span className={lead.nextActionAt ? 'next-action' : 'next-action next-action--warning'}>
          <CalendarClock size={14} />
          {nextActionLabel(lead)}
        </span>
      </td>
      <td data-label="Último contacto">{formatDate(lead.updatedAt)}</td>
      <td data-label="Fecha de creación">{formatDate(lead.createdAt)}</td>
      <td data-label="Acciones">
        <LeadActionsMenu
          lead={lead}
          onConvert={onConvert}
          onCreateTask={onCreateTask}
          onMarkLost={onMarkLost}
          onOpen={onOpen}
        />
      </td>
    </tr>
  );
}

function LeadActionsMenu({
  lead,
  onConvert,
  onCreateTask,
  onMarkLost,
  onOpen,
}: {
  lead: Lead;
  onConvert: () => void;
  onCreateTask: () => void;
  onMarkLost: () => void;
  onOpen: () => void;
}) {
  const [open, setOpen] = useState(false);

  function run(action: () => void) {
    setOpen(false);
    action();
  }

  return (
    <div className="lead-actions-menu">
      <button aria-label="Abrir acciones" onClick={() => setOpen((current) => !current)} type="button">
        <MoreHorizontal size={18} />
      </button>
      {open ? (
        <div className="lead-actions-popover">
          <button onClick={() => run(onOpen)} type="button">
            Ver detalle
          </button>
          <button onClick={() => run(onOpen)} type="button">
            Editar
          </button>
          <button onClick={() => run(onOpen)} type="button">
            Cambiar etapa
          </button>
          <button onClick={() => run(onCreateTask)} type="button">
            Crear tarea
          </button>
          {lead.status === 'OPEN' ? (
            <button onClick={() => run(onConvert)} type="button">
              Convertir a socio
            </button>
          ) : null}
          {lead.status !== 'LOST' ? (
            <button className="danger-action" onClick={() => run(onMarkLost)} type="button">
              Marcar como perdido
            </button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

export function LeadDetailDrawer({
  lead,
  onClose,
  onConvert,
  onCreateTask,
  onMarkLost,
  onMove,
  stages,
}: {
  lead: Lead | null;
  onClose: () => void;
  onConvert: (lead: Lead) => void;
  onCreateTask: (lead: Lead) => void;
  onMarkLost: (lead: Lead) => void;
  onMove: (lead: Lead, stageId: string) => void;
  stages: PipelineStage[];
}) {
  if (!lead) return null;

  return (
    <div className="drawer-backdrop" role="presentation">
      <aside className="lead-drawer" aria-label="Ficha rápida del lead">
        <header>
          <div>
            <span className="drawer-eyebrow">Ficha rápida</span>
            <h2>
              {lead.firstName} {lead.lastName ?? ''}
            </h2>
            <p>{lead.interest ?? 'Sin interés indicado'}</p>
          </div>
          <button onClick={onClose} type="button">
            <X size={20} />
          </button>
        </header>

        <section className="drawer-section">
          <h3>Datos del lead</h3>
          <dl className="lead-definition-list">
            <div>
              <dt>Contacto</dt>
              <dd>{lead.phone ?? lead.email ?? 'Sin contacto'}</dd>
            </div>
            <div>
              <dt>Origen</dt>
              <dd>{translateSource(lead.source)}</dd>
            </div>
            <div>
              <dt>Estado</dt>
              <dd>
                <StatusBadge status={lead.status} />
              </dd>
            </div>
          </dl>
        </section>

        <section className="drawer-section">
          <h3>Etapa actual</h3>
          <select
            className="stage-select"
            onChange={(event) => onMove(lead, event.target.value)}
            value={lead.pipelineStageId}
          >
            {stages.map((stage) => (
              <option key={stage.id} value={stage.id}>
                {stage.name}
              </option>
            ))}
          </select>
        </section>

        <section className="drawer-section">
          <h3>Historial básico</h3>
          <div className="timeline-lite">
            <div>
              <CircleDot size={16} />
              <span>Lead creado el {formatDate(lead.createdAt)}</span>
            </div>
            <div>
              <CircleDot size={16} />
              <span>Última actualización el {formatDate(lead.updatedAt)}</span>
            </div>
          </div>
        </section>

        <section className="drawer-section">
          <h3>Próxima tarea</h3>
          <div className={lead.nextActionAt ? 'next-task-box' : 'next-task-box next-task-box--warning'}>
            <CalendarClock size={18} />
            <span>{nextActionLabel(lead)}</span>
          </div>
        </section>

        <footer className="drawer-actions">
          <button onClick={() => onMove(lead, lead.pipelineStageId)} type="button">
            Mover etapa
          </button>
          <button onClick={() => onCreateTask(lead)} type="button">
            Crear tarea
          </button>
          {lead.status === 'OPEN' ? (
            <button className="drawer-actions__primary" onClick={() => onConvert(lead)} type="button">
              Convertir a socio
            </button>
          ) : null}
          {lead.status !== 'LOST' ? (
            <button className="drawer-actions__danger" onClick={() => onMarkLost(lead)} type="button">
              Marcar perdido
            </button>
          ) : null}
        </footer>
      </aside>
    </div>
  );
}

export function CreateLeadModal({
  initialStageId,
  onClose,
  onSubmit,
  saving,
  stages,
}: {
  initialStageId: string;
  onClose: () => void;
  onSubmit: (payload: CreateLeadPayload) => void;
  saving: boolean;
  stages: PipelineStage[];
}) {
  const [form, setForm] = useState<CreateLeadPayload>({
    pipelineStageId: initialStageId,
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    source: 'WALK_IN',
    interest: '',
  });
  const [validation, setValidation] = useState('');

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!form.firstName?.trim()) {
      setValidation('El nombre es obligatorio.');
      return;
    }

    if (!form.phone?.trim() && !form.email?.trim()) {
      setValidation('Indica al menos un teléfono o email.');
      return;
    }

    setValidation('');
    onSubmit({
      ...form,
      firstName: form.firstName.trim(),
      lastName: form.lastName?.trim() || undefined,
      phone: form.phone?.trim() || undefined,
      email: form.email?.trim() || undefined,
      interest: form.interest?.trim() || undefined,
    });
  }

  return (
    <div className="modal-backdrop" role="presentation">
      <form className="modal-card create-lead-modal" onSubmit={submit}>
        <header>
          <div>
            <h2>Nuevo lead</h2>
            <p>Alta rápida para empezar seguimiento comercial.</p>
          </div>
          <button onClick={onClose} type="button">
            Cerrar
          </button>
        </header>

        {validation ? <p className="form-error">{validation}</p> : null}

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
            <span>Teléfono</span>
            <input
              onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))}
              value={form.phone}
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
          <label className="field">
            <span>Etapa inicial</span>
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
          <label className="field field--wide">
            <span>Interés principal</span>
            <input
              onChange={(event) => setForm((current) => ({ ...current, interest: event.target.value }))}
              placeholder="Ej. Prueba de HIIT, plan premium, bono mensual..."
              value={form.interest}
            />
          </label>
          <label className="field field--wide">
            <span>Nota interna opcional</span>
            <input placeholder="Ej. Viene recomendado por un socio actual." />
          </label>
        </div>

        <button className="primary-action" disabled={saving} type="submit">
          {saving ? 'Guardando...' : 'Crear lead'}
          <ArrowRight size={18} />
        </button>
      </form>
    </div>
  );
}

export function StatusBadge({ status }: { status: string }) {
  const label = status === 'CONVERTED' ? 'Convertido' : status === 'LOST' ? 'Perdido' : 'Abierto';
  return <span className={`status-badge status-badge--${status.toLowerCase()}`}>{label}</span>;
}

export function SourceBadge({ source }: { source: string }) {
  return <span className="source-badge">{translateSource(source)}</span>;
}

function nextActionLabel(lead: Lead) {
  if (lead.status === 'CONVERTED') return 'Socio creado desde este lead';
  if (lead.status === 'LOST') return 'Lead marcado como perdido';
  if (!lead.nextActionAt) return 'Crear seguimiento pendiente';

  return `Seguimiento: ${formatDate(lead.nextActionAt)}`;
}

function translateSource(source: string) {
  const match = leadSources.find((item) => item.value === source);
  return match?.label ?? 'Otro';
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: 'short',
  }).format(new Date(value));
}

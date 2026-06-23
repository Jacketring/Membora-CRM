'use client';

import {
  Bell,
  CalendarClock,
  CalendarDays,
  CheckCircle2,
  Dumbbell,
  LayoutDashboard,
  LogOut,
  MoreHorizontal,
  Plus,
  Search,
  Users,
  WalletCards,
  X,
} from 'lucide-react';
import Link from 'next/link';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import {
  apiDelete,
  apiGet,
  apiPatch,
  apiPost,
  clearSession,
  CreateTaskPayload,
  getStoredToken,
  getStoredUser,
  Member,
  StaffUser,
  Task,
} from '@/lib/api';

const taskTypes = [
  { label: 'Comercial', value: 'SALES' },
  { label: 'Retención', value: 'RETENTION' },
  { label: 'Pago', value: 'PAYMENT' },
  { label: 'Operativa', value: 'OPERATIONAL' },
  { label: 'Otra', value: 'OTHER' },
] as const;

const taskStatuses = [
  { label: 'Pendiente', value: 'PENDING' },
  { label: 'Completada', value: 'COMPLETED' },
  { label: 'Cancelada', value: 'CANCELLED' },
] as const;

interface TaskFiltersState {
  query: string;
  type: string;
  status: string;
}

const initialFilters: TaskFiltersState = {
  query: '',
  type: '',
  status: '',
};

const TASKS_PAGE_SIZE = 10;

export default function TasksPage() {
  const router = useRouter();
  const user = useMemo(() => getStoredUser(), []);
  const [tasks, setTasks] = useState<Task[]>([]);
  const [members, setMembers] = useState<Member[]>([]);
  const [staffUsers, setStaffUsers] = useState<StaffUser[]>([]);
  const [filters, setFilters] = useState<TaskFiltersState>(initialFilters);
  const [selectedTask, setSelectedTask] = useState<Task | null>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [messageType, setMessageType] = useState<'success' | 'error'>('error');
  const [selectedTaskIds, setSelectedTaskIds] = useState<string[]>([]);
  const [currentPage, setCurrentPage] = useState(1);

  useEffect(() => {
    if (!getStoredToken()) {
      router.replace('/login');
      return;
    }

    loadData();
  }, [router]);

  useEffect(() => {
    setCurrentPage(1);
    setSelectedTaskIds([]);
  }, [filters]);

  async function loadData() {
    setMessage('');
    setLoading(true);

    try {
      const [loadedTasks, loadedMembers] = await Promise.all([
        apiGet<Task[]>('/tasks'),
        apiGet<Member[]>('/members'),
      ]);
      setTasks(loadedTasks);
      setMembers(loadedMembers);

      try {
        const loadedStaffUsers = await apiGet<StaffUser[]>('/users');
        setStaffUsers(loadedStaffUsers);
      } catch {
        const currentUser = getStoredUser();
        setStaffUsers(
          currentUser
            ? [
                {
                  id: currentUser.id,
                  name: currentUser.name,
                  email: currentUser.email,
                  status: 'ACTIVE',
                  role: {
                    key: currentUser.role,
                    name: translateStaffRole(currentUser.role),
                  },
                },
              ]
            : [],
        );
      }
    } catch {
      showError('No se pudieron cargar las tareas.');
    } finally {
      setLoading(false);
    }
  }

  async function loadTasks() {
    const loadedTasks = await apiGet<Task[]>('/tasks');
    setTasks(loadedTasks);
  }

  function logout() {
    clearSession();
    router.push('/login');
  }

  function showSuccess(text: string) {
    setMessageType('success');
    setMessage(text);
  }

  function showError(text: string) {
    setMessageType('error');
    setMessage(text);
  }

  const filteredTasks = tasks.filter((task) => {
    const term = filters.query.trim().toLowerCase();
    const relatedName = getRelatedName(task);
    const matchesQuery =
      !term ||
      [task.title, task.description, relatedName, task.assignedUser?.name]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(term));

    return (
      matchesQuery &&
      (!filters.type || task.type === filters.type) &&
      (!filters.status || task.status === filters.status)
    );
  });

  const now = new Date();
  const todayKey = toDateKey(now);
  const pendingTasks = tasks.filter((task) => task.status === 'PENDING').length;
  const completedTasks = tasks.filter((task) => task.status === 'COMPLETED').length;
  const overdueTasks = tasks.filter((task) => task.status === 'PENDING' && task.dueAt && new Date(task.dueAt) < now).length;
  const todayTasks = tasks.filter((task) => task.dueAt && toDateKey(new Date(task.dueAt)) === todayKey).length;
  const totalPages = Math.max(1, Math.ceil(filteredTasks.length / TASKS_PAGE_SIZE));
  const safeCurrentPage = Math.min(currentPage, totalPages);
  const paginatedTasks = filteredTasks.slice(
    (safeCurrentPage - 1) * TASKS_PAGE_SIZE,
    safeCurrentPage * TASKS_PAGE_SIZE,
  );
  const selectedTasksCount = selectedTaskIds.length;

  async function createTask(payload: CreateTaskPayload, memberIds: string[]) {
    setSaving(true);
    setMessage('');

    try {
      await apiPost<Task>('/tasks', {
        ...payload,
        memberIds,
      });

      setShowCreateModal(false);
      await loadTasks();
      showSuccess('Tarea creada correctamente.');
    } catch {
      showError('No se pudo crear la tarea.');
    } finally {
      setSaving(false);
    }
  }

  async function updateTaskStatus(task: Task, status: Task['status']) {
    setMessage('');

    try {
      await apiPatch<Task>(`/tasks/${task.id}`, { status });
      await loadTasks();
      setSelectedTask((current) => (current?.id === task.id ? null : current));
      showSuccess(status === 'COMPLETED' ? 'Tarea completada correctamente.' : 'Tarea actualizada correctamente.');
    } catch {
      showError('No se pudo actualizar la tarea.');
    }
  }

  function toggleTaskSelection(taskId: string) {
    setSelectedTaskIds((current) =>
      current.includes(taskId) ? current.filter((id) => id !== taskId) : [...current, taskId],
    );
  }

  function toggleVisibleTasksSelection(taskIds: string[]) {
    setSelectedTaskIds((current) => {
      const allVisibleSelected = taskIds.length > 0 && taskIds.every((id) => current.includes(id));

      if (allVisibleSelected) {
        return current.filter((id) => !taskIds.includes(id));
      }

      return Array.from(new Set([...current, ...taskIds]));
    });
  }

  async function completeSelectedTasks() {
    const selectedTasks = tasks.filter((task) => selectedTaskIds.includes(task.id));

    if (!selectedTasks.length) {
      return;
    }

    setMessage('');

    try {
      await Promise.all(
        selectedTasks.map((task) => apiPatch<Task>(`/tasks/${task.id}`, { status: 'COMPLETED' })),
      );
      setSelectedTaskIds([]);
      await loadTasks();
      showSuccess(`${selectedTasks.length} tareas marcadas como completadas.`);
    } catch {
      showError('No se pudieron completar las tareas seleccionadas.');
    }
  }

  async function deleteTask(task: Task) {
    const confirmed = window.confirm(`¿Eliminar la tarea "${task.title}"? Esta acción no se puede deshacer.`);

    if (!confirmed) {
      return;
    }

    setMessage('');

    try {
      await apiDelete<{ deleted: boolean }>(`/tasks/${task.id}`);
      setSelectedTaskIds((current) => current.filter((id) => id !== task.id));
      setSelectedTask((current) => (current?.id === task.id ? null : current));
      await loadTasks();
      showSuccess('Tarea eliminada correctamente.');
    } catch {
      showError('No se pudo eliminar la tarea.');
    }
  }

  async function deleteSelectedTasks() {
    const selectedTasks = tasks.filter((task) => selectedTaskIds.includes(task.id));

    if (!selectedTasks.length) {
      return;
    }

    const confirmed = window.confirm(
      `¿Eliminar ${selectedTasks.length} tareas seleccionadas? Esta acción no se puede deshacer.`,
    );

    if (!confirmed) {
      return;
    }

    setMessage('');

    try {
      await Promise.all(selectedTasks.map((task) => apiDelete<{ deleted: boolean }>(`/tasks/${task.id}`)));
      setSelectedTaskIds([]);
      setSelectedTask((current) => (current && selectedTaskIds.includes(current.id) ? null : current));
      await loadTasks();
      showSuccess(`${selectedTasks.length} tareas eliminadas correctamente.`);
    } catch {
      showError('No se pudieron eliminar las tareas seleccionadas.');
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
          <Link className="active" href="/tasks">
            <CheckCircle2 size={20} />
            Tareas
          </Link>
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
              placeholder="Buscar tareas, leads o responsables..."
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
              <h2>Tareas</h2>
              <p>Organiza seguimientos comerciales, pagos pendientes y acciones operativas del centro.</p>
            </div>
          </div>

          {message ? (
            <div className={messageType === 'success' ? 'notice notice-success' : 'notice notice-error'}>{message}</div>
          ) : null}

          <TaskMetrics
            completedTasks={completedTasks}
            overdueTasks={overdueTasks}
            pendingTasks={pendingTasks}
            todayTasks={todayTasks}
          />

          <TaskFilters filters={filters} onChange={setFilters} onCreate={() => setShowCreateModal(true)} />

          {loading ? (
            <div className="loading-card">Cargando tabla de tareas...</div>
          ) : (
            <>
              {selectedTasksCount ? (
                <BulkTaskActions
                  onClear={() => setSelectedTaskIds([])}
                  onComplete={completeSelectedTasks}
                  onDelete={deleteSelectedTasks}
                  selectedCount={selectedTasksCount}
                />
              ) : null}

              <TasksTable
                onCancel={(task) => updateTaskStatus(task, 'CANCELLED')}
                onComplete={(task) => updateTaskStatus(task, 'COMPLETED')}
                onDelete={deleteTask}
                onOpen={setSelectedTask}
                onReopen={(task) => updateTaskStatus(task, 'PENDING')}
                onToggleTask={toggleTaskSelection}
                onToggleVisibleTasks={toggleVisibleTasksSelection}
                selectedTaskIds={selectedTaskIds}
                tasks={paginatedTasks}
                totalTasks={filteredTasks.length}
              />

              <PaginationControls
                currentPage={safeCurrentPage}
                onPageChange={setCurrentPage}
                pageSize={TASKS_PAGE_SIZE}
                totalItems={filteredTasks.length}
                totalPages={totalPages}
              />
            </>
          )}
        </div>
      </section>

      {showCreateModal ? (
        <CreateTaskModal
          members={members}
          onClose={() => setShowCreateModal(false)}
          onSubmit={createTask}
          saving={saving}
          staffUsers={staffUsers}
        />
      ) : null}

      <TaskDetailDrawer
        onCancel={(task) => updateTaskStatus(task, 'CANCELLED')}
        onClose={() => setSelectedTask(null)}
        onComplete={(task) => updateTaskStatus(task, 'COMPLETED')}
        onDelete={deleteTask}
        onReopen={(task) => updateTaskStatus(task, 'PENDING')}
        task={selectedTask}
      />
    </main>
  );
}

function TaskMetrics({
  completedTasks,
  overdueTasks,
  pendingTasks,
  todayTasks,
}: {
  completedTasks: number;
  overdueTasks: number;
  pendingTasks: number;
  todayTasks: number;
}) {
  return (
    <section className="lead-metrics">
      <MetricCard label="Pendientes" value={pendingTasks} tone="blue" />
      <MetricCard label="Para hoy" value={todayTasks} tone="green" />
      <MetricCard label="Completadas" value={completedTasks} tone="dark" />
      <MetricCard label="Vencidas" value={overdueTasks} tone="orange" />
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

function TaskFilters({
  filters,
  onChange,
  onCreate,
}: {
  filters: TaskFiltersState;
  onChange: (filters: TaskFiltersState) => void;
  onCreate: () => void;
}) {
  return (
    <section className="lead-toolbar">
      <div className="lead-search">
        <Search size={18} />
        <input
          onChange={(event) => onChange({ ...filters, query: event.target.value })}
          placeholder="Buscar por título, descripción o persona vinculada"
          value={filters.query}
        />
      </div>
      <div className="lead-filter-group">
        <label>
          <span>Tipo</span>
          <select onChange={(event) => onChange({ ...filters, type: event.target.value })} value={filters.type}>
            <option value="">Todos</option>
            {taskTypes.map((type) => (
              <option key={type.value} value={type.value}>
                {type.label}
              </option>
            ))}
          </select>
        </label>
        <label>
          <span>Estado</span>
          <select onChange={(event) => onChange({ ...filters, status: event.target.value })} value={filters.status}>
            <option value="">Todos</option>
            {taskStatuses.map((status) => (
              <option key={status.value} value={status.value}>
                {status.label}
              </option>
            ))}
          </select>
        </label>
      </div>
      <button className="primary-action primary-action--compact" onClick={onCreate} type="button">
        <Plus size={18} />
        Nueva tarea
      </button>
    </section>
  );
}

function TasksTable({
  onCancel,
  onComplete,
  onDelete,
  onOpen,
  onReopen,
  onToggleTask,
  onToggleVisibleTasks,
  selectedTaskIds,
  tasks,
  totalTasks,
}: {
  onCancel: (task: Task) => void;
  onComplete: (task: Task) => void;
  onDelete: (task: Task) => void;
  onOpen: (task: Task) => void;
  onReopen: (task: Task) => void;
  onToggleTask: (taskId: string) => void;
  onToggleVisibleTasks: (taskIds: string[]) => void;
  selectedTaskIds: string[];
  tasks: Task[];
  totalTasks: number;
}) {
  const visibleTaskIds = tasks.map((task) => task.id);
  const allVisibleSelected = visibleTaskIds.length > 0 && visibleTaskIds.every((id) => selectedTaskIds.includes(id));

  return (
    <section className="leads-table-card">
      <header>
        <div>
          <h3>Listado de tareas</h3>
          <span>{totalTasks} resultados</span>
        </div>
      </header>

      <div className="leads-table-wrap">
        <table className="leads-table tasks-table">
          <thead>
            <tr>
              <th className="select-column">
                <input
                  aria-label="Seleccionar tareas visibles"
                  checked={allVisibleSelected}
                  disabled={!visibleTaskIds.length}
                  onChange={() => onToggleVisibleTasks(visibleTaskIds)}
                  type="checkbox"
                />
              </th>
              <th>Tarea</th>
              <th>Tipo</th>
              <th>Vinculado a</th>
              <th>Responsable</th>
              <th>Vencimiento</th>
              <th>Estado</th>
              <th>Creación</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {tasks.length ? (
              tasks.map((task) => (
                <TaskTableRow
                  key={task.id}
                  onCancel={() => onCancel(task)}
                  onComplete={() => onComplete(task)}
                  onDelete={() => onDelete(task)}
                  onOpen={() => onOpen(task)}
                  onReopen={() => onReopen(task)}
                  onToggle={() => onToggleTask(task.id)}
                  selected={selectedTaskIds.includes(task.id)}
                  task={task}
                />
              ))
            ) : (
              <tr>
                <td className="leads-empty-cell" colSpan={9}>
                  No hay tareas que coincidan con los filtros actuales.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}

function TaskTableRow({
  onCancel,
  onComplete,
  onDelete,
  onOpen,
  onReopen,
  onToggle,
  selected,
  task,
}: {
  onCancel: () => void;
  onComplete: () => void;
  onDelete: () => void;
  onOpen: () => void;
  onReopen: () => void;
  onToggle: () => void;
  selected: boolean;
  task: Task;
}) {
  return (
    <tr className="lead-data-row">
      <td className="select-column" data-label="Seleccionar">
        <input
          aria-label={`Seleccionar tarea ${task.title}`}
          checked={selected}
          onChange={onToggle}
          type="checkbox"
        />
      </td>
      <td data-label="Tarea">
        <button className="lead-name-button" onClick={onOpen} type="button">
          {task.title}
        </button>
        <small className="task-description">{task.description ?? 'Sin descripción'}</small>
      </td>
      <td data-label="Tipo">
        <TaskTypeBadge type={task.type} />
      </td>
      <td data-label="Vinculado a">
        <TaskLinkedMembers task={task} />
      </td>
      <td data-label="Responsable">{task.assignedUser?.name ?? 'Sin asignar'}</td>
      <td data-label="Vencimiento">
        <span className={isOverdue(task) ? 'next-action next-action--warning' : 'next-action'}>
          <CalendarClock size={14} />
          {formatDate(task.dueAt)}
        </span>
      </td>
      <td data-label="Estado">
        <TaskStatusBadge status={task.status} />
      </td>
      <td data-label="Creación">{formatDate(task.createdAt)}</td>
      <td data-label="Acciones">
        <TaskActionsMenu
          onCancel={onCancel}
          onComplete={onComplete}
          onDelete={onDelete}
          onOpen={onOpen}
          onReopen={onReopen}
          task={task}
        />
      </td>
    </tr>
  );
}

function TaskActionsMenu({
  onCancel,
  onComplete,
  onDelete,
  onOpen,
  onReopen,
  task,
}: {
  onCancel: () => void;
  onComplete: () => void;
  onDelete: () => void;
  onOpen: () => void;
  onReopen: () => void;
  task: Task;
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
          {task.status !== 'COMPLETED' ? (
            <button onClick={() => run(onComplete)} type="button">
              Marcar completada
            </button>
          ) : (
            <button onClick={() => run(onReopen)} type="button">
              Reabrir tarea
            </button>
          )}
          {task.status !== 'CANCELLED' ? (
            <button className="danger-action" onClick={() => run(onCancel)} type="button">
              Cancelar tarea
            </button>
          ) : null}
          <button className="danger-action" onClick={() => run(onDelete)} type="button">
            Eliminar tarea
          </button>
        </div>
      ) : null}
    </div>
  );
}

function BulkTaskActions({
  onClear,
  onComplete,
  onDelete,
  selectedCount,
}: {
  onClear: () => void;
  onComplete: () => void;
  onDelete: () => void;
  selectedCount: number;
}) {
  return (
    <section className="bulk-actions-bar">
      <strong>{selectedCount} tareas seleccionadas</strong>
      <div>
        <button onClick={onComplete} type="button">
          Marcar completadas
        </button>
        <button className="danger-action" onClick={onDelete} type="button">
          Eliminar
        </button>
        <button className="ghost-action" onClick={onClear} type="button">
          Limpiar selección
        </button>
      </div>
    </section>
  );
}

function PaginationControls({
  currentPage,
  onPageChange,
  pageSize,
  totalItems,
  totalPages,
}: {
  currentPage: number;
  onPageChange: (page: number) => void;
  pageSize: number;
  totalItems: number;
  totalPages: number;
}) {
  if (totalItems <= pageSize) {
    return null;
  }

  const pages = Array.from({ length: totalPages }, (_, index) => index + 1);
  const firstItem = (currentPage - 1) * pageSize + 1;
  const lastItem = Math.min(currentPage * pageSize, totalItems);

  return (
    <nav className="pagination-controls" aria-label="PaginaciÃ³n de tareas">
      <span>
        Mostrando {firstItem}-{lastItem} de {totalItems}
      </span>
      <div className="pagination-pages">
        <button disabled={currentPage === 1} onClick={() => onPageChange(currentPage - 1)} type="button">
          Anterior
        </button>
        {pages.map((page) => (
          <button
            className={page === currentPage ? 'active' : ''}
            key={page}
            onClick={() => onPageChange(page)}
            type="button"
          >
            {page}
          </button>
        ))}
        <button disabled={currentPage === totalPages} onClick={() => onPageChange(currentPage + 1)} type="button">
          Siguiente
        </button>
      </div>
    </nav>
  );
}

function TaskLinkedMembers({ task }: { task: Task }) {
  const [open, setOpen] = useState(false);
  const linkedMembers = getTaskMembers(task);

  if (linkedMembers.length === 0) {
    if (task.lead) {
      return (
        <span>
          {task.lead.firstName} {task.lead.lastName ?? ''}
        </span>
      );
    }

    return <span>Sin vincular</span>;
  }

  if (linkedMembers.length === 1) {
    return <span>{getTaskMemberName(linkedMembers[0])}</span>;
  }

  return (
    <div className="linked-members-menu">
      <button onClick={() => setOpen((current) => !current)} type="button">
        {linkedMembers.length} socios vinculados
      </button>
      {open ? (
        <div className="linked-members-popover">
          {linkedMembers.map((member) => (
            <div key={member.id}>
              <strong>{getTaskMemberName(member)}</strong>
              <small>{member.email ?? member.phone ?? 'Sin contacto'}</small>
            </div>
          ))}
        </div>
      ) : null}
    </div>
  );
}

function CreateTaskModal({
  members,
  onClose,
  onSubmit,
  saving,
  staffUsers,
}: {
  members: Member[];
  onClose: () => void;
  onSubmit: (payload: CreateTaskPayload, memberIds: string[]) => void;
  saving: boolean;
  staffUsers: StaffUser[];
}) {
  const storedUser = useMemo(() => getStoredUser(), []);
  const defaultAssignedUserId =
    staffUsers.find((staffUser) => staffUser.id === storedUser?.id)?.id ?? staffUsers[0]?.id ?? null;
  const [form, setForm] = useState<CreateTaskPayload>({
    title: '',
    description: '',
    type: 'SALES',
    status: 'PENDING',
    dueAt: '',
    assignedUserId: defaultAssignedUserId,
  });
  const [memberQuery, setMemberQuery] = useState('');
  const [selectedMembers, setSelectedMembers] = useState<Member[]>([]);
  const [validation, setValidation] = useState('');

  const filteredMembers = useMemo(() => {
    const term = memberQuery.trim().toLowerCase();
    const selectedIds = new Set(selectedMembers.map((member) => member.id));

    if (!term) {
      return [];
    }

    return members
      .filter((member) => !selectedIds.has(member.id))
      .filter((member) =>
        [member.firstName, member.lastName, member.email, member.phone]
          .filter(Boolean)
          .some((value) => String(value).toLowerCase().includes(term)),
      )
      .slice(0, 8);
  }, [memberQuery, members, selectedMembers]);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!form.title.trim()) {
      setValidation('El título es obligatorio.');
      return;
    }

    setValidation('');
    onSubmit({
      ...form,
      title: form.title.trim(),
      description: form.description?.trim() || undefined,
      dueAt: form.dueAt ? new Date(form.dueAt).toISOString() : null,
    }, selectedMembers.map((member) => member.id));
  }

  function selectMember(member: Member) {
    setSelectedMembers((current) => [...current, member]);
    setMemberQuery('');
  }

  function removeMember(memberId: string) {
    setSelectedMembers((current) => current.filter((member) => member.id !== memberId));
  }

  return (
    <div className="modal-backdrop" role="presentation">
      <form className="modal-card create-lead-modal" onSubmit={submit}>
        <header>
          <div>
            <h2>Nueva tarea</h2>
            <p>Registra una acción de seguimiento o gestión interna.</p>
          </div>
          <button onClick={onClose} type="button">
            Cerrar
          </button>
        </header>

        {validation ? <p className="form-error">{validation}</p> : null}

        <div className="form-grid">
          <label className="field field--wide">
            <span>Título</span>
            <input
              onChange={(event) => setForm((current) => ({ ...current, title: event.target.value }))}
              required
              value={form.title}
            />
          </label>
          <label className="field">
            <span>Tipo</span>
            <select
              onChange={(event) => setForm((current) => ({ ...current, type: event.target.value as Task['type'] }))}
              value={form.type}
            >
              {taskTypes.map((type) => (
                <option key={type.value} value={type.value}>
                  {type.label}
                </option>
              ))}
            </select>
          </label>
          <label className="field">
            <span>Vencimiento</span>
            <input
              onChange={(event) => setForm((current) => ({ ...current, dueAt: event.target.value }))}
              type="datetime-local"
              value={form.dueAt ?? ''}
            />
          </label>
          <label className="field field--wide">
            <span>Responsable</span>
            <select
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  assignedUserId: event.target.value || null,
                }))
              }
              value={form.assignedUserId ?? ''}
            >
              <option value="">Sin responsable</option>
              {staffUsers.map((staffUser) => (
                <option key={staffUser.id} value={staffUser.id}>
                  {staffUser.name} - {translateStaffRole(staffUser.role.key)}
                </option>
              ))}
            </select>
          </label>
          <label className="field field--wide">
            <span>Descripción</span>
            <input
              onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))}
              placeholder="Ej. Llamar para confirmar prueba, revisar pago pendiente..."
              value={form.description ?? ''}
            />
          </label>
          <div className="field field--wide member-picker">
            <span>Socios vinculados</span>
            <div className="member-picker__search">
              <Search size={18} />
              <input
                onChange={(event) => setMemberQuery(event.target.value)}
                placeholder="Escribe el nombre, email o teléfono de un socio"
                value={memberQuery}
              />
            </div>
            {selectedMembers.length ? (
              <div className="member-picker__selected">
                {selectedMembers.map((member) => (
                  <button key={member.id} onClick={() => removeMember(member.id)} type="button">
                    {getMemberName(member)}
                    <X size={14} />
                  </button>
                ))}
              </div>
            ) : (
              <small className="member-picker__hint">
                Si seleccionas varios socios, quedarán vinculados dentro de la misma tarea.
              </small>
            )}
            {memberQuery ? (
              <div className="member-picker__results">
                {filteredMembers.length ? (
                  filteredMembers.map((member) => (
                    <button key={member.id} onClick={() => selectMember(member)} type="button">
                      <strong>{getMemberName(member)}</strong>
                      <small>{member.email ?? member.phone ?? translateMemberStatus(member.status)}</small>
                    </button>
                  ))
                ) : (
                  <p>No hay socios que coincidan.</p>
                )}
              </div>
            ) : null}
          </div>
        </div>

        <button className="primary-action" disabled={saving} type="submit">
          {saving ? 'Guardando...' : 'Crear tarea'}
        </button>
      </form>
    </div>
  );
}

function TaskDetailDrawer({
  onCancel,
  onClose,
  onComplete,
  onDelete,
  onReopen,
  task,
}: {
  onCancel: (task: Task) => void;
  onClose: () => void;
  onComplete: (task: Task) => void;
  onDelete: (task: Task) => void;
  onReopen: (task: Task) => void;
  task: Task | null;
}) {
  if (!task) return null;

  return (
    <div className="drawer-backdrop" role="presentation">
      <aside className="lead-drawer" aria-label="Ficha rápida de la tarea">
        <header>
          <div>
            <span className="drawer-eyebrow">Ficha rápida</span>
            <h2>{task.title}</h2>
            <p>{task.description ?? 'Sin descripción'}</p>
          </div>
          <button onClick={onClose} type="button">
            <X size={20} />
          </button>
        </header>

        <section className="drawer-section">
          <h3>Datos de la tarea</h3>
          <dl className="lead-definition-list">
            <div>
              <dt>Tipo</dt>
              <dd>{translateTaskType(task.type)}</dd>
            </div>
            <div>
              <dt>Estado</dt>
              <dd>
                <TaskStatusBadge status={task.status} />
              </dd>
            </div>
            <div>
              <dt>Vinculada a</dt>
              <dd>
                <TaskLinkedMembers task={task} />
              </dd>
            </div>
          </dl>
        </section>

        <section className="drawer-section">
          <h3>Planificación</h3>
          <div className={isOverdue(task) ? 'next-task-box next-task-box--warning' : 'next-task-box'}>
            <CalendarClock size={18} />
            <span>{formatDate(task.dueAt)}</span>
          </div>
        </section>

        <footer className="drawer-actions">
          {task.status !== 'COMPLETED' ? (
            <button className="drawer-actions__primary" onClick={() => onComplete(task)} type="button">
              Marcar completada
            </button>
          ) : (
            <button onClick={() => onReopen(task)} type="button">
              Reabrir tarea
            </button>
          )}
          {task.status !== 'CANCELLED' ? (
            <button className="drawer-actions__danger" onClick={() => onCancel(task)} type="button">
              Cancelar tarea
            </button>
          ) : null}
          <button className="drawer-actions__danger" onClick={() => onDelete(task)} type="button">
            Eliminar tarea
          </button>
        </footer>
      </aside>
    </div>
  );
}

function TaskStatusBadge({ status }: { status: Task['status'] }) {
  const labels: Record<Task['status'], string> = {
    PENDING: 'Pendiente',
    COMPLETED: 'Completada',
    CANCELLED: 'Cancelada',
  };

  return <span className={`status-badge status-badge--${status.toLowerCase()}`}>{labels[status]}</span>;
}

function TaskTypeBadge({ type }: { type: Task['type'] }) {
  return <span className="source-badge">{translateTaskType(type)}</span>;
}

function translateTaskType(type: Task['type']) {
  return taskTypes.find((item) => item.value === type)?.label ?? 'Otra';
}

function getRelatedName(task: Task) {
  const linkedMembers = getTaskMembers(task);

  if (linkedMembers.length) {
    return linkedMembers.map(getTaskMemberName).join(', ');
  }

  if (task.lead) {
    return `${task.lead.firstName} ${task.lead.lastName ?? ''}`.trim();
  }

  if (task.member) {
    return `${task.member.firstName} ${task.member.lastName ?? ''}`.trim();
  }

  return 'Sin vincular';
}

function getTaskMembers(task: Task) {
  if (task.taskMembers?.length) {
    return task.taskMembers.map((item) => item.member);
  }

  return task.member ? [{ ...task.member, phone: null }] : [];
}

function getTaskMemberName(member: {
  firstName: string;
  lastName: string | null;
}) {
  return `${member.firstName} ${member.lastName ?? ''}`.trim();
}

function getMemberName(member: Member) {
  return `${member.firstName} ${member.lastName ?? ''}`.trim();
}

function translateMemberStatus(status: Member['status']) {
  const labels: Record<Member['status'], string> = {
    ACTIVE: 'Activo',
    INACTIVE: 'Inactivo',
    AT_RISK: 'En riesgo',
    CANCELLED: 'Cancelado',
    PAYMENT_PENDING: 'Pago pendiente',
  };

  return labels[status];
}

function translateStaffRole(role: string) {
  const labels: Record<string, string> = {
    GYM_ADMIN: 'Administrador',
    SALES_RECEPTION: 'Recepción / comercial',
    TRAINER: 'Entrenador',
    SUPERADMIN: 'Superadmin',
  };

  return labels[role] ?? role;
}

function isOverdue(task: Task) {
  return task.status === 'PENDING' && Boolean(task.dueAt) && new Date(task.dueAt as string) < new Date();
}

function formatDate(value: string | null) {
  if (!value) {
    return 'Sin fecha';
  }

  return new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

function toDateKey(date: Date) {
  return date.toISOString().slice(0, 10);
}

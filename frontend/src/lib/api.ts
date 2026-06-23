const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'https://crm.josehurtado.dev/api';
const TOKEN_KEY = 'membora_access_token';
const USER_KEY = 'membora_user';

export interface AuthUser {
  id: string;
  tenantId: string | null;
  tenantName: string | null;
  role: string;
  name: string;
  email: string;
}

export interface LoginResponse {
  accessToken: string;
  user: AuthUser;
}

export interface DashboardSummary {
  generatedAt: string;
  kpis: {
    activeMembers: number;
    openLeads: number;
    newMembersThisMonth: number;
    pendingPayments: number;
    overduePayments: number;
    overdueTasks: number;
    upcomingReservations: number;
    weeklyCheckIns: number;
    openAlerts: number;
    estimatedMrr: number;
  };
  recentLeads: Array<{
    id: string;
    firstName: string;
    lastName: string | null;
    email: string | null;
    status: string;
    pipelineStage: {
      name: string;
      key: string;
    };
  }>;
  upcomingTasks: Array<{
    id: string;
    title: string;
    status: string;
    dueAt: string | null;
    assignedUser: {
      name: string;
    } | null;
  }>;
  openRiskAlerts: Array<{
    id: string;
    type: string;
    severity: string;
    message: string;
    detectedAt: string;
  }>;
}

export interface PipelineStage {
  id: string;
  name: string;
  key: string;
  order: number;
  isFinal: boolean;
}

export interface Lead {
  id: string;
  tenantId: string;
  pipelineStageId: string;
  assignedUserId: string | null;
  firstName: string;
  lastName: string | null;
  email: string | null;
  phone: string | null;
  source: string;
  interest: string | null;
  status: 'OPEN' | 'CONVERTED' | 'LOST';
  lostReason: string | null;
  nextActionAt: string | null;
  createdAt: string;
  updatedAt: string;
  pipelineStage: PipelineStage;
  assignedUser: {
    id: string;
    name: string;
    email: string;
  } | null;
}

export interface CreateLeadPayload {
  pipelineStageId: string;
  firstName: string;
  lastName?: string;
  email?: string;
  phone?: string;
  source?: string;
  interest?: string;
  nextActionAt?: string;
}

export interface Task {
  id: string;
  tenantId: string;
  assignedUserId: string | null;
  leadId: string | null;
  memberId: string | null;
  title: string;
  description: string | null;
  type: 'SALES' | 'RETENTION' | 'PAYMENT' | 'OPERATIONAL' | 'OTHER';
  status: 'PENDING' | 'COMPLETED' | 'CANCELLED';
  dueAt: string | null;
  completedAt: string | null;
  createdAt: string;
  updatedAt: string;
  assignedUser: {
    id: string;
    name: string;
    email: string;
  } | null;
  lead: {
    id: string;
    firstName: string;
    lastName: string | null;
    email: string | null;
    status: string;
  } | null;
  member: {
    id: string;
    firstName: string;
    lastName: string | null;
    email: string | null;
    status: string;
  } | null;
  taskMembers: Array<{
    id: string;
    member: {
      id: string;
      firstName: string;
      lastName: string | null;
      email: string | null;
      phone: string | null;
      status: string;
    };
  }>;
}

export interface Member {
  id: string;
  tenantId: string;
  leadId: string | null;
  firstName: string;
  lastName: string | null;
  email: string | null;
  phone: string | null;
  status: 'ACTIVE' | 'INACTIVE' | 'AT_RISK' | 'CANCELLED' | 'PAYMENT_PENDING';
  joinedAt: string;
  cancelledAt: string | null;
  notes: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface StaffUser {
  id: string;
  name: string;
  email: string;
  status: string;
  role: {
    key: string;
    name: string;
  };
}

export interface CreateTaskPayload {
  title: string;
  description?: string | null;
  type?: Task['type'];
  status?: Task['status'];
  dueAt?: string | null;
  leadId?: string | null;
  memberId?: string | null;
  memberIds?: string[];
  assignedUserId?: string | null;
}

export function getStoredToken() {
  if (typeof window === 'undefined') {
    return null;
  }

  return window.localStorage.getItem(TOKEN_KEY);
}

export function getStoredUser(): AuthUser | null {
  if (typeof window === 'undefined') {
    return null;
  }

  const rawUser = window.localStorage.getItem(USER_KEY);
  return rawUser ? (JSON.parse(rawUser) as AuthUser) : null;
}

export function storeSession(session: LoginResponse) {
  window.localStorage.setItem(TOKEN_KEY, session.accessToken);
  window.localStorage.setItem(USER_KEY, JSON.stringify(session.user));
}

export function clearSession() {
  window.localStorage.removeItem(TOKEN_KEY);
  window.localStorage.removeItem(USER_KEY);
}

export async function login(email: string, password: string) {
  const response = await fetch(`${API_URL}/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ email, password }),
  });

  if (!response.ok) {
    throw new Error('Credenciales incorrectas');
  }

  return (await response.json()) as LoginResponse;
}

export async function apiGet<T>(path: string) {
  const token = getStoredToken();

  if (!token) {
    throw new Error('Sesión no iniciada');
  }

  const response = await fetch(`${API_URL}${path}`, {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });

  if (!response.ok) {
    throw new Error(`Error ${response.status}`);
  }

  return (await response.json()) as T;
}

export async function apiPost<T>(path: string, body?: unknown) {
  const token = getStoredToken();

  if (!token) {
    throw new Error('Sesión no iniciada');
  }

  const response = await fetch(`${API_URL}${path}`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  if (!response.ok) {
    throw new Error(`Error ${response.status}`);
  }

  return (await response.json()) as T;
}

export async function apiPatch<T>(path: string, body: unknown) {
  const token = getStoredToken();

  if (!token) {
    throw new Error('Sesión no iniciada');
  }

  const response = await fetch(`${API_URL}${path}`, {
    method: 'PATCH',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(body),
  });

  if (!response.ok) {
    throw new Error(`Error ${response.status}`);
  }

  return (await response.json()) as T;
}

export async function apiDelete<T>(path: string) {
  const token = getStoredToken();

  if (!token) {
    throw new Error('SesiÃ³n no iniciada');
  }

  const response = await fetch(`${API_URL}${path}`, {
    method: 'DELETE',
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });

  if (!response.ok) {
    throw new Error(`Error ${response.status}`);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}

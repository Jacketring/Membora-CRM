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

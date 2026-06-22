import { RoleKey } from '@prisma/client';

export interface AuthUser {
  userId: string;
  tenantId: string | null;
  role: RoleKey;
  email: string;
}

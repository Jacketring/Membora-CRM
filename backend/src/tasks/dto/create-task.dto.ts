import { TaskStatus, TaskType } from '@prisma/client';

export interface CreateTaskDto {
  assignedUserId?: string | null;
  leadId?: string | null;
  memberId?: string | null;
  title: string;
  description?: string | null;
  type?: TaskType;
  status?: TaskStatus;
  dueAt?: string | null;
}

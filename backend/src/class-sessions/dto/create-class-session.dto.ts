import { ClassSessionStatus } from '@prisma/client';

export interface CreateClassSessionDto {
  classTypeId: string;
  trainerUserId?: string | null;
  startsAt: string;
  endsAt?: string | null;
  capacity: number;
  status?: ClassSessionStatus;
}

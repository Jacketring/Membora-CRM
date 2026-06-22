import { CheckInMethod } from '@prisma/client';

export interface CreateCheckInDto {
  memberId: string;
  classSessionId?: string | null;
  reservationId?: string | null;
  method?: CheckInMethod;
  checkedInAt?: string;
}

import { PaymentMethod, PaymentStatus } from '@prisma/client';

export interface CreatePaymentDto {
  memberId: string;
  subscriptionId?: string | null;
  amount: string | number;
  currency?: string;
  paymentMethod?: PaymentMethod;
  status?: PaymentStatus;
  paidAt?: string | null;
  dueDate?: string | null;
  notes?: string | null;
}

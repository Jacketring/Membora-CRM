import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { PaymentMethod, PaymentStatus, RoleKey } from '@prisma/client';
import { AuthUser } from '../auth/auth-user';
import { PrismaService } from '../prisma/prisma.service';
import { CreatePaymentDto } from './dto/create-payment.dto';

@Injectable()
export class PaymentsService {
  constructor(private readonly prisma: PrismaService) {}

  findAll(user: AuthUser) {
    const tenantId = this.requireTenant(user);

    return this.prisma.payment.findMany({
      where: { tenantId },
      include: {
        member: {
          select: {
            id: true,
            firstName: true,
            lastName: true,
            email: true,
            status: true,
          },
        },
        subscription: {
          include: {
            membershipPlan: true,
          },
        },
      },
      orderBy: { createdAt: 'desc' },
    });
  }

  async create(user: AuthUser, dto: CreatePaymentDto) {
    const tenantId = this.requireTenant(user);

    if (!dto.memberId) {
      throw new BadRequestException('memberId is required');
    }

    const amount = this.parseAmount(dto.amount);

    const member = await this.prisma.member.findFirst({
      where: {
        id: dto.memberId,
        tenantId,
      },
      select: { id: true },
    });

    if (!member) {
      throw new NotFoundException('Member not found');
    }

    if (dto.subscriptionId) {
      await this.ensureSubscriptionBelongsToMember(
        tenantId,
        dto.memberId,
        dto.subscriptionId,
      );
    }

    const status = dto.status ?? PaymentStatus.PAID;
    const paidAt =
      dto.paidAt === undefined
        ? status === PaymentStatus.PAID
          ? new Date()
          : null
        : this.parseNullableDate(dto.paidAt);

    return this.prisma.payment.create({
      data: {
        tenantId,
        memberId: dto.memberId,
        subscriptionId: dto.subscriptionId ?? null,
        amount,
        currency: dto.currency?.trim().toUpperCase() || 'EUR',
        paymentMethod: dto.paymentMethod ?? PaymentMethod.OTHER,
        status,
        paidAt,
        dueDate: this.parseNullableDate(dto.dueDate),
        notes: this.optionalText(dto.notes),
      },
      include: {
        member: {
          select: {
            id: true,
            firstName: true,
            lastName: true,
            email: true,
          },
        },
        subscription: {
          include: {
            membershipPlan: true,
          },
        },
      },
    });
  }

  private requireTenant(user: AuthUser): string {
    if (!user.tenantId || user.role === RoleKey.SUPERADMIN) {
      throw new ForbiddenException('A tenant user is required');
    }

    return user.tenantId;
  }

  private async ensureSubscriptionBelongsToMember(
    tenantId: string,
    memberId: string,
    subscriptionId: string,
  ) {
    const subscription = await this.prisma.subscription.findFirst({
      where: {
        id: subscriptionId,
        tenantId,
        memberId,
      },
      select: { id: true },
    });

    if (!subscription) {
      throw new BadRequestException('Invalid subscriptionId');
    }
  }

  private parseAmount(value: string | number) {
    const amount = Number(value);

    if (!Number.isFinite(amount) || amount <= 0) {
      throw new BadRequestException('amount must be greater than zero');
    }

    return amount.toFixed(2);
  }

  private parseNullableDate(value?: string | null): Date | null | undefined {
    if (value === undefined) {
      return undefined;
    }

    if (value === null || value.trim() === '') {
      return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      throw new BadRequestException('Invalid date');
    }

    return date;
  }

  private optionalText(value?: string | null): string | null | undefined {
    if (value === undefined) {
      return undefined;
    }

    const trimmed = value?.trim();
    return trimmed ? trimmed : null;
  }
}

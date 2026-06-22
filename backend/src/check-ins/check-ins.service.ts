import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { CheckInMethod, ReservationStatus, RoleKey } from '@prisma/client';
import { AuthUser } from '../auth/auth-user';
import { PrismaService } from '../prisma/prisma.service';
import { CreateCheckInDto } from './dto/create-check-in.dto';

@Injectable()
export class CheckInsService {
  constructor(private readonly prisma: PrismaService) {}

  findAll(user: AuthUser) {
    const tenantId = this.requireTenant(user);

    return this.prisma.checkIn.findMany({
      where: { tenantId },
      include: {
        member: {
          select: {
            id: true,
            firstName: true,
            lastName: true,
            email: true,
          },
        },
        classSession: {
          include: {
            classType: true,
            trainer: {
              select: {
                id: true,
                name: true,
                email: true,
              },
            },
          },
        },
        reservation: true,
        createdByUser: {
          select: {
            id: true,
            name: true,
            email: true,
          },
        },
      },
      orderBy: { checkedInAt: 'desc' },
    });
  }

  async create(user: AuthUser, dto: CreateCheckInDto) {
    const tenantId = this.requireTenant(user);

    if (!dto.memberId) {
      throw new BadRequestException('memberId is required');
    }

    const member = await this.prisma.member.findFirst({
      where: { id: dto.memberId, tenantId },
      select: { id: true },
    });

    if (!member) {
      throw new NotFoundException('Member not found');
    }

    if (dto.classSessionId) {
      await this.ensureClassSessionBelongsToTenant(tenantId, dto.classSessionId);
    }

    if (dto.reservationId) {
      const reservation = await this.prisma.reservation.findFirst({
        where: {
          id: dto.reservationId,
          tenantId,
          memberId: dto.memberId,
        },
        select: {
          id: true,
          classSessionId: true,
          checkIn: { select: { id: true } },
        },
      });

      if (!reservation) {
        throw new BadRequestException('Invalid reservationId');
      }

      if (reservation.checkIn) {
        throw new BadRequestException('Reservation already has a check-in');
      }

      if (dto.classSessionId && dto.classSessionId !== reservation.classSessionId) {
        throw new BadRequestException(
          'reservationId does not match classSessionId',
        );
      }

      return this.prisma.$transaction(async (tx) => {
        const checkIn = await tx.checkIn.create({
          data: {
            tenantId,
            memberId: dto.memberId,
            classSessionId: reservation.classSessionId,
            reservationId: reservation.id,
            method: dto.method ?? CheckInMethod.MANUAL,
            checkedInAt: this.parseDate(dto.checkedInAt) ?? new Date(),
            createdByUserId: user.userId,
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
            classSession: {
              include: {
                classType: true,
              },
            },
            reservation: true,
          },
        });

        await tx.reservation.update({
          where: { id: reservation.id },
          data: { status: ReservationStatus.ATTENDED },
        });

        return checkIn;
      });
    }

    return this.prisma.checkIn.create({
      data: {
        tenantId,
        memberId: dto.memberId,
        classSessionId: dto.classSessionId ?? null,
        reservationId: null,
        method: dto.method ?? CheckInMethod.MANUAL,
        checkedInAt: this.parseDate(dto.checkedInAt) ?? new Date(),
        createdByUserId: user.userId,
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
        classSession: {
          include: {
            classType: true,
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

  private async ensureClassSessionBelongsToTenant(
    tenantId: string,
    classSessionId: string,
  ) {
    const session = await this.prisma.classSession.findFirst({
      where: { id: classSessionId, tenantId },
      select: { id: true },
    });

    if (!session) {
      throw new BadRequestException('Invalid classSessionId');
    }
  }

  private parseDate(value?: string): Date | undefined {
    if (!value) {
      return undefined;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      throw new BadRequestException('Invalid date');
    }

    return date;
  }
}

import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { ClassSessionStatus, ReservationStatus, RoleKey } from '@prisma/client';
import { AuthUser } from '../auth/auth-user';
import { PrismaService } from '../prisma/prisma.service';
import { CreateReservationDto } from './dto/create-reservation.dto';

@Injectable()
export class ReservationsService {
  constructor(private readonly prisma: PrismaService) {}

  findAll(user: AuthUser) {
    const tenantId = this.requireTenant(user);

    return this.prisma.reservation.findMany({
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
      },
      orderBy: { createdAt: 'desc' },
    });
  }

  async create(user: AuthUser, dto: CreateReservationDto) {
    const tenantId = this.requireTenant(user);

    if (!dto.memberId || !dto.classSessionId) {
      throw new BadRequestException('memberId and classSessionId are required');
    }

    const member = await this.prisma.member.findFirst({
      where: { id: dto.memberId, tenantId },
      select: { id: true },
    });

    if (!member) {
      throw new NotFoundException('Member not found');
    }

    const session = await this.prisma.classSession.findFirst({
      where: { id: dto.classSessionId, tenantId },
      select: {
        id: true,
        capacity: true,
        status: true,
      },
    });

    if (!session) {
      throw new NotFoundException('Class session not found');
    }

    if (session.status !== ClassSessionStatus.SCHEDULED) {
      throw new BadRequestException('Class session does not accept reservations');
    }

    const activeStatuses = [ReservationStatus.RESERVED, ReservationStatus.ATTENDED];
    const [existingReservation, activeReservationsCount] =
      await this.prisma.$transaction([
        this.prisma.reservation.findFirst({
          where: {
            tenantId,
            memberId: dto.memberId,
            classSessionId: dto.classSessionId,
            status: { in: activeStatuses },
          },
          select: { id: true },
        }),
        this.prisma.reservation.count({
          where: {
            tenantId,
            classSessionId: dto.classSessionId,
            status: { in: activeStatuses },
          },
        }),
      ]);

    if (existingReservation) {
      throw new BadRequestException('Member already has an active reservation');
    }

    if (activeReservationsCount >= session.capacity) {
      throw new BadRequestException('Class session is full');
    }

    return this.prisma.reservation.create({
      data: {
        tenantId,
        memberId: dto.memberId,
        classSessionId: dto.classSessionId,
        status: ReservationStatus.RESERVED,
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
}

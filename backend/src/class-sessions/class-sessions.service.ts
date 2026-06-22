import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { ClassSessionStatus, RoleKey } from '@prisma/client';
import { AuthUser } from '../auth/auth-user';
import { PrismaService } from '../prisma/prisma.service';
import { CreateClassSessionDto } from './dto/create-class-session.dto';

@Injectable()
export class ClassSessionsService {
  constructor(private readonly prisma: PrismaService) {}

  findAll(user: AuthUser) {
    const tenantId = this.requireTenant(user);

    return this.prisma.classSession.findMany({
      where: { tenantId },
      include: {
        classType: true,
        trainer: {
          select: {
            id: true,
            name: true,
            email: true,
          },
        },
        reservations: true,
      },
      orderBy: { startsAt: 'asc' },
    });
  }

  async create(user: AuthUser, dto: CreateClassSessionDto) {
    const tenantId = this.requireTenant(user);

    if (!dto.classTypeId) {
      throw new BadRequestException('classTypeId is required');
    }

    const capacity = Number(dto.capacity);

    if (!Number.isInteger(capacity) || capacity <= 0) {
      throw new BadRequestException('capacity must be greater than zero');
    }

    const startsAt = this.parseDate(dto.startsAt);
    const endsAt = this.parseNullableDate(dto.endsAt);

    if (endsAt && endsAt <= startsAt) {
      throw new BadRequestException('endsAt must be after startsAt');
    }

    const classType = await this.prisma.classType.findFirst({
      where: { id: dto.classTypeId, tenantId, isActive: true },
      select: { id: true },
    });

    if (!classType) {
      throw new BadRequestException('Invalid classTypeId');
    }

    if (dto.trainerUserId) {
      await this.ensureTrainerBelongsToTenant(tenantId, dto.trainerUserId);
    }

    return this.prisma.classSession.create({
      data: {
        tenantId,
        classTypeId: dto.classTypeId,
        trainerUserId: dto.trainerUserId ?? null,
        startsAt,
        endsAt,
        capacity,
        status: dto.status ?? ClassSessionStatus.SCHEDULED,
      },
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
    });
  }

  private requireTenant(user: AuthUser): string {
    if (!user.tenantId || user.role === RoleKey.SUPERADMIN) {
      throw new ForbiddenException('A tenant user is required');
    }

    return user.tenantId;
  }

  private async ensureTrainerBelongsToTenant(tenantId: string, userId: string) {
    const trainer = await this.prisma.user.findFirst({
      where: {
        id: userId,
        tenantId,
        role: { key: RoleKey.TRAINER },
      },
      select: { id: true },
    });

    if (!trainer) {
      throw new NotFoundException('Trainer not found');
    }
  }

  private parseDate(value?: string): Date {
    if (!value) {
      throw new BadRequestException('Date is required');
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      throw new BadRequestException('Invalid date');
    }

    return date;
  }

  private parseNullableDate(value?: string | null): Date | null {
    if (!value) {
      return null;
    }

    return this.parseDate(value);
  }
}

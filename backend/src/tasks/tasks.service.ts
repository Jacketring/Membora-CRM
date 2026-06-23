import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { RoleKey, TaskStatus, TaskType } from '@prisma/client';
import { AuthUser } from '../auth/auth-user';
import { PrismaService } from '../prisma/prisma.service';
import { CreateTaskDto } from './dto/create-task.dto';
import { UpdateTaskDto } from './dto/update-task.dto';

@Injectable()
export class TasksService {
  constructor(private readonly prisma: PrismaService) {}

  async findAll(user: AuthUser) {
    const tenantId = this.requireTenant(user);

    return this.prisma.task.findMany({
      where: { tenantId },
      include: this.taskInclude(),
      orderBy: [{ status: 'asc' }, { dueAt: 'asc' }, { createdAt: 'desc' }],
    });
  }

  async create(user: AuthUser, dto: CreateTaskDto) {
    const tenantId = this.requireTenant(user);

    if (!dto.title?.trim()) {
      throw new BadRequestException('title is required');
    }

    await this.validateRelations(tenantId, {
      assignedUserId: dto.assignedUserId,
      leadId: dto.leadId,
      memberId: dto.memberId,
    });

    return this.prisma.task.create({
      data: {
        tenantId,
        assignedUserId: dto.assignedUserId ?? null,
        leadId: dto.leadId ?? null,
        memberId: dto.memberId ?? null,
        title: dto.title.trim(),
        description: this.optionalText(dto.description),
        type: dto.type ?? TaskType.OTHER,
        status: dto.status ?? TaskStatus.PENDING,
        dueAt: this.parseNullableDate(dto.dueAt),
        completedAt:
          dto.status === TaskStatus.COMPLETED ? new Date() : undefined,
      },
      include: this.taskInclude(),
    });
  }

  async update(user: AuthUser, id: string, dto: UpdateTaskDto) {
    const tenantId = this.requireTenant(user);
    const task = await this.prisma.task.findFirst({
      where: { id, tenantId },
      select: { id: true },
    });

    if (!task) {
      throw new NotFoundException('Task not found');
    }

    await this.validateRelations(tenantId, {
      assignedUserId: dto.assignedUserId,
      leadId: dto.leadId,
      memberId: dto.memberId,
    });

    return this.prisma.task.update({
      where: { id },
      data: {
        assignedUserId: dto.assignedUserId,
        leadId: dto.leadId,
        memberId: dto.memberId,
        title: dto.title?.trim(),
        description:
          dto.description === undefined
            ? undefined
            : this.optionalText(dto.description),
        type: dto.type,
        status: dto.status,
        dueAt:
          dto.dueAt === undefined ? undefined : this.parseNullableDate(dto.dueAt),
        completedAt:
          dto.completedAt === undefined
            ? dto.status === TaskStatus.COMPLETED
              ? new Date()
              : undefined
            : this.parseNullableDate(dto.completedAt),
      },
      include: this.taskInclude(),
    });
  }

  async remove(user: AuthUser, id: string) {
    const tenantId = this.requireTenant(user);
    const task = await this.prisma.task.findFirst({
      where: { id, tenantId },
      select: { id: true },
    });

    if (!task) {
      throw new NotFoundException('Task not found');
    }

    await this.prisma.riskAlert.deleteMany({ where: { taskId: id, tenantId } });
    await this.prisma.task.delete({ where: { id } });

    return { deleted: true };
  }

  private requireTenant(user: AuthUser): string {
    if (!user.tenantId || user.role === RoleKey.SUPERADMIN) {
      throw new ForbiddenException('A tenant user is required');
    }

    return user.tenantId;
  }

  private async validateRelations(
    tenantId: string,
    relations: {
      assignedUserId?: string | null;
      leadId?: string | null;
      memberId?: string | null;
    },
  ) {
    if (relations.assignedUserId) {
      const assignedUser = await this.prisma.user.findFirst({
        where: { id: relations.assignedUserId, tenantId },
        select: { id: true },
      });

      if (!assignedUser) {
        throw new BadRequestException('Invalid assignedUserId');
      }
    }

    if (relations.leadId) {
      const lead = await this.prisma.lead.findFirst({
        where: { id: relations.leadId, tenantId },
        select: { id: true },
      });

      if (!lead) {
        throw new BadRequestException('Invalid leadId');
      }
    }

    if (relations.memberId) {
      const member = await this.prisma.member.findFirst({
        where: { id: relations.memberId, tenantId },
        select: { id: true },
      });

      if (!member) {
        throw new BadRequestException('Invalid memberId');
      }
    }
  }

  private taskInclude() {
    return {
      assignedUser: {
        select: {
          id: true,
          name: true,
          email: true,
        },
      },
      lead: {
        select: {
          id: true,
          firstName: true,
          lastName: true,
          email: true,
          status: true,
        },
      },
      member: {
        select: {
          id: true,
          firstName: true,
          lastName: true,
          email: true,
          status: true,
        },
      },
    };
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

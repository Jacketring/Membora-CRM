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

    try {
      return await this.prisma.task.findMany({
        where: { tenantId },
        include: this.taskInclude(),
        orderBy: [{ status: 'asc' }, { dueAt: 'asc' }, { createdAt: 'desc' }],
      });
    } catch {
      return this.prisma.task.findMany({
        where: { tenantId },
        include: this.legacyTaskInclude(),
        orderBy: [{ status: 'asc' }, { dueAt: 'asc' }, { createdAt: 'desc' }],
      });
    }
  }

  async create(user: AuthUser, dto: CreateTaskDto) {
    const tenantId = this.requireTenant(user);

    if (!dto.title?.trim()) {
      throw new BadRequestException('title is required');
    }

    const memberIds = this.normalizeMemberIds(dto.memberIds, dto.memberId);

    await this.validateRelations(tenantId, {
      assignedUserId: dto.assignedUserId,
      leadId: dto.leadId,
      memberId: dto.memberId,
      memberIds,
    });

    const data = {
      tenantId,
      assignedUserId: dto.assignedUserId ?? null,
      leadId: dto.leadId ?? null,
      memberId: memberIds.length === 1 ? memberIds[0] : null,
      title: dto.title.trim(),
      description: this.optionalText(dto.description),
      type: dto.type ?? TaskType.OTHER,
      status: dto.status ?? TaskStatus.PENDING,
      dueAt: this.parseNullableDate(dto.dueAt),
      completedAt:
        dto.status === TaskStatus.COMPLETED ? new Date() : undefined,
      taskMembers: memberIds.length
        ? {
            create: memberIds.map((memberId) => ({
              tenantId,
              memberId,
            })),
          }
        : undefined,
    };

    try {
      return await this.prisma.task.create({
        data,
        include: this.taskInclude(),
      });
    } catch {
      return this.prisma.task.create({
        data: {
          ...data,
          memberId: memberIds[0] ?? null,
          taskMembers: undefined,
        },
        include: this.legacyTaskInclude(),
      });
    }
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

    const memberIds =
      dto.memberIds === undefined ? undefined : this.normalizeMemberIds(dto.memberIds, dto.memberId);

    await this.validateRelations(tenantId, {
      assignedUserId: dto.assignedUserId,
      leadId: dto.leadId,
      memberId: dto.memberId,
      memberIds,
    });

    try {
      return await this.prisma.$transaction(async (tx) => {
        if (memberIds !== undefined) {
          await tx.taskMember.deleteMany({ where: { taskId: id } });
        }

        return tx.task.update({
          where: { id },
          data: {
            assignedUserId: dto.assignedUserId,
            leadId: dto.leadId,
            memberId:
              memberIds === undefined
                ? dto.memberId
                : memberIds.length === 1
                  ? memberIds[0]
                  : null,
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
            taskMembers:
              memberIds === undefined || memberIds.length === 0
                ? undefined
                : {
                    create: memberIds.map((memberId) => ({
                      tenantId,
                      memberId,
                    })),
                  },
          },
          include: this.taskInclude(),
        });
      });
    } catch {
      return this.prisma.task.update({
        where: { id },
        data: {
          assignedUserId: dto.assignedUserId,
          leadId: dto.leadId,
          memberId:
            memberIds === undefined
              ? dto.memberId
              : memberIds[0] ?? null,
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
        include: this.legacyTaskInclude(),
      });
    }
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

    try {
      await this.prisma.taskMember.deleteMany({ where: { taskId: id } });
    } catch {
      // Compatibility fallback for deployments where task_members is not present yet.
    }

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
      memberIds?: string[];
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

    if (relations.memberIds?.length) {
      const membersCount = await this.prisma.member.count({
        where: {
          id: { in: relations.memberIds },
          tenantId,
        },
      });

      if (membersCount !== relations.memberIds.length) {
        throw new BadRequestException('Invalid memberIds');
      }
    }
  }

  private normalizeMemberIds(memberIds?: string[] | null, memberId?: string | null) {
    return Array.from(
      new Set([...(memberIds ?? []), ...(memberId ? [memberId] : [])].filter(Boolean)),
    );
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
      taskMembers: {
        include: {
          member: {
            select: {
              id: true,
              firstName: true,
              lastName: true,
              email: true,
              phone: true,
              status: true,
            },
          },
        },
      },
    };
  }

  private legacyTaskInclude() {
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

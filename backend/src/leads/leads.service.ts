import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { LeadSource, LeadStatus, MemberStatus, RoleKey } from '@prisma/client';
import { AuthUser } from '../auth/auth-user';
import { PrismaService } from '../prisma/prisma.service';
import { CreateLeadDto } from './dto/create-lead.dto';
import { UpdateLeadDto } from './dto/update-lead.dto';

@Injectable()
export class LeadsService {
  constructor(private readonly prisma: PrismaService) {}

  async findAll(user: AuthUser) {
    const tenantId = this.requireTenant(user);

    return this.prisma.lead.findMany({
      where: { tenantId },
      include: {
        pipelineStage: true,
        assignedUser: {
          select: {
            id: true,
            name: true,
            email: true,
          },
        },
      },
      orderBy: [{ createdAt: 'desc' }],
    });
  }

  async findOne(user: AuthUser, id: string) {
    const tenantId = this.requireTenant(user);
    const lead = await this.prisma.lead.findFirst({
      where: { id, tenantId },
      include: {
        pipelineStage: true,
        assignedUser: {
          select: {
            id: true,
            name: true,
            email: true,
          },
        },
        tasks: true,
        communicationLogs: true,
      },
    });

    if (!lead) {
      throw new NotFoundException('Lead not found');
    }

    return lead;
  }

  async create(user: AuthUser, dto: CreateLeadDto) {
    const tenantId = this.requireTenant(user);

    if (!dto.firstName?.trim()) {
      throw new BadRequestException('firstName is required');
    }

    await this.ensurePipelineStageBelongsToTenant(tenantId, dto.pipelineStageId);

    if (dto.assignedUserId) {
      await this.ensureUserBelongsToTenant(tenantId, dto.assignedUserId);
    }

    return this.prisma.lead.create({
      data: {
        tenantId,
        pipelineStageId: dto.pipelineStageId,
        assignedUserId: dto.assignedUserId,
        firstName: dto.firstName.trim(),
        lastName: this.optionalText(dto.lastName),
        email: this.optionalEmail(dto.email),
        phone: this.optionalText(dto.phone),
        source: dto.source ?? LeadSource.OTHER,
        interest: this.optionalText(dto.interest),
        status: LeadStatus.OPEN,
        nextActionAt: this.optionalDate(dto.nextActionAt),
      },
      include: {
        pipelineStage: true,
        assignedUser: {
          select: {
            id: true,
            name: true,
            email: true,
          },
        },
      },
    });
  }

  async update(user: AuthUser, id: string, dto: UpdateLeadDto) {
    const tenantId = this.requireTenant(user);
    await this.findOne(user, id);

    if (dto.pipelineStageId) {
      await this.ensurePipelineStageBelongsToTenant(tenantId, dto.pipelineStageId);
    }

    if (dto.assignedUserId) {
      await this.ensureUserBelongsToTenant(tenantId, dto.assignedUserId);
    }

    const data = {
      pipelineStageId: dto.pipelineStageId,
      assignedUserId: dto.assignedUserId,
      firstName: dto.firstName?.trim(),
      lastName:
        dto.lastName === undefined ? undefined : this.optionalText(dto.lastName),
      email: dto.email === undefined ? undefined : this.optionalEmail(dto.email),
      phone: dto.phone === undefined ? undefined : this.optionalText(dto.phone),
      source: dto.source,
      interest:
        dto.interest === undefined ? undefined : this.optionalText(dto.interest),
      status: dto.status,
      lostReason:
        dto.lostReason === undefined
          ? undefined
          : this.optionalText(dto.lostReason),
      nextActionAt:
        dto.nextActionAt === undefined
          ? undefined
          : this.optionalDate(dto.nextActionAt),
    };

    return this.prisma.lead.update({
      where: { id },
      data,
      include: {
        pipelineStage: true,
        assignedUser: {
          select: {
            id: true,
            name: true,
            email: true,
          },
        },
      },
    });
  }

  async convertToMember(user: AuthUser, id: string) {
    const tenantId = this.requireTenant(user);
    const lead = await this.prisma.lead.findFirst({
      where: { id, tenantId },
      include: { members: true },
    });

    if (!lead) {
      throw new NotFoundException('Lead not found');
    }

    if (lead.status === LeadStatus.CONVERTED || lead.members.length > 0) {
      throw new BadRequestException('Lead already converted');
    }

    const convertedStage = await this.prisma.pipelineStage.findFirst({
      where: {
        tenantId,
        key: 'CONVERTED',
      },
      select: { id: true },
    });

    if (!convertedStage) {
      throw new BadRequestException('Converted pipeline stage not found');
    }

    return this.prisma.$transaction(async (tx) => {
      const member = await tx.member.create({
        data: {
          tenantId,
          leadId: lead.id,
          firstName: lead.firstName,
          lastName: lead.lastName,
          email: lead.email,
          phone: lead.phone,
          status: MemberStatus.ACTIVE,
          joinedAt: new Date(),
          notes: `Converted from lead ${lead.id}`,
        },
      });

      await tx.lead.update({
        where: { id: lead.id },
        data: {
          status: LeadStatus.CONVERTED,
          pipelineStageId: convertedStage.id,
        },
      });

      return member;
    });
  }

  async revertConversion(user: AuthUser, id: string) {
    const tenantId = this.requireTenant(user);
    const lead = await this.prisma.lead.findFirst({
      where: { id, tenantId },
      include: { members: true },
    });

    if (!lead) {
      throw new NotFoundException('Lead not found');
    }

    if (lead.status !== LeadStatus.CONVERTED && lead.members.length === 0) {
      throw new BadRequestException('Lead is not converted');
    }

    const targetStage = (await this.prisma.pipelineStage.findFirst({
      where: {
        tenantId,
        key: 'CONTACTED',
      },
      select: { id: true },
    })) ?? await this.prisma.pipelineStage.findFirst({
      where: {
        tenantId,
        isFinal: false,
      },
      orderBy: { order: 'asc' },
      select: { id: true },
    });

    if (!targetStage) {
      throw new BadRequestException('Open pipeline stage not found');
    }

    return this.prisma.$transaction(async (tx) => {
      await tx.member.updateMany({
        where: {
          tenantId,
          leadId: lead.id,
        },
        data: {
          leadId: null,
          status: MemberStatus.CANCELLED,
          cancelledAt: new Date(),
        },
      });

      return tx.lead.update({
        where: { id: lead.id },
        data: {
          status: LeadStatus.OPEN,
          pipelineStageId: targetStage.id,
          lostReason: null,
        },
        include: {
          pipelineStage: true,
          assignedUser: {
            select: {
              id: true,
              name: true,
              email: true,
            },
          },
        },
      });
    });
  }

  async remove(user: AuthUser, id: string) {
    const tenantId = this.requireTenant(user);
    const lead = await this.prisma.lead.findFirst({
      where: { id, tenantId },
      include: { members: true },
    });

    if (!lead) {
      throw new NotFoundException('Lead not found');
    }

    if (lead.members.length > 0) {
      throw new BadRequestException('Revert the conversion before deleting this lead');
    }

    await this.prisma.$transaction([
      this.prisma.riskAlert.deleteMany({
        where: {
          tenantId,
          OR: [{ leadId: id }, { task: { leadId: id } }],
        },
      }),
      this.prisma.communicationLog.deleteMany({ where: { tenantId, leadId: id } }),
      this.prisma.task.deleteMany({ where: { tenantId, leadId: id } }),
      this.prisma.lead.delete({ where: { id } }),
    ]);

    return { deleted: true };
  }

  private requireTenant(user: AuthUser): string {
    if (!user.tenantId || user.role === RoleKey.SUPERADMIN) {
      throw new ForbiddenException('A tenant user is required');
    }

    return user.tenantId;
  }

  private async ensurePipelineStageBelongsToTenant(
    tenantId: string,
    pipelineStageId: string,
  ) {
    const stage = await this.prisma.pipelineStage.findFirst({
      where: {
        id: pipelineStageId,
        tenantId,
      },
      select: { id: true },
    });

    if (!stage) {
      throw new BadRequestException('Invalid pipelineStageId');
    }
  }

  private async ensureUserBelongsToTenant(tenantId: string, userId: string) {
    const user = await this.prisma.user.findFirst({
      where: {
        id: userId,
        tenantId,
      },
      select: { id: true },
    });

    if (!user) {
      throw new BadRequestException('Invalid assignedUserId');
    }
  }

  private optionalText(value?: string | null): string | null | undefined {
    if (value === undefined) {
      return undefined;
    }

    const trimmed = value?.trim();
    return trimmed ? trimmed : null;
  }

  private optionalEmail(value?: string | null): string | null | undefined {
    if (value === undefined) {
      return undefined;
    }

    const trimmed = value?.trim().toLowerCase();
    return trimmed ? trimmed : null;
  }

  private optionalDate(value?: string | null): Date | null | undefined {
    if (value === undefined) {
      return undefined;
    }

    if (value === null || value.trim() === '') {
      return null;
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
      throw new BadRequestException('Invalid date');
    }

    return parsed;
  }
}

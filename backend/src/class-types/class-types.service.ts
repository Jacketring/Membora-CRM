import { ForbiddenException, Injectable } from '@nestjs/common';
import { RoleKey } from '@prisma/client';
import { AuthUser } from '../auth/auth-user';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class ClassTypesService {
  constructor(private readonly prisma: PrismaService) {}

  findAll(user: AuthUser) {
    const tenantId = this.requireTenant(user);

    return this.prisma.classType.findMany({
      where: { tenantId },
      orderBy: [{ isActive: 'desc' }, { name: 'asc' }],
    });
  }

  private requireTenant(user: AuthUser): string {
    if (!user.tenantId || user.role === RoleKey.SUPERADMIN) {
      throw new ForbiddenException('A tenant user is required');
    }

    return user.tenantId;
  }
}

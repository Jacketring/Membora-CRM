import { ForbiddenException, Injectable } from '@nestjs/common';
import { RoleKey, UserStatus } from '@prisma/client';
import { AuthUser } from '../auth/auth-user';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class UsersService {
  constructor(private readonly prisma: PrismaService) {}

  findAll(user: AuthUser) {
    if (!user.tenantId || user.role === RoleKey.SUPERADMIN) {
      throw new ForbiddenException('A tenant user is required');
    }

    return this.prisma.user.findMany({
      where: {
        tenantId: user.tenantId,
        status: UserStatus.ACTIVE,
      },
      select: {
        id: true,
        name: true,
        email: true,
        status: true,
        role: {
          select: {
            key: true,
            name: true,
          },
        },
      },
      orderBy: [{ role: { name: 'asc' } }, { name: 'asc' }],
    });
  }
}

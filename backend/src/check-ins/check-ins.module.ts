import { Module } from '@nestjs/common';
import { AuthModule } from '../auth/auth.module';
import { PrismaModule } from '../prisma/prisma.module';
import { CheckInsController } from './check-ins.controller';
import { CheckInsService } from './check-ins.service';

@Module({
  imports: [AuthModule, PrismaModule],
  controllers: [CheckInsController],
  providers: [CheckInsService],
})
export class CheckInsModule {}

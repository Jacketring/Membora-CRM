import { Module } from '@nestjs/common';
import { AuthModule } from '../auth/auth.module';
import { PrismaModule } from '../prisma/prisma.module';
import { ClassTypesController } from './class-types.controller';
import { ClassTypesService } from './class-types.service';

@Module({
  imports: [AuthModule, PrismaModule],
  controllers: [ClassTypesController],
  providers: [ClassTypesService],
})
export class ClassTypesModule {}

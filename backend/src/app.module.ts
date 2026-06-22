import { Module } from '@nestjs/common';
import { AppController } from './app.controller';
import { AppService } from './app.service';
import { AuthModule } from './auth/auth.module';
import { LeadsModule } from './leads/leads.module';
import { MembershipPlansModule } from './membership-plans/membership-plans.module';
import { MembersModule } from './members/members.module';
import { PaymentsModule } from './payments/payments.module';
import { PipelineStagesModule } from './pipeline-stages/pipeline-stages.module';
import { PrismaModule } from './prisma/prisma.module';
import { SubscriptionsModule } from './subscriptions/subscriptions.module';

@Module({
  imports: [
    PrismaModule,
    AuthModule,
    LeadsModule,
    PipelineStagesModule,
    MembersModule,
    MembershipPlansModule,
    SubscriptionsModule,
    PaymentsModule,
  ],
  controllers: [AppController],
  providers: [AppService],
})
export class AppModule {}

import { Body, Controller, Get, Post, UseGuards } from '@nestjs/common';
import { AuthUser } from '../auth/auth-user';
import { CurrentUser } from '../auth/current-user.decorator';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { CreatePaymentDto } from './dto/create-payment.dto';
import { PaymentsService } from './payments.service';

@UseGuards(JwtAuthGuard)
@Controller('payments')
export class PaymentsController {
  constructor(private readonly paymentsService: PaymentsService) {}

  @Get()
  findAll(@CurrentUser() user: AuthUser) {
    return this.paymentsService.findAll(user);
  }

  @Post()
  create(@CurrentUser() user: AuthUser, @Body() dto: CreatePaymentDto) {
    return this.paymentsService.create(user, dto);
  }
}

import { Body, Controller, Get, Post, UseGuards } from '@nestjs/common';
import { AuthUser } from '../auth/auth-user';
import { CurrentUser } from '../auth/current-user.decorator';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { CheckInsService } from './check-ins.service';
import { CreateCheckInDto } from './dto/create-check-in.dto';

@UseGuards(JwtAuthGuard)
@Controller('check-ins')
export class CheckInsController {
  constructor(private readonly checkInsService: CheckInsService) {}

  @Get()
  findAll(@CurrentUser() user: AuthUser) {
    return this.checkInsService.findAll(user);
  }

  @Post()
  create(@CurrentUser() user: AuthUser, @Body() dto: CreateCheckInDto) {
    return this.checkInsService.create(user, dto);
  }
}

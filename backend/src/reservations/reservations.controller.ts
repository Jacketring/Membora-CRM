import { Body, Controller, Get, Post, UseGuards } from '@nestjs/common';
import { AuthUser } from '../auth/auth-user';
import { CurrentUser } from '../auth/current-user.decorator';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { CreateReservationDto } from './dto/create-reservation.dto';
import { ReservationsService } from './reservations.service';

@UseGuards(JwtAuthGuard)
@Controller('reservations')
export class ReservationsController {
  constructor(private readonly reservationsService: ReservationsService) {}

  @Get()
  findAll(@CurrentUser() user: AuthUser) {
    return this.reservationsService.findAll(user);
  }

  @Post()
  create(@CurrentUser() user: AuthUser, @Body() dto: CreateReservationDto) {
    return this.reservationsService.create(user, dto);
  }
}

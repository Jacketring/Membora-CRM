import { Body, Controller, Get, Post, UseGuards } from '@nestjs/common';
import { AuthUser } from '../auth/auth-user';
import { CurrentUser } from '../auth/current-user.decorator';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { ClassSessionsService } from './class-sessions.service';
import { CreateClassSessionDto } from './dto/create-class-session.dto';

@UseGuards(JwtAuthGuard)
@Controller('class-sessions')
export class ClassSessionsController {
  constructor(private readonly classSessionsService: ClassSessionsService) {}

  @Get()
  findAll(@CurrentUser() user: AuthUser) {
    return this.classSessionsService.findAll(user);
  }

  @Post()
  create(@CurrentUser() user: AuthUser, @Body() dto: CreateClassSessionDto) {
    return this.classSessionsService.create(user, dto);
  }
}

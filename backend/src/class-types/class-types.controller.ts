import { Controller, Get, UseGuards } from '@nestjs/common';
import { AuthUser } from '../auth/auth-user';
import { CurrentUser } from '../auth/current-user.decorator';
import { JwtAuthGuard } from '../auth/jwt-auth.guard';
import { ClassTypesService } from './class-types.service';

@UseGuards(JwtAuthGuard)
@Controller('class-types')
export class ClassTypesController {
  constructor(private readonly classTypesService: ClassTypesService) {}

  @Get()
  findAll(@CurrentUser() user: AuthUser) {
    return this.classTypesService.findAll(user);
  }
}

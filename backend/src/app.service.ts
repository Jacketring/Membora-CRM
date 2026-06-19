import { Injectable } from '@nestjs/common';

@Injectable()
export class AppService {
  getHealth() {
    return {
      status: 'ok',
      service: 'membora-crm-backend',
      timestamp: new Date().toISOString(),
    };
  }
}

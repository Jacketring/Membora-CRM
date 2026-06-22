const { PrismaClient } = require('@prisma/client');
const bcrypt = require('bcryptjs');

const prisma = new PrismaClient();

const DEMO_PASSWORD = 'MemboraDemo2026!';
const demoPasswordHash = bcrypt.hashSync(DEMO_PASSWORD, 10);

const addDays = (date, days) => {
  const copy = new Date(date);
  copy.setDate(copy.getDate() + days);
  return copy;
};

const addHours = (date, hours) => {
  const copy = new Date(date);
  copy.setHours(copy.getHours() + hours);
  return copy;
};

async function cleanDemoTenant(tenantId) {
  await prisma.auditLog.deleteMany({ where: { tenantId } });
  await prisma.riskAlert.deleteMany({ where: { tenantId } });
  await prisma.communicationLog.deleteMany({ where: { tenantId } });
  await prisma.checkIn.deleteMany({ where: { tenantId } });
  await prisma.reservation.deleteMany({ where: { tenantId } });
  await prisma.task.deleteMany({ where: { tenantId } });
  await prisma.payment.deleteMany({ where: { tenantId } });
  await prisma.subscription.deleteMany({ where: { tenantId } });
  await prisma.classSession.deleteMany({ where: { tenantId } });
  await prisma.classType.deleteMany({ where: { tenantId } });
  await prisma.member.deleteMany({ where: { tenantId } });
  await prisma.lead.deleteMany({ where: { tenantId } });
  await prisma.pipelineStage.deleteMany({ where: { tenantId } });
  await prisma.membershipPlan.deleteMany({ where: { tenantId } });
  await prisma.user.deleteMany({ where: { tenantId } });
  await prisma.tenant.delete({ where: { id: tenantId } });
}

async function seedRoles() {
  const roles = [
    {
      key: 'SUPERADMIN',
      name: 'Superadmin SaaS',
      description: 'Administrador global de la plataforma.',
    },
    {
      key: 'GYM_ADMIN',
      name: 'Administrador del gimnasio',
      description: 'Gestiona la operativa completa de un gimnasio.',
    },
    {
      key: 'SALES_RECEPTION',
      name: 'Recepcion / comercial',
      description: 'Gestiona leads, recepcion, tareas y reservas.',
    },
    {
      key: 'TRAINER',
      name: 'Entrenador',
      description: 'Gestiona clases, reservas y asistencia.',
    },
  ];

  const result = {};

  for (const role of roles) {
    result[role.key] = await prisma.role.upsert({
      where: { key: role.key },
      update: {
        name: role.name,
        description: role.description,
      },
      create: role,
    });
  }

  return result;
}

async function main() {
  const now = new Date();
  const roles = await seedRoles();

  const existingTenant = await prisma.tenant.findUnique({
    where: { slug: 'nexofit-studio' },
  });

  if (existingTenant) {
    await cleanDemoTenant(existingTenant.id);
  }

  const tenant = await prisma.tenant.create({
    data: {
      name: 'NexoFit Studio',
      slug: 'nexofit-studio',
      email: 'hola@nexofit.demo',
      phone: '+34 600 100 200',
      address: 'Calle Demo 12, Madrid',
      status: 'ACTIVE',
    },
  });

  const existingSuperadmin = await prisma.user.findFirst({
    where: {
      tenantId: null,
      email: 'superadmin@membora.demo',
      roleId: roles.SUPERADMIN.id,
    },
  });

  const superadmin =
    existingSuperadmin ??
    (await prisma.user.create({
      data: {
        tenantId: null,
        roleId: roles.SUPERADMIN.id,
        name: 'Superadmin Membora',
        email: 'superadmin@membora.demo',
        passwordHash: demoPasswordHash,
        status: 'ACTIVE',
      },
    }));

  const admin = await prisma.user.create({
    data: {
      tenantId: tenant.id,
      roleId: roles.GYM_ADMIN.id,
      name: 'Laura Martin',
      email: 'admin@nexofit.demo',
      passwordHash: demoPasswordHash,
      status: 'ACTIVE',
    },
  });

  const reception = await prisma.user.create({
    data: {
      tenantId: tenant.id,
      roleId: roles.SALES_RECEPTION.id,
      name: 'Carlos Medina',
      email: 'recepcion@nexofit.demo',
      passwordHash: demoPasswordHash,
      status: 'ACTIVE',
    },
  });

  const trainer = await prisma.user.create({
    data: {
      tenantId: tenant.id,
      roleId: roles.TRAINER.id,
      name: 'Marta Ruiz',
      email: 'entrenador@nexofit.demo',
      passwordHash: demoPasswordHash,
      status: 'ACTIVE',
    },
  });

  const stages = {};
  const pipelineStages = [
    ['NEW_LEAD', 'Nuevo lead', 1, false],
    ['CONTACTED', 'Contactado', 2, false],
    ['TRIAL_SCHEDULED', 'Visita o prueba agendada', 3, false],
    ['TRIAL_COMPLETED', 'Prueba realizada', 4, false],
    ['OFFER_SENT', 'Alta propuesta', 5, false],
    ['CONVERTED', 'Convertido a socio', 6, true],
    ['LOST', 'Perdido', 7, true],
  ];

  for (const [key, name, order, isFinal] of pipelineStages) {
    stages[key] = await prisma.pipelineStage.create({
      data: {
        tenantId: tenant.id,
        key,
        name,
        order,
        isFinal,
      },
    });
  }

  const basicPlan = await prisma.membershipPlan.create({
    data: {
      tenantId: tenant.id,
      name: 'Basico',
      description: 'Acceso general al gimnasio en horario valle.',
      price: '29.90',
      billingPeriod: 'MONTHLY',
      durationDays: 30,
      isActive: true,
    },
  });

  const premiumPlan = await prisma.membershipPlan.create({
    data: {
      tenantId: tenant.id,
      name: 'Premium',
      description: 'Acceso completo con clases incluidas.',
      price: '49.90',
      billingPeriod: 'MONTHLY',
      durationDays: 30,
      isActive: true,
    },
  });

  const unlimitedPlan = await prisma.membershipPlan.create({
    data: {
      tenantId: tenant.id,
      name: 'Clases ilimitadas',
      description: 'Plan mensual para asistencia intensiva a clases.',
      price: '69.90',
      billingPeriod: 'MONTHLY',
      durationDays: 30,
      isActive: true,
    },
  });

  const leads = await Promise.all([
    prisma.lead.create({
      data: {
        tenantId: tenant.id,
        pipelineStageId: stages.NEW_LEAD.id,
        assignedUserId: reception.id,
        firstName: 'Ana',
        lastName: 'Lopez',
        email: 'ana.lopez@example.com',
        phone: '+34 611 111 111',
        source: 'WEBSITE',
        interest: 'Clases de fuerza',
        status: 'OPEN',
        nextActionAt: addDays(now, 1),
      },
    }),
    prisma.lead.create({
      data: {
        tenantId: tenant.id,
        pipelineStageId: stages.TRIAL_SCHEDULED.id,
        assignedUserId: reception.id,
        firstName: 'Sergio',
        lastName: 'Perez',
        email: 'sergio.perez@example.com',
        phone: '+34 622 222 222',
        source: 'SOCIAL_MEDIA',
        interest: 'Prueba de HIIT',
        status: 'OPEN',
        nextActionAt: addDays(now, 2),
      },
    }),
    prisma.lead.create({
      data: {
        tenantId: tenant.id,
        pipelineStageId: stages.CONVERTED.id,
        assignedUserId: reception.id,
        firstName: 'Beatriz',
        lastName: 'Santos',
        email: 'beatriz.santos@example.com',
        phone: '+34 633 333 333',
        source: 'REFERRAL',
        interest: 'Plan premium',
        status: 'CONVERTED',
      },
    }),
    prisma.lead.create({
      data: {
        tenantId: tenant.id,
        pipelineStageId: stages.LOST.id,
        assignedUserId: reception.id,
        firstName: 'Miguel',
        lastName: 'Torres',
        email: 'miguel.torres@example.com',
        phone: '+34 644 444 444',
        source: 'PHONE',
        interest: 'Bono mensual',
        status: 'LOST',
        lostReason: 'Precio no encaja con sus necesidades actuales.',
      },
    }),
  ]);

  const convertedMember = await prisma.member.create({
    data: {
      tenantId: tenant.id,
      leadId: leads[2].id,
      firstName: 'Beatriz',
      lastName: 'Santos',
      email: 'beatriz.santos@example.com',
      phone: '+34 633 333 333',
      status: 'ACTIVE',
      joinedAt: addDays(now, -20),
      notes: 'Convertida desde lead por recomendacion.',
    },
  });

  const activeMember = await prisma.member.create({
    data: {
      tenantId: tenant.id,
      firstName: 'Diego',
      lastName: 'Navarro',
      email: 'diego.navarro@example.com',
      phone: '+34 655 555 555',
      status: 'ACTIVE',
      joinedAt: addDays(now, -90),
    },
  });

  const atRiskMember = await prisma.member.create({
    data: {
      tenantId: tenant.id,
      firstName: 'Elena',
      lastName: 'Garcia',
      email: 'elena.garcia@example.com',
      phone: '+34 666 666 666',
      status: 'AT_RISK',
      joinedAt: addDays(now, -150),
      notes: 'Sin asistencia registrada en las ultimas semanas.',
    },
  });

  const paymentPendingMember = await prisma.member.create({
    data: {
      tenantId: tenant.id,
      firstName: 'Raul',
      lastName: 'Moreno',
      email: 'raul.moreno@example.com',
      phone: '+34 677 777 777',
      status: 'PAYMENT_PENDING',
      joinedAt: addDays(now, -45),
    },
  });

  const subscriptions = await Promise.all([
    prisma.subscription.create({
      data: {
        tenantId: tenant.id,
        memberId: convertedMember.id,
        membershipPlanId: premiumPlan.id,
        status: 'ACTIVE',
        startDate: addDays(now, -20),
        endDate: addDays(now, 10),
      },
    }),
    prisma.subscription.create({
      data: {
        tenantId: tenant.id,
        memberId: activeMember.id,
        membershipPlanId: unlimitedPlan.id,
        status: 'ACTIVE',
        startDate: addDays(now, -30),
        endDate: addDays(now, 1),
      },
    }),
    prisma.subscription.create({
      data: {
        tenantId: tenant.id,
        memberId: atRiskMember.id,
        membershipPlanId: basicPlan.id,
        status: 'EXPIRED',
        startDate: addDays(now, -60),
        endDate: addDays(now, -30),
      },
    }),
    prisma.subscription.create({
      data: {
        tenantId: tenant.id,
        memberId: paymentPendingMember.id,
        membershipPlanId: premiumPlan.id,
        status: 'PENDING',
        startDate: addDays(now, -5),
        endDate: addDays(now, 25),
      },
    }),
  ]);

  await prisma.payment.createMany({
    data: [
      {
        tenantId: tenant.id,
        memberId: convertedMember.id,
        subscriptionId: subscriptions[0].id,
        amount: '49.90',
        currency: 'EUR',
        paymentMethod: 'CARD',
        status: 'PAID',
        paidAt: addDays(now, -20),
        dueDate: addDays(now, -20),
        notes: 'Pago inicial premium.',
      },
      {
        tenantId: tenant.id,
        memberId: activeMember.id,
        subscriptionId: subscriptions[1].id,
        amount: '69.90',
        currency: 'EUR',
        paymentMethod: 'TRANSFER',
        status: 'PAID',
        paidAt: addDays(now, -30),
        dueDate: addDays(now, -30),
      },
      {
        tenantId: tenant.id,
        memberId: atRiskMember.id,
        subscriptionId: subscriptions[2].id,
        amount: '29.90',
        currency: 'EUR',
        paymentMethod: 'CASH',
        status: 'OVERDUE',
        dueDate: addDays(now, -15),
      },
      {
        tenantId: tenant.id,
        memberId: paymentPendingMember.id,
        subscriptionId: subscriptions[3].id,
        amount: '49.90',
        currency: 'EUR',
        paymentMethod: 'OTHER',
        status: 'PENDING',
        dueDate: addDays(now, 2),
      },
    ],
  });

  const functional = await prisma.classType.create({
    data: {
      tenantId: tenant.id,
      name: 'Funcional',
      description: 'Entrenamiento funcional en grupo.',
      defaultDurationMinutes: 50,
      isActive: true,
    },
  });

  const hiit = await prisma.classType.create({
    data: {
      tenantId: tenant.id,
      name: 'HIIT',
      description: 'Clase de alta intensidad.',
      defaultDurationMinutes: 45,
      isActive: true,
    },
  });

  const yoga = await prisma.classType.create({
    data: {
      tenantId: tenant.id,
      name: 'Yoga',
      description: 'Movilidad, respiracion y control postural.',
      defaultDurationMinutes: 60,
      isActive: true,
    },
  });

  const functionalSession = await prisma.classSession.create({
    data: {
      tenantId: tenant.id,
      classTypeId: functional.id,
      trainerUserId: trainer.id,
      startsAt: addHours(addDays(now, 1), 9),
      endsAt: addHours(addDays(now, 1), 10),
      capacity: 12,
      status: 'SCHEDULED',
    },
  });

  const hiitSession = await prisma.classSession.create({
    data: {
      tenantId: tenant.id,
      classTypeId: hiit.id,
      trainerUserId: trainer.id,
      startsAt: addHours(addDays(now, 2), 18),
      endsAt: addHours(addDays(now, 2), 19),
      capacity: 10,
      status: 'SCHEDULED',
    },
  });

  await prisma.classSession.create({
    data: {
      tenantId: tenant.id,
      classTypeId: yoga.id,
      trainerUserId: trainer.id,
      startsAt: addHours(addDays(now, -1), 17),
      endsAt: addHours(addDays(now, -1), 18),
      capacity: 15,
      status: 'COMPLETED',
    },
  });

  const reservation = await prisma.reservation.create({
    data: {
      tenantId: tenant.id,
      memberId: convertedMember.id,
      classSessionId: functionalSession.id,
      status: 'RESERVED',
    },
  });

  await prisma.reservation.createMany({
    data: [
      {
        tenantId: tenant.id,
        memberId: activeMember.id,
        classSessionId: functionalSession.id,
        status: 'RESERVED',
      },
      {
        tenantId: tenant.id,
        memberId: atRiskMember.id,
        classSessionId: hiitSession.id,
        status: 'CANCELLED',
        cancelledAt: addDays(now, -1),
      },
      {
        tenantId: tenant.id,
        memberId: paymentPendingMember.id,
        classSessionId: hiitSession.id,
        status: 'NO_SHOW',
      },
    ],
  });

  await prisma.checkIn.create({
    data: {
      tenantId: tenant.id,
      memberId: convertedMember.id,
      classSessionId: functionalSession.id,
      reservationId: reservation.id,
      method: 'QR',
      checkedInAt: now,
      createdByUserId: reception.id,
    },
  });

  const overdueTask = await prisma.task.create({
    data: {
      tenantId: tenant.id,
      assignedUserId: reception.id,
      leadId: leads[0].id,
      title: 'Llamar a Ana Lopez',
      description: 'Confirmar interes y proponer prueba gratuita.',
      type: 'SALES',
      status: 'PENDING',
      dueAt: addDays(now, -1),
    },
  });

  await prisma.task.createMany({
    data: [
      {
        tenantId: tenant.id,
        assignedUserId: reception.id,
        memberId: atRiskMember.id,
        title: 'Contactar socio inactivo',
        description: 'Revisar motivo de inactividad y ofrecer seguimiento.',
        type: 'RETENTION',
        status: 'PENDING',
        dueAt: addDays(now, 1),
      },
      {
        tenantId: tenant.id,
        assignedUserId: admin.id,
        memberId: paymentPendingMember.id,
        title: 'Revisar pago pendiente',
        type: 'PAYMENT',
        status: 'PENDING',
        dueAt: addDays(now, 2),
      },
    ],
  });

  await prisma.communicationLog.createMany({
    data: [
      {
        tenantId: tenant.id,
        leadId: leads[1].id,
        userId: reception.id,
        channel: 'PHONE',
        direction: 'OUTBOUND',
        summary: 'Se confirma prueba para esta semana.',
        occurredAt: addDays(now, -1),
      },
      {
        tenantId: tenant.id,
        memberId: convertedMember.id,
        userId: reception.id,
        channel: 'IN_PERSON',
        direction: 'INTERNAL_NOTE',
        summary: 'Alta completada tras visita al centro.',
        occurredAt: addDays(now, -20),
      },
    ],
  });

  await prisma.riskAlert.createMany({
    data: [
      {
        tenantId: tenant.id,
        memberId: paymentPendingMember.id,
        type: 'PAYMENT_PENDING',
        severity: 'MEDIUM',
        status: 'OPEN',
        message: 'Socio con pago pendiente.',
      },
      {
        tenantId: tenant.id,
        memberId: atRiskMember.id,
        type: 'MEMBERSHIP_EXPIRED',
        severity: 'HIGH',
        status: 'OPEN',
        message: 'Membresia vencida sin renovacion.',
      },
      {
        tenantId: tenant.id,
        taskId: overdueTask.id,
        leadId: leads[0].id,
        type: 'OVERDUE_TASK',
        severity: 'MEDIUM',
        status: 'OPEN',
        message: 'Tarea comercial vencida.',
      },
    ],
  });

  await prisma.auditLog.createMany({
    data: [
      {
        tenantId: null,
        userId: superadmin.id,
        action: 'SEED_GLOBAL_ROLES',
        entityType: 'Role',
        entityId: 'global',
        metadata: { source: 'seed' },
      },
      {
        tenantId: tenant.id,
        userId: admin.id,
        action: 'SEED_DEMO_TENANT',
        entityType: 'Tenant',
        entityId: tenant.id,
        metadata: { tenant: 'NexoFit Studio' },
      },
    ],
  });

  console.log('Seed completado correctamente.');
  console.log(`Tenant demo: ${tenant.name} (${tenant.slug})`);
  console.log('Usuarios demo:');
  console.log(`- superadmin@membora.demo / ${DEMO_PASSWORD}`);
  console.log(`- admin@nexofit.demo / ${DEMO_PASSWORD}`);
  console.log(`- recepcion@nexofit.demo / ${DEMO_PASSWORD}`);
  console.log(`- entrenador@nexofit.demo / ${DEMO_PASSWORD}`);
}

main()
  .catch((error) => {
    console.error(error);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });

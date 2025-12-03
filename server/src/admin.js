import mongoose from 'mongoose';
import bcrypt from 'bcryptjs';
import { z } from 'zod';
import { config } from './config.js';
import { User, Audit } from './models.js';

try {
  const input = z
    .object({
      email: z.email().transform((value) => value.toLowerCase()),
      password: z
        .string()
        .min(12, 'ADMIN_PASSWORD must have at least 12 characters.')
        .max(72)
        .refine((value) => Buffer.byteLength(value, 'utf8') <= 72),
      firstName: z.string().trim().min(1).max(50),
      lastName: z.string().trim().min(1).max(50),
    })
    .parse({
      email: process.env.ADMIN_EMAIL,
      password: process.env.ADMIN_PASSWORD,
      firstName: process.env.ADMIN_FIRST_NAME || 'Campus',
      lastName: process.env.ADMIN_LAST_NAME || 'Admin',
    });
  if (!config.domains.includes(input.email.split('@')[1]))
    throw new Error('ADMIN_EMAIL must use an allowed college domain.');
  await mongoose.connect(config.mongoUri, { serverSelectionTimeoutMS: 10_000 });
  await User.init();
  if (await User.exists({ email: input.email }))
    throw new Error('This account already exists. No user was changed.');
  const admin = await User.create({
    firstName: input.firstName,
    lastName: input.lastName,
    email: input.email,
    role: 'admin',
    passwordHash: await bcrypt.hash(input.password, 12),
  });
  await Audit.create({
    actor: admin._id,
    action: 'Created administrator',
    detail: 'Administrator created with the local setup command.',
  });
  console.log(`Administrator ${admin.email} created. Sign in and add your campus sections.`);
} catch (error) {
  console.error(
    error instanceof z.ZodError
      ? error.issues.map((issue) => `${issue.path.join('.')}: ${issue.message}`).join('\n')
      : error.message,
  );
  process.exitCode = 1;
} finally {
  await mongoose.disconnect();
}

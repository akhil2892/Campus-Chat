import multer from 'multer';
import { randomUUID } from 'node:crypto';
import { mkdir, writeFile, unlink } from 'node:fs/promises';
import path from 'node:path';
import { fileTypeFromBuffer } from 'file-type';
import { config } from './config.js';
import { fail } from './security.js';

export const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 10 * 1024 * 1024, files: 1, fields: 10 },
});
const images = new Set(['image/png', 'image/jpeg', 'image/gif']);
const documents = new Set([
  'application/pdf',
  'application/zip',
  'application/msword',
  'application/x-cfb',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]);
export async function saveUpload(file, imageOnly = false) {
  if (!file) return undefined;
  if (imageOnly && file.size > 5 * 1024 * 1024)
    fail(400, 'Profile images must be 5 MB or smaller.');
  let detected = await fileTypeFromBuffer(file.buffer);
  if (
    !detected &&
    path.extname(file.originalname).toLowerCase() === '.txt' &&
    !file.buffer.includes(0)
  ) {
    try {
      new TextDecoder('utf-8', { fatal: true }).decode(file.buffer);
      detected = { mime: 'text/plain', ext: 'txt' };
    } catch {
      /* Not UTF-8 text. */
    }
  }
  if (
    !detected ||
    !(
      images.has(detected.mime) ||
      (!imageOnly && (documents.has(detected.mime) || detected.mime === 'text/plain'))
    )
  )
    fail(
      400,
      imageOnly
        ? 'Choose a PNG, JPG, or GIF image.'
        : 'Choose an image, PDF, Word document, ZIP, or text file.',
    );
  await mkdir(config.uploads, { recursive: true });
  const filename = `${randomUUID()}.${detected.ext}`;
  await writeFile(path.join(config.uploads, filename), file.buffer);
  return {
    filename,
    originalName: path
      .basename(file.originalname)
      .replace(/[\r\n]/g, '')
      .slice(0, 150),
    mime: detected.mime,
    size: file.size,
  };
}
export async function removeUpload(filename) {
  if (filename && path.basename(filename) === filename)
    await unlink(path.join(config.uploads, filename)).catch(() => {});
}

import crypto from "crypto";

const ALGORITHM = "aes-256-cbc";
// Must be exactly 32 bytes for aes-256-cbc
const SECRET_KEY = "chitholebrewingchiangmaisecret99"; 
const IV_LENGTH = 16;

export function hashPassword(password: string): string {
  const salt = crypto.randomBytes(16).toString("hex");
  const hash = crypto.pbkdf2Sync(password, salt, 1000, 64, "sha512").toString("hex");
  return `${salt}:${hash}`;
}

export function verifyPassword(password: string, storedHash: string): boolean {
  try {
    const [salt, hash] = storedHash.split(":");
    if (!salt || !hash) return false;
    const testHash = crypto.pbkdf2Sync(password, salt, 1000, 64, "sha512").toString("hex");
    return hash === testHash;
  } catch (e) {
    return false;
  }
}

export interface SessionPayload {
  userId: string;
  email: string;
  role: string;
  name: string;
  expires: number;
}

export function encryptSession(payload: SessionPayload): string {
  const text = JSON.stringify(payload);
  const iv = crypto.randomBytes(IV_LENGTH);
  const cipher = crypto.createCipheriv(ALGORITHM, Buffer.from(SECRET_KEY), iv);
  let encrypted = cipher.update(text);
  encrypted = Buffer.concat([encrypted, cipher.final()]);
  return iv.toString("hex") + ":" + encrypted.toString("hex");
}

export function decryptSession(token: string): SessionPayload | null {
  try {
    const parts = token.split(":");
    const ivHex = parts.shift();
    if (!ivHex) return null;
    const iv = Buffer.from(ivHex, "hex");
    const encryptedText = Buffer.from(parts.join(":"), "hex");
    const decipher = crypto.createDecipheriv(ALGORITHM, Buffer.from(SECRET_KEY), iv);
    let decrypted = decipher.update(encryptedText);
    decrypted = Buffer.concat([decrypted, decipher.final()]);
    const payload = JSON.parse(decrypted.toString()) as SessionPayload;
    if (Date.now() > payload.expires) {
      return null; // Expired session
    }
    return payload;
  } catch (error) {
    return null;
  }
}

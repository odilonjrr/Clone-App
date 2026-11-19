import { randomBytes, createHash } from "node:crypto";
export function randomToken(bytes = 18) {
  return randomBytes(bytes).toString("base64url");
}
export function sha256(s: string) {
  return createHash("sha256").update(s).digest("hex");
}

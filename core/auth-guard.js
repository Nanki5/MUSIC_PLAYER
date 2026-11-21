// core/auth-guard.js
import { request } from './api.js';

// оборачиваем request, чтобы на 401 дёргать глобальный обработчик
const original = request;
let onUnauthorized = () => {};

export function setUnauthorizedHandler(fn) { onUnauthorized = fn; }

export async function safeRequest(path, opts) {
  try {
    return await original(path, opts);
  } catch (e) {
    if (e.status === 401) onUnauthorized();
    throw e;
  }
}
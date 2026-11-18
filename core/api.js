// core/api.js
import { API_BASE } from './config.js';

/**
 * Универсальная обёртка над fetch.
 * - Всегда credentials: 'include' (куки ходят)
 * - Автоматически JSON-сериализует `json`
 * - Автоматически FormData для `formData` (Content-Type не ставит!)
 * - На 204 возвращает null
 * - На ошибку бросает Error с .status и .message из {error}
 */
export async function request(path, { method = 'GET', json, formData } = {}) {
  const opts = { method, credentials: 'include' };

  if (json !== undefined) {
    opts.headers = { 'Content-Type': 'application/json' };
    opts.body = JSON.stringify(json);
  } else if (formData) {
    opts.body = formData; // Content-Type НЕ ставим — браузер сам с boundary
  }

  const res = await fetch(API_BASE + path, opts);

  // 204 No Content (logout, delete) — тела нет
  if (res.status === 204) return null;

  // пытаемся распарсить JSON, но не падаем, если тело пустое
  const body = await res.json().catch(() => ({}));

  if (!res.ok) {
    const err = new Error(body.error || `HTTP ${res.status}`);
    err.status = res.status;
    throw err;
  }
  return body;
}

export const auth = {
  register: (login, password) =>
    request('/api/auth/register', { method: 'POST', json: { login, password } }),
  login: (login, password) =>
    request('/api/auth/login', { method: 'POST', json: { login, password } }),
  logout: () =>
    request('/api/auth/logout', { method: 'POST' }),
  me: () =>
    request('/api/me'),
  deleteAccount: () =>
    request('/api/auth/account', { method: 'DELETE' }),
};


export const songs = {
  list: ({ query = '', sort = 'uploaded_at', limit = 20, offset = 0 } = {}) => {
    const qs = new URLSearchParams({ query, sort, limit, offset });
    return request(`/api/songs?${qs}`);
  },
  upload: ({ title, author, file, cover }) => {
    const fd = new FormData();
    fd.append('title', title);
    fd.append('author', author);
    fd.append('file', file);
    if (cover) fd.append('cover', cover);
    return request('/api/songs', { method: 'POST', formData: fd });
  },
};


export const likes = {
  toggle: (songId) =>
    request(`/api/songs/${songId}/like`, { method: 'POST' }),
  myLikes: ({ limit = 20, offset = 0 } = {}) =>
    request(`/api/me/likes?${new URLSearchParams({ limit, offset })}`),
};

export const playlists = {
  create: ({ name, songIds = [], picture }) => {
    const fd = new FormData();
    fd.append('name', name);
    fd.append('songs', JSON.stringify(songIds)); // строка JSON!
    if (picture) fd.append('picture', picture);
    return request('/api/playlists', { method: 'POST', formData: fd });
  },
  get: (id) =>
    request(`/api/playlists/${id}`),
  rename: (id, name) =>
    request(`/api/playlists/${id}`, { method: 'PATCH', json: { name } }),
  remove: (id) =>
    request(`/api/playlists/${id}`, { method: 'DELETE' }),
  changePicture: (id, picture) => {
    const fd = new FormData();
    fd.append('picture', picture);
    return request(`/api/playlists/${id}/picture`, { method: 'PATCH', formData: fd });
  },
  mine: () =>
    request('/api/me/playlists'),
  toggleSong: (playlistId, songId) =>
    request(`/api/playlists/${playlistId}/songs/${songId}`, { method: 'POST' }),
};
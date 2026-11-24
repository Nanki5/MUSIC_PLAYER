import { API_URL } from './config.js';

function mapSong(s) {
  return {
    id: s.id,
    title: s.title,
    artist: s.author,
    audio: s.audio_url,
    cover: s.cover_url,
    likeCount: s.like_count ?? 0,
    likedAt: s.liked_at || null,
    liked: s.liked ?? Boolean(s.liked_at),
  };
}

function mapPlaylist(p) {
  return {
    id: p.id,
    name: p.name,
    published: p.published,
    cover: p.picture_url,
    tracks: Array.isArray(p.songs) ? p.songs.map(mapSong) : [],
  };
}

export async function request(path, options = {}) {
  const res = await fetch(`${API_URL}${path}`, {
    ...options,
    credentials: 'include',
  });

  if (res.status === 204) return null;

  let data = null;
  try {
    data = await res.json();
  } catch {}

  if (!res.ok) {
    const err = new Error(data?.error || `Ошибка сервера (${res.status})`);
    err.status = res.status;
    throw err;
  }

  return data;
}

export const API = {
  async getCurrentUser() {
    try {
      const user = await request('/api/me');
      return { name: user.login, login: user.login };
    } catch (err) {
      if (err.status === 401) return null;
      throw err;
    }
  },

  async login(login, password) {
    const user = await request('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ login, password }),
    });
    return { name: user.login, login: user.login };
  },

  async register(login, password) {
    const user = await request('/api/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ login, password }),
    });
    return { name: user.login, login: user.login };
  },

  async logout() {
    await request('/api/auth/logout', { method: 'POST' });
    location.href = 'home.html';
  },

  async requireAuth(redirectTo = 'login.html') {
    const user = await this.getCurrentUser();
    if (!user) {
      location.href = redirectTo;
      return null;
    }
    return user;
  },

  async getTracks({ query = '', sort = 'uploaded_at', limit = 20, offset = 0 } = {}) {
    const params = new URLSearchParams({ query, sort, limit, offset });
    const list = await request(`/api/songs?${params}`);
    return list.map(mapSong);
  },

  async getPopularTracks(limit = 8) {
    return this.getTracks({ sort: 'likes', limit });
  },

  async getLikedTracks(limit = 50, offset = 0) {
    const params = new URLSearchParams({ limit, offset });
    const list = await request(`/api/me/likes?${params}`);
    return list.map(mapSong);
  },

  // GET /api/songs не отдаёт признак "лайкнут ли трек текущим пользователем",
  // поэтому собираем id лайкнутых треков из /api/me/likes постранично.
  async getLikedIds(chunkSize = 100) {
    const ids = new Set();
    let offset = 0;
    for (;;) {
      const page = await this.getLikedTracks(chunkSize, offset);
      page.forEach(t => ids.add(String(t.id)));
      if (page.length < chunkSize) break;
      offset += chunkSize;
    }
    return ids;
  },

  async toggleLike(songId) {
    return request(`/api/songs/${songId}/like`, { method: 'POST' });
  },

  async uploadTrack(formData) {
    const song = await request('/api/songs', {
      method: 'POST',
      body: formData,
    });
    return mapSong(song);
  },

  // Публичный каталог: только опубликованные плейлисты.
  async getPlaylists({ limit = 10, offset = 0 } = {}) {
    const params = new URLSearchParams({ limit, offset });
    const list = await request(`/api/playlists?${params}`);
    return list.map(mapPlaylist);
  },

  async getMyPlaylists() {
    const list = await request('/api/me/playlists');
    return list.map(mapPlaylist);
  },

  async getPlaylist(id) {
    const playlist = await request(`/api/playlists/${id}`);
    return mapPlaylist(playlist);
  },
};

window.API = API;

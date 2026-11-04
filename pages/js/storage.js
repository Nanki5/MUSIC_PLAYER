// storage.js
const DB = {
    KEY: 'unimusic',
    read() { 
        const raw = localStorage.getItem(this.KEY);
        return raw ? JSON.parse(raw) : { users: [], tracks: [], playlists: [], currentUser: null };
    },
    write(data) {
        localStorage.setItem(this.KEY, JSON.stringify(data));
    },

    getUsers(){ return this.read().users; },
    findUser(email) { return this.read().users.find(u => u.email === email); },
    addUser(user) {
        const data = this.read();
        user.id = crypto.randomUUID();
        user.createdAt = Date.now();
        data.users.push(user);
        this.write(data);
        return user;
    },

    // AUTH
    getCurrentUser() {
        const id = this.read().currentUser;
        if (!id) return null;
        return this.read().users.find(u => u.id === id) || null;
    },
    setCurrentUser(id) {
        const data = this.read();
        data.currentUser = id;
        this.write(data);
    },
    logout() {
        const data = this.read();
        data.currentUser = null;
        this.write(data);
    },

    getTracks()           { return this.read().tracks; },
    getPublicTracks()     { return this.read().tracks.filter(t => t.isPublic); },
    getTracksByUser(uid)  { return this.read().tracks.filter(t => t.ownerId === uid); },
    addTrack(track) {
        const data = this.read();
        track.id = crypto.randomUUID();
        track.createdAt = Date.now();
        data.tracks.push(track);
        this.write(data);
        return track;
    },
    updateTrack(id, patch) {
        const data = this.read();
        const i = data.tracks.findIndex(t => t.id === id);
        if (i === -1) return null;
        data.tracks[i] = { ...data.tracks[i], ...patch };
        this.write(data);
        return data.tracks[i];
    },
    deleteTrack(id) {
        const data = this.read();
        data.tracks = data.tracks.filter(t => t.id !== id);
        data.playlists.forEach(p => {
            p.trackIds = p.trackIds.filter(tid => tid !== id);
        });
        this.write(data);
    },

    getPlaylists()          { return this.read().playlists; },
    getPublicPlaylists()    { return this.read().playlists.filter(p => p.isPublic); },
    getPlaylistsByUser(uid) { return this.read().playlists.filter(p => p.ownerId === uid); },
    getPlaylist(id)         { return this.read().playlists.find(p => p.id === id); },
    addPlaylist(pl) {
        const data = this.read();
        pl.id = crypto.randomUUID();
        pl.createdAt = Date.now();
        pl.trackIds = pl.trackIds || [];
        data.playlists.push(pl);
        this.write(data);
        return pl;
    },
    updatePlaylist(id, patch) {
        const data = this.read();
        const i = data.playlists.findIndex(p => p.id === id);
        if (i === -1) return null;
        data.playlists[i] = { ...data.playlists[i], ...patch };
        this.write(data);
        return data.playlists[i];
    },
    deletePlaylist(id) {
        const data = this.read();
        data.playlists = data.playlists.filter(p => p.id !== id);
        this.write(data);
    },
};
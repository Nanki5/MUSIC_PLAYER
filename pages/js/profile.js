document.addEventListener('DOMContentLoaded', () => {
    Auth.requireAuth('login.html');
    const user = DB.getCurrentUser();
    renderMyTracks(user);

    document.getElementById('totalCount').textContent = DB.getTracksByUser(user.id).length;
    document.getElementById('favoriteCount').textContent =
        DB.getTracksByUser(user.id).filter(t => t.isPublic).length;
});

function renderMyTracks(user) {
    const tracks = DB.getTracksByUser(user.id);
    const list = document.querySelector('.track__list');
    if (!tracks.length) {
        list.innerHTML = '<p style="color: var(--color-text-muted)">У вас пока нет треков. <a href="add_track.html">Добавить</a></p>';
        return;
    }

    list.innerHTML = tracks.map((t, i) => `
        <article class="track__row" data-id="${t.id}">
            <img src="${t.cover}" alt="" class="track__row-cover">
            <div class="track__row-info">
                <h3 class="track__row-title">${t.title}</h3>
                <p class="track__row-artist">${t.artist} · ${t.genre}</p>
            </div>
            <button class="botton__publish ${t.isPublic ? 'botton__publish--active' : ''}" title="${t.isPublic ? 'Снять с публикации' : 'Опубликовать'}">
                <i class="${t.isPublic ? 'ri-eye-fill' : 'ri-eye-off-line'}"></i>
            </button>
            <button class="botton__play" data-play="${i}"><i class="ri-play-fill"></i></button>
            <button class="botton__delete"><i class="ri-delete-bin-line"></i></button>
        </article>
    `).join('');

    list.addEventListener('click', e => {
        const row = e.target.closest('.track__row');
        if (!row) return;
        const id = row.dataset.id;

        if (e.target.closest('.botton__delete')) {
            if (confirm('Удалить трек?')) {
                DB.deleteTrack(id);
                renderMyTracks(user);
            }
        }
        if (e.target.closest('.botton__publish')) {
            const t = DB.getTracks().find(x => x.id === id);
            DB.updateTrack(id, { isPublic: !t.isPublic });
            renderMyTracks(user);
        }
        if (e.target.closest('[data-play]')) {
            const i = parseInt(e.target.closest('[data-play]').dataset.play);
            Player.play(tracks, i);
        }
    });
}
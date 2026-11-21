document.addEventListener('DOMContentLoaded', async () => {
    const user = await API.requireAuth('login.html');
    if (!user) return;
    await renderMyTracks();
});

async function renderMyTracks() {
    const tracks = await API.getLikedTracks();
    const list = document.querySelector('.track__list');

    document.getElementById('totalCount').textContent = tracks.length;
    document.getElementById('favoriteCount').textContent = tracks.length;

    if (!tracks.length) {
        list.innerHTML = '<p style="color: var(--color-text-muted)">У вас пока нет лайкнутых треков.</p>';
        return;
    }

    list.innerHTML = tracks.map((t, i) => `
        <article class="track__row" data-id="${t.id}">
            <img src="${t.cover}" alt="" class="track__row-cover">
            <div class="track__row-info">
                <h3 class="track__row-title">${t.title}</h3>
                <p class="track__row-artist">${t.artist}</p>
            </div>
            <button class="botton__play" data-play="${i}"><i class="ri-play-fill"></i></button>
            <button class="botton__delete" title="Убрать из лайков"><i class="ri-heart-fill"></i></button>
        </article>
    `).join('');

    list.onclick = async e => {
        const row = e.target.closest('.track__row');
        if (!row) return;
        const id = row.dataset.id;

        if (e.target.closest('.botton__delete')) {
            await API.toggleLike(id);
            renderMyTracks();
        }
        if (e.target.closest('[data-play]')) {
            const i = parseInt(e.target.closest('[data-play]').dataset.play, 10);
            Player.play(tracks, i);
        }
    };
}

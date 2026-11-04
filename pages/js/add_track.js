const AVAILABLE_AUDIO = [
    'Status Quo - In The Army Now',
    'Районы-кварталы',
    'ДДТ – Что такое осень',
    'Opus - Live is life',
];
const AVAILABLE_COVERS = ['images', 'Районы-кварталы', 'Что такое осень', 'images'];

document.addEventListener('DOMContentLoaded', () => {
    Auth.requireAuth('login.html'); 
    const audioSel = document.getElementById('audioSelect');
    const coverSel = document.getElementById('coverSelect');

    AVAILABLE_AUDIO.forEach(name => {
        audioSel.insertAdjacentHTML('beforeend',
            `<option value="../../assets/audio/${name}.mp3">${name}</option>`);
    });
    AVAILABLE_COVERS.forEach(name => {
        coverSel.insertAdjacentHTML('beforeend',
            `<option value="../../assets/images/${name}.jpg">${name}</option>`);
    });

    document.getElementById('addTrackForm').addEventListener('submit', e => {
        e.preventDefault();
        const f = e.target;
        const user = DB.getCurrentUser();
        DB.addTrack({
            title:    f.title.value.trim(),
            artist:   f.artist.value.trim(),
            genre:    f.genre.value,
            audio:    f.audio.value,
            cover:    f.cover.value,
            ownerId:  user.id,
            isPublic: f.isPublic.checked,
        });

        alert('Трек добавлен!');
        location.href = 'profile.html';
    });
});
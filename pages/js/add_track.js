document.addEventListener('DOMContentLoaded', async () => {
    const user = await API.requireAuth('login.html');
    if (!user) return;

    document.getElementById('addTrackForm').addEventListener('submit', async e => {
        e.preventDefault();
        const f = e.target;

        const audioFile = f.file.files[0];
        if (!audioFile) return alert('Выберите аудиофайл');

        const formData = new FormData();
        formData.append('title', f.title.value.trim());
        formData.append('author', f.artist.value.trim()); // API ждет ключ author
        formData.append('file', audioFile);

        if (f.cover && f.cover.files[0]) {
            formData.append('cover', f.cover.files[0]);
        }

        const submitBtn = f.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            await API.uploadTrack(formData);
            alert('Трек успешно загружен!');
            location.href = 'home.html';
        } catch (err) {
            alert(err.message);
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    });
});

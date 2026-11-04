const Auth = {
    hash(password) {
        let h = 0;
        for (let i = 0; i < password.length; i++) {
            h = (h << 5) - h + password.charCodeAt(i);
            h |= 0;
        }
        return String(h);
    },

    register({ email, password, name }) {
        if (DB.findUser(email)) throw new Error('Пользователь с таким email уже существует');
        if (password.length < 6) throw new Error('Пароль должен быть не короче 6 символов');
        const user = DB.addUser({
            email,
            name: name || email.split('@')[0],
            passwordHash: this.hash(password),
        });
        DB.setCurrentUser(user.id);
        return user;
    },

    login({ email, password }) {
        const user = DB.findUser(email);
        if (!user) throw new Error('Пользователь не найден');
        if (user.passwordHash !== this.hash(password)) throw new Error('Неверный пароль');
        DB.setCurrentUser(user.id);
        return user;
    },

    logout() {
        DB.logout();
        location.href = 'home.html';
    },

    requireAuth(redirectTo = 'login.html') {
        if (!DB.getCurrentUser()) location.href = redirectTo;
    },
};
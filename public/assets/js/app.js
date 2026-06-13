document.addEventListener('alpine:init', () => {
    Alpine.data('authForm', () => ({
        data: {},
        errors: [],
        success: null,
        loading: false,
        submit(url) {
            this.loading = true;
            this.errors = [];
            this.success = null;
            const formData = new FormData(this.$el);
            fetch(url, { method: 'POST', body: formData })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    this.loading = false;
                    if (!ok) {
                        this.errors = data.errors || [data.error || 'Something went wrong.'];
                    } else {
                        this.success = 'Success!';
                        if (data.redirect) window.location.href = data.redirect;
                    }
                })
                .catch(() => {
                    this.loading = false;
                    this.errors = ['Network error.'];
                });
        }
    }));
});

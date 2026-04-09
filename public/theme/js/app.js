document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-refresh-date]').forEach((el) => {
        const now = new Date();
        el.textContent = now.toLocaleDateString('fr-FR', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        });
    });

    const countdownElements = Array.from(document.querySelectorAll('[data-offer-countdown]'));

    const formatCountdown = (diffMs) => {
        if (diffMs <= 0) {
            return 'Expirée';
        }

        const totalSeconds = Math.floor(diffMs / 1000);
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);

        if (days > 0) {
            return `${days}j ${hours}h ${minutes}m`;
        }

        if (hours > 0) {
            return `${hours}h ${minutes}m`;
        }

        return `${minutes}m`;
    };

    const refreshCountdowns = () => {
        const now = Date.now();

        countdownElements.forEach((el) => {
            const endValue = el.getAttribute('data-offer-countdown');
            if (!endValue) {
                return;
            }

            const endDate = new Date(endValue);
            if (Number.isNaN(endDate.getTime())) {
                return;
            }

            const diffMs = endDate.getTime() - now;
            const label = formatCountdown(diffMs);
            el.textContent = `Expire dans ${label}`;

            if (diffMs <= 0) {
                el.classList.add('is-expired');
            }
        });
    };

    if (countdownElements.length > 0) {
        refreshCountdowns();
        window.setInterval(refreshCountdowns, 1000);
    }

    const searchForm = document.querySelector('[data-admin-search-form]');
    const searchInput = document.querySelector('[data-admin-search-input]');
    const userCards = Array.from(document.querySelectorAll('.user-card'));

    if (searchForm && searchInput) {
        const hasUserCards = userCards.length > 0;

        const applyFilter = () => {
            const query = searchInput.value.trim().toLowerCase();

            if (!hasUserCards) {
                return;
            }

            if (query === '') {
                userCards.forEach((card) => {
                    card.style.display = '';
                });
                return;
            }

            const normalizedCards = userCards.map((card) => {
                const name = (card.querySelector('h3')?.textContent || '').trim().toLowerCase();
                const paragraphs = card.querySelectorAll('p');
                const email = (paragraphs[0]?.textContent || '').trim().toLowerCase();
                const role = (card.querySelector('.user-role-pill')?.textContent || '').trim().toLowerCase();
                const text = card.textContent.toLowerCase();

                return { card, name, email, role, text };
            });

            // Prefer exact matches first (name, email or role), fallback to partial contains.
            let matches = normalizedCards.filter((entry) => {
                return entry.name === query || entry.email === query || entry.role === query;
            });

            if (matches.length === 0) {
                matches = normalizedCards.filter((entry) => entry.text.includes(query));
            }

            const selected = matches.length > 0 ? matches[0].card : null;

            userCards.forEach((card) => {
                const visible = selected === card;
                card.style.display = visible ? '' : 'none';
            });
        };

        if (hasUserCards) {
            const params = new URLSearchParams(window.location.search);
            const queryFromUrl = params.get('q') || '';
            if (queryFromUrl) {
                searchInput.value = queryFromUrl;
                applyFilter();
            }

            searchInput.addEventListener('input', applyFilter);
        }

        searchForm.addEventListener('submit', (event) => {
            if (hasUserCards) {
                event.preventDefault();
                applyFilter();
            }
        });
    }
});
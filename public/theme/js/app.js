document.addEventListener('DOMContentLoaded', () => {
    const rippleSelectors = '.primary-btn, .outline-btn, .danger-btn, .cta-btn, .icon-btn';
    document.querySelectorAll(rippleSelectors).forEach((btn) => {
        btn.addEventListener('click', (event) => {
            const rect = btn.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'btn-ripple';

            const size = Math.max(rect.width, rect.height) * 0.9;
            const x = event.clientX - rect.left - size / 2;
            const y = event.clientY - rect.top - size / 2;

            ripple.style.width = `${size}px`;
            ripple.style.height = `${size}px`;
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;

            btn.appendChild(ripple);
            ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
        });
    });

    document.querySelectorAll('[data-refresh-date]').forEach((el) => {
        const now = new Date();
        el.textContent = now.toLocaleDateString('fr-FR', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        });
    });

    const sortieModal = document.getElementById('sortie-detail-modal');
    const closeModal = () => {
        if (!sortieModal) return;
        sortieModal.hidden = true;
        document.body.style.overflow = '';
    };
    const openModal = () => {
        if (!sortieModal) return;
        sortieModal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    if (sortieModal) {
        sortieModal.querySelectorAll('[data-sortie-modal-close]').forEach((el) => {
            el.addEventListener('click', closeModal);
        });

        const modalTitle = document.getElementById('sortie-modal-title');
        const modalDescription = document.getElementById('sortie-modal-description');
        const modalMeta = document.getElementById('sortie-modal-meta');
        const modalMedia = document.getElementById('sortie-modal-media');
        const modalQuestions = document.getElementById('sortie-modal-questions');

        document.querySelectorAll('[data-sortie-detail]').forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                const interactive = event.target.closest('a, button, form, input, textarea, select, summary, label');
                if (interactive) {
                    return;
                }

                const title = trigger.getAttribute('data-sortie-title') || 'Sortie';
                const status = trigger.getAttribute('data-sortie-status') || '-';
                const date = trigger.getAttribute('data-sortie-date') || '-';
                const city = trigger.getAttribute('data-sortie-city') || '-';
                const budget = trigger.getAttribute('data-sortie-budget') || '-';
                const places = trigger.getAttribute('data-sortie-places') || '-';
                const type = trigger.getAttribute('data-sortie-type') || '-';
                const lieu = trigger.getAttribute('data-sortie-lieu') || '-';
                const pointRencontre = trigger.getAttribute('data-sortie-point') || '-';
                const createur = trigger.getAttribute('data-sortie-createur') || '-';
                const rawQuestions = trigger.getAttribute('data-sortie-questions') || '';
                const description = trigger.getAttribute('data-sortie-description') || 'Aucune description';
                const image = trigger.getAttribute('data-sortie-image') || '';

                if (modalTitle) {
                    modalTitle.textContent = title;
                }
                if (modalDescription) {
                    modalDescription.textContent = description;
                }
                if (modalMeta) {
                    modalMeta.innerHTML = [
                        `Statut: ${status}`,
                        `Date: ${date}`,
                        `Ville: ${city}`,
                        `Type: ${type}`,
                        `Places: ${places}`,
                        `Budget max: ${budget}`,
                        `Lieu: ${lieu}`,
                        `Point de rencontre: ${pointRencontre}`,
                        `Créateur: ${createur}`
                    ].map((entry) => `<span>${entry}</span>`).join('');
                }
                if (modalMedia) {
                    if (image) {
                        modalMedia.innerHTML = `<img src="${image}" alt="${title.replace(/"/g, '&quot;')}">`;
                    } else {
                        modalMedia.innerHTML = '';
                    }
                }

                if (modalQuestions) {
                    let parsedQuestions = [];
                    if (rawQuestions) {
                        try {
                            const decoded = JSON.parse(rawQuestions);
                            if (Array.isArray(decoded)) {
                                parsedQuestions = decoded.map((entry) => String(entry || '').trim()).filter(Boolean);
                            }
                        } catch (_) {
                            // Keep an empty list if JSON cannot be parsed
                        }
                    }

                    if (parsedQuestions.length > 0) {
                        modalQuestions.innerHTML = `<h4>Questions posées</h4><ul>${parsedQuestions.map((q) => `<li>${q.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</li>`).join('')}</ul>`;
                    } else {
                        modalQuestions.innerHTML = '<h4>Questions posées</h4><p>Aucune question supplémentaire.</p>';
                    }
                }

                openModal();
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !sortieModal.hidden) {
                closeModal();
            }
        });
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

    const normalizeText = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    document.querySelectorAll('[data-sortie-controls]').forEach((controlsRoot) => {
        const scope = controlsRoot.parentElement || document;
        const list = scope.querySelector('[data-sortie-list]');
        const emptyState = scope.querySelector('[data-sortie-empty]');
        const search = controlsRoot.querySelector('[data-sortie-search]');
        const status = controlsRoot.querySelector('[data-sortie-status]');
        const sort = controlsRoot.querySelector('[data-sortie-sort]');

        if (!list || !search || !status || !sort) {
            return;
        }

        const cards = Array.from(list.querySelectorAll('[data-sortie-detail]'));
        if (cards.length === 0) {
            return;
        }

        const parseDateTs = (card) => Number(card.getAttribute('data-sortie-date-ts') || 0);
        const parsePlaces = (card) => Number(card.getAttribute('data-sortie-places') || 0);
        const parseId = (card) => Number(card.getAttribute('data-sortie-id') || 0);

        const apply = () => {
            const query = normalizeText(search.value);
            const statusFilter = normalizeText(status.value);
            const sortMode = String(sort.value || 'recent');

            const visibleCards = cards.filter((card) => {
                const statusRaw = normalizeText(card.getAttribute('data-sortie-status'));
                if (statusFilter && statusRaw !== statusFilter) {
                    return false;
                }

                if (!query) {
                    return true;
                }

                const searchable = [
                    card.getAttribute('data-sortie-title'),
                    card.getAttribute('data-sortie-city'),
                    card.getAttribute('data-sortie-type'),
                    card.getAttribute('data-sortie-description'),
                    card.getAttribute('data-sortie-lieu'),
                    card.getAttribute('data-sortie-createur'),
                    card.getAttribute('data-sortie-status')
                ].map(normalizeText).join(' ');

                return searchable.includes(query);
            });

            const sortedCards = [...visibleCards].sort((a, b) => {
                const aTitle = normalizeText(a.getAttribute('data-sortie-title'));
                const bTitle = normalizeText(b.getAttribute('data-sortie-title'));
                const aCity = normalizeText(a.getAttribute('data-sortie-city'));
                const bCity = normalizeText(b.getAttribute('data-sortie-city'));
                const aDate = parseDateTs(a);
                const bDate = parseDateTs(b);
                const aPlaces = parsePlaces(a);
                const bPlaces = parsePlaces(b);
                const aId = parseId(a);
                const bId = parseId(b);

                switch (sortMode) {
                case 'date_asc':
                    return (aDate || 0) - (bDate || 0) || aId - bId;
                case 'date_desc':
                    return (bDate || 0) - (aDate || 0) || bId - aId;
                case 'title_asc':
                    return aTitle.localeCompare(bTitle, 'fr');
                case 'title_desc':
                    return bTitle.localeCompare(aTitle, 'fr');
                case 'city_asc':
                    return aCity.localeCompare(bCity, 'fr') || aTitle.localeCompare(bTitle, 'fr');
                case 'places_desc':
                    return (bPlaces || 0) - (aPlaces || 0) || (bDate || 0) - (aDate || 0);
                case 'recent':
                default:
                    return (bDate || 0) - (aDate || 0) || bId - aId;
                }
            });

            const visibleSet = new Set(sortedCards);
            cards.forEach((card) => {
                const isVisible = visibleSet.has(card);
                card.classList.toggle('sortie-card--hidden', !isVisible);
            });

            sortedCards.forEach((card) => {
                list.appendChild(card);
            });

            if (emptyState) {
                emptyState.hidden = sortedCards.length > 0;
            }
        };

        let searchDebounceId = null;
        search.addEventListener('input', () => {
            if (searchDebounceId) {
                window.clearTimeout(searchDebounceId);
            }
            searchDebounceId = window.setTimeout(apply, 110);
        });
        status.addEventListener('change', apply);
        sort.addEventListener('change', apply);
        apply();
    });

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const formatDateTime = (raw) => {
        if (!raw) return '';
        const normalized = String(raw).replace(' ', 'T');
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) {
            return String(raw);
        }
        return date.toLocaleString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const notificationRoots = Array.from(document.querySelectorAll('[data-notification-root]'));
    notificationRoots.forEach((root) => {
        const feedUrl = root.getAttribute('data-feed-url');
        const readAllUrl = root.getAttribute('data-read-all-url');
        const isInline = root.getAttribute('data-notification-inline') === 'true';
        const toggleBtn = root.querySelector('[data-notification-toggle]');
        const panel = root.querySelector('[data-notification-panel]');
        const list = root.querySelector('[data-notification-list]');
        const badge = root.querySelector('[data-notification-badge]');
        const readAllBtn = root.querySelector('[data-notification-read-all]');

        if (!feedUrl || !panel || !list || !badge) {
            return;
        }

        const renderItems = (items) => {
            if (!Array.isArray(items) || items.length === 0) {
                list.innerHTML = '<div class="notification-empty">Aucune notification</div>';
                return;
            }

            list.innerHTML = items.map((item) => {
                const title = escapeHtml(item.title || 'Notification');
                const body = escapeHtml(item.body || '');
                const created = formatDateTime(item.created_at || '');
                const senderName = `${item.sender_prenom || ''} ${item.sender_nom || ''}`.trim();
                const sender = senderName ? ` · ${escapeHtml(senderName)}` : '';
                const url = item.url ? String(item.url) : '';
                const adminUrl = item.admin_url ? String(item.admin_url) : '';
                const links = [
                    url ? `<a href="${escapeHtml(url)}" class="notification-link">Voir la sortie</a>` : '',
                    adminUrl ? `<a href="${escapeHtml(adminUrl)}" class="notification-link">Voir demandes</a>` : ''
                ].filter(Boolean).join('');

                return `
                    <article class="notification-item">
                        <h4 class="notification-item__title">${title}</h4>
                        <p class="notification-item__body">${body}</p>
                        <div class="notification-item__meta">${escapeHtml(created)}${sender}</div>
                        ${links ? `<div class="notification-item__actions">${links}</div>` : ''}
                    </article>
                `;
            }).join('');
        };

        const updateBadge = (unread) => {
            const value = Number(unread || 0);
            if (value > 0) {
                badge.hidden = false;
                badge.textContent = value > 99 ? '99+' : String(value);
            } else {
                badge.hidden = true;
                badge.textContent = '0';
            }
        };

        const loadNotifications = async () => {
            try {
                const response = await fetch(feedUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) return;
                const payload = await response.json();
                renderItems(payload.items || []);
                updateBadge(payload.unread || 0);
            } catch (_) {
                // Keep silent in UI on network errors.
            }
        };

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                panel.hidden = !panel.hidden;
                if (!panel.hidden) {
                    loadNotifications();
                }
            });
        }

        if (!isInline) {
            document.addEventListener('click', (event) => {
                if (!root.contains(event.target)) {
                    panel.hidden = true;
                }
            });
        } else {
            panel.hidden = false;
        }

        if (readAllBtn && readAllUrl) {
            readAllBtn.addEventListener('click', async () => {
                const token = readAllBtn.getAttribute('data-notification-token') || '';
                const body = new URLSearchParams({ _token: token });
                try {
                    const response = await fetch(readAllUrl, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body
                    });
                    if (response.ok) {
                        loadNotifications();
                    }
                } catch (_) {
                    // ignore silently
                }
            });
        }

        loadNotifications();
        window.setInterval(loadNotifications, 12000);
    });

    document.querySelectorAll('[data-participation-form]').forEach((form) => {
        const contactPrefer = form.querySelector('[data-contact-prefer]');
        const contactValue = form.querySelector('[data-contact-value]');
        const contactHint = form.querySelector('[data-contact-hint]');
        const nbPlacesInput = form.querySelector('[data-nb-places]');
        const defaultEmail = (contactValue?.dataset.defaultEmail || '').trim();
        const defaultPhone = (contactValue?.dataset.defaultPhone || '').trim();

        if (!contactPrefer || !contactValue) {
            return;
        }

        const ensureErrorNode = (input) => {
            const parent = input.closest('.form-group') || input.parentElement;
            if (!parent) {
                return null;
            }

            const key = input.getAttribute('name') || input.id || 'field';
            let node = parent.querySelector(`.field-hint.field-hint--error[data-error-for="${key}"]`);
            if (!node) {
                node = document.createElement('small');
                node.className = 'field-hint field-hint--error';
                node.setAttribute('data-error-for', key);
                node.hidden = true;
                parent.appendChild(node);
            }

            return node;
        };

        const showInlineError = (input, message) => {
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
            input.setCustomValidity('');
            const node = ensureErrorNode(input);
            if (node) {
                node.textContent = message;
                node.hidden = false;
            }
        };

        const clearInlineError = (input) => {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
            input.setCustomValidity('');

            const parent = input.closest('.form-group') || input.parentElement;
            if (!parent) {
                return;
            }

            const key = input.getAttribute('name') || input.id || 'field';
            const node = parent.querySelector(`.field-hint.field-hint--error[data-error-for="${key}"]`);
            if (node) {
                node.hidden = true;
                node.textContent = '';
            }
        };

        const applyContactValidation = (forceDefault = false) => {
            clearInlineError(contactValue);
            const mode = contactPrefer.value;
            if (mode === 'EMAIL') {
                contactValue.type = 'email';
                contactValue.placeholder = 'Ex: nom@email.com';
                contactValue.pattern = '';
                contactValue.maxLength = 255;
                contactValue.setAttribute('title', 'Saisissez une adresse email valide');
                if (forceDefault || !contactValue.value.trim()) {
                    contactValue.value = defaultEmail;
                }
                if (contactHint) {
                    contactHint.textContent = 'Format requis: email valide';
                }

                if (contactValue.value.trim() && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactValue.value.trim())) {
                    clearInlineError(contactValue);
                }
                return;
            }

            contactValue.type = 'tel';
            contactValue.placeholder = 'Ex: 55123456';
            contactValue.pattern = '^\\d{8}$';
            contactValue.maxLength = 8;
            contactValue.setAttribute('title', 'Saisissez exactement 8 chiffres');
            if (forceDefault || !contactValue.value.trim()) {
                contactValue.value = defaultPhone;
            }
            if (contactHint) {
                contactHint.textContent = 'Format requis: téléphone tunisien (8 chiffres)';
            }

            if (contactValue.value.trim() && /^\d{8}$/.test(contactValue.value.trim())) {
                clearInlineError(contactValue);
            }
        };

        applyContactValidation(false);
        contactPrefer.addEventListener('change', () => applyContactValidation(true));

        if (nbPlacesInput) {
            nbPlacesInput.addEventListener('input', () => {
                clearInlineError(nbPlacesInput);
            });
        }
        contactValue.addEventListener('input', () => {
            clearInlineError(contactValue);
        });
        const questionInputs = Array.from(form.querySelectorAll('input[name^="reponses["]'));
        questionInputs.forEach((input) => {
            input.addEventListener('input', () => clearInlineError(input));
        });

        form.addEventListener('submit', (event) => {
            if (!contactValue.value.trim()) {
                event.preventDefault();
                showInlineError(contactValue, 'Le contact de reponse est obligatoire.');
                contactValue.focus();
                return;
            }

            const mode = contactPrefer.value;
            const value = contactValue.value.trim();
            if (nbPlacesInput) {
                const min = Number(nbPlacesInput.getAttribute('min') || 1);
                const max = Number(nbPlacesInput.getAttribute('max') || 1);
                const requested = Number(nbPlacesInput.value || min);

                if (!Number.isFinite(requested) || requested < min) {
                    event.preventDefault();
                    showInlineError(nbPlacesInput, 'Le nombre de places doit etre strictement positif.');
                    return;
                }

                if (requested > max) {
                    event.preventDefault();
                    showInlineError(nbPlacesInput, 'Le nombre de places demandees depasse les places restantes.');
                    return;
                }

                clearInlineError(nbPlacesInput);
            }

            if (mode === 'EMAIL') {
                const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
                if (!validEmail) {
                    event.preventDefault();
                    showInlineError(contactValue, 'Veuillez saisir une adresse email valide.');
                    return;
                }
            } else {
                const validPhone = /^\d{8}$/.test(value);
                if (!validPhone) {
                    event.preventDefault();
                    showInlineError(contactValue, 'Le numero de telephone doit contenir exactement 8 chiffres.');
                    return;
                }
            }

            clearInlineError(contactValue);
            if (nbPlacesInput) {
                clearInlineError(nbPlacesInput);
            }

            const invalidQuestion = questionInputs.find((input) => !input.value.trim());
            if (invalidQuestion) {
                event.preventDefault();
                invalidQuestion.focus();
                showInlineError(invalidQuestion, 'Veuillez repondre a toutes les questions obligatoires.');
                return;
            }

            questionInputs.forEach((input) => {
                clearInlineError(input);
            });
        });
    });

    document.querySelectorAll('[data-login-form]').forEach((form) => {
        const emailInput = form.querySelector('[data-login-email]');
        const passwordInput = form.querySelector('[data-login-password]');

        if (!emailInput || !passwordInput) {
            return;
        }

        const getErrorNode = (fieldName) => form.querySelector(`[data-login-error-for="${fieldName}"]`);

        const showFieldError = (input, fieldName, message) => {
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
            const errorNode = getErrorNode(fieldName);
            if (errorNode) {
                errorNode.textContent = message;
            }
        };

        const clearFieldError = (input, fieldName) => {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
            const errorNode = getErrorNode(fieldName);
            if (errorNode) {
                errorNode.textContent = '';
            }
        };

        emailInput.addEventListener('input', () => clearFieldError(emailInput, 'email'));
        passwordInput.addEventListener('input', () => clearFieldError(passwordInput, 'password'));

        form.addEventListener('submit', (event) => {
            let hasError = false;
            const email = emailInput.value.trim();
            const password = passwordInput.value;

            clearFieldError(emailInput, 'email');
            clearFieldError(passwordInput, 'password');

            if (!email) {
                showFieldError(emailInput, 'email', 'L email est obligatoire.');
                hasError = true;
            } else {
                const isValidEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                if (!isValidEmail) {
                    showFieldError(emailInput, 'email', 'Veuillez saisir une adresse email valide.');
                    hasError = true;
                }
            }

            if (!password.trim()) {
                showFieldError(passwordInput, 'password', 'Le mot de passe est obligatoire.');
                hasError = true;
            }

            if (hasError) {
                event.preventDefault();
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    });
});
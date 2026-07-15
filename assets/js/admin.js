/* global sasData, jQuery */
'use strict';

(function () {
    // =========================================================================
    // Core API helper
    // =========================================================================
    const api = {
        async request(endpoint, options = {}) {
            const headers = { 'X-WP-Nonce': sasData.nonce };
            if (!(options.body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
            }
            const res = await fetch(sasData.apiUrl + endpoint, {
                ...options,
                headers: { ...headers, ...(options.headers || {}) },
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({ message: res.statusText }));
                throw new Error(err.message || `HTTP ${res.status}`);
            }
            return res.json();
        },
        get(ep, params = {}) {
            const qs = new URLSearchParams(params).toString();
            return this.request(ep + (qs ? '?' + qs : ''));
        },
        post(ep, data)   { return this.request(ep, { method: 'POST',   body: JSON.stringify(data) }); },
        put(ep, data)    { return this.request(ep, { method: 'PUT',    body: JSON.stringify(data) }); },
        del(ep)          { return this.request(ep, { method: 'DELETE' }); },
        postForm(ep, fd) { return this.request(ep, { method: 'POST',   body: fd }); },
    };

    // =========================================================================
    // Toast notifications
    // =========================================================================
    const toast = {
        container: null,
        init() {
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'sas-toast-container';
                document.body.appendChild(this.container);
            }
        },
        show(msg, type = 'info', duration = 4000) {
            this.init();
            const el = document.createElement('div');
            el.className = `sas-toast sas-toast--${type}`;
            const icons = { success: 'yes-alt', error: 'dismiss', info: 'info-outline' };
            const icon = document.createElement('span');
            icon.className = `dashicons dashicons-${icons[type] || 'info-outline'}`;
            const text = document.createElement('span');
            text.textContent = msg;
            el.append(icon, text);
            this.container.appendChild(el);
            setTimeout(() => {
                el.style.animation = 'sasToastOut .3s ease forwards';
                setTimeout(() => el.remove(), 300);
            }, duration);
        },
        success(msg) { this.show(msg, 'success'); },
        error(msg)   { this.show(msg, 'error'); },
        info(msg)    { this.show(msg, 'info'); },
    };

    // =========================================================================
    // Dark mode
    // =========================================================================
    const darkMode = {
        key: 'sas_dark_mode',
        sunIcon:  '<svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="10" cy="10" r="3.6"/><path d="M10 1.8v2M10 16.2v2M18.2 10h-2M3.8 10h-2M15.5 4.5l-1.4 1.4M5.9 14.1l-1.4 1.4M15.5 15.5l-1.4-1.4M5.9 5.9 4.5 4.5"/></svg>',
        moonIcon: '<svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor"><path d="M17.3 12.4A7.3 7.3 0 0 1 7.6 2.7a7.8 7.8 0 1 0 9.7 9.7Z"/></svg>',
        init() {
            const saved = localStorage.getItem(this.key);
            if (saved === '1') document.body.classList.add('sas-dark');

            const btn = document.createElement('button');
            btn.id = 'sas-dark-toggle';
            btn.title = 'Toggle dark mode';
            btn.innerHTML = document.body.classList.contains('sas-dark') ? this.sunIcon : this.moonIcon;
            btn.addEventListener('click', () => this.toggle(btn));
            document.body.appendChild(btn);
        },
        toggle(btn) {
            const dark = document.body.classList.toggle('sas-dark');
            localStorage.setItem(this.key, dark ? '1' : '0');
            btn.innerHTML = dark ? this.sunIcon : this.moonIcon;
        },
    };

    // =========================================================================
    // Chunked uploader
    // =========================================================================
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB

    /** Read all checked .sas-upload-platform checkboxes inside a container element */
    function getSelectedPlatforms(containerEl) {
        const scope = containerEl || document;
        const checked = [...scope.querySelectorAll('.sas-upload-platform:checked')];
        return checked.map(cb => cb.value).filter(Boolean);
    }

    /** Read the checked .sas-upload-content-type radio nearest a card ('reel' default) */
    function getSelectedContentType(cardEl) {
        const scope = cardEl || document;
        const checked = scope.querySelector('.sas-upload-content-type:checked');
        return checked ? checked.value : 'reel';
    }

    /**
     * Stories are Instagram-only. When the content-type radios switch to
     * 'story', lock the platform selector to Instagram (and back when
     * switching to Reel/Video) so the upload can never target YouTube with
     * an unsupported post type.
     */
    function wireContentTypeLock(cardEl) {
        const contentTypeRadios = [...cardEl.querySelectorAll('.sas-upload-content-type')];
        const help              = cardEl.querySelector('[id^="sas-content-type-help"]');
        const ytToggle          = cardEl.querySelector('.sas-upload-platform[value="youtube"]');
        const igToggle          = cardEl.querySelector('.sas-upload-platform[value="instagram"]');
        if (!contentTypeRadios.length || !ytToggle || !igToggle) return;

        let ytWasChecked = ytToggle.checked;

        function apply() {
            const isStory = getSelectedContentType(cardEl) === 'story';
            if (help) help.style.display = isStory ? '' : 'none';

            const ytLabel = ytToggle.closest('.sas-platform-toggle');
            if (isStory) {
                ytWasChecked = ytToggle.checked;
                ytToggle.checked = false;
                ytToggle.disabled = true;
                if (ytLabel) ytLabel.classList.add('sas-platform-toggle--disabled');
                igToggle.checked = true;
            } else {
                ytToggle.disabled = false;
                if (ytLabel) ytLabel.classList.remove('sas-platform-toggle--disabled');
                ytToggle.checked = ytWasChecked;
            }
        }

        contentTypeRadios.forEach(r => r.addEventListener('change', apply));
        apply();
    }

    class ChunkedUploader {
        constructor(file, platforms, contentType, accountId, onProgress, onComplete, onError) {
            this.file        = file;
            this.platforms   = Array.isArray(platforms) ? platforms : [platforms || 'youtube'];
            this.contentType = contentType === 'story' ? 'story' : 'reel';
            this.accountId   = accountId || 0;
            this.onProgress  = onProgress;
            this.onComplete  = onComplete;
            this.onError     = onError;
            this.uploadId    = null;
            this.cancelled   = false;
        }

        async start() {
            try {
                if (this.file.size <= CHUNK_SIZE) {
                    return await this.singleUpload();
                }
                return await this.chunkedUpload();
            } catch (e) {
                this.onError(e.message);
            }
        }

        async singleUpload() {
            const fd = new FormData();
            fd.append('file', this.file);
            this.platforms.forEach(p => fd.append('platforms[]', p));
            fd.append('content_type', this.contentType);
            fd.append('account_id', this.accountId);

            this.onProgress(50);
            const result = await api.postForm('/upload', fd);
            this.onProgress(100);
            this.onComplete(result);
            return result;
        }

        async chunkedUpload() {
            const total = Math.ceil(this.file.size / CHUNK_SIZE);

            const { upload_id } = await api.post('/upload/init', {
                file_name:    this.file.name,
                file_size:    this.file.size,
                chunk_size:   CHUNK_SIZE,
                platforms:    this.platforms,
                content_type: this.contentType,
                account_id:   this.accountId,
            });
            this.uploadId = upload_id;

            for (let i = 0; i < total; i++) {
                if (this.cancelled) throw new Error('Upload cancelled');

                const start = i * CHUNK_SIZE;
                const blob  = this.file.slice(start, start + CHUNK_SIZE);
                const fd    = new FormData();
                fd.append('upload_id',   upload_id);
                fd.append('chunk_index', i);
                fd.append('chunk', blob, `chunk_${i}`);

                await api.postForm('/upload/chunk', fd);
                this.onProgress(Math.round(((i + 1) / total) * 90));
            }

            this.onProgress(95);
            const result = await api.post(`/upload/finalize/${upload_id}`, {});
            this.onProgress(100);
            this.onComplete(result);
            return result;
        }

        cancel() { this.cancelled = true; }
    }

    // =========================================================================
    // Upload UI builder
    // =========================================================================

    /**
     * @param areaId       - id of the drop-zone div
     * @param fileInputId  - id of the hidden <input type="file">
     * @param listId       - id of the upload progress list container
     * @param selectorEl   - DOM element containing .sas-upload-platform checkboxes
     *                       (pass null to auto-detect within the same card)
     */
    function initUploadArea(areaId, fileInputId, listId, selectorEl) {
        const area      = document.getElementById(areaId);
        const fileInput = document.getElementById(fileInputId);
        const list      = document.getElementById(listId);
        if (!area || !fileInput) return;

        const card = selectorEl ? selectorEl.closest('.sas-card') : area.closest('.sas-card');
        if (card) wireContentTypeLock(card);

        area.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', e => handleFiles(e.target.files));

        area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('sas-drag-over'); });
        area.addEventListener('dragleave', ()  => area.classList.remove('sas-drag-over'));
        area.addEventListener('drop', e => {
            e.preventDefault();
            area.classList.remove('sas-drag-over');
            handleFiles(e.dataTransfer.files);
        });

        async function handleFiles(files) {
            for (const file of Array.from(files)) {
                const allowed = ['video/mp4', 'video/quicktime'];
                if (!allowed.includes(file.type) && !file.name.match(/\.(mp4|mov)$/i)) {
                    toast.error(`${file.name}: only MP4 and MOV allowed`);
                    continue;
                }
                const scope = selectorEl || area.closest('.sas-card');
                const contentType = getSelectedContentType(scope);
                // Validate at least one platform is selected
                const platforms = getSelectedPlatforms(scope);
                if (!platforms.length) {
                    toast.error(
                        contentType === 'story'
                            ? 'Please connect an Instagram account to upload Stories.'
                            : 'Please select at least one platform (YouTube or Instagram).'
                    );
                    return;
                }
                await uploadFile(file, platforms, contentType);
            }
        }

        function uploadFile(file, platforms, contentType) {
            const platformLabel = platforms.join(' + ');
            const item          = createUploadItem(file.name, file.size, platformLabel);
            if (list) list.appendChild(item.el);

            const accountId = 0; // Primary account per platform is resolved server-side

            return new Promise(resolve => {
                const uploader = new ChunkedUploader(
                    file, platforms, contentType, accountId,
                    pct => item.setProgress(pct),
                    result => {
                        // result.videos = [{id, publish_date}, ...]
                        const videos = result.videos || [];
                        const dates  = videos.map(v => formatDate(v.publish_date)).join(', ');
                        item.setDone(dates ? `Scheduled: ${dates}` : 'Uploaded!');
                        const count = videos.length;
                        toast.success(
                            count > 1
                                ? `${file.name} uploaded — ${count} entries scheduled (${platformLabel})`
                                : `${file.name} uploaded and scheduled!`
                        );
                        reloadCurrentPage();
                        resolve(result);
                    },
                    msg => {
                        item.setError(msg);
                        toast.error(`Upload failed: ${msg}`);
                        resolve(); // continue with next file even if this one failed
                    }
                );
                uploader.start();
            });
        }
    }

    function createUploadItem(name, size, platformLabel) {
        const el = document.createElement('div');
        el.className = 'sas-upload-item';
        const platformInfo = platformLabel
            ? `<span class="sas-upload-item__platform">${esc(platformLabel)}</span>`
            : '';
        el.innerHTML = `
            <span class="sas-upload-item__name" title="${esc(name)}">${esc(name)}</span>
            ${platformInfo}
            <span class="sas-upload-item__size">${formatBytes(size)}</span>
            <div class="sas-upload-item__progress-wrap">
                <div class="sas-upload-item__progress-bar" style="width:0%"></div>
            </div>
            <span class="sas-upload-item__pct">0%</span>
            <span class="sas-upload-item__status"></span>
        `;
        const bar    = el.querySelector('.sas-upload-item__progress-bar');
        const pct    = el.querySelector('.sas-upload-item__pct');
        const status = el.querySelector('.sas-upload-item__status');

        return {
            el,
            setProgress(p) { bar.style.width = p + '%'; pct.textContent = p + '%'; },
            setDone(msg)   {
                bar.style.width = '100%';
                pct.textContent = '100%';
                status.className = 'sas-upload-item__status sas-upload-item__status--done';
                status.replaceChildren(
                    Object.assign(document.createElement('span'), { className: 'dashicons dashicons-yes-alt' }),
                    document.createTextNode(msg),
                );
            },
            setError(msg)  {
                status.className = 'sas-upload-item__status sas-upload-item__status--error';
                status.replaceChildren(
                    Object.assign(document.createElement('span'), { className: 'dashicons dashicons-warning' }),
                    document.createTextNode(msg),
                );
            },
        };
    }

    // =========================================================================
    // Dashboard
    // =========================================================================
    async function initDashboard() {
        await loadStats();
        await loadNextUpload();
        await loadRecentVideos();
        initUploadArea(
            'sas-upload-area',
            'sas-file-input',
            'sas-upload-list',
            document.getElementById('sas-platform-selector-dash')
        );

        document.getElementById('sas-quick-upload-btn')?.addEventListener('click', () => {
            document.getElementById('sas-file-input')?.click();
        });
    }

    async function loadStats() {
        try {
            const stats = await api.get('/stats');
            setText('sas-total',     stats.total     || 0);
            setText('sas-scheduled', stats.scheduled || 0);
            setText('sas-queued',    (stats.queued || 0) + (stats.publishing || 0));
            setText('sas-published', stats.published || 0);
            setText('sas-failed',    stats.failed    || 0);
            setText('sas-storage',   stats.storage_human || '0 B');
        } catch (e) {
            console.error('Stats error', e);
        }
    }

    async function loadNextUpload() {
        const el = document.getElementById('sas-next-upload');
        if (!el) return;

        try {
            const stats = await api.get('/stats');
            const next  = stats.next_scheduled;
            if (next) {
                el.innerHTML = `
                    <div class="sas-countdown">
                        <div class="sas-countdown__title">Next Scheduled Upload</div>
                        <div class="sas-countdown__time" id="sas-countdown-clock">--:--:--</div>
                        <div class="sas-countdown__date">${esc(next.title)} · ${formatDate(next.publish_date)}</div>
                    </div>
                `;
                startCountdown(new Date(next.publish_date));
            } else {
                el.innerHTML = '<p class="sas-empty">No videos scheduled yet.</p>';
            }
        } catch (e) {
            el.innerHTML = '<p class="sas-empty">Unable to load.</p>';
        }
    }

    function startCountdown(targetDate) {
        const el = document.getElementById('sas-countdown-clock');
        if (!el) return;

        function tick() {
            const diff = targetDate - Date.now();
            if (diff <= 0) { el.textContent = 'Publishing now…'; return; }
            const h = Math.floor(diff / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            el.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
            setTimeout(tick, 1000);
        }
        tick();
    }

    async function loadRecentVideos() {
        const el = document.getElementById('sas-recent-videos');
        if (!el) return;

        try {
            const videos = await api.get('/videos', { limit: 5, orderby: 'created_at', order: 'DESC' });
            if (!videos.length) {
                el.innerHTML = '<p class="sas-empty">No videos uploaded yet. Upload your first video above!</p>';
                return;
            }
            el.innerHTML = `
                <table class="sas-table">
                    <thead><tr>
                        <th>Title</th><th>Platform</th><th>Status</th><th>Scheduled</th>
                    </tr></thead>
                    <tbody>${videos.map(v => `
                        <tr>
                            <td><strong>${esc(v.title)}</strong> ${contentTypeBadge(v.content_type)}</td>
                            <td>${platformBadge(v.platform)}</td>
                            <td>${statusBadge(v.status)}</td>
                            <td>${v.publish_date ? formatDate(v.publish_date) : '<span class="sas-text-muted">—</span>'}</td>
                        </tr>
                    `).join('')}</tbody>
                </table>
            `;
        } catch (e) {
            el.innerHTML = '<p class="sas-empty">Unable to load videos.</p>';
        }
    }

    // =========================================================================
    // Videos page
    // =========================================================================
    const videosState = { page: 1, limit: 20, sort: 'created_at', order: 'DESC', search: '', status: '', platform: '' };
    let videosTotal = 0;

    async function initVideos() {
        await loadVideos();
        initUploadArea(
            'sas-upload-area-videos',
            'sas-file-input-videos',
            'sas-upload-list-videos',
            document.getElementById('sas-platform-selector-videos')
        );

        document.getElementById('sas-upload-btn-videos')?.addEventListener('click', () => {
            const panel = document.getElementById('sas-upload-panel');
            if (panel) panel.style.display = panel.style.display === 'none' ? '' : 'none';
        });

        let searchTimer;
        document.getElementById('sas-search')?.addEventListener('input', e => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                videosState.search = e.target.value;
                videosState.page   = 1;
                loadVideos();
            }, 350);
        });

        document.getElementById('sas-status-filter')?.addEventListener('change', e => {
            videosState.status = e.target.value;
            videosState.page   = 1;
            loadVideos();
        });

        document.getElementById('sas-platform-filter')?.addEventListener('change', e => {
            videosState.platform = e.target.value;
            videosState.page     = 1;
            loadVideos();
        });

        document.getElementById('sas-select-all')?.addEventListener('change', e => {
            document.querySelectorAll('.sas-video-check').forEach(cb => cb.checked = e.target.checked);
        });

        document.getElementById('sas-bulk-apply')?.addEventListener('click', bulkAction);

        document.querySelectorAll('.sas-sortable').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                if (videosState.sort === col) {
                    videosState.order = videosState.order === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    videosState.sort  = col;
                    videosState.order = 'DESC';
                }
                document.querySelectorAll('.sas-sortable').forEach(h => h.classList.remove('sas-sort-asc','sas-sort-desc'));
                th.classList.add(videosState.order === 'ASC' ? 'sas-sort-asc' : 'sas-sort-desc');
                loadVideos();
            });
        });

        initVideoModal();
    }

    async function loadVideos() {
        const tbody = document.getElementById('sas-videos-table-body');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="9" class="sas-table__loading"><div class="sas-loading-skeleton"></div></td></tr>';

        try {
            const offset = (videosState.page - 1) * videosState.limit;
            const videos = await api.get('/videos', {
                search:   videosState.search,
                status:   videosState.status,
                platform: videosState.platform,
                orderby:  videosState.sort,
                order:    videosState.order,
                limit:    videosState.limit,
                offset,
            });

            videosTotal = videos.length; // approximate

            if (!videos.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="sas-empty">No videos found.</td></tr>';
                document.getElementById('sas-pagination').innerHTML = '';
                return;
            }

            tbody.innerHTML = videos.map(v => {
                const thumb = v.thumbnail_url
                    ? `<img src="${esc(v.thumbnail_url)}" class="sas-table__thumb" alt="" />`
                    : `<span class="sas-table__no-thumb"><span class="dashicons dashicons-format-video"></span></span>`;
                const date  = v.publish_date ? formatDate(v.publish_date) : '—';
                const dur   = v.duration    ? formatDuration(Number(v.duration)) : '—';
                const size  = v.file_size   ? formatBytes(Number(v.file_size))   : '—';

                // Buttons shown per status
                const canSchedule    = v.status === 'draft';
                const canPublishNow  = !['published', 'publishing', 'queued'].includes(v.status);
                const actionBtns = [
                    canSchedule   ? `<button class="sas-btn sas-btn--sm sas-btn--primary sas-schedule-btn" data-id="${esc(v.id)}">Schedule</button>` : '',
                    canPublishNow ? `<button class="sas-btn sas-btn--sm sas-btn--publish-now sas-publish-now-btn" data-id="${esc(v.id)}" title="Publish immediately"><span class="dashicons dashicons-megaphone"></span> Now</button>` : '',
                    `<button class="sas-btn sas-btn--sm sas-btn--secondary sas-edit-btn" data-video='${JSON.stringify(v).replace(/'/g, "&#39;")}'>Edit</button>`,
                    `<button class="sas-btn sas-btn--sm sas-btn--ghost sas-delete-btn" data-id="${esc(v.id)}" title="Delete"><span class="dashicons dashicons-trash"></span></button>`,
                ].filter(Boolean).join('');

                return `
                <tr data-id="${esc(v.id)}">
                    <td><input type="checkbox" class="sas-video-check" value="${esc(v.id)}" /></td>
                    <td>${thumb}</td>
                    <td><strong>${esc(v.title)}</strong> ${contentTypeBadge(v.content_type)}</td>
                    <td>${platformBadge(v.platform)}</td>
                    <td>${statusBadge(v.status)}</td>
                    <td>${esc(date)}</td>
                    <td>${esc(dur)}</td>
                    <td>${esc(size)}</td>
                    <td><div class="sas-table__actions">${actionBtns}</div></td>
                </tr>`;
            }).join('');

            // Bind row actions
            tbody.querySelectorAll('.sas-schedule-btn').forEach(btn =>
                btn.addEventListener('click', () => scheduleVideo(btn.dataset.id, btn))
            );
            tbody.querySelectorAll('.sas-publish-now-btn').forEach(btn =>
                btn.addEventListener('click', () => publishNow(btn.dataset.id, btn))
            );
            tbody.querySelectorAll('.sas-edit-btn').forEach(btn =>
                btn.addEventListener('click', () => openVideoModal(JSON.parse(btn.dataset.video)))
            );
            tbody.querySelectorAll('.sas-delete-btn').forEach(btn =>
                btn.addEventListener('click', () => deleteVideo(btn.dataset.id))
            );

            renderPagination();
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="9" class="sas-empty">Error loading videos: ${esc(e.message)}</td></tr>`;
        }
    }

    function renderPagination() {
        const el = document.getElementById('sas-pagination');
        if (!el) return;
        // Simple prev/next since we don't have total count from API
        el.innerHTML = `
            <button class="sas-page-btn" id="sas-prev-page" ${videosState.page <= 1 ? 'disabled' : ''}>← Prev</button>
            <span class="sas-text-muted" style="padding:0 12px">Page ${videosState.page}</span>
            <button class="sas-page-btn" id="sas-next-page" ${videosTotal < videosState.limit ? 'disabled' : ''}>Next →</button>
        `;
        document.getElementById('sas-prev-page')?.addEventListener('click', () => { videosState.page--; loadVideos(); });
        document.getElementById('sas-next-page')?.addEventListener('click', () => { videosState.page++; loadVideos(); });
    }

    async function scheduleVideo(id, btn) {
        const orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Scheduling…';
        try {
            const res = await api.post(`/videos/${id}/schedule`, {});
            toast.success(`Scheduled for ${formatDate(res.publish_date)}`);
            loadVideos();
            loadStats();
        } catch (e) {
            toast.error('Schedule failed: ' + e.message);
            btn.disabled   = false;
            btn.textContent = orig;
        }
    }

    async function publishNow(id, btn) {
        if (!confirm('Publish this video immediately? It will be queued and processed within 5 minutes.')) return;
        const orig = btn.textContent;
        btn.disabled    = true;
        btn.textContent = 'Queuing…';
        try {
            const res = await api.post(`/videos/${id}/publish-now`, {});
            toast.success(res.message || 'Video queued for immediate publishing!');
            loadVideos();
            loadStats();
        } catch (e) {
            toast.error('Publish failed: ' + e.message);
            btn.disabled    = false;
            btn.textContent = orig;
        }
    }

    async function deleteVideo(id) {
        if (!confirm(sasData.strings.confirm_delete)) return;
        try {
            await api.del(`/videos/${id}`);
            toast.success('Video deleted.');
            loadVideos();
            loadStats();
        } catch (e) {
            toast.error('Delete failed: ' + e.message);
        }
    }

    async function bulkAction() {
        const action = document.getElementById('sas-bulk-action')?.value;
        if (!action) { toast.info('Please select a bulk action.'); return; }

        const ids = [...document.querySelectorAll('.sas-video-check:checked')].map(cb => cb.value);
        if (!ids.length) { toast.info(sasData.strings.no_selection); return; }

        if (action === 'delete' && !confirm(sasData.strings.confirm_bulk_del)) return;

        try {
            const res = await api.post('/videos/bulk', { action, ids });
            toast.success(`${res.affected} video(s) affected.`);
            document.getElementById('sas-select-all').checked = false;
            loadVideos();
            loadStats();
        } catch (e) {
            toast.error('Bulk action failed: ' + e.message);
        }
    }

    // Video modal
    function initVideoModal() {
        document.querySelectorAll('.sas-modal__close').forEach(btn =>
            btn.addEventListener('click', closeVideoModal)
        );
        document.querySelector('.sas-modal__backdrop')?.addEventListener('click', closeVideoModal);
        document.getElementById('sas-save-video')?.addEventListener('click', saveVideo);
    }

    function openVideoModal(video) {
        document.getElementById('sas-edit-id').value          = video.id;
        document.getElementById('sas-edit-title').value       = video.title || '';
        document.getElementById('sas-edit-description').value = video.description || '';

        let tagsStr = '';
        if (video.tags) {
            try { tagsStr = JSON.parse(video.tags).join(', '); } catch { tagsStr = video.tags; }
        }
        document.getElementById('sas-edit-tags').value = tagsStr;

        // Stories are Instagram-only and don't support captions/descriptions/
        // tags at all — editing them here would be silently ignored at
        // publish time, so disable them instead of implying they do something.
        const isStory = video.content_type === 'story';
        ['sas-edit-title', 'sas-edit-description', 'sas-edit-tags'].forEach(id => {
            document.getElementById(id).disabled = isStory;
        });
        let storyNote = document.getElementById('sas-edit-story-note');
        if (isStory && !storyNote) {
            storyNote = document.createElement('p');
            storyNote.id = 'sas-edit-story-note';
            storyNote.className = 'sas-field__help';
            storyNote.textContent = 'Stories don’t support captions, descriptions, or tags.';
            document.getElementById('sas-edit-title').closest('.sas-field')
                ?.insertAdjacentElement('beforebegin', storyNote);
        }
        if (storyNote) storyNote.style.display = isStory ? '' : 'none';

        if (video.publish_date) {
            // publish_date is stored in WP local timezone; parse as local (no Z)
            const d = new Date(video.publish_date.replace(' ', 'T'));
            const pad = n => String(n).padStart(2, '0');
            document.getElementById('sas-edit-date').value =
                `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        } else {
            document.getElementById('sas-edit-date').value = '';
        }

        document.getElementById('sas-edit-platform').value = video.platform || 'youtube';
        document.getElementById('sas-video-modal').removeAttribute('hidden');
    }

    function closeVideoModal() {
        document.getElementById('sas-video-modal')?.setAttribute('hidden', '');
    }

    async function saveVideo() {
        const id = document.getElementById('sas-edit-id').value;
        const data = {
            title:       document.getElementById('sas-edit-title').value.trim(),
            description: document.getElementById('sas-edit-description').value.trim(),
            tags:        document.getElementById('sas-edit-tags').value.split(',').map(t => t.trim()).filter(Boolean),
            platform:    document.getElementById('sas-edit-platform').value,
        };
        const dateVal = document.getElementById('sas-edit-date').value;
        if (dateVal) data.publish_date = dateVal.replace('T', ' ') + ':00';

        const btn = document.getElementById('sas-save-video');
        btn.disabled    = true;
        btn.textContent = sasData.strings.saving;

        try {
            await api.put(`/videos/${id}`, data);
            toast.success('Video updated.');
            closeVideoModal();
            loadVideos();
        } catch (e) {
            toast.error('Save failed: ' + e.message);
        } finally {
            btn.disabled    = false;
            btn.textContent = 'Save Changes';
        }
    }

    // =========================================================================
    // Calendar
    // =========================================================================
    const cal = { year: 0, month: 0, events: [] };

    async function initCalendar() {
        const now = new Date();
        cal.year  = now.getFullYear();
        cal.month = now.getMonth();
        await renderCalendar();

        document.getElementById('sas-cal-prev')?.addEventListener('click', async () => {
            cal.month--;
            if (cal.month < 0) { cal.month = 11; cal.year--; }
            await renderCalendar();
        });

        document.getElementById('sas-cal-next')?.addEventListener('click', async () => {
            cal.month++;
            if (cal.month > 11) { cal.month = 0; cal.year++; }
            await renderCalendar();
        });
    }

    async function renderCalendar() {
        const title = document.getElementById('sas-cal-title');
        if (title) {
            title.textContent = new Date(cal.year, cal.month, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        }

        const start = `${cal.year}-${String(cal.month + 1).padStart(2,'0')}-01`;
        const lastDay = new Date(cal.year, cal.month + 1, 0).getDate();
        const end   = `${cal.year}-${String(cal.month + 1).padStart(2,'0')}-${String(lastDay).padStart(2,'0')}`;

        try {
            cal.events = await api.get('/calendar', { start, end });
        } catch (e) {
            cal.events = [];
        }

        const body     = document.getElementById('sas-calendar-body');
        if (!body) return;

        const firstDay = new Date(cal.year, cal.month, 1).getDay();
        const today    = new Date();
        const todayStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;

        // Group events by date
        const byDate = {};
        cal.events.forEach(ev => {
            const d = ev.date ? ev.date.slice(0,10) : null;
            if (d) (byDate[d] = byDate[d] || []).push(ev);
        });

        let html = '';

        // Empty cells before month start
        for (let i = 0; i < firstDay; i++) {
            html += '<div class="sas-cal-day sas-cal-day--empty"></div>';
        }

        for (let d = 1; d <= lastDay; d++) {
            const dateStr  = `${cal.year}-${String(cal.month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            const isToday  = dateStr === todayStr;
            const dayEvts  = byDate[dateStr] || [];

            html += `<div class="sas-cal-day${isToday ? ' sas-cal-day--today' : ''}" data-date="${dateStr}">`;
            html += `<div class="sas-cal-day__num">${d}</div>`;
            dayEvts.slice(0,3).forEach(ev => {
                html += `<span class="sas-cal-event sas-cal-event--${esc(ev.status)}" data-id="${esc(ev.id)}" title="${esc(ev.title)}">${esc(ev.title)}</span>`;
            });
            if (dayEvts.length > 3) {
                html += `<span class="sas-text-muted" style="font-size:11px">+${dayEvts.length - 3} more</span>`;
            }
            html += '</div>';
        }

        body.innerHTML = html;

        // Click events to show popover
        body.querySelectorAll('.sas-cal-event').forEach(el => {
            el.addEventListener('click', e => showCalPopover(e, Number(el.dataset.id)));
        });
    }

    function showCalPopover(e, videoId) {
        const video  = cal.events.find(v => v.id === videoId);
        if (!video) return;

        const pop = document.getElementById('sas-cal-popover');
        if (!pop) return;

        pop.innerHTML = `
            <strong>${esc(video.title)}</strong><br>
            ${platformBadge(video.platform)} ${statusBadge(video.status)}<br>
            <span class="sas-text-muted">${video.date ? formatDate(video.date) : '—'}</span>
        `;
        pop.style.left = e.pageX + 10 + 'px';
        pop.style.top  = e.pageY + 10 + 'px';
        pop.removeAttribute('hidden');

        const hide = () => { pop.setAttribute('hidden', ''); document.removeEventListener('click', hide); };
        setTimeout(() => document.addEventListener('click', hide), 10);
        e.stopPropagation();
    }

    // =========================================================================
    // Accounts
    // =========================================================================
    async function initAccounts() {
        await loadAccounts();

        document.getElementById('sas-connect-youtube')?.addEventListener('click', async (btn) => {
            const el = btn.target || btn.currentTarget;
            el.disabled = true;
            el.textContent = sasData.strings.connecting;
            try {
                const { url } = await api.get('/oauth/youtube/url');
                window.location.href = url;
            } catch (e) {
                toast.error(e.message);
                el.disabled    = false;
                el.textContent = 'Connect YouTube';
            }
        });

        document.getElementById('sas-connect-instagram')?.addEventListener('click', async (btn) => {
            const el = btn.target || btn.currentTarget;
            el.disabled = true;
            el.textContent = sasData.strings.connecting;
            try {
                const { url } = await api.get('/oauth/instagram/url');
                window.location.href = url;
            } catch (e) {
                toast.error(e.message);
                el.disabled    = false;
                el.textContent = 'Connect Instagram';
            }
        });
    }

    async function loadAccounts() {
        try {
            const accounts = await api.get('/accounts');

            // Update status for each platform card
            const ytCard  = document.getElementById('sas-youtube-status');
            const igCard  = document.getElementById('sas-instagram-status');

            const ytAcc = accounts.find(a => a.platform === 'youtube');
            const igAcc = accounts.find(a => a.platform === 'instagram');

            if (ytCard) {
                ytCard.innerHTML = ytAcc
                    ? `<span class="sas-account-card__status--connected"><span class="dashicons dashicons-yes-alt"></span> Connected: ${esc(ytAcc.account_name)}</span>`
                    : '<span class="sas-text-muted">Not connected</span>';
            }

            if (igCard) {
                igCard.innerHTML = igAcc
                    ? `<span class="sas-account-card__status--connected"><span class="dashicons dashicons-yes-alt"></span> Connected: ${esc(igAcc.account_name)}</span>`
                    : '<span class="sas-text-muted">Not connected</span>';
            }

            if (accounts.length > 0) {
                const card = document.getElementById('sas-accounts-list-card');
                const list = document.getElementById('sas-connected-accounts');
                if (card) card.style.display = '';
                if (list) {
                    list.innerHTML = accounts.map(a => `
                        <div class="sas-connected-account-row">
                            <span class="sas-connected-account-row__platform">${platformBadge(a.platform)}</span>
                            <span class="sas-connected-account-row__name">${esc(a.account_name)}</span>
                            <span class="sas-connected-account-row__expires">${tokenExpiresLabel(a.token_expires_at)}</span>
                            <button class="sas-btn sas-btn--sm sas-btn--ghost sas-disconnect-btn" data-id="${esc(a.id)}">Disconnect</button>
                        </div>
                    `).join('');

                    list.querySelectorAll('.sas-disconnect-btn').forEach(btn =>
                        btn.addEventListener('click', async () => {
                            if (!confirm('Disconnect this account?')) return;
                            try {
                                await api.del(`/accounts/${btn.dataset.id}`);
                                toast.success('Account disconnected.');
                                loadAccounts();
                            } catch (e) {
                                toast.error(e.message);
                            }
                        })
                    );
                }
            }
        } catch (e) {
            console.error('Accounts error', e);
        }
    }

    // =========================================================================
    // Settings
    // =========================================================================
    // =========================================================================
    // Upload-time slot helpers (used by Settings page)
    // =========================================================================

    function addMinutes(timeStr, minutes) {
        const [h, m] = timeStr.split(':').map(Number);
        const total  = h * 60 + m + minutes;
        return `${String(Math.floor(total / 60) % 24).padStart(2,'0')}:${String(total % 60).padStart(2,'0')}`;
    }

    function renderUploadTimes(count, existingTimes = []) {
        const container = document.getElementById('sas-upload-times-container');
        if (!container) return;

        const resolved = [];
        for (let i = 0; i < count; i++) {
            if (existingTimes[i]) {
                resolved.push(existingTimes[i]);
            } else {
                resolved.push(addMinutes(resolved[i - 1] || '19:00', 5));
            }
        }

        const grid = document.createElement('div');
        grid.className = 'sas-upload-times-grid';

        resolved.forEach((t, i) => {
            const field = document.createElement('div');
            field.className = 'sas-field';
            field.innerHTML =
                `<label for="sas-upload-time-${i}">Upload Time ${i + 1}</label>` +
                `<input type="time" id="sas-upload-time-${i}" class="sas-input sas-upload-time-input" value="${esc(t)}" />`;
            grid.appendChild(field);
        });

        container.innerHTML = '';
        container.appendChild(grid);

        container.querySelectorAll('.sas-upload-time-input').forEach(inp =>
            inp.addEventListener('change', validateUploadTimes)
        );
        validateUploadTimes();
    }

    function validateUploadTimes() {
        const inputs = [...document.querySelectorAll('.sas-upload-time-input')];
        const values = inputs.map(i => i.value).filter(Boolean);

        inputs.forEach(i => i.classList.remove('sas-input--error'));
        if (values.length < 2) return true;

        const toMin = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
        const sorted = [...values].map(toMin).sort((a, b) => a - b);

        let ok = true;
        for (let i = 1; i < sorted.length; i++) {
            if (sorted[i] - sorted[i - 1] < 5) { ok = false; break; }
        }

        if (!ok) inputs.forEach(inp => { if (inp.value) inp.classList.add('sas-input--error'); });
        return ok;
    }

    // =========================================================================
    // Settings
    // =========================================================================
    async function initSettings() {
        await loadSettings();

        // Re-render time slots whenever the count changes
        document.getElementById('sas-uploads-per-day')?.addEventListener('input', e => {
            const count = Math.min(15, Math.max(1, parseInt(e.target.value) || 1));
            const existingTimes = [...document.querySelectorAll('.sas-upload-time-input')].map(i => i.value);
            renderUploadTimes(count, existingTimes);
        });

        document.getElementById('sas-settings-form')?.addEventListener('submit', async e => {
            e.preventDefault();

            if (!validateUploadTimes()) {
                toast.error('Upload times must be at least 5 minutes apart and cannot repeat.');
                return;
            }

            const btn = document.getElementById('sas-save-settings-btn');
            btn.disabled    = true;
            btn.textContent = sasData.strings.saving;

            // Auto-fill any blank time slots with previous + 5 min before collecting
            const timeInputs = [...document.querySelectorAll('.sas-upload-time-input')];
            timeInputs.forEach((inp, i) => {
                if (!inp.value) {
                    const prev = i > 0 ? timeInputs[i - 1].value : '19:00';
                    inp.value = addMinutes(prev || '19:00', 5);
                }
            });

            const data = {
                uploads_per_day:     document.getElementById('sas-uploads-per-day')?.value,
                upload_times:        timeInputs.map(i => i.value).filter(Boolean),
                weekdays:            [...document.querySelectorAll('input[name="weekdays[]"]:checked')].map(cb => cb.value),
                default_description: document.getElementById('sas-default-description')?.value || '',
                default_tags:        document.getElementById('sas-default-tags')?.value || '',
                youtube_client_id:   document.getElementById('sas-yt-client-id')?.value || '',
                youtube_category:    document.getElementById('sas-yt-category')?.value || '22',
                youtube_privacy:     document.getElementById('sas-yt-privacy')?.value || 'public',
                instagram_app_id:    document.getElementById('sas-ig-app-id')?.value || '',
                instagram_config_id: document.getElementById('sas-ig-config-id')?.value || '',
            };

            const ytSecret = document.getElementById('sas-yt-client-secret')?.value;
            if (ytSecret) data.youtube_client_secret = ytSecret;

            const igSecret = document.getElementById('sas-ig-app-secret')?.value;
            if (igSecret) data.instagram_app_secret = igSecret;

            try {
                await api.post('/settings', data);
                toast.success(sasData.strings.saved);
            } catch (e) {
                toast.error(sasData.strings.error + ': ' + e.message);
            } finally {
                btn.disabled    = false;
                btn.textContent = 'Save Settings';
            }
        });
    }

    async function loadSettings() {
        try {
            const s = await api.get('/settings');

            if (s.uploads_per_day) setVal('sas-uploads-per-day', s.uploads_per_day);

            // Resolve which times to show: new upload_times array → legacy upload_time → default
            const count = parseInt(s.uploads_per_day) || 1;
            const times = Array.isArray(s.upload_times) && s.upload_times.length
                ? s.upload_times
                : s.upload_time ? [s.upload_time] : ['19:00'];
            renderUploadTimes(count, times);

            if (s.weekdays && Array.isArray(s.weekdays)) {
                document.querySelectorAll('input[name="weekdays[]"]').forEach(cb => {
                    cb.checked = s.weekdays.includes(cb.value);
                });
            }

            if (s.default_description) setVal('sas-default-description', s.default_description);
            if (s.default_tags)        setVal('sas-default-tags', s.default_tags);
            if (s.youtube_client_id)   setVal('sas-yt-client-id', s.youtube_client_id);
            if (s.youtube_category)    setVal('sas-yt-category', s.youtube_category);
            if (s.youtube_privacy)     setVal('sas-yt-privacy', s.youtube_privacy);
            if (s.instagram_app_id)    setVal('sas-ig-app-id', s.instagram_app_id);
            if (s.instagram_config_id) setVal('sas-ig-config-id', s.instagram_config_id);
        } catch (e) {
            console.error('Settings error', e);
        }
    }

    // =========================================================================
    // Logs
    // =========================================================================
    async function initLogs() {
        await loadLogs();

        document.getElementById('sas-log-level-filter')?.addEventListener('change', loadLogs);
        document.getElementById('sas-clear-logs')?.addEventListener('click', async () => {
            if (!confirm('Clear all logs?')) return;
            try {
                await api.del('/logs');
                toast.success('Logs cleared.');
                loadLogs();
            } catch (e) {
                toast.error(e.message);
            }
        });
    }

    async function loadLogs() {
        const tbody = document.getElementById('sas-logs-table-body');
        if (!tbody) return;

        const level = document.getElementById('sas-log-level-filter')?.value || '';
        tbody.innerHTML = '<tr><td colspan="5" class="sas-table__loading"><div class="sas-loading-skeleton"></div></td></tr>';

        try {
            const logs = await api.get('/logs', { level, limit: 200 });
            if (!logs.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="sas-empty">No logs found.</td></tr>';
                return;
            }
            tbody.innerHTML = logs.map(l => `
                <tr>
                    <td class="sas-text-muted">${esc(formatDate(l.created_at ? l.created_at.replace(' ', 'T') + 'Z' : ''))}</td>
                    <td><span class="sas-badge sas-badge--level-${esc(l.level)}">${esc(l.level)}</span></td>
                    <td><code>${esc(l.action)}</code></td>
                    <td>${esc(l.message)}</td>
                    <td>${l.video_id ? `#${esc(l.video_id)}` : '—'}</td>
                </tr>
            `).join('');
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="5" class="sas-empty">Error: ${esc(e.message)}</td></tr>`;
        }
    }

    // =========================================================================
    // Utilities
    // =========================================================================
    function esc(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val;
    }

    function formatBytes(bytes) {
        if (!bytes) return '0 B';
        const units = ['B','KB','MB','GB','TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[Math.min(i, units.length - 1)];
    }

    function formatDuration(sec) {
        if (!sec) return '0:00';
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        if (h > 0) return `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        return `${m}:${String(s).padStart(2,'0')}`;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        return new Date(dateStr).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    }

    function tokenExpiresLabel(expiresAt) {
        if (!expiresAt) return '';
        const d = new Date(expiresAt.replace(' ', 'T') + 'Z');
        if (d <= new Date()) {
            return '<span title="Token auto-refreshes before each publish">Auto-refreshes when needed</span>';
        }
        return 'Expires: ' + d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    }

    function statusBadge(status) {
        const labels = {
            draft:'Draft', queued:'Queued', scheduled:'Scheduled',
            publishing:'Publishing', published:'Published',
            failed:'Failed', cancelled:'Cancelled',
        };
        return `<span class="sas-badge sas-badge--${esc(status)}">${esc(labels[status] || status)}</span>`;
    }

    function platformBadge(platform) {
        return `<span class="sas-platform sas-platform--${esc(platform)}">${esc(platform)}</span>`;
    }

    function contentTypeBadge(contentType) {
        if (contentType !== 'story') return '';
        return `<span class="sas-badge sas-badge--scheduled">${esc('Story')}</span>`;
    }

    function reloadCurrentPage() {
        const page = document.querySelector('[data-page]')?.dataset.page;
        if (page === 'dashboard') { loadStats(); loadNextUpload(); loadRecentVideos(); }
        if (page === 'videos')    loadVideos();
    }

    // =========================================================================
    // Publish-to toggles — show the connected account per platform and
    // disable platforms that have no account connected yet.
    // =========================================================================
    async function annotatePlatformToggles() {
        const toggles = document.querySelectorAll('.sas-upload-platform');
        if (!toggles.length) return;
        try {
            const accounts = await api.get('/accounts');
            toggles.forEach((input) => {
                const acc   = accounts.find((a) => a.platform === input.value);
                const label = input.closest('.sas-platform-toggle');
                const inner = label?.querySelector('.sas-platform-toggle__inner');
                if (acc) {
                    input.disabled = false;
                    if (inner && !inner.querySelector('.sas-platform-toggle__account')) {
                        const s = document.createElement('span');
                        s.className = 'sas-platform-toggle__account';
                        s.style.cssText = 'font-size:11px;opacity:.75;margin-left:6px;';
                        s.textContent = acc.account_name;
                        inner.appendChild(s);
                    }
                } else {
                    input.checked  = false;
                    input.disabled = true;
                    if (label) {
                        label.style.opacity = '.5';
                        label.style.cursor  = 'not-allowed';
                        label.title = 'Not connected — connect this account from your Soulitam Social dashboard (Social Accounts).';
                    }
                }
            });
        } catch (e) {
            console.error('Platform toggle annotate failed', e);
        }
    }

    // =========================================================================
    // Support
    // =========================================================================
    function initSupport() {
        document.getElementById('sas-support-form')?.addEventListener('submit', async e => {
            e.preventDefault();

            const btn = document.getElementById('sas-send-support-btn');
            btn.disabled    = true;
            btn.textContent = sasData.strings.saving;

            const data = {
                name:    document.getElementById('sas-support-name')?.value || '',
                email:   document.getElementById('sas-support-email')?.value || '',
                topic:   document.getElementById('sas-support-topic')?.value || '',
                message: document.getElementById('sas-support-message')?.value || '',
            };

            try {
                await api.post('/contact', data);
                toast.success('Message sent! Our team will get back to you shortly.');
                e.target.reset();
                document.getElementById('sas-support-name').value  = data.name;
                document.getElementById('sas-support-email').value = data.email;
            } catch (err) {
                toast.error(sasData.strings.error + ': ' + err.message);
            } finally {
                btn.disabled    = false;
                btn.textContent = 'Send Message';
            }
        });
    }

    // =========================================================================
    // Router – dispatch based on current page
    // =========================================================================
    function init() {
        darkMode.init();

        const page = document.querySelector('.sas-wrap')?.dataset.page;
        if (page === 'dashboard') initDashboard();
        if (page === 'videos')    initVideos();
        if (page === 'calendar')  initCalendar();
        if (page === 'accounts')  initAccounts();
        if (page === 'settings')  initSettings();
        if (page === 'logs')      initLogs();
        if (page === 'support')   initSupport();

        annotatePlatformToggles();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

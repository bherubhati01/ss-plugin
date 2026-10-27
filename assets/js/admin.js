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
        patch(ep, data)  { return this.request(ep, { method: 'PATCH',  body: JSON.stringify(data) }); },
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

    // Stories have no YouTube equivalent — mirrors the backend's
    // VideoService._resolve_accounts, which re-enabled Facebook here after
    // FacebookPublisher._publish_story was extended to wait for Meta's
    // publishing_phase status (the likely cause of the Graph API failures
    // that had this disabled). Unverified against a real Facebook Page as
    // of this change.
    const STORY_CAPABLE_PLATFORMS = ['instagram', 'facebook'];
    // Photo posts have no YouTube equivalent at all (YouTube's API has no
    // photo-posting capability, full stop) — mirrors the backend's
    // VideoService._resolve_accounts PHOTO branch.
    const PHOTO_CAPABLE_PLATFORMS = ['instagram', 'facebook'];

    const CONTENT_TYPE_HELP_TEXT = {
        story: 'Stories can only publish to Instagram or Facebook, and publish without a caption.',
        photo: 'Photo posts can only publish to Instagram or Facebook — YouTube has no photo-posting capability.',
    };

    function capablePlatformsFor(contentType) {
        if (contentType === 'story') return STORY_CAPABLE_PLATFORMS;
        if (contentType === 'photo') return PHOTO_CAPABLE_PLATFORMS;
        return null; // reel/video — every connected platform is eligible
    }

    /**
     * When the content-type radios switch to 'story' or 'photo', disable +
     * uncheck any platform toggle that post type can't publish to (and
     * restore it when switching back to Reel/Video) so the upload can
     * never target a platform with an unsupported post type. If switching
     * leaves nothing checked, auto-check the first eligible toggle rather
     * than silently leaving the upload targeting zero accounts.
     */
    function wireContentTypeLock(cardEl) {
        const contentTypeRadios = [...cardEl.querySelectorAll('.sas-upload-content-type')];
        const help              = cardEl.querySelector('[id^="sas-content-type-help"]');
        const toggles           = [...cardEl.querySelectorAll('.sas-upload-platform')];
        if (!contentTypeRadios.length || !toggles.length) return;

        const wasChecked = new Map(toggles.map(t => [t.value, t.checked]));

        function apply() {
            const contentType = getSelectedContentType(cardEl);
            const capable      = capablePlatformsFor(contentType);
            if (help) {
                help.textContent = CONTENT_TYPE_HELP_TEXT[contentType] || '';
                help.style.display = capable ? '' : 'none';
            }

            toggles.forEach(toggle => {
                const label = toggle.closest('.sas-platform-toggle');
                // A toggle annotatePlatformToggles() hid has no connected
                // account at all — leave it alone either way, it's never
                // eligible regardless of content type.
                if (label?.hidden) return;

                const eligible = !capable || capable.includes(toggle.value);
                if (!eligible) {
                    wasChecked.set(toggle.value, toggle.checked);
                    toggle.checked = false;
                    toggle.disabled = true;
                    if (label) label.classList.add('sas-platform-toggle--disabled');
                } else {
                    toggle.disabled = false;
                    if (label) label.classList.remove('sas-platform-toggle--disabled');
                    toggle.checked = wasChecked.get(toggle.value) ?? toggle.checked;
                }
            });

            if (capable && !toggles.some(t => capable.includes(t.value) && t.checked)) {
                const first = toggles.find(t => capable.includes(t.value) && !t.closest('.sas-platform-toggle')?.hidden);
                if (first) first.checked = true;
            }
        }

        contentTypeRadios.forEach(r => r.addEventListener('change', apply));
        apply();
    }

    class ChunkedUploader {
        /**
         * @param file File
         * @param meta { platforms, content_type, caption, description, tags,
         *               scheduled_at ('Y-m-d H:i:s', WP-local, or null),
         *               publish_now }
         */
        constructor(file, meta, onProgress, onComplete, onError) {
            this.file        = file;
            this.meta        = meta;
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
            this.meta.platforms.forEach(p => fd.append('platforms[]', p));
            fd.append('content_type', this.meta.content_type);
            fd.append('account_id', 0);
            fd.append('caption', this.meta.caption || '');
            fd.append('description', this.meta.description || '');
            (this.meta.tags || []).forEach(t => fd.append('tags[]', t));
            if (this.meta.scheduled_at) fd.append('scheduled_at', this.meta.scheduled_at);
            if (this.meta.publish_now)  fd.append('publish_now', '1');

            this.onProgress(50);
            const result = await api.postForm('/upload', fd);
            this.onProgress(100);
            this.onComplete(result);
            return result;
        }

        async chunkedUpload() {
            const total = Math.ceil(this.file.size / CHUNK_SIZE);

            const { upload_id } = await api.post('/upload/init', {
                file_name: this.file.name,
                file_size: this.file.size,
                ...this.meta,
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

    /**
     * Uploads 1-10 images in a single multipart request and registers them
     * as one photo/carousel post. Images are small enough (25 MB ceiling
     * each, enforced server-side too) that chunking isn't needed — unlike
     * ChunkedUploader there's no real progress to report mid-flight, so
     * this jumps to 50% before the request and 100% after, same simplified
     * convention ChunkedUploader.singleUpload() already uses for one file.
     */
    class PhotoUploader {
        /**
         * @param files File[]
         * @param meta  { platforms, caption, description, tags, scheduled_at, publish_now }
         */
        constructor(files, meta, onProgress, onComplete, onError) {
            this.files       = files;
            this.meta        = meta;
            this.onProgress  = onProgress;
            this.onComplete  = onComplete;
            this.onError     = onError;
        }

        async start() {
            try {
                const fd = new FormData();
                this.files.forEach(f => fd.append('files[]', f));
                this.meta.platforms.forEach(p => fd.append('platforms[]', p));
                fd.append('caption', this.meta.caption || '');
                fd.append('description', this.meta.description || '');
                (this.meta.tags || []).forEach(t => fd.append('tags[]', t));
                if (this.meta.scheduled_at) fd.append('scheduled_at', this.meta.scheduled_at);
                if (this.meta.publish_now)  fd.append('publish_now', '1');

                this.onProgress(50);
                const result = await api.postForm('/upload/photo', fd);
                this.onProgress(100);
                this.onComplete(result);
                return result;
            } catch (e) {
                this.onError(e.message);
            }
        }

        cancel() { /* no in-flight chunks to stop — the single request either finishes or errors */ }
    }

    // =========================================================================
    // Upload wizard — Content step (post type, destinations, file) then
    // Details step (caption/description/tags, schedule, Publish Now/Schedule),
    // same shape as the dashboard's own New Video dialog. One shared modal
    // (admin/templates/partials/upload-wizard-modal.php) is included on both
    // the Dashboard and Videos page; every ".sas-upload-trigger" button on
    // the current page opens it. The real upload only starts once Publish
    // Now/Schedule is clicked from the Details step — selecting a file in
    // step 1 just previews it, it doesn't upload yet.
    // =========================================================================
    // Always an array — reel/story keep exactly one entry (a new selection
    // replaces it, same as the old single-file behavior); photo allows up
    // to MAX_PHOTO_FILES, added to rather than replaced.
    let uploadSelectedFiles = [];
    const MAX_PHOTO_FILES = 10;
    const VIDEO_MIME_TYPES = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'];
    const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    function initUploadWizard() {
        const modal = document.getElementById('sas-upload-modal');
        if (!modal) return;

        const contentPanel  = modal.querySelector('[data-panel="content"]');
        const detailsPanel  = modal.querySelector('[data-panel="details"]');
        const stepContentEl = modal.querySelector('.sas-wizard-step[data-step="content"]');
        const stepDetailsEl = modal.querySelector('.sas-wizard-step[data-step="details"]');

        const backBtn       = document.getElementById('sas-upload-back');
        const nextBtn        = document.getElementById('sas-upload-next');
        const publishNowBtn  = document.getElementById('sas-upload-publish-now');
        const scheduleBtn    = document.getElementById('sas-upload-schedule-btn');
        const cancelBtn      = document.getElementById('sas-upload-cancel');
        const closeBtn       = document.getElementById('sas-upload-modal-close');
        const backdrop       = modal.querySelector('.sas-modal__backdrop');

        const area            = document.getElementById('sas-upload-area');
        const areaTitle        = document.getElementById('sas-upload-area-title');
        const areaHint          = document.getElementById('sas-upload-area-hint');
        const fileInput        = document.getElementById('sas-file-input');
        const preview           = document.getElementById('sas-upload-file-preview');
        const progressWrap      = document.getElementById('sas-upload-progress-wrap');
        const accountsError     = document.getElementById('sas-upload-accounts-error');
        const captionEl          = document.getElementById('sas-upload-caption');
        const descriptionEl      = document.getElementById('sas-upload-description');
        const tagsEl              = document.getElementById('sas-upload-tags');
        const scheduleInputEl      = document.getElementById('sas-upload-schedule');

        wireContentTypeLock(modal);

        document.querySelectorAll('.sas-upload-trigger').forEach(btn =>
            btn.addEventListener('click', openModal)
        );

        const allButtons = [nextBtn, backBtn, publishNowBtn, scheduleBtn, cancelBtn, closeBtn];

        function openModal() {
            resetWizard();
            modal.hidden = false;
        }

        function closeModal() {
            modal.hidden = true;
        }

        closeBtn?.addEventListener('click', closeModal);
        backdrop?.addEventListener('click', closeModal);
        cancelBtn?.addEventListener('click', closeModal);

        function resetWizard() {
            uploadSelectedFiles = [];
            preview.style.display = 'none';
            preview.innerHTML = '';
            progressWrap.style.display = 'none';
            progressWrap.innerHTML = '';
            accountsError.style.display = 'none';
            captionEl.value = '';
            descriptionEl.value = '';
            tagsEl.value = '';
            scheduleInputEl.value = '';
            allButtons.forEach(b => { if (b) b.disabled = false; });
            applyFileInputMode();
            showStep('content');
        }

        /**
         * Swaps the dropzone's accept/multiple attributes and copy between
         * "one video file" (reel/story) and "up to 10 images" (photo) —
         * fired on open and on every content-type change.
         */
        function applyFileInputMode() {
            const isPhoto = getSelectedContentType(modal) === 'photo';
            fileInput.accept = isPhoto
                ? 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'
                : 'video/mp4,video/quicktime,video/x-msvideo,video/webm,.mp4,.mov,.avi,.webm';
            fileInput.multiple = isPhoto;
            if (areaTitle) areaTitle.textContent = isPhoto
                ? 'Drop images here or click to browse'
                : 'Drop a video here or click to browse';
            if (areaHint) areaHint.textContent = isPhoto
                ? `JPG, PNG, WEBP — up to ${MAX_PHOTO_FILES} images, max 25 MB each`
                : 'MP4, MOV, AVI, WEBM — max 5 GB';
        }
        modal.querySelectorAll('.sas-upload-content-type').forEach(r => r.addEventListener('change', () => {
            // Switching post type mid-selection would otherwise leave stale
            // video/image files picked under the previous mode — clear and
            // let the user re-pick under the new mode's rules.
            uploadSelectedFiles = [];
            renderPreview();
            applyFileInputMode();
        }));

        function showStep(step) {
            contentPanel.hidden = step !== 'content';
            detailsPanel.hidden = step !== 'details';
            stepContentEl.classList.toggle('is-active', step === 'content');
            stepContentEl.classList.toggle('is-done', step === 'details');
            stepDetailsEl.classList.toggle('is-active', step === 'details');
            backBtn.style.display       = step === 'details' ? '' : 'none';
            nextBtn.style.display       = step === 'content' ? '' : 'none';
            publishNowBtn.style.display = step === 'details' ? '' : 'none';
            scheduleBtn.style.display   = step === 'details' ? '' : 'none';

            if (step === 'details') {
                const isStory = getSelectedContentType(modal) === 'story';
                ['sas-upload-caption-field', 'sas-upload-description-field', 'sas-upload-tags-field'].forEach(id => {
                    document.getElementById(id).style.display = isStory ? 'none' : '';
                });
                document.getElementById('sas-upload-story-note').style.display = isStory ? '' : 'none';
            }
        }

        area.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', e => addFiles(e.target.files));
        area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('sas-drag-over'); });
        area.addEventListener('dragleave', () => area.classList.remove('sas-drag-over'));
        area.addEventListener('drop', e => {
            e.preventDefault();
            area.classList.remove('sas-drag-over');
            addFiles(e.dataTransfer.files);
        });

        /**
         * @param fileList FileList from an <input type=file> change event or a drop
         */
        function addFiles(fileList) {
            const files = [...(fileList || [])];
            if (!files.length) return;
            const isPhoto = getSelectedContentType(modal) === 'photo';

            if (!isPhoto) {
                const file = files[0];
                if (!VIDEO_MIME_TYPES.includes(file.type) && !file.name.match(/\.(mp4|mov|avi|webm)$/i)) {
                    toast.error(`${file.name}: only MP4, MOV, AVI, or WEBM allowed`);
                    return;
                }
                uploadSelectedFiles = [file]; // a new pick always replaces, same as the old single-file behavior
                renderPreview();
                return;
            }

            const accepted = [];
            for (const file of files) {
                if (!IMAGE_MIME_TYPES.includes(file.type) && !file.name.match(/\.(jpe?g|png|webp)$/i)) {
                    toast.error(`${file.name}: only JPG, PNG, or WEBP allowed`);
                    continue;
                }
                accepted.push(file);
            }
            const room = MAX_PHOTO_FILES - uploadSelectedFiles.length;
            if (accepted.length > room) {
                toast.error(`A photo post supports at most ${MAX_PHOTO_FILES} images — only the first ${room} of these were added.`);
            }
            uploadSelectedFiles = uploadSelectedFiles.concat(accepted.slice(0, Math.max(0, room)));
            renderPreview();
        }

        function removeFileAt(index) {
            uploadSelectedFiles.splice(index, 1);
            renderPreview();
        }

        function renderPreview() {
            if (!uploadSelectedFiles.length) {
                preview.style.display = 'none';
                preview.innerHTML = '';
                return;
            }
            preview.style.display = '';
            preview.innerHTML = '';
            uploadSelectedFiles.forEach((file, i) => {
                const row = document.createElement('div');
                row.className = 'sas-upload-item';
                row.innerHTML = `
                    <span class="sas-upload-item__name" title="${esc(file.name)}">${esc(file.name)}</span>
                    <span class="sas-upload-item__size">${formatBytes(file.size)}</span>
                    <button type="button" class="sas-upload-item__remove" title="Remove">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                `;
                row.querySelector('.sas-upload-item__remove').addEventListener('click', (e) => {
                    e.stopPropagation();
                    removeFileAt(i);
                });
                preview.appendChild(row);
            });
        }

        nextBtn.addEventListener('click', () => {
            const isPhoto = getSelectedContentType(modal) === 'photo';
            if (!uploadSelectedFiles.length) {
                toast.error(isPhoto ? 'Select at least one image to upload.' : 'Select a video file to upload.');
                return;
            }
            const platforms = getSelectedPlatforms(modal);
            if (!platforms.length) {
                accountsError.textContent = getSelectedContentType(modal) === 'story'
                    ? 'Connect an Instagram or Facebook account to upload Stories.'
                    : isPhoto
                        ? 'Connect an Instagram or Facebook account to upload photos.'
                        : 'Select at least one platform to publish to.';
                accountsError.style.display = '';
                return;
            }
            accountsError.style.display = 'none';
            showStep('details');
        });

        backBtn.addEventListener('click', () => showStep('content'));
        publishNowBtn.addEventListener('click', () => submit(true));
        scheduleBtn.addEventListener('click', () => submit(false));

        function submit(publishNow) {
            const contentType = getSelectedContentType(modal);
            const isPhoto     = contentType === 'photo';
            if (!uploadSelectedFiles.length) {
                toast.error(isPhoto ? 'Select at least one image to upload.' : 'Select a video file to upload.');
                showStep('content');
                return;
            }
            const platforms = getSelectedPlatforms(modal);
            if (!platforms.length) { showStep('content'); return; }

            const scheduleVal = scheduleInputEl.value; // 'YYYY-MM-DDTHH:MM' (WP-local)
            if (!publishNow && !scheduleVal) {
                toast.error('Pick a date & time, or use Publish Now.');
                return;
            }

            const isStory = contentType === 'story';
            const meta = {
                platforms,
                content_type: contentType,
                caption:      isStory ? '' : captionEl.value.trim(),
                description:  isStory ? '' : descriptionEl.value.trim(),
                tags:         isStory ? [] : tagsEl.value.split(',').map(t => t.trim()).filter(Boolean),
                // WP-local 'Y-m-d H:i:s' — same convention as the Edit Video
                // modal's publish_date; SAS_API converts it to ISO using
                // wp_timezone(), so no browser-timezone guessing here.
                scheduled_at: publishNow || !scheduleVal ? null : scheduleVal.replace('T', ' ') + ':00',
                publish_now:  publishNow,
            };

            allButtons.forEach(b => { if (b) b.disabled = true; });
            progressWrap.style.display = '';
            progressWrap.innerHTML = '';

            const itemLabel = isPhoto
                ? `${uploadSelectedFiles.length} image${uploadSelectedFiles.length !== 1 ? 's' : ''}`
                : uploadSelectedFiles[0].name;
            const itemSize = uploadSelectedFiles.reduce((sum, f) => sum + f.size, 0);
            const item = createUploadItem(itemLabel, itemSize, platforms.join(' + '));
            progressWrap.appendChild(item.el);

            const onComplete = result => {
                const [v] = result.videos || [];
                item.setDone(publishNow ? 'Queued to publish now!' : (v?.publish_date ? `Scheduled: ${formatDate(v.publish_date)}` : 'Uploaded!'));
                toast.success(
                    publishNow
                        ? `${itemLabel} uploaded — queued for immediate publishing!`
                        : `${itemLabel} uploaded and scheduled!`
                );
                reloadCurrentPage();
                setTimeout(closeModal, 700);
            };
            const onError = msg => {
                item.setError(msg);
                toast.error(`Upload failed: ${msg}`);
                allButtons.forEach(b => { if (b) b.disabled = false; });
            };

            const uploader = isPhoto
                ? new PhotoUploader(uploadSelectedFiles, meta, pct => item.setProgress(pct), onComplete, onError)
                : new ChunkedUploader(uploadSelectedFiles[0], meta, pct => item.setProgress(pct), onComplete, onError);
            uploader.start();
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
    // Bulk Import — CSV only (no XLSX; this plugin has no JS bundler to ship
    // a binary-parsing library with, and the dashboard's own Excel import
    // already works for anyone who'd rather use that). Same column schema
    // as the dashboard's template, hand-parsed with a small RFC4180-ish CSV
    // reader so quoted commas/newlines inside a cell don't break rows.
    // =========================================================================
    let bulkImportRows = [];

    function parseCsvText(text) {
        const rows = [];
        let row = [], field = '', inQuotes = false;
        for (let i = 0; i < text.length; i++) {
            const c = text[i];
            if (inQuotes) {
                if (c === '"') {
                    if (text[i + 1] === '"') { field += '"'; i++; } else { inQuotes = false; }
                } else {
                    field += c;
                }
            } else if (c === '"') {
                inQuotes = true;
            } else if (c === ',') {
                row.push(field); field = '';
            } else if (c === '\n' || c === '\r') {
                if (c === '\r' && text[i + 1] === '\n') i++;
                row.push(field); field = '';
                if (row.length > 1 || row[0] !== '') rows.push(row);
                row = [];
            } else {
                field += c;
            }
        }
        if (field !== '' || row.length) { row.push(field); rows.push(row); }
        return rows;
    }

    const CSV_HEADER_ALIASES = {
        file_url: 'file_url', 'video url': 'file_url', video_url: 'file_url', url: 'file_url',
        image_urls: 'image_urls', 'image urls': 'image_urls', images: 'image_urls',
        caption: 'caption', title: 'caption',
        description: 'description', desc: 'description',
        tags: 'tags', tag: 'tags', hashtags: 'tags',
        content_type: 'content_type', 'content type': 'content_type', type: 'content_type',
        scheduled_at: 'scheduled_at', 'scheduled at': 'scheduled_at', schedule: 'scheduled_at', date: 'scheduled_at',
    };

    function normalizeContentType(raw) {
        const v = String(raw || '').trim().toLowerCase();
        if (v === 'story' || v === 'stories') return 'story';
        if (v === 'photo' || v === 'photos' || v === 'image' || v === 'images') return 'photo';
        return 'reel';
    }

    function parseBulkImportCsv(text) {
        const table = parseCsvText(text);
        if (!table.length) return { rows: [], skipped: 0 };
        const headers = table[0].map(h => h.trim().toLowerCase());
        const fieldFor = headers.map(h => CSV_HEADER_ALIASES[h] || null);
        if (!fieldFor.includes('file_url') && !fieldFor.includes('image_urls')) {
            throw new Error('No "file_url" or "image_urls" column found in the header row.');
        }

        const rows = [];
        let skipped = 0;
        for (let r = 1; r < table.length; r++) {
            const raw = table[r];
            if (!raw.some(c => c.trim())) continue; // fully blank row
            const item = { file_url: '', image_urls: '', caption: '', description: '', tags: '', content_type: 'reel', scheduled_at: '' };
            fieldFor.forEach((field, i) => {
                if (!field) return;
                const val = (raw[i] || '').trim();
                if (field === 'content_type') item.content_type = normalizeContentType(val);
                else item[field] = val;
            });
            if (!item.file_url && !item.image_urls && !item.caption) continue;

            const isPhoto = item.content_type === 'photo';
            const imageUrls = isPhoto ? item.image_urls.split(/[\n,;|]+/).map(u => u.trim()).filter(Boolean) : [];
            if (isPhoto ? !imageUrls.length : !item.file_url) {
                skipped++;
                continue;
            }
            rows.push({
                caption: item.content_type === 'story' ? '' : item.caption,
                description: item.content_type === 'story' ? '' : item.description,
                tags: item.content_type === 'story' ? [] : item.tags.split(',').map(t => t.trim()).filter(Boolean),
                content_type: item.content_type,
                file_url: isPhoto ? undefined : item.file_url,
                image_urls: isPhoto ? imageUrls : undefined,
                scheduled_at: item.scheduled_at || '',
            });
        }
        return { rows, skipped };
    }

    function downloadSampleCsv() {
        const header = 'file_url,image_urls,caption,description,tags,content_type,scheduled_at';
        const lines = [
            header,
            'https://your-site.com/wp-content/uploads/video.mp4,,"My video title","A longer description","travel,lifestyle",reel,',
            '"",https://your-site.com/wp-content/uploads/photo-1.jpg|https://your-site.com/wp-content/uploads/photo-2.jpg,"Swipe through our new collection","","newcollection,shop",photo,',
            'https://your-site.com/wp-content/uploads/story.mp4,,,,,story,',
        ];
        const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'bulk-import-template.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    function initBulkImport() {
        const modal     = document.getElementById('sas-bulk-import-modal');
        const trigger   = document.getElementById('sas-bulk-import-trigger');
        const fileInput = document.getElementById('sas-bulk-import-file');
        const summary   = document.getElementById('sas-bulk-import-summary');
        const submitBtn = document.getElementById('sas-bulk-import-submit');
        const resultsWrap = document.getElementById('sas-bulk-import-results');
        const resultsBody = document.getElementById('sas-bulk-import-results-body');
        if (!modal || !trigger) return;

        document.getElementById('sas-bulk-import-sample')?.addEventListener('click', downloadSampleCsv);

        trigger.addEventListener('click', () => {
            bulkImportRows = [];
            fileInput.value = '';
            summary.textContent = '';
            submitBtn.disabled = true;
            resultsWrap.style.display = 'none';
            resultsBody.innerHTML = '';
            modal.removeAttribute('hidden');
        });
        modal.querySelectorAll('.sas-modal__close, .sas-modal__backdrop').forEach(el =>
            el.addEventListener('click', () => modal.setAttribute('hidden', ''))
        );

        fileInput.addEventListener('change', async () => {
            const file = fileInput.files?.[0];
            submitBtn.disabled = true;
            resultsWrap.style.display = 'none';
            resultsBody.innerHTML = '';
            if (!file) { summary.textContent = ''; return; }
            try {
                const text = await file.text();
                const { rows, skipped } = parseBulkImportCsv(text);
                bulkImportRows = rows;
                if (!rows.length) {
                    summary.textContent = 'No valid rows found. Make sure the file has a file_url or image_urls column with valid links.';
                    return;
                }
                summary.textContent = `${rows.length} item${rows.length !== 1 ? 's' : ''} found` +
                    (skipped ? ` · ${skipped} row${skipped !== 1 ? 's' : ''} skipped (missing/invalid link)` : '');
                submitBtn.disabled = false;
            } catch (e) {
                summary.textContent = `Error: ${e.message}`;
            }
        });

        submitBtn.addEventListener('click', async () => {
            if (!bulkImportRows.length) return;
            const platforms = [...modal.querySelectorAll('.sas-bulk-import-platform:checked')].map(cb => cb.value);
            if (!platforms.length) { toast.error('Select at least one platform to publish to.'); return; }

            submitBtn.disabled = true;
            submitBtn.textContent = sasData.strings.saving;
            resultsWrap.style.display = '';
            resultsBody.innerHTML = bulkImportRows.map((r, i) => `
                <tr data-row="${i}">
                    <td>${i + 2}</td>
                    <td class="sas-truncate">${esc(r.caption || (r.content_type === 'photo' ? `${r.image_urls.length} image(s)` : r.file_url))}</td>
                    <td><span class="dashicons dashicons-clock"></span> Pending</td>
                    <td></td>
                </tr>
            `).join('');

            try {
                const videos = bulkImportRows.map(r => {
                    const item = {
                        caption: r.caption, description: r.description, tags: r.tags, content_type: r.content_type,
                    };
                    if (r.content_type === 'photo') {
                        item.images = r.image_urls.map(u => ({ image_url: u }));
                    } else {
                        item.file_url = r.file_url;
                    }
                    if (r.scheduled_at) {
                        // 'YYYY-MM-DDTHH:MM' or 'YYYY-MM-DD HH:MM' (WP-local)
                        // — SAS_API converts via wp_timezone() the same way
                        // the upload wizard's schedule field already does.
                        item.scheduled_at = r.scheduled_at.trim().replace('T', ' ');
                    }
                    return item;
                });
                const result = await api.post('/videos/bulk-create', { platforms, videos });
                (result.results || []).forEach(res => {
                    const tr = resultsBody.querySelector(`tr[data-row="${res.index}"]`);
                    if (!tr) return;
                    const statusCell = tr.children[2];
                    const errorCell  = tr.children[3];
                    if (res.status === 'created') {
                        statusCell.innerHTML = '<span class="dashicons dashicons-yes-alt" style="color:var(--sas-success-text)"></span> Done';
                    } else {
                        statusCell.innerHTML = '<span class="dashicons dashicons-warning" style="color:var(--sas-danger-text)"></span> Failed';
                        errorCell.textContent = res.error || '';
                    }
                });
                toast.success(`${result.created} imported, ${result.failed} failed.`);
                loadVideos();
                loadStats();
            } catch (e) {
                toast.error(`Import failed: ${e.message}`);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Import';
            }
        });
    }

    // =========================================================================
    // Dashboard
    // =========================================================================
    async function initDashboard() {
        await loadStats();
        await loadNextUpload();
        await loadRecentVideos();
        initUploadWizard();
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

    async function loadNotifications() {
        const el = document.getElementById('sas-notifications');
        if (!el) return;

        try {
            const notifications = await api.get('/notifications');
            if (!notifications.length) {
                el.innerHTML = '<p class="sas-empty">No announcements right now.</p>';
                return;
            }
            const typeClass = { info: 'info', success: 'success', warning: 'warning', error: 'error' };
            el.innerHTML = `
                <ul class="sas-notification-list">${notifications.map(n => `
                    <li class="sas-notification sas-notification--${typeClass[n.type] || 'info'}">
                        <div class="sas-notification__title">${esc(n.title)}</div>
                        ${n.message ? `<div class="sas-notification__message">${esc(n.message)}</div>` : ''}
                        ${n.image_url ? `<img src="${esc(n.image_url)}" alt="" class="sas-notification__image">` : ''}
                        <div class="sas-notification__date">${formatDate(n.created_at)}</div>
                    </li>
                `).join('')}</ul>
            `;
        } catch (e) {
            el.innerHTML = '<p class="sas-empty">Unable to load announcements.</p>';
        }
    }

    // =========================================================================
    // Videos page
    // =========================================================================
    const videosState = { page: 1, limit: 20, search: '', status: '', platform: '' };
    let videosTotal = 0;

    async function initVideos() {
        await loadVideos();
        initUploadWizard();
        initBulkImport();

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

        initVideoModal();
    }

    async function loadVideos() {
        const tbody = document.getElementById('sas-videos-table-body');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="7" class="sas-table__loading"><div class="sas-loading-skeleton"></div></td></tr>';

        try {
            const offset = (videosState.page - 1) * videosState.limit;
            // One row per publish destination (SAS_API::get_videos() now
            // proxies /api/v1/videos/plugin/targets/) — same granularity as
            // the dashboard's own Video Upload list: a video sent to 2
            // platforms is 2 rows here too, not 1. Already returned newest
            // first by the backend, so no client-side sort needed.
            const videos = await api.get('/videos', {
                search:   videosState.search,
                status:   videosState.status,
                platform: videosState.platform,
                limit:    videosState.limit,
                offset,
            });

            videosTotal = videos.length; // approximate

            if (!videos.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="sas-empty">No videos found.</td></tr>';
                document.getElementById('sas-pagination').innerHTML = '';
                return;
            }

            tbody.innerHTML = videos.map(v => {
                const scheduled = v.publish_date ? formatDate(v.publish_date) : '—';
                const created   = v.created_at   ? formatDate(v.created_at)   : '—';

                // Buttons shown per status — action endpoints operate on the
                // parent Video, not this target row, so every button below
                // uses v.video_id (not v.id, which is just this row's own
                // target id).
                const canSchedule    = v.status === 'draft';
                const canPublishNow  = !['published', 'publishing', 'queued'].includes(v.status);
                const actionBtns = [
                    canSchedule   ? `<button class="sas-btn sas-btn--sm sas-btn--primary sas-schedule-btn" data-id="${esc(v.video_id)}">Schedule</button>` : '',
                    canPublishNow ? `<button class="sas-btn sas-btn--sm sas-btn--publish-now sas-publish-now-btn" data-id="${esc(v.video_id)}" title="Publish immediately"><span class="dashicons dashicons-megaphone"></span> Now</button>` : '',
                    `<button class="sas-btn sas-btn--sm sas-btn--secondary sas-edit-btn" data-video='${JSON.stringify(v).replace(/'/g, "&#39;")}'>Edit</button>`,
                    `<button class="sas-btn sas-btn--sm sas-btn--ghost sas-delete-btn" data-id="${esc(v.video_id)}" title="Delete"><span class="dashicons dashicons-trash"></span></button>`,
                ].filter(Boolean).join('');

                return `
                <tr data-id="${esc(v.video_id)}">
                    <td><input type="checkbox" class="sas-video-check" value="${esc(v.video_id)}" /></td>
                    <td><strong>${esc(v.title)}</strong> ${contentTypeBadge(v.content_type)}</td>
                    <td>${platformBadge(v.platform)}<span class="sas-destination__account">${esc(v.account_name)}</span></td>
                    <td>${statusBadge(v.status)}</td>
                    <td>${esc(scheduled)}</td>
                    <td>${esc(created)}</td>
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
            tbody.innerHTML = `<tr><td colspan="7" class="sas-empty">Error loading videos: ${esc(e.message)}</td></tr>`;
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
        document.getElementById('sas-edit-id').value          = video.video_id;
        document.getElementById('sas-edit-title').value       = video.title || '';
        document.getElementById('sas-edit-description').value = video.description || '';

        let tagsStr = '';
        if (video.tags) {
            try { tagsStr = JSON.parse(video.tags).join(', '); } catch { tagsStr = video.tags; }
        }
        document.getElementById('sas-edit-tags').value = tagsStr;

        // Stories don't support captions/descriptions/tags at all on either
        // Instagram or Facebook — editing them here would be silently
        // ignored at publish time, so disable them instead of implying
        // they do something.
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
        // Connecting an account now happens entirely in the Meavr dashboard
        // (see admin/templates/accounts.php) — there's no local "Connect"
        // button on this page to wire up anymore. The corresponding
        // /oauth/*/url REST routes (SAS_API::youtube_oauth_url() etc.) and
        // platform-service get_auth_url() methods still work correctly if
        // ever called, but nothing in the current UI calls them.
        await loadAccounts();
    }

    async function loadAccounts() {
        try {
            const accounts = await api.get('/accounts');

            // Update status for each platform card
            const ytCard  = document.getElementById('sas-youtube-status');
            const igCard  = document.getElementById('sas-instagram-status');
            const fbCard  = document.getElementById('sas-facebook-status');

            const ytAcc = accounts.find(a => a.platform === 'youtube');
            const igAcc = accounts.find(a => a.platform === 'instagram');
            const fbAcc = accounts.find(a => a.platform === 'facebook');

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

            if (fbCard) {
                fbCard.innerHTML = fbAcc
                    ? `<span class="sas-account-card__status--connected"><span class="dashicons dashicons-yes-alt"></span> Connected: ${esc(fbAcc.account_name)}</span>`
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
                youtube_category:    document.getElementById('sas-yt-category')?.value || '22',
                youtube_privacy:     document.getElementById('sas-yt-privacy')?.value || 'public',
            };

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
            if (s.youtube_category)    setVal('sas-yt-category', s.youtube_category);
            if (s.youtube_privacy)     setVal('sas-yt-privacy', s.youtube_privacy);
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
    // Automations — Comment Automation (comment -> public reply + DM) and
    // DM Reply (inbound DM -> auto-reply). Both tabs share the same
    // Account dropdown data and the same Activity modal; each has its own
    // rule-create/edit modal since their fields genuinely differ (Comment
    // Automation has posts/public-reply/DM-message-and-button, DM Reply is
    // just trigger + reply variants).
    // =========================================================================
    let automationAccounts = [];

    async function initAutomations() {
        document.querySelectorAll('.sas-tab').forEach(btn => btn.addEventListener('click', () => {
            document.querySelectorAll('.sas-tab').forEach(b => b.classList.toggle('is-active', b === btn));
            document.querySelectorAll('[data-tab-panel]').forEach(p => {
                p.hidden = p.dataset.tabPanel !== btn.dataset.tab;
            });
        }));

        try {
            automationAccounts = (await api.get('/accounts')).filter(a => a.platform !== 'youtube');
        } catch (e) {
            automationAccounts = [];
        }
        const accountOptions = automationAccounts
            .map(a => `<option value="${esc(a.id)}">${esc(a.account_name)} (${esc(a.platform)})</option>`)
            .join('');
        ['sas-automation-account-filter', 'sas-dm-automation-account-filter'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.insertAdjacentHTML('beforeend', accountOptions);
        });
        ['sas-automation-account', 'sas-dm-automation-account'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = accountOptions;
        });

        document.getElementById('sas-automation-account-filter')?.addEventListener('change', loadAutomationRules);
        document.getElementById('sas-dm-automation-account-filter')?.addEventListener('change', loadDmAutomationRules);
        document.getElementById('sas-new-automation-rule')?.addEventListener('click', () => openAutomationModal(null));
        document.getElementById('sas-new-dm-automation-rule')?.addEventListener('click', () => openDmAutomationModal(null));

        document.getElementById('sas-automation-trigger')?.addEventListener('change', updateAutomationKeywordsVisibility);
        document.getElementById('sas-dm-automation-trigger')?.addEventListener('change', updateDmAutomationKeywordsVisibility);

        document.querySelectorAll('.sas-modal__close').forEach(btn => btn.addEventListener('click', () => {
            btn.closest('.sas-modal')?.setAttribute('hidden', '');
        }));
        document.querySelectorAll('.sas-modal__backdrop').forEach(bd => bd.addEventListener('click', () => {
            bd.closest('.sas-modal')?.setAttribute('hidden', '');
        }));

        document.getElementById('sas-save-automation')?.addEventListener('click', saveAutomationRule);
        document.getElementById('sas-save-dm-automation')?.addEventListener('click', saveDmAutomationRule);
        document.getElementById('sas-automation-delete')?.addEventListener('click', deleteAutomationRule);
        document.getElementById('sas-dm-automation-delete')?.addEventListener('click', deleteDmAutomationRule);

        await Promise.all([loadAutomationRules(), loadDmAutomationRules()]);
    }

    function accountLabel(id) {
        const a = automationAccounts.find(x => String(x.id) === String(id));
        return a ? `${esc(a.account_name)} ${platformBadge(a.platform)}` : `#${esc(id)}`;
    }

    // ── Comment Automation ──────────────────────────────────────────────────

    async function loadAutomationRules() {
        const tbody = document.getElementById('sas-automation-rules-table-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" class="sas-table__loading"><div class="sas-loading-skeleton"></div></td></tr>';
        try {
            const accountId = document.getElementById('sas-automation-account-filter')?.value || '';
            const rules = await api.get('/automation-rules', accountId ? { social_account: accountId } : {});
            if (!rules.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="sas-empty">No automations yet.</td></tr>';
                return;
            }
            tbody.innerHTML = rules.map(r => `
                <tr data-id="${esc(r.id)}">
                    <td>${accountLabel(r.social_account)}</td>
                    <td>${r.trigger_type === 'any_comment' ? '<span class="sas-text-muted">Any comment</span>' : esc(r.keywords.join(', '))}</td>
                    <td>${r.posts.length}</td>
                    <td>${esc(r.sent_count)}${r.max_sends ? ' / ' + esc(r.max_sends) : ''}</td>
                    <td><label class="sas-switch"><input type="checkbox" class="sas-automation-toggle" ${r.is_active ? 'checked' : ''}><span class="sas-switch__track"></span></label></td>
                    <td class="sas-table__actions">
                        <button class="sas-btn sas-btn--sm sas-btn--secondary sas-automation-activity-btn">Activity</button>
                        <button class="sas-btn sas-btn--sm sas-btn--secondary sas-automation-edit-btn">Edit</button>
                    </td>
                </tr>
            `).join('');

            tbody.querySelectorAll('tr').forEach(tr => {
                const id = tr.dataset.id;
                const rule = rules.find(r => String(r.id) === String(id));
                tr.querySelector('.sas-automation-toggle')?.addEventListener('change', async (e) => {
                    try {
                        await api.patch(`/automation-rules/${id}`, { is_active: e.target.checked });
                        toast.success(e.target.checked ? 'Automation activated' : 'Automation deactivated');
                    } catch (err) {
                        e.target.checked = !e.target.checked;
                        toast.error(err.message);
                    }
                });
                tr.querySelector('.sas-automation-edit-btn')?.addEventListener('click', () => openAutomationModal(rule));
                tr.querySelector('.sas-automation-activity-btn')?.addEventListener('click', () => openActivityModal('automation-rules', id));
            });
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="6" class="sas-empty">Error: ${esc(e.message)}</td></tr>`;
        }
    }

    function updateAutomationKeywordsVisibility() {
        const isAny = document.getElementById('sas-automation-trigger')?.value === 'any_comment';
        const field = document.getElementById('sas-automation-keywords-field');
        if (field) field.style.display = isAny ? 'none' : '';
    }

    function openAutomationModal(rule) {
        const modal = document.getElementById('sas-automation-modal');
        if (!modal) return;
        document.getElementById('sas-automation-modal-title').textContent = rule ? 'Edit Automation' : 'New Automation';
        setVal('sas-automation-id', rule ? rule.id : '');
        document.getElementById('sas-automation-account').value = rule ? rule.social_account : (automationAccounts[0]?.id ?? '');
        document.getElementById('sas-automation-account').disabled = !!rule;
        // Posts are stored as backend post ids, not URLs, and the URL is
        // only known right after a resolve (not kept on the rule) — so
        // editing starts this field blank; whatever's pasted on save is
        // ADDED to the rule's existing posts (see existingIds below), it
        // never replaces them.
        document.getElementById('sas-automation-posts').value = '';
        document.getElementById('sas-automation-posts').dataset.existingIds = rule ? JSON.stringify(rule.posts) : '[]';
        setText('sas-automation-posts-hint', rule && rule.posts.length
            ? `Currently watching ${rule.posts.length} post(s). Leave blank to keep them, or paste link(s) to replace the set.`
            : 'Paste the post/Reel link(s) this rule should watch.');
        document.getElementById('sas-automation-trigger').value = rule?.trigger_type || 'keyword';
        setVal('sas-automation-keywords', (rule?.keywords || []).join(', '));
        setVal('sas-automation-public-replies', (rule?.public_reply_texts || []).join('\n'));
        setVal('sas-automation-dm-message', rule?.dm_message_text || '');
        setVal('sas-automation-dm-button-label', rule?.dm_button_label || '');
        setVal('sas-automation-dm-button-url', rule?.dm_button_url || '');
        setVal('sas-automation-max-sends', rule?.max_sends ?? '');
        document.getElementById('sas-automation-active').checked = rule ? !!rule.is_active : true;
        document.getElementById('sas-automation-active-field').style.display = rule ? '' : 'none';
        document.getElementById('sas-automation-delete').style.display = rule ? '' : 'none';
        updateAutomationKeywordsVisibility();
        modal.removeAttribute('hidden');
    }

    async function saveAutomationRule() {
        const btn = document.getElementById('sas-save-automation');
        const id = document.getElementById('sas-automation-id').value;
        const accountId = document.getElementById('sas-automation-account').value;
        if (!accountId) { toast.error('Select an account.'); return; }

        btn.disabled = true;
        btn.textContent = sasData.strings.saving;
        try {
            // Resolve every pasted URL into a post id before the rule
            // itself is saved — the backend rule-save call takes ids, not
            // URLs. An edit's field starts blank (see openAutomationModal);
            // leaving it blank keeps the rule's existing posts untouched,
            // pasting anything REPLACES the full set — simpler than
            // appending forever with no way to ever remove one here.
            const urls = document.getElementById('sas-automation-posts').value
                .split('\n').map(u => u.trim()).filter(Boolean);
            const existingIds = JSON.parse(document.getElementById('sas-automation-posts').dataset.existingIds || '[]');
            let postIds = existingIds;
            if (urls.length) {
                const resolvedIds = [];
                for (const url of urls) {
                    try {
                        const post = await api.post(`/accounts/${accountId}/posts/resolve-url`, { url });
                        resolvedIds.push(post.id);
                    } catch (err) {
                        toast.error(`Could not resolve ${url}: ${err.message}`);
                    }
                }
                postIds = [...new Set(resolvedIds)];
            }

            const triggerType = document.getElementById('sas-automation-trigger').value;
            const payload = {
                social_account: Number(accountId),
                posts: postIds,
                trigger_type: triggerType,
                keywords: triggerType === 'any_comment' ? [] : document.getElementById('sas-automation-keywords').value.split(',').map(k => k.trim()).filter(Boolean),
                public_reply_texts: document.getElementById('sas-automation-public-replies').value.split('\n').map(t => t.trim()).filter(Boolean).slice(0, 5),
                dm_message_text: document.getElementById('sas-automation-dm-message').value.trim(),
                dm_button_label: document.getElementById('sas-automation-dm-button-label').value.trim(),
                dm_button_url: document.getElementById('sas-automation-dm-button-url').value.trim(),
                max_sends: document.getElementById('sas-automation-max-sends').value || null,
            };
            if (id) payload.is_active = document.getElementById('sas-automation-active').checked;

            if (!postIds.length) { toast.error('Add at least one post.'); return; }
            if (!payload.public_reply_texts.length) { toast.error('Add at least one public reply.'); return; }
            if (!payload.dm_message_text) { toast.error('Add a DM message.'); return; }

            if (id) {
                await api.patch(`/automation-rules/${id}`, payload);
            } else {
                await api.post('/automation-rules', payload);
            }
            toast.success('Automation saved.');
            document.getElementById('sas-automation-modal').setAttribute('hidden', '');
            loadAutomationRules();
        } catch (e) {
            toast.error(e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save';
        }
    }

    async function deleteAutomationRule() {
        const id = document.getElementById('sas-automation-id').value;
        if (!id || !confirm('Delete this automation? Comments will no longer trigger a reply or DM.')) return;
        try {
            await api.del(`/automation-rules/${id}`);
            toast.success('Automation deleted.');
            document.getElementById('sas-automation-modal').setAttribute('hidden', '');
            loadAutomationRules();
        } catch (e) {
            toast.error(e.message);
        }
    }

    // ── DM Reply ─────────────────────────────────────────────────────────────

    async function loadDmAutomationRules() {
        const tbody = document.getElementById('sas-dm-automation-rules-table-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" class="sas-table__loading"><div class="sas-loading-skeleton"></div></td></tr>';
        try {
            const accountId = document.getElementById('sas-dm-automation-account-filter')?.value || '';
            const rules = await api.get('/dm-automation-rules', accountId ? { social_account: accountId } : {});
            if (!rules.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="sas-empty">No DM automations yet.</td></tr>';
                return;
            }
            tbody.innerHTML = rules.map(r => `
                <tr data-id="${esc(r.id)}">
                    <td>${accountLabel(r.social_account)}</td>
                    <td>${r.trigger_type === 'any_message' ? '<span class="sas-text-muted">Any message</span>' : esc(r.keywords.join(', '))}</td>
                    <td>${r.reply_texts.length}</td>
                    <td>${esc(r.sent_count)}${r.max_sends ? ' / ' + esc(r.max_sends) : ''}</td>
                    <td><label class="sas-switch"><input type="checkbox" class="sas-dm-automation-toggle" ${r.is_active ? 'checked' : ''}><span class="sas-switch__track"></span></label></td>
                    <td class="sas-table__actions">
                        <button class="sas-btn sas-btn--sm sas-btn--secondary sas-dm-automation-activity-btn">Activity</button>
                        <button class="sas-btn sas-btn--sm sas-btn--secondary sas-dm-automation-edit-btn">Edit</button>
                    </td>
                </tr>
            `).join('');

            tbody.querySelectorAll('tr').forEach(tr => {
                const id = tr.dataset.id;
                const rule = rules.find(r => String(r.id) === String(id));
                tr.querySelector('.sas-dm-automation-toggle')?.addEventListener('change', async (e) => {
                    try {
                        await api.patch(`/dm-automation-rules/${id}`, { is_active: e.target.checked });
                        toast.success(e.target.checked ? 'DM automation activated' : 'DM automation deactivated');
                    } catch (err) {
                        e.target.checked = !e.target.checked;
                        toast.error(err.message);
                    }
                });
                tr.querySelector('.sas-dm-automation-edit-btn')?.addEventListener('click', () => openDmAutomationModal(rule));
                tr.querySelector('.sas-dm-automation-activity-btn')?.addEventListener('click', () => openActivityModal('dm-automation-rules', id));
            });
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="6" class="sas-empty">Error: ${esc(e.message)}</td></tr>`;
        }
    }

    function updateDmAutomationKeywordsVisibility() {
        const isAny = document.getElementById('sas-dm-automation-trigger')?.value === 'any_message';
        const field = document.getElementById('sas-dm-automation-keywords-field');
        if (field) field.style.display = isAny ? 'none' : '';
    }

    function openDmAutomationModal(rule) {
        const modal = document.getElementById('sas-dm-automation-modal');
        if (!modal) return;
        document.getElementById('sas-dm-automation-modal-title').textContent = rule ? 'Edit DM Automation' : 'New DM Automation';
        setVal('sas-dm-automation-id', rule ? rule.id : '');
        document.getElementById('sas-dm-automation-account').value = rule ? rule.social_account : (automationAccounts[0]?.id ?? '');
        document.getElementById('sas-dm-automation-account').disabled = !!rule;
        document.getElementById('sas-dm-automation-trigger').value = rule?.trigger_type || 'keyword';
        setVal('sas-dm-automation-keywords', (rule?.keywords || []).join(', '));
        setVal('sas-dm-automation-replies', (rule?.reply_texts || []).join('\n'));
        setVal('sas-dm-automation-max-sends', rule?.max_sends ?? '');
        document.getElementById('sas-dm-automation-active').checked = rule ? !!rule.is_active : true;
        document.getElementById('sas-dm-automation-active-field').style.display = rule ? '' : 'none';
        document.getElementById('sas-dm-automation-delete').style.display = rule ? '' : 'none';
        updateDmAutomationKeywordsVisibility();
        modal.removeAttribute('hidden');
    }

    async function saveDmAutomationRule() {
        const btn = document.getElementById('sas-save-dm-automation');
        const id = document.getElementById('sas-dm-automation-id').value;
        const accountId = document.getElementById('sas-dm-automation-account').value;
        if (!accountId) { toast.error('Select an account.'); return; }

        const triggerType = document.getElementById('sas-dm-automation-trigger').value;
        const payload = {
            social_account: Number(accountId),
            trigger_type: triggerType,
            keywords: triggerType === 'any_message' ? [] : document.getElementById('sas-dm-automation-keywords').value.split(',').map(k => k.trim()).filter(Boolean),
            reply_texts: document.getElementById('sas-dm-automation-replies').value.split('\n').map(t => t.trim()).filter(Boolean).slice(0, 5),
            max_sends: document.getElementById('sas-dm-automation-max-sends').value || null,
        };
        if (id) payload.is_active = document.getElementById('sas-dm-automation-active').checked;

        if (!payload.reply_texts.length) { toast.error('Add at least one auto-reply.'); return; }
        if (triggerType === 'keyword' && !payload.keywords.length) { toast.error('Add at least one keyword, or switch to Any message.'); return; }

        btn.disabled = true;
        btn.textContent = sasData.strings.saving;
        try {
            if (id) {
                await api.patch(`/dm-automation-rules/${id}`, payload);
            } else {
                await api.post('/dm-automation-rules', payload);
            }
            toast.success('DM automation saved.');
            document.getElementById('sas-dm-automation-modal').setAttribute('hidden', '');
            loadDmAutomationRules();
        } catch (e) {
            toast.error(e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save';
        }
    }

    async function deleteDmAutomationRule() {
        const id = document.getElementById('sas-dm-automation-id').value;
        if (!id || !confirm('Delete this DM automation? Matching DMs will no longer get an auto-reply.')) return;
        try {
            await api.del(`/dm-automation-rules/${id}`);
            toast.success('DM automation deleted.');
            document.getElementById('sas-dm-automation-modal').setAttribute('hidden', '');
            loadDmAutomationRules();
        } catch (e) {
            toast.error(e.message);
        }
    }

    // ── Activity modal (shared) ─────────────────────────────────────────────

    async function openActivityModal(kind, id) {
        const modal = document.getElementById('sas-automation-activity-modal');
        const head  = document.getElementById('sas-automation-activity-head');
        const body  = document.getElementById('sas-automation-activity-body');
        if (!modal || !head || !body) return;

        const isComment = kind === 'automation-rules';
        head.innerHTML = isComment
            ? '<th>Commenter</th><th>Comment</th><th>Matched</th><th>Public Reply</th><th>DM</th><th>When</th>'
            : '<th>Sender</th><th>Message</th><th>Matched</th><th>Reply</th><th>When</th>';
        body.innerHTML = `<tr><td colspan="6" class="sas-table__loading"><div class="sas-loading-skeleton"></div></td></tr>`;
        modal.removeAttribute('hidden');

        try {
            const events = await api.get(`/${kind}/${id}/events`);
            if (!events.length) {
                body.innerHTML = `<tr><td colspan="6" class="sas-empty">No activity yet.</td></tr>`;
                return;
            }
            body.innerHTML = events.map(e => isComment ? `
                <tr>
                    <td>${esc(e.commenter_username || '—')}</td>
                    <td class="sas-truncate" title="${esc(e.comment_text)}">${esc(e.comment_text || '—')}</td>
                    <td>${esc(e.matched_keyword || '—')}</td>
                    <td>${statusBadge(e.public_reply_status)}</td>
                    <td>${statusBadge(e.dm_status)}</td>
                    <td class="sas-text-muted">${esc(formatDate(e.created_at ? e.created_at.replace(' ', 'T') + 'Z' : ''))}</td>
                </tr>
            ` : `
                <tr>
                    <td>${esc(e.sender_username || '—')}</td>
                    <td class="sas-truncate" title="${esc(e.message_text)}">${esc(e.message_text || '—')}</td>
                    <td>${esc(e.matched_keyword || '—')}</td>
                    <td>${statusBadge(e.dm_status)}</td>
                    <td class="sas-text-muted">${esc(formatDate(e.created_at ? e.created_at.replace(' ', 'T') + 'Z' : ''))}</td>
                </tr>
            `).join('');
        } catch (e) {
            body.innerHTML = `<tr><td colspan="6" class="sas-empty">Error: ${esc(e.message)}</td></tr>`;
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
            partially_published:'Partially Published',
            failed:'Failed', cancelled:'Cancelled',
            // Automation event statuses (AutomationEvent/DmAutomationEvent.*_status).
            pending:'Pending', sent:'Sent',
        };
        return `<span class="sas-badge sas-badge--${esc(status)}">${esc(labels[status] || status)}</span>`;
    }

    const PLATFORM_ICONS = { youtube: 'youtube.svg', instagram: 'instagram.svg', facebook: 'facebook.svg' };

    function platformBadge(platform) {
        const icon = PLATFORM_ICONS[platform];
        const img = icon
            ? `<img src="${sasData.pluginUrl}assets/images/${icon}" width="12" height="12" alt="" class="sas-platform__icon">`
            : '';
        return `<span class="sas-platform sas-platform--${esc(platform)}">${img}${esc(platform)}</span>`;
    }

    function contentTypeBadge(contentType) {
        if (contentType !== 'story') return '';
        return `<span class="sas-badge sas-badge--scheduled">${esc('Story')}</span>`;
    }

    function reloadCurrentPage() {
        const page = document.querySelector('[data-page]')?.dataset.page;
        if (page === 'dashboard') { loadStats(); loadNextUpload(); loadRecentVideos(); loadNotifications(); }
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
                    if (label) label.hidden = false;
                    if (inner && !inner.querySelector('.sas-platform-toggle__account')) {
                        const s = document.createElement('span');
                        s.className = 'sas-platform-toggle__account';
                        s.style.cssText = 'font-size:11px;opacity:.75;margin-left:6px;';
                        s.textContent = acc.account_name;
                        inner.appendChild(s);
                    }
                } else {
                    // No connected account for this platform at all — hide the
                    // option entirely rather than showing a disabled, grayed-
                    // out toggle the user can't do anything with.
                    input.checked  = false;
                    input.disabled = true;
                    if (label) label.hidden = true;
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
        if (page === 'logs')        initLogs();
        if (page === 'automations') initAutomations();
        if (page === 'support')     initSupport();

        annotatePlatformToggles();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

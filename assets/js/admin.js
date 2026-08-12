/* ============================================================================
   OWERE & ASSOCIATES — admin.js
   CMS content editor · image previewers · AJAX lead filters ·
   inline status updates · confirms · toasts
   ========================================================================== */

(function () {
    'use strict';

    var d = document;

    /* ---------- Generic helpers ---------- */

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------- Toasts ---------- */

    var toastWrap = null;

    function showToast(type, message) {
        if (!toastWrap) {
            toastWrap = d.createElement('div');
            toastWrap.className = 'toasts';
            d.body.appendChild(toastWrap);
        }
        var toast = d.createElement('div');
        toast.className = 'toast toast--' + type;
        toast.innerHTML = '<span class="toast__msg">' + escapeHtml(message) + '</span>';
        toastWrap.appendChild(toast);
        setTimeout(function () { toast.classList.add('is-hiding'); }, 3600);
        setTimeout(function () { toast.remove(); }, 4100);
        toast.addEventListener('click', function () { toast.remove(); });
    }

    /* ---------- Flash alert dismissal ---------- */
    d.querySelectorAll('[data-alert]').forEach(function (alertEl) {
        var closeBtn = alertEl.querySelector('[data-alert-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () { alertEl.remove(); });
        }
    });

    /* ---------- Image preview before upload (logos) ---------- */
    var fileInput = d.getElementById('logoFile');
    var previewBox = d.getElementById('uploadPreview');
    var previewImg = d.getElementById('uploadPreviewImg');

    if (fileInput && previewBox) {
        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0];
            if (!file) {
                previewBox.classList.remove('is-visible');
                return;
            }
            if (!/^image\//.test(file.type)) {
                previewBox.classList.remove('is-visible');
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                if (previewImg) previewImg.src = e.target.result;
                previewBox.classList.add('is-visible');
            };
            reader.readAsDataURL(file);
        });
    }

    /* ---------- Live avatar preview (settings profile) ----------
       Shows the chosen photo in the avatar circle immediately, before saving,
       so the upload never feels like "nothing happened". */
    var avatarFile = d.getElementById('profile_avatar');
    var avatarBox = d.getElementById('profileAvatar');
    if (avatarFile && avatarBox) {
        avatarFile.addEventListener('change', function () {
            var file = avatarFile.files && avatarFile.files[0];
            if (!file || !/^image\//.test(file.type)) {
                showToast('error', 'That file is not a supported image — use JPG, PNG, WEBP, GIF or SVG.');
                avatarFile.value = '';
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                avatarBox.innerHTML = '<img src="' + e.target.result + '" alt="New profile photo preview">';
            };
            reader.readAsDataURL(file);
        });
    }

    /* ---------- Upload zone click-to-browse ---------- */
    var uploadZone = d.getElementById('uploadZone');
    if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', function () { fileInput.click(); });
        uploadZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            uploadZone.classList.add('is-dragging');
        });
        uploadZone.addEventListener('dragleave', function () {
            uploadZone.classList.remove('is-dragging');
        });
        uploadZone.addEventListener('drop', function (e) {
            e.preventDefault();
            uploadZone.classList.remove('is-dragging');
            if (e.dataTransfer.files && e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
        fileInput.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    /* ---------- Delegated: inline status change (auto-submit) ---------- */
    d.addEventListener('change', function (e) {
        var select = e.target.closest('[data-status-select]');
        if (!select) return;
        var form = select.closest('form');
        if (form) form.submit();
    });

    /* ---------- Delegated: confirm destructive actions ---------- */
    d.addEventListener('click', function (e) {
        var el = e.target.closest('[data-confirm]');
        if (!el) return;
        var message = el.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(message)) {
            e.preventDefault();
        }
    });

    /* ---------- Copy image path (media library) ---------- */
    d.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy-path]');
        if (!btn) return;
        var path = btn.getAttribute('data-copy-path') || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(path).then(function () {
                showToast('success', 'Path copied: ' + path);
            }, function () {
                showToast('error', 'Could not copy automatically — use the prompt to copy it manually.');
                window.prompt('Copy this path:', path);
            });
        } else {
            window.prompt('Copy this path:', path);
        }
    });

    /* ---------- Media library page (admin/media.php) ----------
       "Place on site…" dialog: pick a spot, submit once, done. Also the
       client-side search filter over the tile grid. */
    var placeModal = d.getElementById('placeModal');
    if (placeModal) {
        var placeSlot = d.getElementById('placeSlot');
        var placeName = d.getElementById('placeName');
        var placePreview = d.getElementById('placePreview');
        var placeSubmit = d.getElementById('placeSubmit');

        function closePlaceModal() {
            placeModal.hidden = true;
        }

        function selectPlaceSlot(btn) {
            placeSlots.forEach(function (s) { s.classList.toggle('is-selected', s === btn); });
            placeSlot.value = btn.getAttribute('data-place-slot') || '';
            placeSubmit.disabled = !placeSlot.value;
        }

        var placeSlots = Array.prototype.slice.call(d.querySelectorAll('[data-place-slot]'));
        placeSlots.forEach(function (btn) {
            btn.addEventListener('click', function () { selectPlaceSlot(btn); });
        });

        d.addEventListener('click', function (e) {
            var open = e.target.closest('[data-place-open]');
            if (open) {
                placeName.value = open.getAttribute('data-place-open') || '';
                placePreview.src = '../assets/images/content/' + placeName.value;
                placePreview.alt = placeName.value;
                selectPlaceSlot(placeSlots[0]);
                placeModal.hidden = false;
                return;
            }
            if (e.target.closest('[data-place-close]')) {
                closePlaceModal();
            }
        });

        d.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !placeModal.hidden) {
                closePlaceModal();
            }
        });
    }

    /* Search/filter the media library tiles by file name. */
    var mediaSearch = d.getElementById('mediaSearch');
    if (mediaSearch) {
        mediaSearch.addEventListener('input', function () {
            var q = mediaSearch.value.trim().toLowerCase();
            d.querySelectorAll('[data-media-tile]').forEach(function (tile) {
                var name = (tile.getAttribute('data-name') || '').toLowerCase();
                tile.hidden = q !== '' && name.indexOf(q) === -1;
            });
        });
    }


    /* ---------- AJAX lead filtering ---------- */
    var filterForm = d.getElementById('leadFilterForm');
    var tableBody = d.getElementById('leadsTableBody');
    var rowCount = d.getElementById('rowCount');

    if (filterForm && tableBody) {
        var debounceTimer;

        function fetchLeads() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                var params = new URLSearchParams(new FormData(filterForm));
                params.set('ajax', '1');

                fetch(window.location.pathname + '?' + params.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (res) { return res.text(); })
                    .then(function (html) {
                        var tmp = d.createElement('div');
                        tmp.innerHTML = html;
                        var rows = tmp.querySelectorAll('tr[data-lead-row]');
                        tableBody.innerHTML = '';
                        rows.forEach(function (row) { tableBody.appendChild(row); });
                        if (rowCount) {
                            rowCount.textContent = rows.length + ' lead' + (rows.length === 1 ? '' : 's');
                        }
                    })
                    .catch(function () { /* silent — fallback to full page reload */ });
            }, 250);
        }

        filterForm.querySelectorAll('select, input').forEach(function (field) {
            field.addEventListener('change', fetchLeads);
            field.addEventListener('input', fetchLeads);
        });
    }

    /* ---------- Activity log filter ----------
       The action dropdown applies instantly; the search box submits on Enter
       (or blur) so typing is never interrupted by a page reload. */
    var activityForm = d.getElementById('activityFilterForm');
    if (activityForm) {
        activityForm.querySelectorAll('select').forEach(function (field) {
            field.addEventListener('change', function () { activityForm.submit(); });
        });
        activityForm.querySelectorAll('input[type="search"]').forEach(function (field) {
            field.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') activityForm.submit();
            });
            field.addEventListener('blur', function () { activityForm.submit(); });
        });
    }

    /* ---------- Sidebar toggle (mobile) ---------- */
    var sidebar = d.querySelector('.sidebar');
    if (sidebar) {
        var toggle = d.querySelector('[data-sidebar-toggle]');
        if (toggle) {
            toggle.addEventListener('click', function () {
                sidebar.classList.toggle('is-open');
            });
        }
    }

    /* ========================================================================
       CMS CONTENT EDITOR (admin/content.php)
       ====================================================================== */

    var cmsForm = d.getElementById('cmsForm');
    if (cmsForm) {

    var csrf = (cmsForm.querySelector('input[name="csrf_token"]') || {}).value || '';
    var savebar = d.querySelector('[data-savebar]');
    var saveBtn = d.querySelector('[data-save-btn]');
    var saveStatus = d.querySelector('[data-save-status]');
    var tabs = Array.prototype.slice.call(d.querySelectorAll('.cms-tab'));
    var panels = Array.prototype.slice.call(d.querySelectorAll('.cms-panel'));
    var dirty = false;
    var saveTimer = null;
    var draftTimer = null;
    var lastAutosaveOk = true;

    /* ---------- Automatic draft saving ----------
       Every edit is mirrored to localStorage, so closing the tab or a
       browser crash can never lose work. The draft is cleared as soon
       as the content is published with "Save Changes". */
    var DRAFT_KEY = 'owere-cms-draft-v2';
    var SKIP_DRAFT_FIELDS = { csrf_token: 1, cms_action: 1 };

    function collectFormState() {
        syncRtes();
        var state = {};
        cmsForm.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (!el.name || SKIP_DRAFT_FIELDS[el.name]) return;
            if (el.type === 'checkbox' || el.type === 'radio') {
                state[el.name] = el.checked ? '1' : '0';
            } else {
                state[el.name] = el.value;
            }
        });
        // Preserve list STRUCTURE too (rows the user added or removed).
        state.__lists = {};
        d.querySelectorAll('[data-list]').forEach(function (list) {
            var name = list.getAttribute('data-name');
            var rows = list.querySelector('[data-list-rows]');
            if (name && rows) {
                state.__lists[name] = rows.innerHTML;
            }
        });
        // Preserve the pillar blocks themselves (pillars added or removed).
        var pillarsWrap = d.querySelector('[data-pillars]');
        if (pillarsWrap) {
            state.__pillars = pillarsWrap.innerHTML;
        }
        return state;
    }

    function applyFormState(state) {
        if (!state) return;

        // Restore pillar blocks first (added/removed pillars) so the nested
        // lists and field values below have real elements to fill.
        var pillarsWrap = d.querySelector('[data-pillars]');
        if (state.__pillars && pillarsWrap && typeof state.__pillars === 'string') {
            pillarsWrap.innerHTML = state.__pillars;
            pillarsWrap.setAttribute('data-index', String(pillarsWrap.querySelectorAll('[data-pillar-block]').length));
            // Restored nodes are brand-new — clear the bound marker so their
            // image fields (Upload / Choose from library / Remove) get listeners
            // re-attached below instead of being skipped as "already bound".
            pillarsWrap.querySelectorAll('[data-img-field]').forEach(function (f) {
                f.removeAttribute('data-bound');
            });
        }

        // Restore list structure (added/removed rows).
        if (state.__lists) {
            Object.keys(state.__lists).forEach(function (name) {
                var list = d.querySelector('[data-list][data-name="' + name + '"]');
                var rows = list && list.querySelector('[data-list-rows]');
                if (list && rows && typeof state.__lists[name] === 'string') {
                    rows.innerHTML = state.__lists[name];
                    list.setAttribute('data-index', String(list.querySelectorAll('[data-list-row]').length));
                    rows.querySelectorAll('[data-img-field]').forEach(function (f) {
                        f.removeAttribute('data-bound');
                    });
                }
            });
        }

        // Apply field values (fills typed text into the restored rows too).
        cmsForm.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (!el.name || SKIP_DRAFT_FIELDS[el.name]) return;
            if (!(el.name in state)) return;
            if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = state[el.name] === '1';
            } else {
                el.value = state[el.name];
            }
        });
        // Mirror restored rich-text values into the visual editors.
        rtes.forEach(function (rte) {
            var input = rte.querySelector('[data-rte-input]');
            var editor = rte.querySelector('[data-rte-editor]');
            if (input && editor && input.name && input.name in state) {
                editor.innerHTML = (state[input.name] || '').replace(/src="assets\//g, 'src="../assets/');
            }
        });
        // Refresh image previews to match the restored values.
        d.querySelectorAll('[data-img-field]').forEach(function (field) {
            var input = field.querySelector('[data-img-input]');
            var preview = field.querySelector('[data-img-preview]');
            if (!input || !preview) return;
            if (input.value) {
                preview.innerHTML = '<img src="../' + escapeHtml(input.value) + '" alt="Current image preview">';
            } else {
                preview.innerHTML = '<span class="img-field__empty">No image — click “Upload image”</span>';
            }
        });
        // Re-connect dynamic controls inside restored/added pillar blocks.
        bindImageFields(d);
        bindLists(d);
        syncPillarHeads();
    }

    function saveDraft() {
        try {
            var payload = { savedAt: Date.now(), state: collectFormState() };
            localStorage.setItem(DRAFT_KEY, JSON.stringify(payload));
            lastAutosaveOk = true;
            if (saveStatus && saveStatus.textContent !== 'Saving…') {
                var t = new Date();
                var hh = String(t.getHours()).padStart(2, '0');
                var mm = String(t.getMinutes()).padStart(2, '0');
                saveStatus.textContent = 'Draft saved automatically at ' + hh + ':' + mm + ' — click Save Changes to publish';
            }
        } catch (err) {
            lastAutosaveOk = false;
        }
    }

    function scheduleDraft() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 800);
    }

    function clearDraft() {
        try { localStorage.removeItem(DRAFT_KEY); } catch (err) { /* ignore */ }
        var bar = d.querySelector('[data-draft-bar]');
        if (bar) bar.remove();
    }

    /* ---------- Dirty tracking ---------- */
    function markDirty() {
        if (dirty) {
            scheduleDraft();
            kickSavebarTimer();
            return;
        }
        dirty = true;
        if (savebar) savebar.hidden = false;
        if (saveStatus) saveStatus.textContent = 'You have unsaved changes — draft saved automatically';
        scheduleDraft();
        kickSavebarTimer();
    }

    function kickSavebarTimer() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            if (dirty && savebar) savebar.hidden = false;
        }, 120);
    }

    function setClean() {
        dirty = false;
        if (savebar) {
            savebar.hidden = true;
            if (saveStatus) saveStatus.textContent = 'All changes saved';
        }
        tabs.forEach(function (tab) { tab.classList.remove('is-dirty'); });
    }

    /* Mark the tab that contains the edited control. */
    cmsForm.addEventListener('input', function (e) {
        var panel = e.target.closest ? e.target.closest('[data-panel]') : null;
        if (panel) {
            var key = panel.getAttribute('data-panel');
            var tab = d.querySelector('.cms-tab[data-tab="' + key + '"]');
            if (tab) tab.classList.add('is-dirty');
        }
        markDirty();
    });
    cmsForm.addEventListener('change', function (e) {
        var panel = e.target.closest ? e.target.closest('[data-panel]') : null;
        if (panel) {
            var key = panel.getAttribute('data-panel');
            var tab = d.querySelector('.cms-tab[data-tab="' + key + '"]');
            if (tab) tab.classList.add('is-dirty');
        }
        markDirty();
    });

    window.addEventListener('beforeunload', function (e) {
        // If the draft is safely stored, closing the tab loses nothing — don't nag.
        if (!dirty || lastAutosaveOk) return;
        e.preventDefault();
        e.returnValue = '';
    });

    // Flush the draft synchronously when the tab closes or navigates away.
    window.addEventListener('pagehide', function () {
        if (dirty) saveDraft();
    });

    /* ---------- Tabs ---------- */
    function activateTab(key) {
        tabs.forEach(function (tab) {
            var on = tab.getAttribute('data-tab') === key;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-panel') !== key;
        });
        try { sessionStorage.setItem('owere-cms-tab', key); } catch (e) { /* ignore */ }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateTab(tab.getAttribute('data-tab'));
        });
    });

    var storedTab = null;
    try { storedTab = sessionStorage.getItem('owere-cms-tab'); } catch (e) { /* ignore */ }
    activateTab(storedTab && d.querySelector('.cms-tab[data-tab="' + storedTab + '"]') ? storedTab : (tabs[0] ? tabs[0].getAttribute('data-tab') : 'home'));

    /* ---------- Rich text editors ---------- */
    var rtes = Array.prototype.slice.call(d.querySelectorAll('[data-rte]'));

    function isFormat(value) {
        var v = String(value || '').toLowerCase();
        return v === 'h3' || v === '<h3>';
    }

    function updateToolbar(rte) {
        rte.querySelectorAll('[data-rte-cmd]').forEach(function (btn) {
            var cmd = btn.getAttribute('data-rte-cmd');
            var active = false;
            if (cmd === 'bold' || cmd === 'italic' || cmd === 'underline') {
                try { active = document.queryCommandState(cmd); } catch (e) { /* ignore */ }
            } else if (cmd === 'h3') {
                try { active = isFormat(document.queryCommandValue('formatBlock')); } catch (e) { /* ignore */ }
            } else if (cmd === 'insertUnorderedList' || cmd === 'insertOrderedList') {
                try { active = document.queryCommandState(cmd); } catch (e) { /* ignore */ }
            }
            btn.classList.toggle('is-active', !!active);
        });
    }

    rtes.forEach(function (rte) {
        var editor = rte.querySelector('[data-rte-editor]');
        var input = rte.querySelector('[data-rte-input]');

        rte.querySelectorAll('[data-rte-cmd]').forEach(function (btn) {
            btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
            btn.addEventListener('click', function () {
                var cmd = btn.getAttribute('data-rte-cmd');
                if (cmd === 'createLink') {
                    var url = window.prompt('Enter the link address (https://…):', 'https://');
                    if (url) {
                        document.execCommand('createLink', false, url.trim());
                    }
                } else if (cmd === 'h3') {
                    var current = 'p';
                    try { current = isFormat(document.queryCommandValue('formatBlock')) ? 'p' : 'h3'; } catch (e) { /* ignore */ }
                    document.execCommand('formatBlock', false, current);
                } else if (cmd === 'insertImage') {
                    // Capture the caret BEFORE the picker modal steals focus/selection.
                    var savedRange = null;
                    var curSel = window.getSelection();
                    if (curSel && curSel.rangeCount > 0
                        && editor.contains(curSel.getRangeAt(0).commonAncestorContainer)) {
                        savedRange = curSel.getRangeAt(0).cloneRange();
                    }
                    openMediaPicker(null, function (path) {
                        var img = d.createElement('img');
                        img.src = '../' + path; // admin preview prefix; stripped on save
                        img.alt = '';
                        if (savedRange && editor.contains(savedRange.commonAncestorContainer)) {
                            savedRange.deleteContents();
                            savedRange.insertNode(img);
                            savedRange.setStartAfter(img);
                            savedRange.collapse(true);
                            var sel2 = window.getSelection();
                            if (sel2) { sel2.removeAllRanges(); sel2.addRange(savedRange); }
                        } else {
                            editor.appendChild(img);
                        }
                        syncRtes();
                        markDirty();
                        updateToolbar(rte);
                    });
                } else {
                    document.execCommand(cmd, false, null);
                }
                updateToolbar(rte);
                markDirty();
                editor.focus();
            });
        });

        editor.addEventListener('keyup', function () { updateToolbar(rte); });
        editor.addEventListener('mouseup', function () { updateToolbar(rte); });
    });

    function syncRtes() {
        rtes.forEach(function (rte) {
            var editor = rte.querySelector('[data-rte-editor]');
            var input = rte.querySelector('[data-rte-input]');
            if (input) {
                // Stored content is site-relative (assets/…); the editor
                // preview uses ../assets/… because /admin/ lives one level deep.
                input.value = editor.innerHTML.trim().replace(/src="\.\.\/assets\//g, 'src="assets/');
            }
        });
    }

    /* ---------- Restore an automatic draft (if any) ----------
       Runs after the RTE editors are initialised so rich-text content
       can be mirrored back into the visual editors. */
    var draft = null;
    try { draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (err) { /* ignore */ }

    if (draft && draft.state && Object.keys(draft.state).length) {
        applyFormState(draft.state);
        markDirty();
        showDraftBar(draft.savedAt);
    }

    function showDraftBar(savedAt) {
        var bar = d.createElement('div');
        bar.className = 'draft-bar';
        bar.setAttribute('data-draft-bar', '');
        var when = savedAt ? new Date(savedAt) : null;
        var timeText = (when && !isNaN(when.getTime()))
            ? ' from ' + when.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            : '';
        bar.innerHTML =
            '<span class="draft-bar__msg">We restored your unsaved draft' + timeText +
            '. Review it, then click <strong>Save Changes</strong> to publish.</span>' +
            '<span class="draft-bar__actions">' +
            '<button type="button" class="btn btn--navy btn--sm" data-draft-discard>Discard draft</button>' +
            '<button type="button" class="draft-bar__close" data-draft-close aria-label="Dismiss">&times;</button>' +
            '</span>';
        cmsForm.parentNode.insertBefore(bar, cmsForm);

        bar.querySelector('[data-draft-discard]').addEventListener('click', function () {
            clearDraft();
            window.location.reload();
        });
        bar.querySelector('[data-draft-close]').addEventListener('click', function () {
            bar.remove();
        });
    }

    /* ---------- Image fields ----------
       Bound once per field (dataset.bound guard) so fields inside pillar
       blocks that were added or restored from a draft work the same way. */
    function bindImageFields(scope) {
        scope.querySelectorAll('[data-img-field]').forEach(function (field) {
            if (field.dataset.bound) return;
            field.dataset.bound = '1';

            var input = field.querySelector('[data-img-input]');
            var fileEl = field.querySelector('[data-img-file]');
            var preview = field.querySelector('[data-img-preview]');
            var upBtn = field.querySelector('[data-img-upload]');
            var rmBtn = field.querySelector('[data-img-remove]');
            var libBtn = field.querySelector('[data-img-library]');

            function renderPreview(path) {
                if (!preview) return;
                if (path) {
                    preview.innerHTML = '<img src="../' + escapeHtml(path) + '" alt="Current image preview">';
                } else {
                    preview.innerHTML = '<span class="img-field__empty">No image — click “Upload image”</span>';
                }
            }

            if (upBtn) upBtn.addEventListener('click', function () { fileEl.click(); });

            if (rmBtn) rmBtn.addEventListener('click', function () {
                input.value = '';
                renderPreview('');
                markDirty();
            });

            if (libBtn) libBtn.addEventListener('click', function () {
                openMediaPicker(field);
            });

            if (fileEl) fileEl.addEventListener('change', function () {
                if (!fileEl.files || !fileEl.files.length) return;
                var fd = new FormData();
                fd.append('file', fileEl.files[0]);
                fd.append('csrf_token', csrf);

                upBtn.disabled = true;
                upBtn.textContent = 'Uploading…';

                fetch('../api/upload-image.php', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data && data.ok) {
                            input.value = data.path;
                            renderPreview(data.path);
                            markDirty();
                            showToast('success', 'Image uploaded.');
                        } else {
                            showToast('error', (data && data.message) || 'Upload failed.');
                        }
                    })
                    .catch(function () {
                        showToast('error', 'Upload failed — check your connection and try again.');
                    })
                    .then(function () {
                        upBtn.disabled = false;
                        upBtn.textContent = 'Upload image';
                    });

                fileEl.value = '';
            });
        });
    }

    bindImageFields(d);

    /* ---------- Design & Theme (content.php → Design & Theme tab) ----------
       Preset cards fill the colour inputs; the mini preview re-skins live so
       the non-technical user sees the result before saving. */
    var themePresets = d.getElementById('themePresets');
    if (themePresets) {
        var themeHexes = Array.prototype.slice.call(d.querySelectorAll('[data-theme-hex]'));
        var themeColors = Array.prototype.slice.call(d.querySelectorAll('[data-theme-color]'));
        var themeSectionHexes = Array.prototype.slice.call(d.querySelectorAll('[data-section-hex]'));
        var themeSectionColors = Array.prototype.slice.call(d.querySelectorAll('[data-section-color]'));
        var themePreview = d.getElementById('themePreview');
        var themePresetInput = d.querySelector('[data-theme-preset-input]');

        function syncThemePreview() {
            // Mirror hex values into the native colour pickers.
            themeHexes.forEach(function (hex, i) {
                if (themeColors[i] && /^#[0-9a-f]{6}$/i.test(hex.value)) {
                    themeColors[i].value = hex.value;
                }
            });
            themeSectionHexes.forEach(function (hex, i) {
                if (themeSectionColors[i] && /^#[0-9a-f]{6}$/i.test(hex.value)) {
                    themeSectionColors[i].value = hex.value;
                }
            });
            if (!themePreview) return;
            var primary = (themeHexes[0] && themeHexes[0].value) || '#0B192C';
            var accent  = (themeHexes[1] && themeHexes[1].value) || '#D4AF37';
            var bg      = (themeHexes[2] && themeHexes[2].value) || '#F8FAFC';
            themePreview.querySelectorAll('[data-preview-primary]').forEach(function (el) {
                el.style.background = primary;
            });
            var title = themePreview.querySelector('[data-preview-title]');
            if (title) title.style.color = primary;
            var btn = themePreview.querySelector('[data-preview-accent]');
            if (btn) { btn.style.background = accent; btn.style.color = '#0B192C'; }
            var body = themePreview.querySelector('.theme-preview__body');
            if (body) body.style.background = bg;
            // Section bands: custom colour if set, otherwise the theme primary.
            // Zones are matched by data-zone, not array position, so reordering
            // the pickers in the form can never miscolour the preview.
            var sectionBg = function (zone) {
                var hex = d.querySelector('[data-section-hex][data-zone="' + zone + '"]');
                return (hex && /^#[0-9a-f]{6}$/i.test(hex.value)) ? hex.value : primary;
            };
            themePreview.querySelectorAll('[data-preview-hero]').forEach(function (el) {
                el.style.background = sectionBg('hero');
            });
            themePreview.querySelectorAll('[data-preview-stats]').forEach(function (el) {
                el.style.background = sectionBg('stats');
            });
            themePreview.querySelectorAll('[data-preview-cta]').forEach(function (el) {
                el.style.background = sectionBg('cta');
            });
            themePreview.querySelectorAll('[data-preview-footer]').forEach(function (el) {
                el.style.background = sectionBg('footer');
            });
        }

        themePresets.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-theme-preset]');
            if (!btn) return;
            var key = btn.getAttribute('data-theme-preset');
            themePresets.querySelectorAll('[data-theme-preset]').forEach(function (b) {
                b.classList.toggle('is-selected', b === btn);
            });
            if (themePresetInput) themePresetInput.value = key;
            if (key === 'custom') {
                syncThemePreview();
                markDirty();
                return;
            }
            var values = [btn.getAttribute('data-primary'), btn.getAttribute('data-accent'), btn.getAttribute('data-background')];
            themeHexes.forEach(function (hex, i) {
                if (values[i]) hex.value = values[i];
            });
            syncThemePreview();
            markDirty();
        });

        themeHexes.forEach(function (hex) {
            hex.addEventListener('input', function () {
                // Manual colour edits mean the palette is now custom.
                if (themePresetInput) themePresetInput.value = 'custom';
                themePresets.querySelectorAll('[data-theme-preset]').forEach(function (b) {
                    b.classList.toggle('is-selected', b.getAttribute('data-theme-preset') === 'custom');
                });
                syncThemePreview();
            });
        });
        themeColors.forEach(function (color, i) {
            color.addEventListener('input', function () {
                if (themeHexes[i]) themeHexes[i].value = color.value;
                if (themePresetInput) themePresetInput.value = 'custom';
                themePresets.querySelectorAll('[data-theme-preset]').forEach(function (b) {
                    b.classList.toggle('is-selected', b.getAttribute('data-theme-preset') === 'custom');
                });
                syncThemePreview();
                markDirty();
            });
        });

        /* ---------- Per-section backgrounds ----------
           Colour + hex + “Use theme colour” reset, wired per row so the
           order can never drift. Typing in the hex box already marks the
           form dirty via the global input listener. */
        d.querySelectorAll('[data-section-row]').forEach(function (row) {
            var color = row.querySelector('[data-section-color]');
            var hex = row.querySelector('[data-section-hex]');
            var reset = row.querySelector('[data-section-reset]');
            if (color) color.addEventListener('input', function () {
                if (hex) hex.value = color.value;
                syncThemePreview();
            });
            if (hex) hex.addEventListener('input', function () {
                if (color && /^#[0-9a-f]{6}$/i.test(hex.value)) {
                    color.value = hex.value;
                }
                syncThemePreview();
            });
            if (reset) reset.addEventListener('click', function () {
                if (hex) hex.value = '';
                if (color) color.value = (themeHexes[0] && themeHexes[0].value) || '#0B192C';
                syncThemePreview();
                markDirty();
            });
        });

        /* ---------- Font pairings ---------- */
        var themeFontsWrap = d.getElementById('themeFonts');
        if (themeFontsWrap) {
            var themeFontsInput = d.querySelector('[data-theme-fonts-input]');

            function loadGoogleFont(family) {
                if (!family || d.querySelector('link[data-fontlink="' + family + '"]')) return;
                var link = d.createElement('link');
                link.rel = 'stylesheet';
                link.setAttribute('data-fontlink', family);
                link.href = 'https://fonts.googleapis.com/css2?family='
                    + encodeURIComponent(family).replace(/%20/g, '+') + ':wght@400;600;700&display=swap';
                d.head.appendChild(link);
            }

            function applyFontPair(display, body) {
                var title = themePreview && themePreview.querySelector('[data-preview-title]');
                if (title && display) title.style.fontFamily = "'" + display + "', Georgia, serif";
                var text = themePreview && themePreview.querySelector('.theme-preview__text');
                if (text && body) text.style.fontFamily = "'" + body + "', sans-serif";
                loadGoogleFont(display);
                loadGoogleFont(body);
            }

            themeFontsWrap.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-theme-fonts]');
                if (!btn) return;
                themeFontsWrap.querySelectorAll('[data-theme-fonts]').forEach(function (b) {
                    b.classList.toggle('is-selected', b === btn);
                });
                if (themeFontsInput) themeFontsInput.value = btn.getAttribute('data-theme-fonts');
                applyFontPair(btn.getAttribute('data-display'), btn.getAttribute('data-body'));
                markDirty();
            });

            // Reflect the saved pairing on load. Read the hidden input (not the
            // server-rendered selected class) so a restored draft's font choice
            // is previewed correctly too.
            var currentKey = themeFontsInput ? themeFontsInput.value : '';
            var current = currentKey
                ? themeFontsWrap.querySelector('[data-theme-fonts="' + currentKey + '"]')
                : null;
            if (current) applyFontPair(current.getAttribute('data-display'), current.getAttribute('data-body'));
        }

        syncThemePreview(); // initial state
    }

    /* ---------- Media library picker ---------- */
    var mediaModal = null;
    var mediaField = null;
    var mediaOnPick = null;

    function ensureMediaModal() {
        if (mediaModal) return mediaModal;
        mediaModal = d.createElement('div');
        mediaModal.className = 'media-modal';
        mediaModal.setAttribute('role', 'dialog');
        mediaModal.setAttribute('aria-modal', 'true');
        mediaModal.hidden = true;
        mediaModal.innerHTML =
            '<div class="media-modal__backdrop" data-media-close></div>' +
            '<div class="media-modal__panel">' +
            '<div class="media-modal__head">' +
            '<h3>Choose an image from the library</h3>' +
            '<button type="button" class="media-modal__close" data-media-close aria-label="Close">&times;</button>' +
            '</div>' +
            '<div class="media-modal__grid" data-media-grid>' +
            '<p class="table__muted">Loading…</p>' +
            '</div>' +
            '<p class="form__hint" style="margin-top:12px;">Manage and add more images on the <a href="media.php" target="_blank" rel="noopener">Media Library</a> page.</p>' +
            '</div>';
        d.body.appendChild(mediaModal);

        mediaModal.addEventListener('click', function (e) {
            if (e.target.closest('[data-media-close]')) {
                mediaModal.hidden = true;
                return;
            }
            var item = e.target.closest('[data-media-path]');
            if (!item) return;
            var path = item.getAttribute('data-media-path') || '';

            // Callback mode (e.g. RTE image insert): hand the path over.
            if (typeof mediaOnPick === 'function') {
                var cb = mediaOnPick;
                mediaOnPick = null;
                mediaModal.hidden = true;
                cb(path);
                return;
            }

            if (!mediaField) return;
            var input = mediaField.querySelector('[data-img-input]');
            var preview = mediaField.querySelector('[data-img-preview]');
            if (input) input.value = path;
            if (preview) {
                preview.innerHTML = path
                    ? '<img src="../' + escapeHtml(path) + '" alt="Current image preview">'
                    : '<span class="img-field__empty">No image — click “Upload image”</span>';
            }
            mediaModal.hidden = true;
            markDirty();
        });

        d.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && mediaModal && !mediaModal.hidden) {
                mediaModal.hidden = true;
            }
        });
        return mediaModal;
    }

    function openMediaPicker(field, onPick) {
        mediaField = field || null;
        mediaOnPick = (typeof onPick === 'function') ? onPick : null;
        var modal = ensureMediaModal();
        var grid = modal.querySelector('[data-media-grid]');
        grid.innerHTML = '<p class="table__muted">Loading…</p>';
        modal.hidden = false;

        fetch('../api/media-list.php', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    grid.innerHTML = '<p class="table__muted">Could not load the library.</p>';
                    return;
                }
                if (!data.images.length) {
                    grid.innerHTML = '<p class="table__muted">No images yet — use the “Upload image” button to add your first one.</p>';
                    return;
                }
                grid.innerHTML = data.images.map(function (img) {
                    return '<button type="button" class="media-item" data-media-path="' + escapeHtml(img.path) + '">' +
                        '<img src="../' + escapeHtml(img.path) + '" alt="' + escapeHtml(img.name) + '" loading="lazy">' +
                        '<span class="media-item__name">' + escapeHtml(img.name) + '</span>' +
                        '</button>';
                }).join('');
            })
            .catch(function () {
                grid.innerHTML = '<p class="table__muted">Could not load the library — check your connection.</p>';
            });
    }


    /* ---------- Repeatable lists ---------- */
    function nextListIndex(list) {
        var max = -1;
        list.querySelectorAll('[data-list-row]').forEach(function (row) {
            var name = (row.querySelector('input') || {}).name || '';
            var match = name.match(/\[(\d+)\]/);
            if (match) max = Math.max(max, parseInt(match[1], 10));
        });
        var fromAttr = parseInt(list.getAttribute('data-index') || '0', 10);
        return Math.max(max + 1, fromAttr);
    }

    function bindList(list) {
        if (list.dataset.bound) return;
        list.dataset.bound = '1';

        var rowsWrap = list.querySelector('[data-list-rows]');
        var tpl = list.querySelector('template[data-list-template]');
        var addBtn = list.querySelector('[data-list-add]');

        if (addBtn && tpl && rowsWrap) {
            addBtn.addEventListener('click', function () {
                var idx = nextListIndex(list);
                var html = tpl.innerHTML.split('__i__').join(idx);
                var tmp = d.createElement('div');
                tmp.innerHTML = html.trim();
                var row = tmp.firstElementChild;
                rowsWrap.appendChild(row);
                list.setAttribute('data-index', String(idx + 1));
                // New rows may contain image fields (e.g. the photo gallery).
                bindImageFields(row);
                markDirty();
            });
        }
    }

    function bindLists(scope) {
        scope.querySelectorAll('[data-list]').forEach(bindList);
    }

    bindLists(d);

    d.addEventListener('click', function (e) {
        var rmBtn = e.target.closest('[data-list-remove]');
        if (!rmBtn) return;
        var row = rmBtn.closest('[data-list-row]');
        if (row) {
            row.remove();
            markDirty();
        }
    });

    /* ---------- Pillar editor (Services tab) ----------
       Pillars can be added and removed freely. A new pillar is cloned from
       the server-rendered template with a fresh index, its key (anchor id)
       is auto-generated from the title, and image fields / checklist lists
       inside it are bound the same way as the original pillars. */
    function nextPillarIndex(wrap) {
        var max = -1;
        wrap.querySelectorAll('[data-pillar-block]').forEach(function (block) {
            var input = block.querySelector('input[name]');
            var match = input && input.name.match(/service\[(\d+)\]/);
            if (match) max = Math.max(max, parseInt(match[1], 10));
        });
        var fromAttr = parseInt(wrap.getAttribute('data-index') || '0', 10);
        return Math.max(max + 1, fromAttr);
    }

    function pillarIndexFromName(name) {
        var m = String(name || '').match(/service\[(\d+)\]/);
        return m ? parseInt(m[1], 10) : -1;
    }

    function slugify(text) {
        return String(text || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function uniquePillarKey(base) {
        var used = {};
        d.querySelectorAll('[data-pillar-key]').forEach(function (k) { used[k.value] = 1; });
        if (!used[base]) return base;
        var n = 2;
        while (used[base + '-' + n]) n += 1;
        return base + '-' + n;
    }

    function syncPillarHeads() {
        d.querySelectorAll('[data-pillar-block]').forEach(function (block) {
            var titleInput = block.querySelector('[data-pillar-title]');
            var numInput = block.querySelector('[data-pillar-num-input]');
            var liveTitle = block.querySelector('[data-pillar-live-title]');
            var liveNum = block.querySelector('[data-pillar-live-num]');
            if (liveTitle) {
                liveTitle.textContent = (titleInput && titleInput.value.trim()) ? titleInput.value.trim() : 'New pillar';
            }
            if (liveNum && numInput) {
                liveNum.textContent = numInput.value;
            }
        });
    }

    function addPillar() {
        var wrap = d.querySelector('[data-pillars]');
        var tpl = d.querySelector('template[data-pillar-template]');
        if (!wrap || !tpl) return;

        var idx = nextPillarIndex(wrap);
        var html = tpl.innerHTML.split('__i__').join(idx);
        var tmp = d.createElement('div');
        tmp.innerHTML = html.trim();
        var block = tmp.firstElementChild;
        wrap.appendChild(block);
        wrap.setAttribute('data-index', String(idx + 1));

        var numInput = block.querySelector('[data-pillar-num-input]');
        if (numInput && !numInput.value) {
            numInput.value = String(idx + 1).padStart(2, '0');
        }
        var keyInput = block.querySelector('[data-pillar-key]');
        if (keyInput && !keyInput.value) {
            keyInput.value = 'pillar-' + (idx + 1);
        }

        syncPillarHeads();
        bindImageFields(block);
        bindLists(block);

        var title = block.querySelector('[data-pillar-title]');
        if (title) title.focus();
        markDirty();
    }

    function removePillar(btn) {
        var blocks = d.querySelectorAll('[data-pillar-block]');
        if (blocks.length <= 1) {
            showToast('error', 'At least one pillar is required — edit this one instead.');
            return;
        }
        var block = btn.closest('[data-pillar-block]');
        if (!block) return;
        if (!window.confirm('Remove this pillar? It will disappear from the homepage, the Services page and the booking form.')) return;
        block.remove();
        syncPillarHeads();
        markDirty();
    }

    d.addEventListener('click', function (e) {
        var addBtn = e.target.closest('[data-pillar-add]');
        if (addBtn) {
            addPillar();
            return;
        }
        var rmBtn = e.target.closest('[data-pillar-remove]');
        if (rmBtn) {
            removePillar(rmBtn);
        }
    });

    // Auto-generate the pillar key (its services.php anchor) from the title,
    // and keep the group heading live — but never overwrite a key that is not
    // an auto-generated one (e.g. tax, ngo, corporate).
    d.addEventListener('input', function (e) {
        var title = e.target.closest('[data-pillar-title]');
        var num = e.target.closest('[data-pillar-num-input]');
        var block = null;

        if (title) {
            block = title.closest('[data-pillar-block]');
            if (block) {
                var keyInput = block.querySelector('[data-pillar-key]');
                if (keyInput && (keyInput.value === '' || /^pillar-\d+$/.test(keyInput.value))) {
                    var base = slugify(title.value) || ('pillar-' + (pillarIndexFromName(title.name) + 1));
                    keyInput.value = uniquePillarKey(base);
                }
            }
        } else if (num) {
            block = num.closest('[data-pillar-block]');
        }

        if (block) syncPillarHeads();
    });

    /* ---------- Save (AJAX with graceful fallback) ---------- */
    var onSubmit = function (e) {
        e.preventDefault();
        syncRtes();
        if (!cmsForm.reportValidity()) return;

        if (saveBtn) saveBtn.disabled = true;
        if (saveStatus) saveStatus.textContent = 'Saving…';

        fetch('content.php', {
            method: 'POST',
            body: new FormData(cmsForm),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    setClean();
                    clearDraft();
                    showToast('success', data.message || 'Website content saved.');
                } else {
                    showToast('error', (data && data.message) || 'Could not save the content.');
                }
            })
            .catch(function () {
                // Fallback: submit normally (full page reload).
                cmsForm.removeEventListener('submit', onSubmit);
                cmsForm.submit();
            })
            .then(function () {
                if (saveBtn) saveBtn.disabled = false;
            });
    };
    cmsForm.addEventListener('submit', onSubmit);

    /* ---------- Discard ---------- */
    var discardBtn = d.querySelector('[data-save-discard]');
    if (discardBtn) {
        discardBtn.addEventListener('click', function () {
            if (window.confirm('Discard all unsaved changes?')) {
                clearDraft();
                window.location.reload();
            }
        });
    }

    /* ---------- Restore section defaults ---------- */
    d.querySelectorAll('[data-reset-sections]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sections = (btn.getAttribute('data-reset-sections') || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            if (!sections.length) return;
            if (!window.confirm('Restore the original default text for this section? The page will reload, so any unsaved changes in OTHER tabs will also be discarded.')) return;

            btn.disabled = true;
            var original = btn.textContent;
            btn.textContent = 'Restoring…';

            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('cms_action', 'reset');
            sections.forEach(function (s) { fd.append('sections[]', s); });

            fetch('content.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    showToast(data && data.ok ? 'success' : 'error', (data && data.message) || 'Could not reset this section.');
                    clearDraft();
                    window.location.reload();
                })
                .catch(function () {
                    showToast('error', 'Could not reset this section.');
                    btn.disabled = false;
                    btn.textContent = original;
                });
        });
    });
    }

    /* ---------- Admin Panel Theme Switcher (Dark / Light) ---------- */
    var themeToggleBtns = d.querySelectorAll('.theme-toggle');
    var radioLight = d.getElementById('themeRadioLight');
    var radioDark = d.getElementById('themeRadioDark');

    function applyAdminTheme(theme, notify) {
        var mode = theme === 'dark' ? 'dark' : 'light';
        d.documentElement.setAttribute('data-admin-theme', mode);
        try {
            localStorage.setItem('owere_admin_theme', mode);
        } catch (e) {}

        themeToggleBtns.forEach(function (btn) {
            var textSpan = btn.querySelector('.theme-toggle__text');
            if (textSpan) {
                textSpan.textContent = mode === 'dark' ? 'Dark' : 'Light';
            }
        });

        if (radioLight && radioDark) {
            if (mode === 'dark') {
                radioDark.checked = true;
            } else {
                radioLight.checked = true;
            }
        }

        if (notify) {
            showToast('success', 'Switched to ' + (mode === 'dark' ? 'Executive Dark' : 'Light') + ' mode');
        }
    }

    var initialTheme = d.documentElement.getAttribute('data-admin-theme') || 'light';
    applyAdminTheme(initialTheme, false);

    themeToggleBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var curr = d.documentElement.getAttribute('data-admin-theme') || 'light';
            var targetMode = curr === 'dark' ? 'light' : 'dark';
            applyAdminTheme(targetMode, true);
        });
    });

    if (radioLight) {
        radioLight.addEventListener('change', function () {
            if (radioLight.checked) applyAdminTheme('light', true);
        });
    }
    if (radioDark) {
        radioDark.addEventListener('change', function () {
            if (radioDark.checked) applyAdminTheme('dark', true);
        });
    }
})();

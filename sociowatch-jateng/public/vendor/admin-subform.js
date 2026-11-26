/* SocioWatch Jateng - 100% Strict-CSP Vanilla JS UI Helpers (No eval / No Alpine) */
(function () {
    'use strict';

    var ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    var MAX_BYTES = 2 * 1024 * 1024;

    function validateAndPreviewPhoto(file, inputEl, previewBox, fileLabel, errorEl, fallbackUrl) {
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        }
        if (!file) {
            setPreviewImage(previewBox, fallbackUrl);
            if (fileLabel) fileLabel.textContent = fileLabel.getAttribute('data-default-text') || '';
            return;
        }
        if (ALLOWED_MIMES.indexOf(file.type) === -1) {
            if (errorEl) {
                errorEl.textContent = 'Hanya file gambar JPG, PNG, atau WEBP yang diizinkan.';
                errorEl.classList.remove('hidden');
            }
            if (inputEl) inputEl.value = '';
            setPreviewImage(previewBox, fallbackUrl);
            return;
        }
        if (file.size > MAX_BYTES) {
            if (errorEl) {
                errorEl.textContent = 'Ukuran foto melebihi batas maksimal 2 MB.';
                errorEl.classList.remove('hidden');
            }
            if (inputEl) inputEl.value = '';
            setPreviewImage(previewBox, fallbackUrl);
            return;
        }
        if (fileLabel) fileLabel.textContent = 'File terpilih: ' + file.name;
        var reader = new FileReader();
        reader.onload = function (e) {
            setPreviewImage(previewBox, e.target.result);
        };
        reader.readAsDataURL(file);
    }

    function setPreviewImage(previewBox, url) {
        if (!previewBox) return;
        previewBox.innerHTML = '';
        if (url) {
            var img = document.createElement('img');
            img.src = url;
            img.alt = 'Preview foto admin';
            img.className = 'w-full h-full object-cover';
            previewBox.appendChild(img);
        } else {
            var icon = document.createElement('i');
            icon.className = 'fas fa-user text-gray-300 text-xl';
            previewBox.appendChild(icon);
        }
    }

    function bindDropzone(dropzone, inputEl, previewBox, fileLabel, errorEl, fallbackUrl) {
        if (!dropzone || !inputEl) return;

        dropzone.addEventListener('click', function () {
            inputEl.click();
        });
        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropzone.classList.add('border-indigo-500', 'bg-indigo-50/70');
            dropzone.classList.remove('border-gray-300');
        });
        dropzone.addEventListener('dragleave', function (e) {
            e.preventDefault();
            dropzone.classList.remove('border-indigo-500', 'bg-indigo-50/70');
            dropzone.classList.add('border-gray-300');
        });
        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropzone.classList.remove('border-indigo-500', 'bg-indigo-50/70');
            dropzone.classList.add('border-gray-300');
            var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (!file) return;
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                inputEl.files = dt.files;
            } catch (_) {}
            validateAndPreviewPhoto(file, inputEl, previewBox, fileLabel, errorEl, fallbackUrl);
        });
        inputEl.addEventListener('change', function () {
            var file = inputEl.files && inputEl.files[0];
            validateAndPreviewPhoto(file, inputEl, previewBox, fileLabel, errorEl, fallbackUrl);
        });
    }

    function makeField(labelText, fieldKey, value, opts) {
        opts = opts || {};
        var wrap = document.createElement('div');
        if (opts.colSpan) wrap.className = opts.colSpan;

        var label = document.createElement('label');
        label.className = 'block text-xs font-medium text-gray-500 mb-1';
        label.textContent = labelText;
        if (opts.required) {
            var star = document.createElement('span');
            star.className = 'text-red-500';
            star.textContent = ' *';
            label.appendChild(star);
        }
        wrap.appendChild(label);

        var el;
        if (opts.textarea) {
            el = document.createElement('textarea');
            el.rows = 2;
            el.className = 'w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none resize-none bg-white';
            el.value = value || '';
        } else {
            el = document.createElement('input');
            el.type = opts.type || 'text';
            el.className = 'w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none bg-white';
            el.value = value || '';
            if (opts.maxlength) el.maxLength = opts.maxlength;
        }
        if (opts.placeholder) el.placeholder = opts.placeholder;
        if (opts.required) el.required = true;
        el.setAttribute('data-admin-field', fieldKey);
        wrap.appendChild(el);
        return wrap;
    }

    window.initAdminSubForm = function (opts) {
        var listEl = document.getElementById(opts.listId);
        var addBtn = document.getElementById(opts.addBtnId);
        var emptyEl = document.getElementById(opts.emptyId);
        if (!listEl || !addBtn) return;

        function reindex() {
            var cards = listEl.querySelectorAll('[data-admin-card]');
            cards.forEach(function (card, idx) {
                var num = card.querySelector('[data-admin-number]');
                if (num) num.textContent = String(idx + 1);
                card.querySelectorAll('[data-admin-field]').forEach(function (input) {
                    var key = input.getAttribute('data-admin-field');
                    input.name = 'admins[' + idx + '][' + key + ']';
                });
            });
            if (emptyEl) {
                emptyEl.classList.toggle('hidden', cards.length > 0);
            }
        }

        function createCard(data) {
            data = data || {};
            var card = document.createElement('div');
            card.setAttribute('data-admin-card', '1');
            card.className = 'border border-gray-100 rounded-xl p-4 mb-4 bg-gray-50 relative';

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.setAttribute('aria-label', 'Hapus admin');
            removeBtn.className = 'absolute top-3 right-3 text-red-400 hover:text-red-600 hover:bg-red-50 w-7 h-7 rounded-lg flex items-center justify-center text-xs transition';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.addEventListener('click', function () {
                card.remove();
                reindex();
            });
            card.appendChild(removeBtn);

            var title = document.createElement('p');
            title.className = 'text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3';
            title.innerHTML = 'Admin #<span data-admin-number="1">1</span>';
            card.appendChild(title);

            var idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.setAttribute('data-admin-field', 'id');
            idInput.value = data.id || '';
            card.appendChild(idInput);

            var grid = document.createElement('div');
            grid.className = 'grid grid-cols-1 md:grid-cols-3 gap-3';

            // Foto Admin row
            var photoWrap = document.createElement('div');
            photoWrap.className = 'md:col-span-3';
            var photoLabel = document.createElement('label');
            photoLabel.className = 'block text-xs font-medium text-gray-500 mb-1.5';
            photoLabel.textContent = 'Foto Admin';
            photoWrap.appendChild(photoLabel);

            var photoRow = document.createElement('div');
            photoRow.className = 'flex items-start gap-3';

            var previewBox = document.createElement('div');
            previewBox.className = 'w-16 h-16 rounded-xl border border-gray-200 bg-white flex items-center justify-center overflow-hidden flex-shrink-0 shadow-xs';
            setPreviewImage(previewBox, data.photo_url || null);
            photoRow.appendChild(previewBox);

            var dropCol = document.createElement('div');
            dropCol.className = 'flex-1 space-y-1.5';

            var dropzone = document.createElement('div');
            dropzone.className = 'border-2 border-dashed border-gray-300 bg-white hover:border-indigo-400 hover:bg-indigo-50/30 rounded-xl px-4 py-3 text-center cursor-pointer transition';

            var fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp';
            fileInput.className = 'hidden';
            fileInput.setAttribute('data-admin-field', 'photo');
            dropzone.appendChild(fileInput);

            var dropPrompt = document.createElement('div');
            dropPrompt.className = 'flex items-center justify-center gap-2 text-xs text-gray-600';
            dropPrompt.innerHTML = '<i class="fas fa-cloud-upload-alt text-indigo-500 text-base"></i><span><span class="font-semibold text-indigo-600">Klik untuk pilih</span> atau <strong>drag &amp; drop</strong> foto di sini</span>';
            dropzone.appendChild(dropPrompt);

            var fileLabel = document.createElement('p');
            fileLabel.className = 'text-[11px] text-gray-400 mt-0.5';
            var defHint = 'JPG, PNG, atau WEBP • Maks. 2 MB (otomatis disanitasi)';
            fileLabel.setAttribute('data-default-text', defHint);
            fileLabel.textContent = defHint;
            dropzone.appendChild(fileLabel);

            dropCol.appendChild(dropzone);

            var errorEl = document.createElement('p');
            errorEl.className = 'text-red-500 text-xs hidden';
            dropCol.appendChild(errorEl);

            if (data.photo_url) {
                var rmLabel = document.createElement('label');
                rmLabel.className = 'inline-flex items-center gap-1.5 text-xs text-red-600 cursor-pointer';
                var rmCheck = document.createElement('input');
                rmCheck.type = 'checkbox';
                rmCheck.value = '1';
                rmCheck.className = 'rounded border-gray-300 text-red-600 focus:ring-red-400';
                rmCheck.setAttribute('data-admin-field', 'remove_photo');
                var rmText = document.createElement('span');
                rmText.textContent = 'Hapus foto saat ini';
                rmLabel.appendChild(rmCheck);
                rmLabel.appendChild(rmText);
                dropCol.appendChild(rmLabel);
            }

            bindDropzone(dropzone, fileInput, previewBox, fileLabel, errorEl, data.photo_url || null);

            photoRow.appendChild(dropCol);
            photoWrap.appendChild(photoRow);
            grid.appendChild(photoWrap);

            // Personal data fields
            grid.appendChild(makeField('Nama Lengkap', 'full_name', data.full_name, { colSpan: 'md:col-span-2', required: true }));
            grid.appendChild(makeField('Alias / Panggilan', 'alias', data.alias));
            grid.appendChild(makeField('NIK', 'nik', data.nik, { maxlength: 20, placeholder: '16 digit NIK' }));
            grid.appendChild(makeField('No. KK (Kartu Keluarga)', 'no_kk', data.no_kk, { maxlength: 20, placeholder: '16 digit No. KK' }));
            grid.appendChild(makeField('Status', 'status', data.status, { placeholder: 'Misal: Lajang / Menikah' }));
            grid.appendChild(makeField('Agama', 'agama', data.agama, { placeholder: 'Misal: Islam' }));
            grid.appendChild(makeField('Tempat, Tanggal Lahir (TTL)', 'ttl', data.ttl, { placeholder: 'Misal: Semarang, 12 Mei 1998' }));
            grid.appendChild(makeField('Pekerjaan', 'occupation', data.occupation, { placeholder: 'Misal: Mahasiswa / Swasta' }));
            grid.appendChild(makeField('No. HP / WhatsApp', 'phone', data.phone, { placeholder: '08xxxxxxxx' }));
            grid.appendChild(makeField('Email', 'email', data.email, { type: 'email' }));
            grid.appendChild(makeField('Afiliasi / Organisasi', 'affiliation', data.affiliation, { colSpan: 'md:col-span-2', placeholder: 'Misal: BEM KM / LBH / Ormas' }));
            grid.appendChild(makeField('Alamat Domisili / Tempat Tinggal', 'alamat', data.alamat, { colSpan: 'md:col-span-3', placeholder: 'Alamat lengkap / domisili' }));
            grid.appendChild(makeField('Catatan (Data Lain)', 'notes', data.notes, { colSpan: 'md:col-span-3', textarea: true, placeholder: 'Catatan data lain, peran admin, atau rekam jejak' }));

            card.appendChild(grid);
            listEl.appendChild(card);
            reindex();
        }

        addBtn.addEventListener('click', function () {
            createCard({});
        });

        var initial = Array.isArray(opts.initialAdmins) ? opts.initialAdmins : [];
        initial.forEach(function (a) { createCard(a); });
        reindex();
    };

    window.initSinglePhotoDropzone = function (rootId) {
        var root = document.getElementById(rootId);
        if (!root) return;
        var dropzone = root.querySelector('[data-photo-dropzone]');
        var inputEl = root.querySelector('[data-photo-input]');
        var previewBox = root.querySelector('[data-photo-preview]');
        var fileLabel = root.querySelector('[data-photo-label]');
        var errorEl = root.querySelector('[data-photo-error]');
        var fallbackUrl = root.getAttribute('data-original-url') || null;
        bindDropzone(dropzone, inputEl, previewBox, fileLabel, errorEl, fallbackUrl);
    };
})();

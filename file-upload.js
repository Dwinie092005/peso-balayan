/**
 * PESO Balayan – File Upload Module
 * File: public/assets/js/file-upload.js
 *
 * Reusable drag-and-drop file upload handler with:
 *  - Drag/drop + click-to-browse
 *  - Preview (images) or file-type icon (docs/PDFs)
 *  - Upload progress bar (XHR)
 *  - MIME type & file size validation (client-side)
 *  - Dispatches custom events for external listeners
 *
 * Usage:
 *   const uploader = FileUpload.init('#my-dropzone', {
 *     inputName    : 'resume',
 *     maxSizeMB    : 5,
 *     allowedTypes : ['application/pdf', 'image/jpeg', 'image/png'],
 *     allowedExts  : ['pdf', 'jpg', 'jpeg', 'png'],
 *     uploadUrl    : '/applicant/upload-resume',
 *     csrfToken    : document.querySelector('meta[name="csrf-token"]')?.content,
 *     onSuccess    : (response) => console.log(response),
 *     onError      : (message)  => console.error(message),
 *   });
 *
 *   uploader.reset();   // Clear the dropzone
 *   uploader.getFile(); // Returns the selected File object or null
 */

const FileUpload = (() => {

  // ── Constants ─────────────────────────────────────────────────
  const DEFAULTS = {
    inputName    : 'file',
    maxSizeMB    : 5,
    allowedTypes : [
      'image/jpeg', 'image/png',
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],
    allowedExts  : ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
    uploadUrl    : null,
    csrfToken    : null,
    autoUpload   : false,
    multiple     : false,
    onSuccess    : null,
    onError      : null,
    onFileSelect : null,
  };

  const MIME_ICON_MAP = {
    'application/pdf'   : 'fa-file-pdf',
    'application/msword': 'fa-file-word',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'fa-file-word',
    'image/jpeg'        : 'fa-file-image',
    'image/png'         : 'fa-file-image',
  };

  // ── Helpers ───────────────────────────────────────────────────

  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
  }

  function getExtension(filename) {
    return filename.split('.').pop().toLowerCase();
  }

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  // ── Validation ────────────────────────────────────────────────

  function validateFile(file, opts) {
    const maxBytes = opts.maxSizeMB * 1024 * 1024;
    const ext      = getExtension(file.name);

    if (!opts.allowedExts.includes(ext)) {
      return `File type ".${ext}" is not allowed. Allowed: ${opts.allowedExts.join(', ')}`;
    }
    if (!opts.allowedTypes.includes(file.type)) {
      return `Invalid file format. Please upload: ${opts.allowedTypes.join(', ')}`;
    }
    if (file.size > maxBytes) {
      return `File size (${formatBytes(file.size)}) exceeds the ${opts.maxSizeMB} MB limit.`;
    }
    return null;
  }

  // ── UI Builders ───────────────────────────────────────────────

  function buildDropzone(container, opts) {
    container.innerHTML = `
      <div class="fu-dropzone" role="button" tabindex="0"
           aria-label="Upload file – click or drag and drop">
        <div class="fu-idle">
          <div class="fu-idle-icon" aria-hidden="true">
            <i class="fas fa-cloud-upload-alt"></i>
          </div>
          <p class="fu-idle-title">Drag &amp; drop your file here</p>
          <p class="fu-idle-sub">
            or <span class="fu-browse-link">browse to upload</span>
          </p>
          <p class="fu-idle-hint">
            Allowed: ${opts.allowedExts.join(', ').toUpperCase()} · Max ${opts.maxSizeMB} MB
          </p>
        </div>

        <div class="fu-preview" aria-live="polite" style="display:none;">
          <div class="fu-preview-thumb" aria-hidden="true"></div>
          <div class="fu-preview-info">
            <p class="fu-preview-name"></p>
            <p class="fu-preview-size"></p>
          </div>
          <button type="button" class="fu-remove-btn" aria-label="Remove file">
            <i class="fas fa-times" aria-hidden="true"></i>
          </button>
        </div>

        <div class="fu-progress" style="display:none;">
          <div class="fu-progress-bar-wrap">
            <div class="fu-progress-bar" role="progressbar"
                 aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                 style="width:0%;"></div>
          </div>
          <p class="fu-progress-label">Uploading… <span class="fu-pct">0%</span></p>
        </div>

        <div class="fu-error" role="alert" aria-live="assertive" style="display:none;">
          <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
          <span class="fu-error-msg"></span>
        </div>

        <input type="file"
               class="fu-file-input"
               name="${escapeHtml(opts.inputName)}"
               accept="${opts.allowedTypes.join(',')}"
               ${opts.multiple ? 'multiple' : ''}
               style="display:none;"
               aria-hidden="true">
      </div>
    `;
  }

  function showError(container, message) {
    const errEl  = container.querySelector('.fu-error');
    const msgEl  = container.querySelector('.fu-error-msg');
    const dropEl = container.querySelector('.fu-dropzone');
    msgEl.textContent = message;
    errEl.style.display  = 'flex';
    dropEl.classList.add('fu-dropzone--error');
    setTimeout(() => {
      errEl.style.display = 'none';
      dropEl.classList.remove('fu-dropzone--error');
    }, 4500);
  }

  function showPreview(container, file) {
    const idleEl    = container.querySelector('.fu-idle');
    const previewEl = container.querySelector('.fu-preview');
    const thumbEl   = container.querySelector('.fu-preview-thumb');
    const nameEl    = container.querySelector('.fu-preview-name');
    const sizeEl    = container.querySelector('.fu-preview-size');

    nameEl.textContent = file.name;
    sizeEl.textContent = formatBytes(file.size);

    thumbEl.innerHTML = '';

    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.alt = file.name;
        img.className = 'fu-thumb-img';
        thumbEl.appendChild(img);
      };
      reader.readAsDataURL(file);
    } else {
      const iconClass = MIME_ICON_MAP[file.type] || 'fa-file-alt';
      thumbEl.innerHTML = `<i class="fas ${iconClass} fu-thumb-icon" aria-hidden="true"></i>`;
    }

    idleEl.style.display    = 'none';
    previewEl.style.display = 'flex';
    container.querySelector('.fu-dropzone').classList.add('fu-dropzone--has-file');
  }

  function resetPreview(container) {
    const idleEl    = container.querySelector('.fu-idle');
    const previewEl = container.querySelector('.fu-preview');
    const progEl    = container.querySelector('.fu-progress');
    const dropEl    = container.querySelector('.fu-dropzone');
    const inputEl   = container.querySelector('.fu-file-input');

    idleEl.style.display    = 'block';
    previewEl.style.display = 'none';
    progEl.style.display    = 'none';
    dropEl.classList.remove('fu-dropzone--has-file', 'fu-dropzone--error', 'fu-dropzone--success');
    inputEl.value = '';
  }

  function showProgress(container, pct) {
    const progEl  = container.querySelector('.fu-progress');
    const barEl   = container.querySelector('.fu-progress-bar');
    const pctEl   = container.querySelector('.fu-pct');

    progEl.style.display = 'block';
    barEl.style.width    = pct + '%';
    barEl.setAttribute('aria-valuenow', pct);
    pctEl.textContent    = pct + '%';
  }

  // ── XHR Upload ────────────────────────────────────────────────

  function uploadFile(file, opts, container) {
    return new Promise((resolve, reject) => {
      const formData = new FormData();
      formData.append(opts.inputName, file);
      if (opts.csrfToken) {
        formData.append('_csrf_token', opts.csrfToken);
      }

      const xhr = new XMLHttpRequest();

      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const pct = Math.round((e.loaded / e.total) * 100);
          showProgress(container, pct);
        }
      });

      xhr.addEventListener('load', () => {
        try {
          const response = JSON.parse(xhr.responseText);
          if (xhr.status >= 200 && xhr.status < 300 && response.success) {
            container.querySelector('.fu-dropzone').classList.add('fu-dropzone--success');
            resolve(response);
          } else {
            reject(response.message || 'Upload failed. Please try again.');
          }
        } catch {
          reject('Server returned an invalid response.');
        }
      });

      xhr.addEventListener('error',   () => reject('Network error. Please check your connection.'));
      xhr.addEventListener('timeout', () => reject('Upload timed out. Please try again.'));

      xhr.timeout = 60000; // 60s
      xhr.open('POST', opts.uploadUrl, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.send(formData);
    });
  }

  // ── Public Init ───────────────────────────────────────────────

  function init(selector, userOpts = {}) {
    const container = document.querySelector(selector);
    if (!container) {
      console.warn(`[FileUpload] Selector "${selector}" not found.`);
      return null;
    }

    const opts     = Object.assign({}, DEFAULTS, userOpts);
    let   selected = null;

    buildDropzone(container, opts);
    injectStyles();

    const dropzoneEl = container.querySelector('.fu-dropzone');
    const inputEl    = container.querySelector('.fu-file-input');

    // ── Event Wiring ─────────────────────────────────────────

    // Click to browse
    dropzoneEl.addEventListener('click', (e) => {
      if (!e.target.closest('.fu-remove-btn')) {
        inputEl.click();
      }
    });

    // Keyboard accessibility
    dropzoneEl.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        inputEl.click();
      }
    });

    // Drag events
    dropzoneEl.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropzoneEl.classList.add('fu-dropzone--dragging');
    });

    ['dragleave', 'dragend'].forEach(evt =>
      dropzoneEl.addEventListener(evt, () =>
        dropzoneEl.classList.remove('fu-dropzone--dragging')
      )
    );

    dropzoneEl.addEventListener('drop', (e) => {
      e.preventDefault();
      dropzoneEl.classList.remove('fu-dropzone--dragging');
      const file = e.dataTransfer.files[0];
      if (file) handleFile(file);
    });

    // Input change
    inputEl.addEventListener('change', () => {
      const file = inputEl.files[0];
      if (file) handleFile(file);
    });

    // Remove button
    container.querySelector('.fu-remove-btn').addEventListener('click', (e) => {
      e.stopPropagation();
      selected = null;
      resetPreview(container);
      container.dispatchEvent(new CustomEvent('fu:removed'));
    });

    // ── Core Handler ──────────────────────────────────────────

    async function handleFile(file) {
      // Reset any previous error/progress
      container.querySelector('.fu-error').style.display  = 'none';
      container.querySelector('.fu-progress').style.display = 'none';
      dropzoneEl.classList.remove('fu-dropzone--error', 'fu-dropzone--success');

      const validationError = validateFile(file, opts);
      if (validationError) {
        showError(container, validationError);
        if (typeof opts.onError === 'function') opts.onError(validationError);
        return;
      }

      selected = file;
      showPreview(container, file);
      container.dispatchEvent(new CustomEvent('fu:selected', { detail: { file } }));

      if (typeof opts.onFileSelect === 'function') opts.onFileSelect(file);

      if (opts.autoUpload && opts.uploadUrl) {
        try {
          showProgress(container, 0);
          const response = await uploadFile(file, opts, container);
          if (typeof opts.onSuccess === 'function') opts.onSuccess(response);
          container.dispatchEvent(new CustomEvent('fu:uploaded', { detail: response }));
        } catch (errMsg) {
          showError(container, errMsg);
          if (typeof opts.onError === 'function') opts.onError(errMsg);
        }
      }
    }

    // ── Public API ────────────────────────────────────────────
    return {
      /**
       * Manually trigger upload (when autoUpload is false).
       */
      upload: async () => {
        if (!selected) {
          showError(container, 'Please select a file first.');
          return null;
        }
        if (!opts.uploadUrl) {
          console.warn('[FileUpload] No uploadUrl configured.');
          return null;
        }
        try {
          showProgress(container, 0);
          const response = await uploadFile(selected, opts, container);
          if (typeof opts.onSuccess === 'function') opts.onSuccess(response);
          return response;
        } catch (errMsg) {
          showError(container, errMsg);
          if (typeof opts.onError === 'function') opts.onError(errMsg);
          return null;
        }
      },

      /** Reset the dropzone to initial state. */
      reset: () => {
        selected = null;
        resetPreview(container);
      },

      /** Returns the currently selected File or null. */
      getFile: () => selected,

      /** Append selected file to an existing FormData object. */
      appendToFormData: (formData) => {
        if (selected) formData.append(opts.inputName, selected);
        return formData;
      },
    };
  }

  // ── CSS Injection (scoped to fu- namespace) ───────────────────

  function injectStyles() {
    if (document.getElementById('fu-styles')) return;

    const style = document.createElement('style');
    style.id = 'fu-styles';
    style.textContent = `
      .fu-dropzone {
        border: 2px dashed var(--gray-border, #E5E7EB);
        border-radius: var(--radius-md, 12px);
        padding: 28px 20px;
        text-align: center;
        cursor: pointer;
        background: var(--light-bg, #F9FAFB);
        transition: border-color 0.25s, background 0.25s, box-shadow 0.25s;
        outline: none;
        position: relative;
      }
      .fu-dropzone:focus-visible {
        box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
      }
      .fu-dropzone--dragging {
        border-color: var(--primary-blue, #2563EB);
        background: var(--lighter-blue, #EFF6FF);
        box-shadow: 0 0 0 4px rgba(37,99,235,0.12);
      }
      .fu-dropzone--has-file {
        border-style: solid;
        border-color: var(--primary-blue, #2563EB);
        background: var(--lighter-blue, #EFF6FF);
      }
      .fu-dropzone--error { border-color: var(--danger-red, #DC2626) !important; }
      .fu-dropzone--success { border-color: var(--success-green, #10B981) !important; }

      .fu-idle-icon { font-size: 38px; color: var(--primary-blue, #2563EB); margin-bottom: 10px; opacity: 0.7; }
      .fu-idle-title { font-size: 14px; font-weight: 600; color: var(--gray-darker, #111827); margin-bottom: 4px; }
      .fu-idle-sub   { font-size: 13px; color: var(--gray-text, #6B7280); }
      .fu-idle-hint  { font-size: 11px; color: var(--gray-text, #6B7280); margin-top: 6px; }
      .fu-browse-link { color: var(--primary-blue, #2563EB); font-weight: 600; text-decoration: underline; cursor: pointer; }

      .fu-preview {
        display: flex; align-items: center; gap: 14px;
        text-align: left; padding: 4px 0;
      }
      .fu-preview-thumb {
        width: 52px; height: 52px; border-radius: 8px;
        background: var(--white, #fff);
        border: 1px solid var(--gray-border, #E5E7EB);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; overflow: hidden;
      }
      .fu-thumb-img { width: 100%; height: 100%; object-fit: cover; }
      .fu-thumb-icon { font-size: 24px; color: var(--primary-blue, #2563EB); }
      .fu-preview-info { flex: 1; min-width: 0; }
      .fu-preview-name {
        font-size: 13px; font-weight: 600; color: var(--gray-darker, #111827);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        max-width: 100%;
      }
      .fu-preview-size { font-size: 11px; color: var(--gray-text, #6B7280); margin-top: 3px; }
      .fu-remove-btn {
        width: 28px; height: 28px; border-radius: 50%;
        border: none; background: var(--danger-light, #FEF2F2);
        color: var(--danger-red, #DC2626); cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px; flex-shrink: 0;
        transition: background 0.2s;
      }
      .fu-remove-btn:hover { background: #FECACA; }

      .fu-progress { margin-top: 12px; text-align: left; }
      .fu-progress-bar-wrap {
        height: 6px; background: var(--gray-border, #E5E7EB);
        border-radius: 10px; overflow: hidden; margin-bottom: 6px;
      }
      .fu-progress-bar {
        height: 100%; background: var(--primary-blue, #2563EB);
        border-radius: 10px;
        transition: width 0.3s ease;
      }
      .fu-progress-label { font-size: 12px; color: var(--gray-text, #6B7280); }

      .fu-error {
        display: flex; align-items: center; gap: 8px;
        margin-top: 10px; padding: 9px 12px;
        background: var(--danger-light, #FEF2F2);
        border: 1px solid #FECACA; border-radius: 8px;
        font-size: 12px; color: var(--danger-red, #DC2626);
        text-align: left;
        animation: slideUp 0.3s ease;
      }

      @keyframes slideUp {
        from { opacity:0; transform:translateY(6px); }
        to   { opacity:1; transform:translateY(0); }
      }
    `;
    document.head.appendChild(style);
  }

  return { init };

})();

// Auto-initialize dropzones declared in HTML with data-file-upload attribute
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-file-upload]').forEach(el => {
    const id   = el.id || ('fu-' + Math.random().toString(36).slice(2, 8));
    el.id      = id;

    const opts = {
      inputName    : el.dataset.inputName    || 'file',
      maxSizeMB    : parseFloat(el.dataset.maxSizeMb || '5'),
      uploadUrl    : el.dataset.uploadUrl    || null,
      autoUpload   : el.dataset.autoUpload   === 'true',
      csrfToken    : document.querySelector('meta[name="csrf-token"]')?.content || '',
      allowedExts  : (el.dataset.allowedExts  || 'jpg,jpeg,png,pdf,doc,docx').split(',').map(s => s.trim()),
      allowedTypes : (el.dataset.allowedTypes || 'image/jpeg,image/png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document').split(',').map(s => s.trim()),
    };

    FileUpload.init('#' + id, opts);
  });
});

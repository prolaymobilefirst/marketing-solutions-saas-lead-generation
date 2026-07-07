'use strict';

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('submit', (e) => {
      if (!window.confirm(el.dataset.confirm)) {
        e.preventDefault();
      }
    });
  });

  initRichTextEditors();
  initContentBlocks();
  initMediaPicker();
});

const RICHTEXT_BUTTONS = [
  { cmd: 'bold', label: 'Gras', title: 'Gras' },
  { cmd: 'italic', label: 'Italique', title: 'Italique' },
  { cmd: 'formatBlock', value: 'h2', label: 'H2', title: 'Titre H2' },
  { cmd: 'formatBlock', value: 'h3', label: 'H3', title: 'Titre H3' },
  { cmd: 'formatBlock', value: 'p', label: '¶', title: 'Paragraphe' },
  { cmd: 'insertUnorderedList', label: '• Liste', title: 'Liste à puces' },
  { cmd: 'insertOrderedList', label: '1. Liste', title: 'Liste numérotée' },
  { cmd: 'formatBlock', value: 'blockquote', label: '" Citation', title: 'Citation' },
  { cmd: 'createLink', label: 'Lien', title: 'Insérer un lien' },
  { cmd: 'unlink', label: 'Suppr. lien', title: 'Supprimer le lien' },
  { cmd: 'removeFormat', label: 'Effacer', title: 'Effacer la mise en forme' },
  { cmd: 'undo', label: '↺', title: 'Annuler' },
  { cmd: 'redo', label: '↻', title: 'Rétablir' },
];

function initRichTextEditors() {
  document.querySelectorAll('textarea[data-richtext]').forEach((textarea) => {
    if (textarea.dataset.richtextInit) return; // re-run safely when blocks are added dynamically
    textarea.dataset.richtextInit = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'richtext-field';

    const toolbar = document.createElement('div');
    toolbar.className = 'richtext-toolbar';
    toolbar.innerHTML = RICHTEXT_BUTTONS.map((btn) => {
      const value = btn.value ? ` data-value="${btn.value}"` : '';
      return `<button type="button" data-cmd="${btn.cmd}"${value} title="${btn.title}">${btn.label}</button>`;
    }).join('') + '<span class="richtext-sep"></span>'
      + '<button type="button" data-action="toggleSource" title="Basculer entre l\'éditeur visuel et le code source">&lt;/&gt; Code source</button>';

    const editable = document.createElement('div');
    editable.className = 'richtext-editable';
    editable.contentEditable = 'true';
    editable.innerHTML = textarea.value;

    textarea.hidden = true;

    textarea.insertAdjacentElement('beforebegin', wrapper);
    wrapper.appendChild(toolbar);
    wrapper.appendChild(editable);
    wrapper.appendChild(textarea);

    let sourceMode = false;
    const syncTextareaFromEditable = () => { textarea.value = editable.innerHTML.trim(); };

    editable.addEventListener('input', syncTextareaFromEditable);
    editable.addEventListener('blur', syncTextareaFromEditable);

    toolbar.addEventListener('click', (e) => {
      const button = e.target.closest('button');
      if (!button) return;
      e.preventDefault();

      if (button.dataset.action === 'toggleSource') {
        sourceMode = !sourceMode;
        toolbar.classList.toggle('source-active', sourceMode);
        if (sourceMode) {
          syncTextareaFromEditable();
          editable.hidden = true;
          textarea.hidden = false;
          button.innerHTML = '&#128065; Aperçu visuel';
          textarea.focus();
        } else {
          editable.innerHTML = textarea.value;
          textarea.hidden = true;
          editable.hidden = false;
          button.innerHTML = '&lt;/&gt; Code source';
          editable.focus();
        }
        return;
      }

      if (sourceMode) return;

      const cmd = button.dataset.cmd;
      editable.focus();
      if (cmd === 'createLink') {
        const url = window.prompt('URL du lien :', 'https://');
        if (!url) return;
        document.execCommand('createLink', false, url);
      } else if (cmd === 'formatBlock') {
        document.execCommand('formatBlock', false, `<${button.dataset.value}>`);
      } else {
        document.execCommand(cmd, false);
      }
      syncTextareaFromEditable();
    });

    const form = textarea.closest('form');
    if (form) {
      form.addEventListener('submit', () => {
        if (!sourceMode) syncTextareaFromEditable();
      });
    }
  });
}

/* ═══════════════════════════════════════════
   BLOG ARTICLE BODY — DYNAMIC CONTENT BLOCKS
   Lets an admin compose an article body as an ordered list of text/image/
   video blocks instead of one HTML blob. Each block gets a unique `key`
   used only to group its form fields together (`blocks[key][field]`);
   reordering moves the row's DOM position, not its key, and PHP rebuilds
   the array in that new DOM order when the form posts.
═══════════════════════════════════════════ */
const CONTENT_BLOCK_LABELS = { text: 'Texte', image: 'Image', video: 'Vidéo YouTube' };
let contentBlockKeySeq = 0;

function nextContentBlockKey() {
  return 'b' + Date.now().toString(36) + (contentBlockKeySeq++);
}

function escapeForBlockHtml(str) {
  return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function escapeForBlockAttr(str) {
  return escapeForBlockHtml(str).replace(/"/g, '&quot;');
}

function buildContentBlockRow(type, data) {
  data = data || {};
  const key = nextContentBlockKey();
  const row = document.createElement('div');
  row.className = 'content-block';
  row.dataset.type = type;

  let fields = `<input type="hidden" name="blocks[${key}][type]" value="${type}" />`;
  if (type === 'text') {
    fields += `<textarea name="blocks[${key}][html]" data-richtext>${escapeForBlockHtml(data.html)}</textarea>`;
  } else if (type === 'image') {
    fields += `
      <div class="admin-field">
        <label>Image</label>
        <div class="media-field-row">
          <input type="text" name="blocks[${key}][src]" value="${escapeForBlockAttr(data.src)}" list="media-files" placeholder="assets/images/exemple.webp" />
          <button type="button" class="admin-btn secondary" data-role="browse-image">Parcourir…</button>
        </div>
      </div>
      <div class="admin-field">
        <label>Texte alternatif (accessibilité)</label>
        <input type="text" name="blocks[${key}][alt]" value="${escapeForBlockAttr(data.alt)}" />
      </div>`;
  } else if (type === 'video') {
    fields += `
      <div class="admin-field">
        <label>URL ou ID YouTube</label>
        <input type="text" name="blocks[${key}][youtubeUrl]" value="${escapeForBlockAttr(data.youtubeId)}" placeholder="https://www.youtube.com/watch?v=..." />
      </div>`;
  }

  row.innerHTML = `
    <div class="content-block-head">
      <span class="content-block-label">${CONTENT_BLOCK_LABELS[type] || type}</span>
      <div class="content-block-controls">
        <button type="button" data-action="move-up" title="Monter">&uarr;</button>
        <button type="button" data-action="move-down" title="Descendre">&darr;</button>
        <button type="button" data-action="remove" title="Supprimer ce bloc">&times; Supprimer</button>
      </div>
    </div>
    <div class="content-block-fields">${fields}</div>
  `;
  return row;
}

function initContentBlocks() {
  const container = document.getElementById('content-blocks');
  if (!container) return;

  let initialBlocks = [];
  try {
    initialBlocks = JSON.parse(container.dataset.initialBlocks || '[]');
  } catch {
    initialBlocks = [];
  }
  initialBlocks.forEach((block) => {
    if (block && block.type) container.appendChild(buildContentBlockRow(block.type, block));
  });
  initRichTextEditors();

  document.querySelectorAll('[data-add-block]').forEach((btn) => {
    btn.addEventListener('click', () => {
      container.appendChild(buildContentBlockRow(btn.dataset.addBlock, {}));
      initRichTextEditors();
    });
  });

  container.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    e.preventDefault();
    const row = btn.closest('.content-block');
    if (!row) return;
    if (btn.dataset.action === 'remove') {
      row.remove();
    } else if (btn.dataset.action === 'move-up') {
      const prev = row.previousElementSibling;
      if (prev) container.insertBefore(row, prev);
    } else if (btn.dataset.action === 'move-down') {
      const next = row.nextElementSibling;
      if (next) container.insertBefore(next, row);
    }
  });
}

/* ═══════════════════════════════════════════
   MEDIA PICKER — thumbnail browser for image content blocks
   The `list="media-files"` datalist on the src input is a plain text
   autocomplete; this adds an actual visual "Parcourir…" picker over the
   same file list (embedded server-side as JSON, no extra request).
═══════════════════════════════════════════ */
function initMediaPicker() {
  const overlay = document.getElementById('media-picker-overlay');
  const grid = document.getElementById('media-picker-grid');
  const closeBtn = document.getElementById('media-picker-close');
  const dataEl = document.getElementById('media-files-data');
  if (!overlay || !grid || !closeBtn || !dataEl) return;

  let files = [];
  try {
    files = JSON.parse(dataEl.textContent || '[]');
  } catch {
    files = [];
  }

  grid.innerHTML = files.map((name) => {
    const path = 'assets/images/' + name;
    return `<button type="button" class="admin-media-item" data-path="${escapeForBlockAttr(path)}">
      <img src="${escapeForBlockAttr(path)}" alt="" loading="lazy" />
      <span class="path">${escapeForBlockHtml(name)}</span>
    </button>`;
  }).join('');

  let targetInput = null;
  const closePicker = () => { overlay.hidden = true; targetInput = null; };

  document.addEventListener('click', (e) => {
    const browseBtn = e.target.closest('[data-role="browse-image"]');
    if (!browseBtn) return;
    e.preventDefault();
    const fieldsWrap = browseBtn.closest('.content-block-fields');
    targetInput = fieldsWrap ? fieldsWrap.querySelector('input[name$="[src]"]') : null;
    overlay.hidden = false;
  });

  grid.addEventListener('click', (e) => {
    const item = e.target.closest('.admin-media-item');
    if (!item || !targetInput) return;
    targetInput.value = item.dataset.path;
    targetInput.dispatchEvent(new Event('input', { bubbles: true }));
    closePicker();
  });

  closeBtn.addEventListener('click', closePicker);
  overlay.addEventListener('click', (e) => { if (e.target === overlay) closePicker(); });
}

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

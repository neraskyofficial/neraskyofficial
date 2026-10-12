(() => {
  const formatSize = (bytes) => {
    if (bytes < 1024) return `${bytes} B`;
    const units = ['KB', 'MB', 'GB'];
    let size = bytes / 1024;
    let unit = 0;
    while (size >= 1024 && unit < units.length - 1) {
      size /= 1024;
      unit += 1;
    }
    return `${size.toFixed(size >= 10 ? 0 : 1)} ${units[unit]}`;
  };

  document.querySelectorAll('input[type="file"]').forEach((input, index) => {
    if (input.classList.contains('hidden') || input.closest('.dropzone, .msg-attach-label')) return;

    input.classList.add('file-picker-control');
    if (input.hasAttribute('data-file-label')) return;

    const status = document.createElement('span');
    status.className = 'file-picker-status';
    status.id = input.id ? `${input.id}-file-status` : `file-picker-status-${index}`;
    status.setAttribute('aria-live', 'polite');
    status.textContent = input.multiple ? 'Choose one or more files' : 'Choose a file to upload';
    input.insertAdjacentElement('afterend', status);

    const describedBy = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    if (!describedBy.includes(status.id)) describedBy.push(status.id);
    input.setAttribute('aria-describedby', describedBy.join(' '));

    input.addEventListener('change', () => {
      const files = Array.from(input.files || []);
      const totalSize = files.reduce((total, file) => total + file.size, 0);
      const hasFiles = files.length > 0;
      input.classList.toggle('has-files', hasFiles);
      status.classList.toggle('has-files', hasFiles);
      if (!hasFiles) {
        status.textContent = input.multiple ? 'Choose one or more files' : 'Choose a file to upload';
      } else if (files.length === 1) {
        status.textContent = `${files[0].name} · ${formatSize(totalSize)}`;
      } else {
        status.textContent = `${files.length} files selected · ${formatSize(totalSize)}`;
      }
    });
  });
})();

(function () {
  const TYPE_MAP = {
    success: { icon: 'bi-check-circle-fill', label: 'Thành công' },
    danger: { icon: 'bi-x-octagon-fill', label: 'Có lỗi' },
    error: { icon: 'bi-x-octagon-fill', label: 'Có lỗi' },
    warning: { icon: 'bi-exclamation-triangle-fill', label: 'Cần kiểm tra' },
    info: { icon: 'bi-info-circle-fill', label: 'Thông báo' }
  };

  function toastContainer() {
    let container = document.getElementById('appToastContainer');
    if (container) return container;
    container = document.createElement('div');
    container.id = 'appToastContainer';
    container.className = 'app-toast-container';
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-atomic', 'true');
    document.body.appendChild(container);
    return container;
  }

  function removeToast(toast) {
    if (!toast || toast.dataset.closing === '1') return;
    toast.dataset.closing = '1';
    toast.classList.add('is-closing');
    window.setTimeout(() => toast.remove(), 180);
  }

  window.showToast = function (message, type = 'info', options = {}) {
    const normalizedType = type === 'error' ? 'danger' : (TYPE_MAP[type] ? type : 'info');
    const config = TYPE_MAP[normalizedType];
    const duration = Number(options.duration) || (normalizedType === 'danger' ? 6500 : 4500);
    const toast = document.createElement('div');
    toast.className = `app-toast app-toast-${normalizedType}`;
    toast.setAttribute('role', normalizedType === 'danger' ? 'alert' : 'status');

    const icon = document.createElement('span');
    icon.className = 'app-toast-icon';
    icon.innerHTML = `<i class="bi ${config.icon}"></i>`;
    const content = document.createElement('div');
    content.className = 'app-toast-content';
    const title = document.createElement('strong');
    title.textContent = options.title || config.label;
    const body = document.createElement('div');
    body.textContent = String(message || '');
    content.append(title, body);
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'app-toast-close';
    close.setAttribute('aria-label', 'Đóng thông báo');
    close.innerHTML = '<i class="bi bi-x-lg"></i>';
    close.addEventListener('click', () => removeToast(toast));
    const progress = document.createElement('span');
    progress.className = 'app-toast-progress';
    progress.style.animationDuration = `${duration}ms`;
    toast.append(icon, content, close, progress);
    toastContainer().appendChild(toast);

    const overflow = Math.max(0, toastContainer().children.length - 4);
    Array.from(toastContainer().children).slice(0, overflow).forEach(removeToast);
    window.setTimeout(() => removeToast(toast), duration);
    return toast;
  };

  function showQueuedToasts() {
    document.querySelectorAll('script[data-app-toasts]').forEach(node => {
      if (node.dataset.processed === '1') return;
      node.dataset.processed = '1';
      try {
        const rows = JSON.parse(node.textContent || '[]');
        (Array.isArray(rows) ? rows : [rows]).forEach(row => {
          if (row && row.message) window.showToast(row.message, row.type || 'info', row);
        });
      } catch (_) {}
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showQueuedToasts, { once: true });
  } else {
    showQueuedToasts();
  }
})();

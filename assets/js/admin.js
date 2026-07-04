/**
 * ملف جافاسكريبت الخاص بلوحة التحكم
 */

document.addEventListener('DOMContentLoaded', function () {
    initRippleEffect();
    initSidebarToggle();
    initModals();
    initTabs();
    initUploadDropzone();
    initSortablePhotos();
    initPasswordToggle();
    initDeleteConfirmations();
    initAutoCloseAlerts();
    initImagePreview();
});

/**
 * تأثير الموجة عند النقر
 */
function initRippleEffect() {
    document.querySelectorAll('.btn, .action-icon-btn, .sidebar-nav-item').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const rect = btn.getBoundingClientRect();
            const ripple = document.createElement('span');
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.className = 'ripple-effect';
            ripple.style.cssText = `position:absolute;border-radius:50%;background:rgba(255,255,255,0.35);
                transform:scale(0);animation:rippleAnim 0.6s linear;pointer-events:none;
                width:${size}px;height:${size}px;left:${x}px;top:${y}px;`;

            btn.style.position = btn.style.position || 'relative';
            btn.style.overflow = btn.style.overflow || 'hidden';
            btn.appendChild(ripple);

            setTimeout(function () { ripple.remove(); }, 600);
        });
    });

    if (!document.getElementById('rippleKeyframes')) {
        const style = document.createElement('style');
        style.id = 'rippleKeyframes';
        style.textContent = '@keyframes rippleAnim { to { transform: scale(3); opacity: 0; } }';
        document.head.appendChild(style);
    }
}

/**
 * فتح/إغلاق الشريط الجانبي في الجوال
 */
function initSidebarToggle() {
    const toggleBtn = document.querySelector('.mobile-sidebar-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (!toggleBtn || !sidebar) return;

    function openSidebar() {
        sidebar.classList.add('open');
        overlay?.classList.add('active');
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay?.classList.remove('active');
    }

    toggleBtn.addEventListener('click', openSidebar);
    overlay?.addEventListener('click', closeSidebar);
}

/**
 * النوافذ المنبثقة (Modals)
 */
function initModals() {
    document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            const modalId = trigger.getAttribute('data-modal-open');
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add('active');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.modal-overlay')?.classList.remove('active');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('active');
        });
    });
}

/**
 * نظام التبويبات
 */
function initTabs() {
    document.querySelectorAll('.tabs').forEach(function (tabGroup) {
        const buttons = tabGroup.querySelectorAll('.tab-btn');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const target = btn.getAttribute('data-tab');
                const container = tabGroup.parentElement;

                buttons.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');

                container.querySelectorAll('.tab-content').forEach(function (content) {
                    content.classList.remove('active');
                });
                document.getElementById(target)?.classList.add('active');
            });
        });
    });
}

/**
 * منطقة السحب والإفلات لرفع الصور
 */
function initUploadDropzone() {
    const dropzone = document.querySelector('.upload-dropzone');
    const fileInput = document.getElementById('photoFileInput');
    const previewGrid = document.querySelector('.upload-preview-grid');

    if (!dropzone || !fileInput) return;

    dropzone.addEventListener('click', function () {
        fileInput.click();
    });

    ['dragenter', 'dragover'].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        });
    });

    dropzone.addEventListener('drop', function (e) {
        const files = e.dataTransfer.files;
        fileInput.files = files;
        handleFilePreview(files);
    });

    fileInput.addEventListener('change', function () {
        handleFilePreview(fileInput.files);
    });

    function handleFilePreview(files) {
        if (!previewGrid) return;
        previewGrid.innerHTML = '';

        Array.from(files).forEach(function (file) {
            if (!file.type.startsWith('image/')) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                const item = document.createElement('div');
                item.className = 'upload-preview-item';
                item.innerHTML = `<img src="${e.target.result}" alt="${file.name}">`;
                previewGrid.appendChild(item);
            };
            reader.readAsDataURL(file);
        });

        const countLabel = document.getElementById('selectedFilesCount');
        if (countLabel) {
            countLabel.textContent = files.length > 0 ? files.length + ' ملف تم اختياره' : '';
        }
    }
}

/**
 * ترتيب الصور بالسحب والإفلات (Sortable) - تنفيذ خفيف بدون مكتبات خارجية
 */
function initSortablePhotos() {
    const grid = document.querySelector('.manage-photos-grid[data-sortable]');
    if (!grid) return;

    let draggedItem = null;

    grid.querySelectorAll('.manage-photo-item').forEach(function (item) {
        item.setAttribute('draggable', 'true');

        item.addEventListener('dragstart', function () {
            draggedItem = item;
            setTimeout(function () { item.style.opacity = '0.4'; }, 0);
        });

        item.addEventListener('dragend', function () {
            item.style.opacity = '1';
            draggedItem = null;
            saveNewOrder(grid);
        });

        item.addEventListener('dragover', function (e) {
            e.preventDefault();
        });

        item.addEventListener('drop', function (e) {
            e.preventDefault();
            if (draggedItem && draggedItem !== item) {
                const items = Array.from(grid.children);
                const draggedIndex = items.indexOf(draggedItem);
                const targetIndex = items.indexOf(item);

                if (draggedIndex < targetIndex) {
                    item.after(draggedItem);
                } else {
                    item.before(draggedItem);
                }
            }
        });
    });

    function saveNewOrder(grid) {
        const orderedIds = Array.from(grid.querySelectorAll('.manage-photo-item')).map(function (item) {
            return item.getAttribute('data-photo-id');
        });

        fetch('ajax/reorder-photos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order: orderedIds,
                csrf_token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            })
        }).then(function (res) { return res.json(); })
          .then(function (data) {
              if (data.success) {
                  showToast('تم تحديث ترتيب الصور بنجاح', 'success');
              }
          })
          .catch(function () {
              showToast('حدث خطأ أثناء حفظ الترتيب', 'danger');
          });
    }
}

/**
 * إظهار/إخفاء كلمة المرور
 */
function initPasswordToggle() {
    document.querySelectorAll('.password-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const input = toggle.parentElement.querySelector('input');
            const icon = toggle.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });
}

/**
 * تأكيد قبل عمليات الحذف
 */
function initDeleteConfirmations() {
    document.querySelectorAll('[data-confirm-delete]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const message = link.getAttribute('data-confirm-delete') || 'هل أنت متأكد من الحذف؟ لا يمكن التراجع عن هذا الإجراء.';
            const targetUrl = link.getAttribute('href') || link.getAttribute('data-href');

            showConfirmModal(message, function () {
                if (link.tagName === 'A') {
                    window.location.href = targetUrl;
                } else if (link.tagName === 'BUTTON' && link.closest('form')) {
                    link.closest('form').submit();
                }
            });
        });
    });
}

function showConfirmModal(message, onConfirm) {
    let modal = document.getElementById('globalConfirmModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'globalConfirmModal';
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-box">
                <div class="modal-header">
                    <span class="modal-title"><i class="fa-solid fa-triangle-exclamation" style="color:#e0525f;margin-left:8px;"></i>تأكيد الإجراء</span>
                </div>
                <p id="confirmModalMessage" style="color:var(--color-silver-dim);font-size:0.92rem;"></p>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="confirmModalCancel">إلغاء</button>
                    <button type="button" class="btn btn-danger" id="confirmModalOk">تأكيد الحذف</button>
                </div>
            </div>`;
        document.body.appendChild(modal);
    }

    document.getElementById('confirmModalMessage').textContent = message;
    modal.classList.add('active');

    const okBtn = document.getElementById('confirmModalOk');
    const cancelBtn = document.getElementById('confirmModalCancel');

    const newOkBtn = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(newOkBtn, okBtn);

    newOkBtn.addEventListener('click', function () {
        modal.classList.remove('active');
        onConfirm();
    });

    cancelBtn.addEventListener('click', function () {
        modal.classList.remove('active');
    });
}

/**
 * إغلاق تلقائي للتنبيهات
 */
function initAutoCloseAlerts() {
    document.querySelectorAll('.alert[data-autoclose]').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function () { alert.remove(); }, 500);
        }, 4000);
    });
}

/**
 * معاينة الصورة الرمزية أو الشعار قبل الرفع
 */
function initImagePreview() {
    document.querySelectorAll('[data-preview-target]').forEach(function (input) {
        input.addEventListener('change', function () {
            const targetId = input.getAttribute('data-preview-target');
            const target = document.getElementById(targetId);
            if (!target || !input.files[0]) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                target.innerHTML = `<img src="${e.target.result}" alt="معاينة">`;
            };
            reader.readAsDataURL(input.files[0]);
        });
    });
}

/**
 * إظهار رسالة توست عائمة
 */
function showToast(message, type) {
    type = type || 'success';
    const toast = document.createElement('div');
    toast.className = 'alert alert-' + type;
    toast.style.cssText = 'position:fixed;top:24px;left:50%;transform:translateX(-50%);z-index:9999;box-shadow:0 10px 30px rgba(0,0,0,0.5);min-width:280px;';

    const icons = {
        success: 'fa-circle-check',
        danger: 'fa-circle-exclamation',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };

    toast.innerHTML = `<i class="fa-solid ${icons[type] || icons.success}"></i> ${message}`;
    document.body.appendChild(toast);

    setTimeout(function () {
        toast.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(-15px)';
        setTimeout(function () { toast.remove(); }, 400);
    }, 3500);
}

/**
 * نسخ نص إلى الحافظة (مثل رابط الألبوم)
 */
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(function () {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i>';
        showToast('تم نسخ الرابط بنجاح', 'success');
        setTimeout(function () {
            btn.innerHTML = originalHtml;
        }, 1500);
    });
}

/**
 * عداد الأحرف لحقول النصوص
 */
document.querySelectorAll('[data-char-count]').forEach(function (input) {
    const maxLength = input.getAttribute('maxlength');
    const counter = document.getElementById(input.getAttribute('data-char-count'));
    if (!counter || !maxLength) return;

    function updateCount() {
        counter.textContent = input.value.length + ' / ' + maxLength;
    }
    input.addEventListener('input', updateCount);
    updateCount();
});

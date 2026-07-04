/**
 * الملف الرئيسي لجافاسكريبت - التأثيرات والتفاعلات
 */

document.addEventListener('DOMContentLoaded', function () {
    initRippleEffect();
    initMobileMenu();
    initPasswordToggle();
    initLightbox();
    initFilterChips();
    initScrollHeader();
    initFadeInOnScroll();
});

/**
 * تأثير الموجة (Ripple) عند النقر على الأزرار
 */
function initRippleEffect() {
    document.querySelectorAll('.btn, .filter-chip, .action-icon-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const rect = btn.getBoundingClientRect();
            const ripple = document.createElement('span');
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.className = 'ripple-effect';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';

            btn.style.position = btn.style.position || 'relative';
            btn.style.overflow = 'hidden';
            btn.appendChild(ripple);

            setTimeout(function () {
                ripple.remove();
            }, 600);
        });
    });
}

/**
 * فتح وإغلاق قائمة الجوال
 */
function initMobileMenu() {
    const toggleBtn = document.querySelector('.mobile-menu-btn');
    const nav = document.querySelector('.main-nav');

    if (!toggleBtn || !nav) return;

    toggleBtn.addEventListener('click', function () {
        nav.classList.toggle('open');
        const icon = toggleBtn.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        }
    });

    document.querySelectorAll('.main-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            nav.classList.remove('open');
        });
    });
}

/**
 * إظهار/إخفاء كلمة المرور في حقول الإدخال
 */
function initPasswordToggle() {
    document.querySelectorAll('.password-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const input = toggle.parentElement.querySelector('input');
            const icon = toggle.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
}

/**
 * الصندوق الضوئي لعرض الصور (Lightbox)
 */
let currentPhotoIndex = 0;
let galleryPhotos = [];

function initLightbox() {
    const photoItems = document.querySelectorAll('.photo-item[data-full]');
    if (photoItems.length === 0) return;

    galleryPhotos = Array.from(photoItems).map(function (item) {
        return {
            full: item.getAttribute('data-full'),
            title: item.getAttribute('data-title') || '',
            id: item.getAttribute('data-photo-id') || ''
        };
    });

    photoItems.forEach(function (item, index) {
        item.addEventListener('click', function () {
            openLightbox(index);
        });
    });

    const lightbox = document.getElementById('lightbox');
    if (!lightbox) return;

    document.getElementById('lightboxClose')?.addEventListener('click', closeLightbox);
    document.getElementById('lightboxPrev')?.addEventListener('click', function () { navigateLightbox(-1); });
    document.getElementById('lightboxNext')?.addEventListener('click', function () { navigateLightbox(1); });

    lightbox.addEventListener('click', function (e) {
        if (e.target === lightbox) closeLightbox();
    });

    document.addEventListener('keydown', function (e) {
        if (!lightbox.classList.contains('active')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') navigateLightbox(1);
        if (e.key === 'ArrowRight') navigateLightbox(-1);
    });
}

function openLightbox(index) {
    currentPhotoIndex = index;
    const lightbox = document.getElementById('lightbox');
    updateLightboxContent();
    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    lightbox.classList.remove('active');
    document.body.style.overflow = '';
}

function navigateLightbox(direction) {
    currentPhotoIndex = (currentPhotoIndex + direction + galleryPhotos.length) % galleryPhotos.length;
    updateLightboxContent();
}

function updateLightboxContent() {
    const photo = galleryPhotos[currentPhotoIndex];
    const img = document.getElementById('lightboxImage');
    const counter = document.getElementById('lightboxCounter');

    if (img) {
        img.style.opacity = '0';
        setTimeout(function () {
            img.src = photo.full;
            img.alt = photo.title;
            img.style.opacity = '1';
        }, 150);
    }

    if (counter) {
        counter.textContent = (currentPhotoIndex + 1) + ' / ' + galleryPhotos.length;
    }

    const downloadBtn = document.getElementById('lightboxDownload');
    if (downloadBtn && photo.id) {
        downloadBtn.setAttribute('href', 'download.php?id=' + encodeURIComponent(photo.id));
    }
}

/**
 * فلترة الألبومات حسب التصنيف (بدون إعادة تحميل الصفحة إن أمكن)
 */
function initFilterChips() {
    document.querySelectorAll('.filter-chip[data-filter]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            document.querySelectorAll('.filter-chip').forEach(function (c) {
                c.classList.remove('active');
            });
            chip.classList.add('active');
        });
    });
}

/**
 * تأثير تصغير الهيدر عند التمرير
 */
function initScrollHeader() {
    const header = document.querySelector('.site-header');
    if (!header) return;

    let lastScroll = 0;
    window.addEventListener('scroll', function () {
        const currentScroll = window.pageYOffset;
        if (currentScroll > 60) {
            header.style.boxShadow = '0 4px 20px rgba(0,0,0,0.4)';
        } else {
            header.style.boxShadow = 'none';
        }
        lastScroll = currentScroll;
    });
}

/**
 * تأثير الظهور التدريجي عند التمرير للعناصر
 */
function initFadeInOnScroll() {
    const items = document.querySelectorAll('.album-card, .photo-item');
    if (items.length === 0) return;

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry, i) {
            if (entry.isIntersecting) {
                setTimeout(function () {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, i * 40);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    items.forEach(function (item) {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(item);
    });
}

/**
 * إغلاق تلقائي لرسائل التنبيه بعد فترة
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert[data-autoclose]').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function () { alert.remove(); }, 500);
        }, 4000);
    });
});

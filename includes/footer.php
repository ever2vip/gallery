</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-social">
            <a href="#" title="فيسبوك"><i class="fa-brands fa-facebook-f"></i></a>
            <a href="#" title="انستقرام"><i class="fa-brands fa-instagram"></i></a>
            <a href="#" title="تويتر"><i class="fa-brands fa-twitter"></i></a>
            <a href="#" title="واتساب"><i class="fa-brands fa-whatsapp"></i></a>
        </div>
        <p><?= e(getSetting('footer_text', '© ' . date('Y') . ' معرض الصور - جميع الحقوق محفوظة')) ?></p>
    </div>
</footer>

<!-- الصندوق الضوئي لعرض الصور -->
<div class="lightbox" id="lightbox">
    <div class="lightbox-content">
        <img src="" alt="" id="lightboxImage">
    </div>
    <button class="lightbox-close" id="lightboxClose"><i class="fa-solid fa-xmark"></i></button>
    <button class="lightbox-prev" id="lightboxPrev"><i class="fa-solid fa-chevron-right"></i></button>
    <button class="lightbox-next" id="lightboxNext"><i class="fa-solid fa-chevron-left"></i></button>
    <?php if (getSetting('allow_downloads', '1') === '1'): ?>
    <a class="lightbox-close" id="lightboxDownload" href="#" style="top:24px;left:84px;" title="تحميل الصورة الأصلية"><i class="fa-solid fa-download"></i></a>
    <?php endif; ?>
    <div class="lightbox-counter" id="lightboxCounter"></div>
</div>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>

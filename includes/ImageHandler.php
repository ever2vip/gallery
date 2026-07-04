<?php
/**
 * فئة معالجة رفع الصور وإنشاء الصور المصغرة والعلامة المائية
 * Image Handling Class
 */

class ImageHandler
{
    private string $uploadDir;
    private array $errors = [];

    public function __construct(string $uploadDir = UPLOAD_DIR)
    {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
    }

    /**
     * معالجة رفع صورة واحدة، إرجاع مصفوفة بيانات الصورة أو null عند الفشل
     */
    public function handleUpload(array $file, int $albumId): ?array
    {
        $this->errors = [];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = 'حدث خطأ أثناء رفع الملف: ' . $this->getUploadErrorMessage($file['error']);
            return null;
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            $this->errors[] = 'حجم الملف "' . $file['name'] . '" أكبر من الحد المسموح (10 ميجابايت)';
            return null;
        }

        if (!isValidImageExtension($file['name'])) {
            $this->errors[] = 'امتداد الملف "' . $file['name'] . '" غير مسموح';
            return null;
        }

        // التحقق الفعلي من أن الملف صورة صالحة (وليس فقط بامتداد صورة)
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $this->errors[] = 'الملف "' . $file['name'] . '" ليس صورة صالحة';
            return null;
        }

        $albumPath = $this->uploadDir . 'albums/' . $albumId . '/';
        $thumbPath = $albumPath . 'thumbs/';

        if (!is_dir($albumPath)) {
            mkdir($albumPath, 0755, true);
        }
        if (!is_dir($thumbPath)) {
            mkdir($thumbPath, 0755, true);
        }

        $filename = generateUniqueFilename($file['name']);
        $destination = $albumPath . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->errors[] = 'فشل نقل الملف "' . $file['name'] . '"';
            return null;
        }

        // إنشاء صورة مصغرة
        $thumbFilename = 'thumb_' . $filename;
        $this->createThumbnail($destination, $thumbPath . $thumbFilename, THUMB_WIDTH, THUMB_HEIGHT, $imageInfo[2]);

        return [
            'filename' => 'albums/' . $albumId . '/' . $filename,
            'thumbnail' => 'albums/' . $albumId . '/thumbs/' . $thumbFilename,
            'original_name' => $file['name'],
            'file_size' => $file['size'],
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
        ];
    }

    /**
     * إنشاء صورة مصغرة بأبعاد محددة مع الحفاظ على النسبة (قص من المنتصف)
     */
    private function createThumbnail(string $source, string $destination, int $targetWidth, int $targetHeight, int $imageType): bool
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $sourceImage = @imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = @imagecreatefrompng($source);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = @imagecreatefromgif($source);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = @imagecreatefromwebp($source);
                break;
            default:
                return false;
        }

        if (!$sourceImage) {
            return false;
        }

        $srcWidth = imagesx($sourceImage);
        $srcHeight = imagesy($sourceImage);

        // حساب أبعاد القص للحفاظ على نسبة العرض للارتفاع المطلوبة
        $srcRatio = $srcWidth / $srcHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($srcRatio > $targetRatio) {
            $cropHeight = $srcHeight;
            $cropWidth = (int) ($srcHeight * $targetRatio);
            $cropX = (int) (($srcWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            $cropWidth = $srcWidth;
            $cropHeight = (int) ($srcWidth / $targetRatio);
            $cropX = 0;
            $cropY = (int) (($srcHeight - $cropHeight) / 2);
        }

        $thumbImage = imagecreatetruecolor($targetWidth, $targetHeight);

        // الحفاظ على الشفافية لملفات PNG و GIF
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
            imagecolortransparent($thumbImage, imagecolorallocatealpha($thumbImage, 0, 0, 0, 127));
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
        }

        imagecopyresampled(
            $thumbImage, $sourceImage,
            0, 0, $cropX, $cropY,
            $targetWidth, $targetHeight, $cropWidth, $cropHeight
        );

        $result = false;
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($thumbImage, $destination, 85);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($thumbImage, $destination, 8);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($thumbImage, $destination);
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($thumbImage, $destination, 85);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($thumbImage);

        return $result;
    }

    /**
     * إضافة علامة مائية نصية على صورة (اختياري، يُفعّل من الإعدادات)
     */
    public function addWatermark(string $imagePath, string $watermarkText): bool
    {
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return false;
        }

        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($imagePath);
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $textColor = imagecolorallocatealpha($image, 255, 255, 255, 60);
        $fontSize = max(12, (int) ($width / 40));

        // استخدام خط النظام الافتراضي المدمج إن لم يتوفر ملف TTF
        $fontPath = __DIR__ . '/../assets/fonts/Cairo-Bold.ttf';
        if (file_exists($fontPath)) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $watermarkText);
            $textWidth = abs($bbox[4] - $bbox[0]);
            $x = $width - $textWidth - 20;
            $y = $height - 20;
            imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $watermarkText);
        } else {
            $x = $width - (strlen($watermarkText) * 10) - 20;
            $y = $height - 30;
            imagestring($image, 5, $x, $y, $watermarkText, $textColor);
        }

        $result = imagejpeg($image, $imagePath, 90);
        imagedestroy($image);

        return $result;
    }

    /**
     * حذف ملفات صورة (الأصلية والمصغرة) من القرص
     */
    public function deletePhotoFiles(string $filename, string $thumbnail): void
    {
        $filePath = $this->uploadDir . $filename;
        $thumbPath = $this->uploadDir . $thumbnail;

        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        if (file_exists($thumbPath)) {
            @unlink($thumbPath);
        }
    }

    /**
     * حذف مجلد ألبوم كامل مع كل الصور بداخله
     */
    public function deleteAlbumDirectory(int $albumId): void
    {
        $albumPath = $this->uploadDir . 'albums/' . $albumId;
        if (is_dir($albumPath)) {
            $this->recursiveDelete($albumPath);
        }
    }

    private function recursiveDelete(string $dir): void
    {
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->recursiveDelete($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'الملف أكبر من الحد المسموح في إعدادات الخادم',
            UPLOAD_ERR_FORM_SIZE => 'الملف أكبر من الحد المسموح في النموذج',
            UPLOAD_ERR_PARTIAL => 'تم رفع جزء من الملف فقط',
            UPLOAD_ERR_NO_FILE => 'لم يتم اختيار ملف',
            UPLOAD_ERR_NO_TMP_DIR => 'المجلد المؤقت غير موجود',
            UPLOAD_ERR_CANT_WRITE => 'فشل الكتابة على القرص',
            UPLOAD_ERR_EXTENSION => 'توقف الرفع بسبب امتداد PHP',
        ];
        return $messages[$errorCode] ?? 'خطأ غير معروف';
    }
}

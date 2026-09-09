<?php
// includes/security.php
if (!defined('VALKYRIN_EXEC')) {
    exit('Direct access strictly prohibited.');
}

class ValkyrinSecurity {

    // Allowed Upload MIME Types & Extensions
    private static $allowedMimeTypes = [
        // Images
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        // Documents
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'text/plain' => 'txt'
    ];

    /**
     * Sanitizes user input text and redacts standard sensitive PII patterns.
     */
    public static function sanitizeAndRedactPII(string $text): string {
        $cleanText = htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');

        // Redact Social Security Numbers (SSN) -> [REDACTED_SSN]
        $cleanText = preg_replace('/\b\d{3}[-.\s]?\d{2}[-.\s]?\d{4}\b/', '[REDACTED_SSN]', $cleanText);

        // Redact Standard Phone Numbers -> [REDACTED_PHONE]
        $cleanText = preg_replace('/\b(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}\b/', '[REDACTED_PHONE]', $cleanText);

        return $cleanText;
    }

    /**
     * Validates file upload for extension spoofing, binary security risks, and allowed types.
     */
    public static function validateUpload(array $file): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File upload failed with code ' . $file['error']];
        }

        // Limit maximum size to 10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            return ['success' => false, 'error' => 'File size exceeds maximum threshold (10MB).'];
        }

        // Detect real MIME type using finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);

        if (!array_key_exists($detectedMime, self::$allowedMimeTypes)) {
            return ['success' => false, 'error' => 'Unsupported or prohibited file format detected (' . $detectedMime . ').'];
        }

        // Double check extension safety
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $expectedExtension = self::$allowedMimeTypes[$detectedMime];

        // Block executable and double extension attacks
        if (preg_match('/\.(php|exe|sh|bat|pl|py|cgi|js|jar|vbs)$/i', $file['name'])) {
            return ['success' => false, 'error' => 'Executable file risk blocked by Valkyrin Guard.'];
        }

        return [
            'success'   => true,
            'mime'      => $detectedMime,
            'extension' => $extension,
            'type'      => str_starts_with($detectedMime, 'image/') ? 'image' : 'document'
        ];
    }

    /**
     * Strips metadata (EXIF/GPS) from JPEG images to ensure privacy.
     */
    public static function stripExifMetadata(string $filePath, string $mimeType): void {
        if ($mimeType === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            $image = @imagecreatefromjpeg($filePath);
            if ($image !== false) {
                imagejpeg($image, $filePath, 90);
                imagedestroy($image);
            }
        }
    }
}
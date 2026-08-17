<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/attachments.php

const ATTACHMENT_MAX_SIZE      = 5 * 1024 * 1024; // 5 MB
const ATTACHMENT_ALLOWED_MIMES = [
    // Bilder
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    // Dokumente
    'application/pdf'          => 'pdf',
    'text/plain'               => 'txt',
    'application/zip'          => 'zip',
    'application/x-rar-compressed' => 'rar',
];

function attachment_dir(): string {
    $d = UPLOAD_DIR . 'attachments/';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

function attachment_url(string $filename): string {
    return FORUM_URL . '/uploads/attachments/' . rawurlencode($filename);
}

function process_attachments(int $postId, int $userId): int {
    if (empty($_FILES['attachments']['name'][0])) return 0;
    $files = $_FILES['attachments'];
    $count = 0;

    foreach ($files['name'] as $i => $name) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($files['size'][$i] > ATTACHMENT_MAX_SIZE) continue;

        $mime = mime_content_type($files['tmp_name'][$i]);
        if (!isset(ATTACHMENT_ALLOWED_MIMES[$mime])) continue;

        $ext     = ATTACHMENT_ALLOWED_MIMES[$mime];
        $newName = 'att_' . $postId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

        if (move_uploaded_file($files['tmp_name'][$i], attachment_dir() . $newName)) {
            $isImg = str_starts_with($mime, 'image/');
            try {
                db_execute(
                    'INSERT INTO attachments (post_id, user_id, filename, original_name, mime_type, size_bytes, is_image)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$postId, $userId, $newName, substr($name, 0, 255), $mime, $files['size'][$i], $isImg ? 1 : 0]
                );
                $count++;
            } catch (Exception $e) {
                @unlink(attachment_dir() . $newName);
            }
        }
    }
    return $count;
}

function post_attachments(int $postId): array {
    try {
        return db_fetchAll('SELECT * FROM attachments WHERE post_id = ? ORDER BY attachment_id', [$postId]);
    } catch (Exception $e) { return []; }
}

function format_filesize(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1024 / 1024, 1) . ' MB';
}

/**
 * Loescht die Anhaenge von Beitraegen — Dateien UND Datenbankzeilen.
 *
 * Ohne das blieben die Dateien nach dem Loeschen eines Beitrags im
 * Upload-Ordner liegen und waren unter ihrer Adresse weiter abrufbar. Der
 * Beitrag war weg, sein Bild nicht — wer die Adresse einmal hatte (oder sie
 * ueber die Zeilen in der Datenbank fand), kam weiter daran.
 *
 * @param int[] $postIds
 * @return int Zahl der geloeschten Dateien
 */
function delete_attachments_for_posts(array $postIds): int {
    $postIds = array_values(array_unique(array_map('intval', $postIds)));
    if (!$postIds) return 0;

    $platzhalter = implode(',', array_fill(0, count($postIds), '?'));
    $weg = 0;
    try {
        $reihen = db_fetchAll(
            "SELECT attachment_id, filename FROM attachments WHERE post_id IN ($platzhalter)",
            $postIds
        );
        foreach ($reihen as $r) {
            $pfad = attachment_dir() . $r['filename'];
            // realpath schuetzt davor, dass ein manipulierter Dateiname aus
            // dem Ordner herausfuehrt.
            $echt = realpath($pfad);
            $ordner = realpath(attachment_dir());
            if ($echt && $ordner && str_starts_with($echt, $ordner) && is_file($echt)) {
                if (@unlink($echt)) $weg++;
            }
        }
        db_execute("DELETE FROM attachments WHERE post_id IN ($platzhalter)", $postIds);
    } catch (Throwable $e) {
        error_log('[attachments] Loeschen fehlgeschlagen: ' . $e->getMessage());
    }
    return $weg;
}

/**
 * Dasselbe fuer ein ganzes Thema.
 */
function delete_attachments_for_thread(int $threadId): int {
    try {
        $ids = array_column(
            db_fetchAll('SELECT post_id FROM posts WHERE thread_id = ?', [$threadId]),
            'post_id'
        );
    } catch (Throwable $e) { return 0; }
    return delete_attachments_for_posts($ids);
}

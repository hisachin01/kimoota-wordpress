<?php
/**
 * Plugin Name: Force WebP Server-Side Converter (Upload Only)
 * Description: 100% reliable server-side conversion for NEW uploads only. No JavaScript, no DOM scanning.
 * Author: Internal
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ---- パス・画質設定 ----
if (!defined('FORCE_WEBP_CWEBP_PATH')) {
    define('FORCE_WEBP_CWEBP_PATH', '/usr/local/bin/cwebp');
}
if (!defined('FORCE_WEBP_JPEG_QUALITY')) {
    define('FORCE_WEBP_JPEG_QUALITY', 80);
}
if (!defined('FORCE_WEBP_PNG_QUALITY')) {
    define('FORCE_WEBP_PNG_QUALITY', 80);
}
if (!defined('FORCE_WEBP_MAX_WIDTH')) {
    define('FORCE_WEBP_MAX_WIDTH', 1200);
}


// ---- 新規アップロード時のフック（メタデータ生成時） ----
add_filter('wp_generate_attachment_metadata', 'force_webp_backend_upload_converter', 10, 2);

function force_webp_backend_upload_converter($metadata, $attachment_id) {
    $post = get_post($attachment_id);
    if (!$post || $post->post_type !== 'attachment') {
        return $metadata;
    }

    // 既にWebP、または対象外のMIMEタイプは完全スルー
    $mime = $post->post_mime_type;
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        return $metadata;
    }

    $file = get_attached_file($attachment_id);
    if (!$file || !file_exists($file)) {
        return $metadata;
    }

    if (!file_exists(FORCE_WEBP_CWEBP_PATH)) {
        error_log('[Force WebP] cwebp binary not found at: ' . FORCE_WEBP_CWEBP_PATH);
        return $metadata;
    }

    $base_dir = dirname($file);
    $files_to_delete = [];

    // 1. 標準エディタで元のJPG/PNGを最大幅1200pxに比例リサイズ
    $editor = wp_get_image_editor($file);
    if (!is_wp_error($editor)) {
        $sizes = $editor->get_size();
        $width = $sizes['width'];
        $height = $sizes['height'];

        if ($width > FORCE_WEBP_MAX_WIDTH) {
            $editor->resize(FORCE_WEBP_MAX_WIDTH, null, false);
            $saved = $editor->save($file);
            if (!is_wp_error($saved)) {
                $new_sizes = $editor->get_size();
                $width = $new_sizes['width'];
                $height = $new_sizes['height'];
            }
        }
    } else {
        return $metadata;
    }

    // 2. フルサイズ画像をcwebpでWebPに変換
    $dest_file = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
    if (!$dest_file || $dest_file === $file) {
        return $metadata;
    }

    $tmp_dest_file = $dest_file . '.tmp';
    $quality = ($mime === 'image/png') ? FORCE_WEBP_PNG_QUALITY : FORCE_WEBP_JPEG_QUALITY;
    $extra = ($mime === 'image/jpeg') ? '-af' : '';

    $cmd = sprintf(
        '%s -q %d -m 6 -mt %s %s -o %s 2>&1',
        escapeshellcmd(FORCE_WEBP_CWEBP_PATH),
        $quality,
        $extra,
        escapeshellarg($file),
        escapeshellarg($tmp_dest_file)
    );

    exec($cmd, $output, $ret);

    if ($ret !== 0 || !@rename($tmp_dest_file, $dest_file)) {
        @unlink($tmp_dest_file);
        error_log('[Force WebP] Conversion failed: ' . implode(' | ', $output));
        return $metadata;
    }

    // 元のフルサイズ画像を削除リストへ
    $files_to_delete[] = $file;

    // メタデータの初期化・再構築
    if (!is_array($metadata)) {
        $metadata = [];
    }
    $metadata['width']  = $width;
    $metadata['height'] = $height;
    
    $relative_path = _wp_relative_upload_path($dest_file);
    if (!empty($relative_path)) {
        $metadata['file'] = $relative_path;
    }

    // 3. すでに生成されている中間サイズ（サムネイル等）も追従してWebP化
    if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
        foreach ($metadata['sizes'] as $size_name => &$size_data) {
            if (empty($size_data['file'])) continue;

            $old_size_file = $base_dir . '/' . $size_data['file'];
            if (!file_exists($old_size_file)) continue;

            $size_mime = (string) @mime_content_type($old_size_file);
            if (!in_array($size_mime, ['image/jpeg', 'image/png'], true)) continue;

            $new_size_file = preg_replace('/\.(jpe?g|png)$/i', '.webp', $old_size_file);
            $tmp_size_file = $new_size_file . '.tmp';
            $size_quality = ($size_mime === 'image/png') ? FORCE_WEBP_PNG_QUALITY : FORCE_WEBP_JPEG_QUALITY;
            $size_extra = ($size_mime === 'image/jpeg') ? '-af' : '';

            $size_cmd = sprintf(
                '%s -q %d -m 6 -mt %s %s -o %s 2>&1',
                escapeshellcmd(FORCE_WEBP_CWEBP_PATH),
                $size_quality,
                $size_extra,
                escapeshellarg($old_size_file),
                escapeshellarg($tmp_size_file)
            );

            exec($size_cmd, $size_output, $size_ret);

            if ($size_ret === 0 && @rename($tmp_size_file, $new_size_file)) {
                $size_data['file'] = basename($new_size_file);
                $size_data['mime-type'] = 'image/webp';
                $size_data['filesize'] = filesize($new_size_file);
                $files_to_delete[] = $old_size_file;
            } else {
                @unlink($tmp_size_file);
            }
        }
        unset($size_data);
    }

    // 4. データベース（DB）側の情報をWebPに完全書き換え
    $metadata['filesize'] = filesize($dest_file);
    
    // フルサイズパス情報をDBに同期
    update_attached_file($attachment_id, $dest_file);

    // postsテーブルのMIMEタイプを強制上書き
    global $wpdb;
    $wpdb->update(
        $wpdb->posts,
        ['post_mime_type' => 'image/webp'],
        ['ID' => $attachment_id]
    );

    // 5. 元画像（JPG/PNG）の物理削除クリーンアップ
    foreach (array_unique($files_to_delete) as $del_file) {
        if (file_exists($del_file) && $del_file !== $dest_file) {
            @unlink($del_file);
        }
    }

    wp_cache_delete($attachment_id, 'posts');

    // 変更済みのメタデータをWordPressに返す
    return $metadata;
}
<?php
/**
 * PagHiper Countdown Secure Proxy
 *
 * This script serves a pre-generated static GIF based on a timestamp.
 * It reads the file from the filesystem and outputs its content directly
 * to ensure compatibility with email clients that block redirects.
 */

// --- 1. Bootstrap WordPress to get its functions ---
$wp_load_path = __DIR__ . '/../../../../../../wp-load.php';
if ( file_exists( $wp_load_path ) ) {
    require_once $wp_load_path;
} else {
    header("HTTP/1.1 500 Internal Server Error");
    error_log('PagHiper Countdown: Could not find wp-load.php. Please check the path.');
    // Serve a 1x1 transparent GIF as a fallback
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

// --- 2. Validate Input ---
if ( ! isset( $_GET['order_due_time'] ) || ! is_numeric( $_GET['order_due_time'] ) ) {
    header("HTTP/1.1 400 Bad Request");
    // Serve a 1x1 transparent GIF as a fallback
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

$timestamp = (int) $_GET['order_due_time'];
$current_time = time();
$remaining_seconds = max(0, $timestamp - $current_time);

// --- 3. Determine the correct GIF file path (filesystem path) ---
$upload_dir = wp_upload_dir();
$base_path = $upload_dir['basedir'] . '/paghiper-timers/';

// The downloaded assets are organized into folders 0-23, representing hours.
$bundle_index = floor( $remaining_seconds / 3600 );

// The filenames are countdown_{total_seconds}.gif.
$filename = 'countdown_' . $remaining_seconds . '.gif';

// If the time has expired, point to the zero-second GIF.
if ($remaining_seconds <= 0) {
    $bundle_index = 0;
    $filename = 'countdown_0.gif';
}

// Check for a _plus suffix file for compatibility with older logic.
$plus_filename = 'countdown_' . $remaining_seconds . '_plus.gif';
if(file_exists($base_path . $bundle_index . '/' . $plus_filename)) {
    $filename = $plus_filename;
}

$file_path = $base_path . $bundle_index . '/' . $filename;

// --- 4. Serve the file or a fallback ---
if ( file_exists( $file_path ) ) {
    header('Content-Type: image/gif');
    header('Content-Length: ' . filesize( $file_path ));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile( $file_path );
    exit;
} else {
    // Log the missing file for debugging purposes
    $log_message = sprintf(
        'PagHiper Countdown: GIF file not found. Requested timestamp: %s, Calculated path: %s',
        isset($_GET['order_due_time']) ? $_GET['order_due_time'] : 'not_set',
        $file_path
    );
    error_log($log_message);

    // Serve a 1x1 transparent GIF if the specific countdown file is not found.
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}
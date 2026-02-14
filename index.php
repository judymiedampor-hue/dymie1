<?php
// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Increase memory limit for image processing
ini_set('memory_limit', '256M');

// Configuration
 $uploadDir = 'uploads/';
 $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
 $maxFileSize = 5 * 1024 * 1024; // 5MB

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Create thumbs directory if it doesn't exist
if (!is_dir($uploadDir . 'thumbs/')) {
    mkdir($uploadDir . 'thumbs/', 0777, true);
}

// --- FUNCTION TO DELETE ALL FILES (Global Scope) ---
function deleteAllFiles($dir) {
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    deleteAllFiles($path);
                    @rmdir($path);
                } else {
                    @unlink($path);
                }
            }
        }
    }
}

// --- AJAX HANDLER: WIPE GALLERY (Triggered by JS on new tab) ---
if (isset($_GET['action']) && $_GET['action'] == 'wipe_gallery') {
    deleteAllFiles($uploadDir);
    echo json_encode(['status' => 'success']);
    exit;
}
// -----------------------------------------------------------------

// --- HANDLE SAVE IMAGE (AJAX APPLY) ---
if (isset($_POST['save_image_data']) && isset($_POST['image_filename'])) {
    $filename = basename($_POST['image_filename']);
    $filepath = $uploadDir . $filename;
    
    if (file_exists($filepath)) {
        $imgData = $_POST['save_image_data'];
        
        if (preg_match('/^data:image\/(\w+);base64,/', $imgData, $type)) {
            $imgData = substr($imgData, strpos($imgData, ',') + 1);
            $type = strtolower($type[1]);
            $data = base64_decode($imgData);
            
            if (file_put_contents($filepath, $data)) {
                createThumbnail($filepath, $uploadDir . 'thumbs/' . $filename, 300, 300);
                echo json_encode(['status' => 'success', 'msg' => 'Image updated successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Failed to overwrite file.']);
            }
        } else {
             echo json_encode(['status' => 'error', 'msg' => 'Invalid image data format.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Original file not found.']);
    }
    exit;
}

// --- HANDLE FILE UPLOAD ---
 $message = "";
 $messageType = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileType = mime_content_type($file['tmp_name']);
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($fileType, $allowedTypes)) {
            if ($file['size'] <= $maxFileSize) {
                $newFileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExt;
                $destination = $uploadDir . $newFileName;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    createThumbnail($destination, $uploadDir . 'thumbs/' . $newFileName, 300, 300);
                    $message = "Image uploaded successfully!";
                    $messageType = "success";
                } else {
                    $message = "Failed to save uploaded file.";
                    $messageType = "danger";
                }
            } else {
                $message = "File size exceeds 5MB limit.";
                $messageType = "danger";
            }
        } else {
            $message = "Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.";
            $messageType = "danger";
        }
    } elseif ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $message = "Please select a file to upload.";
        $messageType = "warning";
    } elseif ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $message = "File is too large (Server limit).";
        $messageType = "danger";
    } else {
        $message = "Error uploading file. Code: " . $file['error'];
        $messageType = "danger";
    }
}

// --- HANDLE IMAGE DELETE ---
if (isset($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $filepath = $uploadDir . $filename;
    $thumbpath = $uploadDir . 'thumbs/' . $filename;
    
    if (file_exists($filepath) && unlink($filepath)) {
        if (file_exists($thumbpath)) {
            unlink($thumbpath);
        }
        $message = "Image deleted successfully!";
        $messageType = "success";
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

// --- THUMBNAIL CREATION FUNCTION ---
function createThumbnail($src, $dest, $targetWidth, $targetHeight) {
    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0777, true);
    }
    
    $info = @getimagesize($src);
    if (!$info) return false;
    
    list($width, $height, $type) = $info;
    
    switch ($type) {
        case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG: $source = imagecreatefrompng($src); break;
        case IMAGETYPE_GIF: $source = imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $source = imagecreatefromwebp($src); break;
        default: return false;
    }
    
    $srcAspect = $width / $height;
    $destAspect = $targetWidth / $targetHeight;
    
    if ($srcAspect > $destAspect) {
        $tempHeight = $targetHeight;
        $tempWidth = (int)($targetHeight * $srcAspect);
    } else {
        $tempWidth = $targetWidth;
        $tempHeight = (int)($targetWidth / $srcAspect);
    }
    
    $temp = imagecreatetruecolor($tempWidth, $tempHeight);
    
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($temp, imagecolorallocatealpha($temp, 0, 0, 0, 127));
        imagealphablending($temp, false);
        imagesavealpha($temp, true);
    }
    
    imagecopyresampled($temp, $source, 0, 0, 0, 0, $tempWidth, $tempHeight, $width, $height);
    
    $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
    
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }
    
    $x = (int)(($tempWidth - $targetWidth) / 2);
    $y = (int)(($tempHeight - $targetHeight) / 2);
    imagecopy($thumbnail, $temp, 0, 0, $x, $y, $targetWidth, $targetHeight);
    
    imagejpeg($thumbnail, $dest, 85);
    
    imagedestroy($source);
    imagedestroy($temp);
    imagedestroy($thumbnail);
    
    return true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rosé Gallery | Professional Image Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --blush: #fff1f3;
            --rose-gold: #f7cac9;
            --dusty-rose: #d4a5a5;
            --mauve: #b5838d;
            --wine: #6d4c5c;
            --deep-rose: #4a2c3a;
            --pink-glow: #ffb6c1;
            --pink-soft: #ffe4e1;
            --pink-light: #fff5f5;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --dark-bg: #1a0f14;
            --light-text: #fff5f5;
            --border-radius: 24px;
            --box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            --transition: all 0.3s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #1a0f14 0%, #2d1a24 50%, #3d2430 100%);
            color: var(--light-text);
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 30%, rgba(255, 182, 193, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(181, 131, 141, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 40% 80%, rgba(255, 228, 225, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }
        
        .container-fluid {
            max-width: 1400px;
            background: rgba(26, 15, 20, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            padding: 35px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .container-fluid::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #f7cac9, #d4a5a5, #b5838d, #6d4c5c);
            z-index: 1;
        }
        
        .header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 25px;
            margin-bottom: 30px;
            position: relative;
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 150px;
            height: 2px;
            background: linear-gradient(90deg, #f7cac9, transparent);
            border-radius: 2px;
        }
        
        .logo {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 2.4rem;
            background: linear-gradient(135deg, #f7cac9, #d4a5a5, #b5838d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo i {
            font-size: 2.2rem;
            color: #f7cac9;
            -webkit-text-fill-color: #f7cac9;
            filter: drop-shadow(0 0 15px rgba(247, 202, 201, 0.5));
        }
        
        .tagline {
            color: rgba(255, 255, 255, 0.6);
            font-weight: 300;
            font-size: 1rem;
            margin-top: 5px;
            letter-spacing: 0.5px;
        }
        
        .badge-rose {
            background: rgba(247, 202, 201, 0.15);
            border: 1px solid rgba(247, 202, 201, 0.3);
            color: #f7cac9;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }
        
        .upload-card {
            background: linear-gradient(145deg, rgba(247, 202, 201, 0.05), rgba(181, 131, 141, 0.02));
            border: 2px dashed rgba(247, 202, 201, 0.3);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 40px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .upload-card:hover {
            border-color: #f7cac9;
            background: linear-gradient(145deg, rgba(247, 202, 201, 0.08), rgba(181, 131, 141, 0.03));
            box-shadow: 0 15px 30px rgba(247, 202, 201, 0.1);
        }
        
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            border-radius: 16px;
        }
        
        .file-upload-button {
            background: linear-gradient(145deg, #f7cac9, #d4a5a5);
            color: #2d1a24;
            padding: 14px 32px;
            border-radius: 16px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 20px rgba(247, 202, 201, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .file-upload-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(247, 202, 201, 0.3);
            background: linear-gradient(145deg, #ffc1c0, #e0b0b0);
        }
        
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .gallery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 10px 22px;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(247, 202, 201, 0.2);
            color: rgba(255, 255, 255, 0.8);
            transition: var(--transition);
            font-size: 0.9rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }
        
        .filter-btn:hover {
            background: rgba(247, 202, 201, 0.15);
            border-color: #f7cac9;
            color: #f7cac9;
            transform: translateY(-2px);
        }
        
        .filter-btn.active {
            background: linear-gradient(145deg, #f7cac9, #d4a5a5);
            color: #2d1a24;
            border-color: transparent;
            box-shadow: 0 8px 16px rgba(247, 202, 201, 0.2);
            font-weight: 600;
        }
        
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }
        
        .image-card {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
            position: relative;
            border: 1px solid rgba(247, 202, 201, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .image-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 30px rgba(247, 202, 201, 0.15);
            border-color: rgba(247, 202, 201, 0.3);
        }
        
        .image-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #f7cac9, #d4a5a5, #b5838d);
            z-index: 2;
        }
        
        .image-thumbnail {
            width: 100%;
            height: 260px;
            object-fit: cover;
            display: block;
            transition: transform 0.6s ease;
        }
        
        .image-card:hover .image-thumbnail {
            transform: scale(1.05);
        }
        
        .image-info {
            padding: 20px;
            position: relative;
            z-index: 1;
            background: linear-gradient(180deg, transparent, rgba(0, 0, 0, 0.2));
        }
        
        .image-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 1rem;
        }
        
        .image-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 15px;
        }
        
        .badge-type {
            background: rgba(247, 202, 201, 0.15);
            color: #f7cac9;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .image-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            flex: 1;
            padding: 8px 12px;
            border-radius: 10px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 0;
            backdrop-filter: blur(10px);
            border: 1px solid transparent;
            cursor: pointer;
        }
        
        .btn-view {
            background: rgba(247, 202, 201, 0.1);
            color: #f7cac9;
            border-color: rgba(247, 202, 201, 0.2);
        }
        
        .btn-view:hover {
            background: #f7cac9;
            color: #2d1a24;
            border-color: #f7cac9;
            transform: translateY(-2px);
        }
        
        .btn-edit {
            background: rgba(212, 165, 165, 0.1);
            color: #d4a5a5;
            border-color: rgba(212, 165, 165, 0.2);
        }
        
        .btn-edit:hover {
            background: #d4a5a5;
            color: #2d1a24;
            border-color: #d4a5a5;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: rgba(181, 131, 141, 0.1);
            color: #b5838d;
            border-color: rgba(181, 131, 141, 0.2);
        }
        
        .btn-delete:hover {
            background: #b5838d;
            color: #2d1a24;
            border-color: #b5838d;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
            background: rgba(247, 202, 201, 0.03);
            border-radius: var(--border-radius);
            border: 2px dashed rgba(247, 202, 201, 0.2);
        }
        
        .empty-icon {
            font-size: 5rem;
            color: rgba(247, 202, 201, 0.2);
            margin-bottom: 20px;
        }
        
        .modal-content {
            border-radius: 20px;
            border: none;
            overflow: hidden;
            background: rgba(26, 15, 20, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(247, 202, 201, 0.2);
        }
        
        .modal-header {
            background: linear-gradient(145deg, rgba(247, 202, 201, 0.15), rgba(181, 131, 141, 0.15));
            color: #f7cac9;
            border-bottom: 1px solid rgba(247, 202, 201, 0.2);
            padding: 20px 30px;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }
        
        .modal-header .btn-close:hover {
            opacity: 1;
        }
        
        .modal-body {
            padding: 0;
        }
        
        .modal-footer {
            border-top: 1px solid rgba(247, 202, 201, 0.1);
            background: rgba(0, 0, 0, 0.2);
            padding: 15px 20px;
        }
        
        .image-preview-container {
            background: rgba(0, 0, 0, 0.3);
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 500px;
            position: relative;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(247, 202, 201, 0.2);
        }
        
        .effect-controls {
            padding: 25px;
            background: rgba(0, 0, 0, 0.2);
            border-left: 1px solid rgba(247, 202, 201, 0.1);
            height: 100%;
        }
        
        .effect-slider {
            width: 100%;
            margin: 8px 0;
            background: rgba(247, 202, 201, 0.1);
            border-radius: 10px;
            height: 6px;
            -webkit-appearance: none;
        }
        
        .effect-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            background: #f7cac9;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(247, 202, 201, 0.5);
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(247, 202, 201, 0.15);
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.03);
            padding: 25px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(247, 202, 201, 0.1);
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            border-color: rgba(247, 202, 201, 0.3);
            background: rgba(247, 202, 201, 0.05);
        }
        
        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            background: linear-gradient(145deg, #f7cac9, #d4a5a5);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
            font-family: 'Playfair Display', serif;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
            margin-top: 5px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .footer {
            text-align: center;
            padding: 30px 0 0;
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.9rem;
            border-top: 1px solid rgba(247, 202, 201, 0.1);
            margin-top: 40px;
        }
        
        .btn-primary-rose {
            background: linear-gradient(145deg, #f7cac9, #d4a5a5);
            border: none;
            color: #2d1a24;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 50px;
            transition: var(--transition);
            box-shadow: 0 8px 16px rgba(247, 202, 201, 0.15);
        }
        
        .btn-primary-rose:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(247, 202, 201, 0.25);
            background: linear-gradient(145deg, #ffc1c0, #e0b0b0);
        }
        
        .btn-outline-rose {
            background: transparent;
            border: 1px solid rgba(247, 202, 201, 0.3);
            color: #f7cac9;
            padding: 10px 20px;
            border-radius: 50px;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .btn-outline-rose:hover {
            border-color: #f7cac9;
            background: rgba(247, 202, 201, 0.1);
            transform: translateY(-2px);
        }
        
        .form-control-rose {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(247, 202, 201, 0.2);
            color: white;
            border-radius: 12px;
            padding: 12px 16px;
        }
        
        .form-control-rose:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #f7cac9;
            box-shadow: 0 0 0 3px rgba(247, 202, 201, 0.1);
            color: white;
        }
        
        .form-label-rose {
            color: #f7cac9;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes roseGlow {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 0.8; }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .image-card {
            animation: fadeIn 0.4s ease forwards;
        }
        
        .stat-card:hover .stat-number {
            animation: roseGlow 1.5s ease-in-out infinite;
        }
        
        @media (max-width: 768px) {
            .container-fluid { padding: 20px; }
            .image-grid { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
            .gallery-header { flex-direction: column; align-items: flex-start; }
            .filter-buttons { width: 100%; overflow-x: auto; padding-bottom: 10px; }
            .stat-number { font-size: 2.2rem; }
            .logo { font-size: 2rem; }
        }
        
        @media (max-width: 576px) {
            .image-grid { grid-template-columns: 1fr; }
            .action-btn { font-size: 0.8rem; padding: 6px 10px; }
        }
        
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(247, 202, 201, 0.05);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #f7cac9, #d4a5a5);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #ffc1c0, #e0b0b0);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="logo">
                        <i class="bi bi-flower2"></i> Dymie's Gallery
                    </h1>
                    <p class="tagline">Elegant image that bring back memories</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge-rose">
                        <i class="bi bi-images me-1"></i>
                        <?php 
                        $imageCount = 0;
                        if (is_dir($uploadDir)) {
                            $files = scandir($uploadDir);
                            foreach ($files as $file) {
                                if ($file != '.' && $file != '..') {
                                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $imageCount++;
                                }
                            }
                        }
                        echo $imageCount . ' Images';
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="upload-card">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert" style="background: rgba(247, 202, 201, 0.1); border: 1px solid rgba(247, 202, 201, 0.3); color: #f7cac9;">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <h4 class="mb-3" style="color: #f7cac9;"><i class="bi bi-cloud-upload me-2"></i>Upload New Image</h4>
            <p class="mb-4" style="color: rgba(255, 255, 255, 0.6);">Upload JPG, PNG, GIF, or WebP images up to 5MB</p>
            
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="row align-items-center g-3">
                    <div class="col-md-6">
                        <div class="file-upload-wrapper">
                            <div class="file-upload-button">
                                <i class="bi bi-folder2-open"></i> Choose Image File
                            </div>
                            <input type="file" name="image" class="file-upload-input" id="imageInput" required accept="image/*">
                        </div>
                        <div class="form-text mt-2" id="fileInfo" style="color: rgba(255, 255, 255, 0.5);">No file selected</div>
                    </div>
                    <div class="col-md-4">
                        <div class="progress d-none" id="uploadProgress" style="height: 8px; background: rgba(247, 202, 201, 0.1); border-radius: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%; background: linear-gradient(90deg, #f7cac9, #d4a5a5);"></div>
                        </div>
                    </div>
                    <div class="col-md-2 text-md-end">
                        <button type="submit" class="btn btn-primary-rose w-100">
                            <i class="bi bi-upload me-2"></i>Upload
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="gallery-header">
            <div>
                <h3 class="mb-0" style="color: white;"><i class="bi bi-grid-3x3-gap me-2" style="color: #f7cac9;"></i>Image Gallery</h3>
                <p class="mb-0" style="color: rgba(255, 255, 255, 0.6);">Manage and edit your uploaded images</p>
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter=".jpg">JPG</button>
                <button class="filter-btn" data-filter=".png">PNG</button>
                <button class="filter-btn" data-filter=".gif">GIF</button>
                <button class="filter-btn" data-filter=".webp">WebP</button>
            </div>
        </div>

        <?php
        $hasImages = false;
        if (is_dir($uploadDir)) {
            $files = scandir($uploadDir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) { $hasImages = true; break; }
                }
            }
        }
        ?>
        
        <?php if (!$hasImages): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-flower2"></i></div>
                <h4 class="mb-3" style="color: white;">No Images Yet</h4>
                <p class="mb-4" style="color: rgba(255, 255, 255, 0.6);">Upload your first image to get started</p>
                <button class="btn btn-primary-rose" onclick="document.getElementById('imageInput').click()">
                    <i class="bi bi-plus-circle me-2"></i>Upload First Image
                </button>
            </div>
        <?php else: ?>
            <div class="image-grid">
                <?php
                $files = scandir($uploadDir);
                usort($files, function($a, $b) use ($uploadDir) {
                    if ($a == '.' || $a == '..') return 1;
                    if ($b == '.' || $b == '..') return -1;
                    return filemtime($uploadDir . $b) - filemtime($uploadDir . $a);
                });
                
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..') {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $filepath = $uploadDir . $file;
                            $thumbPathRaw = $uploadDir . 'thumbs/' . $file;
                            $thumbpath = (file_exists($thumbPathRaw)) ? $thumbPathRaw . '?v=' . filemtime($thumbPathRaw) : $filepath . '?v=' . filemtime($filepath);
                            
                            $filesize = filesize($filepath);
                            $formattedSize = $filesize > 1024 * 1024 ? round($filesize/(1024*1024), 1) . ' MB' : round($filesize/1024) . ' KB';
                            $displayName = strlen($file) > 20 ? substr($file, 13, 20) . '...' : substr($file, 13);
                            ?>
                            <div class="image-card mix <?php echo $ext; ?>">
                                <img src="<?php echo $thumbpath; ?>" class="image-thumbnail" alt="<?php echo htmlspecialchars($displayName); ?>">
                                <div class="image-info">
                                    <h6 class="image-title"><?php echo htmlspecialchars($displayName); ?></h6>
                                    <div class="image-meta">
                                        <span class="badge-type"><?php echo $ext; ?></span>
                                        <span style="color: rgba(255, 255, 255, 0.5);"><?php echo $formattedSize; ?></span>
                                    </div>
                                    <div class="image-actions">
                                        <button type="button" class="action-btn btn-view" data-bs-toggle="modal" data-bs-target="#viewModal" data-image="<?php echo $filepath; ?>" data-filename="<?php echo $file; ?>">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        <button type="button" class="action-btn btn-edit" data-bs-toggle="modal" data-bs-target="#editModal" data-image="<?php echo $filepath; ?>" data-filename="<?php echo $file; ?>"><i class="bi bi-sliders"></i> Edit</button>
                                        <a href="?delete=<?php echo $file; ?>" class="action-btn btn-delete" onclick="return confirm('Delete this image?')"><i class="bi bi-trash"></i> Delete</a>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    }
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $totalSize = 0;
                    if (is_dir($uploadDir)) {
                        $files = scandir($uploadDir);
                        foreach ($files as $file) {
                            if ($file != '.' && $file != '..') {
                                $filepath = $uploadDir . $file;
                                if (file_exists($filepath)) $totalSize += filesize($filepath);
                            }
                        }
                    }
                    echo round($totalSize / (1024 * 1024), 1);
                    ?>
                </div>
                <div class="stat-label">Storage Used (MB)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $imageCount; ?></div>
                <div class="stat-label">Total Images</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">5</div>
                <div class="stat-label">Edit Effects</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">4</div>
                <div class="stat-label">File Types</div>
            </div>
        </div>

        <div class="footer">
            <p>© <?php echo date('Y'); ?> Rosé Gallery. All rights reserved.</p>
            <p class="small" style="color: rgba(255, 255, 255, 0.3);">Elegance in every pixel | Professional Image Management</p>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>View Image: <span id="viewModalFilename"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="image-preview-container">
                        <img id="viewImage" src="" class="preview-image">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary-rose w-100" data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left-circle me-2"></i> Back to Gallery
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-sliders me-2"></i>Edit Image: <span id="modalFilename"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-0">
                        <div class="col-md-8">
                            <div class="image-preview-container">
                                <img id="previewImage" src="" class="preview-image">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="effect-controls">
                                <h6 class="mb-4" style="color: #f7cac9;"><i class="bi bi-magic me-2"></i>Image Effects</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label d-flex justify-content-between form-label-rose">
                                        <span>Grayscale</span>
                                        <span id="grayscaleValue" style="color: #f7cac9;">0%</span>
                                    </label>
                                    <input type="range" class="effect-slider" id="grayscaleSlider" min="0" max="100" value="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label d-flex justify-content-between form-label-rose">
                                        <span>Brightness</span>
                                        <span id="brightnessValue" style="color: #f7cac9;">100%</span>
                                    </label>
                                    <input type="range" class="effect-slider" id="brightnessSlider" min="50" max="200" value="100">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label d-flex justify-content-between form-label-rose">
                                        <span>Contrast</span>
                                        <span id="contrastValue" style="color: #f7cac9;">100%</span>
                                    </label>
                                    <input type="range" class="effect-slider" id="contrastSlider" min="50" max="200" value="100">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label d-flex justify-content-between form-label-rose">
                                        <span>Invert Colors</span>
                                        <span id="invertValue" style="color: #f7cac9;">0%</span>
                                    </label>
                                    <input type="range" class="effect-slider" id="invertSlider" min="0" max="100" value="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label form-label-rose">Watermark Text</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-rose" id="watermarkText" placeholder="Enter watermark text" value="Rosé">
                                        <button class="btn btn-outline-rose" type="button" id="applyWatermark">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label form-label-rose">Resize Image</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="number" class="form-control form-control-rose" id="resizeWidth" placeholder="Width">
                                        </div>
                                        <div class="col-6">
                                            <input type="number" class="form-control form-control-rose" id="resizeHeight" placeholder="Height">
                                        </div>
                                    </div>
                                    <button class="btn btn-outline-rose w-100 mt-2" id="applyResize">
                                        <i class="bi bi-arrows-angle-expand me-2"></i>Apply Resize
                                    </button>
                                </div>
                                
                                <div class="d-grid gap-2 mt-4">
                                    <button class="btn btn-primary-rose" id="saveImage">
                                        <i class="bi bi-check-circle me-2"></i>Apply Changes
                                    </button>
                                    <button class="btn btn-outline-rose" id="resetEffects">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Reset All
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mixitup@3.3.1/dist/mixitup.min.js"></script>
    <script>
        // --- ITO LANG ANG BAGONG DAGDAG (5 LINES LANG) ---
        // Check if bagong tab ito
        if (!sessionStorage.getItem('gallery_tab_active')) {
            sessionStorage.setItem('gallery_tab_active', 'true');
            
            // Delete lahat ng images
            fetch('?action=wipe_gallery')
                .then(response => response.json())
                .then(data => {
                    if(data.status === 'success') {
                        location.reload(); // Auto-refresh para makita na walang laman
                    }
                });
        }
        // -------------------------------------------------

        const containerEl = document.querySelector('.image-grid');
        if (containerEl) {
            const mixer = mixitup(containerEl, {
                selectors: { target: '.image-card' },
                animation: { duration: 300, effects: 'fade translateY(-20px)' }
            });
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    mixer.filter(this.getAttribute('data-filter'));
                });
            });
        }

        const imageInput = document.getElementById('imageInput');
        const fileInfo = document.getElementById('fileInfo');
        
        imageInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                const fileSize = file.size > 1024 * 1024 ? (file.size / (1024 * 1024)).toFixed(1) + ' MB' : (file.size / 1024).toFixed(0) + ' KB';
                fileInfo.innerHTML = `<i class="bi bi-file-image" style="color: #f7cac9;"></i> ${file.name} (${fileSize})`;
                fileInfo.style.color = '#f7cac9';
            } else {
                fileInfo.innerHTML = 'No file selected';
                fileInfo.style.color = 'rgba(255, 255, 255, 0.5)';
            }
        });

        // View Modal Logic
        const viewModal = document.getElementById('viewModal');
        viewModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const imagePath = button.getAttribute('data-image');
            const filename = button.getAttribute('data-filename');
            
            document.getElementById('viewModalFilename').textContent = filename;
            document.getElementById('viewImage').src = imagePath;
        });

        // Edit Modal Logic
        const editModal = document.getElementById('editModal');
        let currentImage = null;
        let originalImage = null;
        
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const imagePath = button.getAttribute('data-image');
            const filename = button.getAttribute('data-filename');
            
            document.getElementById('modalFilename').textContent = filename;
            document.getElementById('previewImage').src = imagePath;
            currentImage = document.getElementById('previewImage');
            originalImage = new Image();
            originalImage.src = imagePath;
            
            resetAllEffects();
        });

        const sliders = ['grayscale', 'brightness', 'contrast', 'invert'];
        sliders.forEach(sliderId => {
            const slider = document.getElementById(sliderId + 'Slider');
            const valueDisplay = document.getElementById(sliderId + 'Value');
            if (slider) {
                slider.addEventListener('input', function() {
                    valueDisplay.textContent = this.value + '%';
                    applyFilters();
                });
            }
        });

        function applyFilters() {
            if (!currentImage) return;
            const grayscale = document.getElementById('grayscaleSlider').value;
            const brightness = document.getElementById('brightnessSlider').value;
            const contrast = document.getElementById('contrastSlider').value;
            const invert = document.getElementById('invertSlider').value;
            currentImage.style.filter = `grayscale(${grayscale}%) brightness(${brightness}%) contrast(${contrast}%) invert(${invert}%)`;
        }

        function resetAllEffects() {
            sliders.forEach(sliderId => {
                const slider = document.getElementById(sliderId + 'Slider');
                const valueDisplay = document.getElementById(sliderId + 'Value');
                if (slider) {
                    if (sliderId === 'brightness' || sliderId === 'contrast') {
                        slider.value = 100; valueDisplay.textContent = '100%';
                    } else {
                        slider.value = 0; valueDisplay.textContent = '0%';
                    }
                }
            });
            if (currentImage) {
                currentImage.style.filter = 'none';
                if(originalImage) currentImage.src = originalImage.src;
            }
            document.getElementById('resizeWidth').value = '';
            document.getElementById('resizeHeight').value = '';
        }

        document.getElementById('applyResize').addEventListener('click', function() {
            const wInput = document.getElementById('resizeWidth').value;
            const hInput = document.getElementById('resizeHeight').value;
            let w = currentImage.naturalWidth;
            let h = currentImage.naturalHeight;

            if (!wInput && !hInput) { w = Math.round(w * 0.5); h = Math.round(h * 0.5); }
            else if (wInput && hInput) { w = parseInt(wInput); h = parseInt(hInput); }
            else return;

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            ctx.filter = getComputedStyle(currentImage).filter;
            canvas.width = w; canvas.height = h;
            ctx.drawImage(currentImage, 0, 0, w, h);
            currentImage.src = canvas.toDataURL('image/jpeg', 0.9);
            document.getElementById('resizeWidth').value = w;
            document.getElementById('resizeHeight').value = h;
        });

        document.getElementById('applyWatermark').addEventListener('click', function() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const text = document.getElementById('watermarkText').value || 'Rosé';
            let w = currentImage.naturalWidth;
            let h = currentImage.naturalHeight;
            canvas.width = w; canvas.height = h;
            ctx.filter = getComputedStyle(currentImage).filter;
            ctx.drawImage(currentImage, 0, 0, w, h);
            
            ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
            ctx.shadowBlur = 5;
            ctx.fillStyle = 'rgba(247, 202, 201, 0.7)';
            ctx.font = 'bold 40px Playfair Display, serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'bottom';
            ctx.fillText(text, canvas.width - 30, canvas.height - 30);
            currentImage.src = canvas.toDataURL();
        });

        document.getElementById('saveImage').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
            btn.disabled = true;

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = currentImage.naturalWidth;
            canvas.height = currentImage.naturalHeight;
            ctx.filter = getComputedStyle(currentImage).filter;
            ctx.drawImage(currentImage, 0, 0);
            
            const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
            const formData = new FormData();
            formData.append('save_image_data', dataUrl);
            formData.append('image_filename', document.getElementById('modalFilename').textContent);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') window.location.reload(); 
                else { alert('Error: ' + data.msg); btn.innerHTML = originalText; btn.disabled = false; }
            })
            .catch(error => { console.error('Error:', error); alert('An error occurred while saving.'); btn.innerHTML = originalText; btn.disabled = false; });
        });

        document.getElementById('resetEffects').addEventListener('click', resetAllEffects);

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('fade-in'); });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
        document.querySelectorAll('.image-card').forEach(card => observer.observe(card));
    </script>
</body>
</html>
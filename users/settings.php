<?php
// users/settings.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$userId  = $_SESSION['user_id'];
$message = '';
$error   = '';

// Now $pdo is initialized and ready
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);;

// Decode stored JSON payloads or default to empty arrays
$socials   = json_decode($user['social_links'] ?? '{}', true) ?: [];
$favorites = json_decode($user['favorites'] ?? '{}', true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_all_settings') {
        $displayName   = trim($_POST['display_name'] ?? '');
        $bio           = trim($_POST['bio'] ?? '');
        $location      = trim($_POST['location'] ?? '');
        $relationship  = trim($_POST['relationship_status'] ?? '');
        $education     = trim($_POST['education'] ?? '');
        $occupation    = trim($_POST['occupation'] ?? '');
        $hobbies       = trim($_POST['hobbies'] ?? '');

        // Pack Social Media Links into JSON
        $socialPayload = json_encode([
            'github'    => trim($_POST['social_github'] ?? ''),
            'x'         => trim($_POST['social_x'] ?? ''),
            'linkedin'  => trim($_POST['social_linkedin'] ?? ''),
            'facebook'  => trim($_POST['social_facebook'] ?? ''),
            'tiktok'    => trim($_POST['social_tiktok'] ?? ''),
            'youtube'   => trim($_POST['social_youtube'] ?? ''),
            'twitch'    => trim($_POST['social_twitch'] ?? ''),
            'medium'    => trim($_POST['social_medium'] ?? '')
        ]);

        // Pack Media & Preferences into JSON
        $favPayload = json_encode([
            'movies'  => trim($_POST['fav_movies'] ?? ''),
            'books'   => trim($_POST['fav_books'] ?? ''),
            'songs'   => trim($_POST['fav_songs'] ?? ''),
            'games'   => trim($_POST['fav_games'] ?? ''),
            'dislikes'=> trim($_POST['dislikes'] ?? '')
        ]);

        // Media Upload Configuration
        $defaultAvatar = 'default_avatar.png';
        $defaultBanner = 'default_banner.jpg';

        $avatarName = !empty($user['avatar']) ? $user['avatar'] : $defaultAvatar;
        $bannerName = !empty($user['banner']) ? $user['banner'] : $defaultBanner;

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $uploadDir = __DIR__ . '/../uploads/profiles/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // 1) Avatar Upload Handler
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath   = $_FILES['avatar']['tmp_name'];
            $fileName      = $_FILES['avatar']['name'];
            $fileSize      = $_FILES['avatar']['size'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($fileExtension, $allowedExtensions)) {
                if ($fileSize <= 2 * 1024 * 1024) {
                    $newAvatarName = 'avatar_node_' . $userId . '_' . time() . '.' . $fileExtension;
                    if (move_uploaded_file($fileTmpPath, $uploadDir . $newAvatarName)) {
                        $avatarName = $newAvatarName;
                    } else {
                        $errors[] = "Error moving uploaded avatar to storage directory.";
                    }
                } else {
                    $errors[] = "Avatar file size exceeds max limit of 2MB.";
                }
            } else {
                $errors[] = "Invalid avatar file type. Allowed: JPG, PNG, WEBP.";
            }
        }

        // 2) Banner Upload Handler
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath   = $_FILES['banner']['tmp_name'];
            $fileName      = $_FILES['banner']['name'];
            $fileSize      = $_FILES['banner']['size'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($fileExtension, $allowedExtensions)) {
                if ($fileSize <= 4 * 1024 * 1024) {
                    $newBannerName = 'banner_node_' . $userId . '_' . time() . '.' . $fileExtension;
                    if (move_uploaded_file($fileTmpPath, $uploadDir . $newBannerName)) {
                        $bannerName = $newBannerName;
                    } else {
                        $errors[] = "Error moving uploaded banner to storage directory.";
                    }
                } else {
                    $errors[] = "Banner file size exceeds max limit of 4MB.";
                }
            } else {
                $errors[] = "Invalid banner file type. Allowed: JPG, PNG, WEBP.";
            }
        }

        // Persist Record
        $up = $pdo->prepare("
            UPDATE users SET 
                display_name = ?, bio = ?, location = ?, relationship_status = ?, 
                education = ?, occupation = ?, hobbies = ?, social_links = ?, 
                favorites = ?, avatar = ?, banner = ?
            WHERE id = ?
        ");
        $up->execute([
            $displayName, $bio, $location, $relationship, 
            $education, $occupation, $hobbies, $socialPayload, 
            $favPayload, $avatarName, $bannerName, $userId
        ]);

        $message = "All Node telemetry and parameters saved!";
        
        // Refresh local cache
        $stmt->execute([$userId]);
        $user      = $stmt->fetch(PDO::FETCH_ASSOC);
        $socials   = json_decode($user['social_links'] ?? '{}', true) ?: [];
        $favorites = json_decode($user['favorites'] ?? '{}', true) ?: [];
    }
}

// 1. INCLUDE DEFAULT SITE HEADER & NAVIGATION
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            
            <h2 class="mb-4"><i class="fa-solid fa-sliders text-danger"></i> Node Control Center</h2>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_all_settings">

                <!-- VISUAL ASSETS -->
                <div class="card bg-secondary text-white mb-4">
                    <div class="card-header fw-bold"><i class="fa-solid fa-image me-2"></i>Visual Assets</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Avatar Asset</label>
                                <input type="file" name="avatar" class="form-control bg-dark text-light border-secondary">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Banner Asset</label>
                                <input type="file" name="banner" class="form-control bg-dark text-light border-secondary">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CORE IDENTITY -->
                <div class="card bg-secondary text-white mb-4">
                    <div class="card-header fw-bold"><i class="fa-solid fa-user me-2"></i>Identity & Bio</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Display Name</label>
                                <input type="text" name="display_name" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($user['display_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location / Sector</label>
                                <input type="text" name="location" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($user['location'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bio / Transmission Signal</label>
                            <textarea name="bio" class="form-control bg-dark text-light border-secondary" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- LIFE & BACKGROUND -->
                <div class="card bg-secondary text-white mb-4">
                    <div class="card-header fw-bold"><i class="fa-solid fa-id-card me-2"></i>Life & Background</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Relationship Status</label>
                                <select name="relationship_status" class="form-select bg-dark text-light border-secondary">
                                    <option value="" <?= ($user['relationship_status'] ?? '') === '' ? 'selected' : '' ?>>Unspecified</option>
                                    <option value="Single" <?= ($user['relationship_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                                    <option value="In a Relationship" <?= ($user['relationship_status'] ?? '') === 'In a Relationship' ? 'selected' : '' ?>>In a Relationship</option>
                                    <option value="Engaged" <?= ($user['relationship_status'] ?? '') === 'Engaged' ? 'selected' : '' ?>>Engaged</option>
                                    <option value="Married" <?= ($user['relationship_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                                    <option value="Complicated" <?= ($user['relationship_status'] ?? '') === 'Complicated' ? 'selected' : '' ?>>It's Complicated</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Education / Academy</label>
                                <input type="text" name="education" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($user['education'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Occupation / Profession</label>
                                <input type="text" name="occupation" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($user['occupation'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hobbies & Passions</label>
                            <textarea name="hobbies" class="form-control bg-dark text-light border-secondary" rows="2"><?= htmlspecialchars($user['hobbies'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- SOCIAL MATRIX LINKS -->
                <div class="card bg-secondary text-white mb-4">
                    <div class="card-header fw-bold"><i class="fa-solid fa-share-nodes me-2"></i>Social Matrix Links</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-brands fa-github me-1"></i> GitHub URL</label>
                                <input type="url" name="social_github" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($socials['github'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-brands fa-x-twitter me-1"></i> X (Twitter) URL</label>
                                <input type="url" name="social_x" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($socials['x'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-brands fa-linkedin me-1"></i> LinkedIn URL</label>
                                <input type="url" name="social_linkedin" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($socials['linkedin'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-brands fa-facebook me-1"></i> Facebook URL</label>
                                <input type="url" name="social_facebook" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($socials['facebook'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-brands fa-tiktok me-1"></i> TikTok URL</label>
                                <input type="url" name="social_tiktok" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($socials['tiktok'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-brands fa-youtube me-1"></i> YouTube URL</label>
                                <input type="url" name="social_youtube" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($socials['youtube'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-brands fa-twitch me-1"></i> Twitch URL</label>
                                <input type="url" name="social_twitch" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($socials['twitch'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-brands fa-medium me-1"></i> Medium URL</label>
                                <input type="url" name="social_medium" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($socials['medium'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PREFERENCES & MEDIA MATRIX -->
                <div class="card bg-secondary text-white mb-4">
                    <div class="card-header fw-bold"><i class="fa-solid fa-compact-disc me-2"></i>Media & Personal Preferences</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-solid fa-film me-1"></i> Favorite Movies & Shows</label>
                                <textarea name="fav_movies" class="form-control bg-dark text-light border-secondary" rows="2"><?= htmlspecialchars($favorites['movies'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-solid fa-book me-1"></i> Favorite Books & Literature</label>
                                <textarea name="fav_books" class="form-control bg-dark text-light border-secondary" rows="2"><?= htmlspecialchars($favorites['books'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-solid fa-music me-1"></i> Favorite Songs & Bands</label>
                                <textarea name="fav_songs" class="form-control bg-dark text-light border-secondary" rows="2"><?= htmlspecialchars($favorites['songs'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="fa-solid fa-gamepad me-1"></i> Favorite Games</label>
                                <textarea name="fav_games" class="form-control bg-dark text-light border-secondary" rows="2"><?= htmlspecialchars($favorites['games'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="fa-solid fa-thumbs-down me-1"></i> Dislikes / Pet Peeves</label>
                            <textarea name="dislikes" class="form-control bg-dark text-light border-secondary" rows="2"><?= htmlspecialchars($favorites['dislikes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-grid mb-5">
                    <button type="submit" class="btn btn-danger btn-lg"><i class="fa-solid fa-floppy-disk me-2"></i> Save Entire Profile Configuration</button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php 
// 2. INCLUDE DEFAULT SITE FOOTER
require_once __DIR__ . '/../includes/footer.php'; 
?>
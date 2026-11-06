<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$tp = $_SESSION['tp_number'];
$errors = [];

$categories = db_all($conn, "SELECT categoryID, category_name FROM categories
    WHERE is_active = 1 ORDER BY categoryID");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim((string)($_POST['categoryID'] ?? ''));
    $name = trim((string)($_POST['device_name'] ?? ''));
    $fault = trim((string)($_POST['fault_description'] ?? ''));

    // check the form details
    $valid = in_array($category, array_column($categories, 'categoryID'), true);

    // (the CSRF token was already checked by require_role())
    if (!$valid) $errors[] = 'Select a valid category.';
    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Invalid device name.';
    if ($fault === '' || mb_strlen($fault) > 255) $errors[] = 'Invalid problem description.';
    if (($_POST['terms_accepted'] ?? '') !== '1') $errors[] = 'Please accept the terms.';

    // check the uploaded photo
    $photo = $_FILES['device_photo'] ?? null;
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $extension = '';

    if (!$photo || $photo['error'] !== UPLOAD_ERR_OK ||
        !is_uploaded_file($photo['tmp_name'])) {
        $errors[] = 'Please upload a valid photo.';
    } elseif ($photo['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Photo must be 5 MB or smaller.';
    } else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($photo['tmp_name']);
        if (!isset($types[$mime]))
            $errors[] = 'Only JPG, PNG and WebP are allowed.';
        else
            $extension = $types[$mime];
    }

    if (!$errors) {
        $folder = __DIR__ . '/../uploads/devices/';
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $path = $folder . $filename;
        $relative = 'uploads/devices/' . $filename;

        // save the photo first, then save the device
        if (!is_dir($folder) || !is_writable($folder) ||
            !move_uploaded_file($photo['tmp_name'], $path)) {
            $errors[] = 'Unable to save photo.';
        } else {
            try {
                $conn->begin_transaction();
                $id = next_id($conn, 'devices', 'deviceID', 'D');
                $hid = next_id($conn, 'device_status_history', 'historyID', 'H');

                db_exec($conn, "INSERT INTO devices
                    (deviceID, submitted_by, categoryID, device_name,
                     fault_description, photo_path, terms_accepted)
                    VALUES (?,?,?,?,?,?,1)", 'ssssss',
                    [$id, $tp, $category, $name, $fault, $relative]);

                db_exec($conn, "INSERT INTO device_status_history
                    (historyID, deviceID, changed_by, new_status)
                    VALUES (?,?,?,'Submitted')", 'sss', [$hid, $id, $tp]);

                $conn->commit();
                set_flash('success', 'Device submitted successfully!');
                redirect('index.php');

            } catch (Throwable $e) {
                $conn->rollback();
                @unlink($path);
                error_log($e->getMessage());
                $errors[] = 'Submission failed. Please try again.';
            }
        }
    }
}

$page_title = 'Submit a Device';
require '../includes/student_top.php';
?>

<div class="student-page-head">
    <span class="page-icon">➕</span>
    <div>
        <span class="student-eyebrow">STUDENT PORTAL</span>
        <h1>Submit a Device</h1>
        <p>Register your electronic device for repair or recycling.</p>
    </div>
</div>

<section class="card student-panel">
    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" class="submit-form">
        <?= csrf_field() ?>

        <div class="submit-field">
            <label for="categoryID">Device Category</label>
            <select name="categoryID" id="categoryID" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c['categoryID']) ?>"
                        <?= ($_POST['categoryID'] ?? '') === $c['categoryID'] ? 'selected' : '' ?>>
                        <?= e($c['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="submit-field">
            <label for="device_name">Device Name</label>
            <input type="text" id="device_name" name="device_name" maxlength="100"
                value="<?= e($_POST['device_name'] ?? '') ?>" required>
        </div>

        <div class="submit-field">
            <label for="fault_description">Problem Description</label>
            <textarea id="fault_description" name="fault_description"
                maxlength="255" required><?= e($_POST['fault_description'] ?? '') ?></textarea>
        </div>

        <div class="submit-field">
            <label for="device_photo">Device Photo</label>
            <input type="file" id="device_photo" name="device_photo"
                accept=".jpg,.jpeg,.png,.webp" required>
            <small>JPG, PNG or WebP. Maximum 5 MB.</small>
        </div>

        <div class="submission-terms">
            <h3>Device Submission Terms</h3>
            <p>By submitting this device, I confirm that:</p>
            <ol>
                <li>The device belongs to me or I have permission to submit it.</li>
                <li>The device information and problem description are accurate.</li>
                <li>I have provided a valid photo of the device.</li>
                <li>I understand that the device will be inspected by the Repair Café team.</li>
                <li>I will follow the collection deadlines and instructions provided by the team.</li>
            </ol>
        </div>

        <label class="submit-terms">
            <input type="checkbox" name="terms_accepted" value="1" required
                <?= isset($_POST['terms_accepted']) ? 'checked' : '' ?>>
            I have read and agree to the device submission terms.
        </label>

        <div class="submit-actions">
            <button type="submit" class="btn btn-primary">Submit Device</button>
            <a href="index.php" class="btn">Back to Dashboard</a>
        </div>
    </form>
</section>

<?php require '../includes/student_bottom.php'; ?>

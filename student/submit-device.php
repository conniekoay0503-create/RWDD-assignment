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
page_head('plus', 'Submit a Device', 'Register a broken device for repair or recycling. You earn points either way.');
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error" role="alert" data-flash="error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
<?php endforeach; ?>

<section class="card">
    <form method="post" enctype="multipart/form-data" novalidate data-validate data-confirm="Submit this device?">
        <?= csrf_field() ?>

        <div class="field-row">
            <div class="field">
                <label for="categoryID"><?= icon('tag') ?> Device category</label>
                <select name="categoryID" id="categoryID" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e($c['categoryID']) ?>"
                            <?= ($_POST['categoryID'] ?? '') === $c['categoryID'] ? 'selected' : '' ?>>
                            <?= e($c['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="device_name"><?= icon('device') ?> Device name</label>
                <input type="text" id="device_name" name="device_name" maxlength="100" placeholder="e.g. Redmi Note 11"
                    value="<?= e($_POST['device_name'] ?? '') ?>" required>
            </div>
        </div>

        <div class="field">
            <label for="fault_description"><?= icon('wrench') ?> Problem description</label>
            <textarea id="fault_description" name="fault_description" rows="4" placeholder="What is wrong with it? e.g. The battery drains within 2 hours"
                maxlength="255" required><?= e($_POST['fault_description'] ?? '') ?></textarea>
        </div>

        <div class="field">
            <label for="device_photo"><?= icon('eye') ?> Device photo</label>
            <input type="file" id="device_photo" name="device_photo" accept=".jpg,.jpeg,.png,.webp" required>
            <small class="field-hint">JPG, PNG or WebP. Maximum 5 MB.</small>
        </div>

        <div class="submission-terms">
            <h3><?= icon('shield') ?> Device submission terms</h3>
            <p>By submitting this device, I confirm that:</p>
            <ol>
                <li>The device belongs to me or I have permission to submit it.</li>
                <li>The device information and problem description are accurate.</li>
                <li>I have provided a valid photo of the device.</li>
                <li>I understand that the device will be inspected by the Repair Café team.</li>
                <li>I will follow the collection deadlines and instructions provided by the team.</li>
            </ol>
        </div>

        <label class="check">
            <input type="checkbox" name="terms_accepted" value="1" required
                <?= isset($_POST['terms_accepted']) ? 'checked' : '' ?>>
            I have read and agree to the device submission terms.
        </label>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary"><?= icon('send') ?> Submit device</button>
            <a class="btn btn-outline" href="my-devices.php"><?= icon('x') ?> Cancel</a>
        </div>
    </form>
</section>

<?php require '../includes/student_bottom.php'; ?>

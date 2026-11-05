
<?php
require '../config/db.php';
require '../includes/functions.php';
require_role($conn, 'student', '../');

$studentTP = $_SESSION['tp_number'];
$errors = [];

// Load active categories from the database.
$categories = db_all(
    $conn,
    "SELECT categoryID, category_name
     FROM categories
     WHERE is_active = 1
     ORDER BY categoryID"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryID = trim((string) ($_POST['categoryID'] ?? ''));
    $deviceName = trim($_POST['device_name'] ?? '');
    $faultDescription = trim($_POST['fault_description'] ?? '');
    $termsAccepted = isset($_POST['terms_accepted']);

    // Server-side validation
    $validCategory = false;
    foreach ($categories as $category) {
        if ($category['categoryID'] === $categoryID) {
            $validCategory = true;
            break;
        }
    }

    if (!$validCategory) {
        $errors[] = 'Please select a valid device category.';
    }

    if ($deviceName === '' || strlen($deviceName) > 100) {
        $errors[] = 'Device name must be between 1 and 100 characters.';
    }

    if ($faultDescription === '' || strlen($faultDescription) > 255) {
        $errors[] = 'Problem description must be between 1 and 255 characters.';
    }

    if (!$termsAccepted) {
        $errors[] = 'You must accept the terms before submitting.';
    }

    // Photo is required because photo_path is NOT NULL.
    $photo = $_FILES['device_photo'] ?? null;
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    $photoExtension = null;

    if (!$photo || $photo['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a device photo.';
    } elseif ($photo['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Photo must be 5 MB or smaller.';
    } elseif (!is_uploaded_file($photo['tmp_name'])) {
        $errors[] = 'Invalid photo upload.';
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($photo['tmp_name']);

        if (!isset($allowedTypes[$mimeType])) {
            $errors[] = 'Only JPG, PNG and WebP photos are allowed.';
        } else {
            $photoExtension = $allowedTypes[$mimeType];
        }
    }

    if (empty($errors)) {
        $uploadDirectory = __DIR__ . '/../uploads/devices/';
        $fileName = bin2hex(random_bytes(16)) . '.' . $photoExtension;
        $absolutePath = $uploadDirectory . $fileName;
        $relativePath = 'uploads/devices/' . $fileName;

        if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {
            $errors[] = 'The device photo folder is not writable.';
        } elseif (!move_uploaded_file($photo['tmp_name'], $absolutePath)) {
            $errors[] = 'Unable to save the uploaded photo.';
        } else {
            try {
                $conn->begin_transaction();

                $deviceID = next_id($conn, 'devices', 'deviceID', 'D');
                $historyID = next_id($conn, 'device_status_history', 'historyID', 'H');
                db_exec(
                    $conn,
                    "INSERT INTO devices
                     (deviceID, submitted_by, categoryID, device_name,
                      fault_description, photo_path, terms_accepted)
                     VALUES (?, ?, ?, ?, ?, ?, 1)",
                    'ssssss',
                    [
                        $deviceID,
                        $studentTP,
                        $categoryID,
                        $deviceName,
                        $faultDescription,
                        $relativePath
                    ]
                );


                db_exec(
                    $conn,
                    "INSERT INTO device_status_history
                     (historyID, deviceID, changed_by, new_status)
                     VALUES (?, ?, ?, 'Submitted')",
                    'sss',
                    [$historyID, $deviceID, $studentTP]
                );

                $conn->commit();

                set_flash('success', 'Device submitted successfully!');
                redirect('index.php');
            } catch (Throwable $exception) {
                $conn->rollback();
                @unlink($absolutePath);
                error_log($exception->getMessage());
                $errors[] = 'Submission failed. Please try again.';
            }
        }
    }
}
?>

<?php $page_title = 'Submit a Device'; require '../includes/student_top.php'; ?>
<div class="student-page-head"><span class="page-icon"><?= icon('plus') ?></span><div><span class="student-eyebrow">STUDENT PORTAL</span><h1>Submit a Device</h1><p>Manage your e-waste journey in one place.</p></div></div>
<section class="card student-panel">


    <span class="role-tag">STUDENT</span>
    
    <p>Register your electronic device for repair or recycling.</p>

    <?php if (!empty($errors)): ?>
        <div role="alert">
            <?php foreach ($errors as $error): ?>
                <p style="color: #b42318;"><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">

        <div>
            <label for="categoryID">Device Category</label>
            <select name="categoryID" id="categoryID" required>
                <option value="">Select category</option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= e($category['categoryID']) ?>"
                        <?= (string) ($_POST['categoryID'] ?? '') ===
                            (string) $category['categoryID'] ? 'selected' : '' ?>
                    >
                        <?= e($category['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="device_name">Device Name</label>
            <input
                type="text"
                id="device_name"
                name="device_name"
                maxlength="100"
                value="<?= e($_POST['device_name'] ?? '') ?>"
                placeholder="e.g. Samsung Galaxy S21"
                required
            >
        </div>

        <div>
            <label for="fault_description">Problem Description</label>
            <textarea
                id="fault_description"
                name="fault_description"
                maxlength="255"
                rows="4"
                placeholder="Describe what is wrong with your device"
                required
            ><?= e($_POST['fault_description'] ?? '') ?></textarea>
        </div>

        <div>
            <label for="device_photo">Device Photo</label>
            <input
                type="file"
                id="device_photo"
                name="device_photo"
                accept=".jpg,.jpeg,.png,.webp"
                required
            >
            <small>JPG, PNG or WebP. Maximum 5 MB.</small>
        </div>

        <div>
            <label>
                <input
                    type="checkbox"
                    name="terms_accepted"
                    value="1"
                    required
                    <?= isset($_POST['terms_accepted']) ? 'checked' : '' ?>
                >
                I agree to the device submission terms.
            </label>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">
                Submit Device
            </button>

            <a href="index.php" class="btn">
                Back to Dashboard
            </a>
        </div>

    </form>

</section>
<?php require '../includes/student_bottom.php'; ?>

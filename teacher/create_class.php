<?php
// Izinkan akses dari origin mana pun
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json'); // Responsnya JSON
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Izinkan POST dan OPTIONS
header('Access-Control-Allow-Headers: Content-Type'); // Izinkan Content-Type

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db.php'; // SESUAIKAN PATH INI KE db.php ANDA

$response = ['success' => false, 'message' => 'Operasi gagal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $teacher_id = $_POST['teacher_id'] ?? null;
    $role = $_POST['role'] ?? null; // Role guru yang request

    if (empty($name) || empty($teacher_id) || $role !== 'teacher') {
        $response['message'] = 'Nama kelas, ID guru, atau peran tidak valid.';
        echo json_encode($response);
        exit();
    }

    try {
        // Hasilkan kode kelas unik
        $code = strtoupper(substr(md5(uniqid()), 0, 6)); // 6 karakter unik

        $imagePath = 'uploads/class_images/default.jpg'; // Path default

        // Handle file upload
        if (isset($_FILES['class_image']) && $_FILES['class_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['class_image']['name'], PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_ext)) {
                $imageName = time() . '_' . uniqid() . '.' . $file_ext;
                $target_dir = __DIR__ . '/../../uploads/class_images/'; // Sesuaikan path ke folder upload
                $target_file = $target_dir . $imageName;

                // Pastikan folder uploads/class_images ada dan writable
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                if (move_uploaded_file($_FILES['class_image']['tmp_name'], $target_file)) {
                    $imagePath = 'uploads/class_images/' . $imageName; // Path relatif untuk disimpan di DB
                } else {
                    error_log("Failed to move uploaded file for class: " . $_FILES['class_image']['name']);
                    // Lanjutkan tanpa gambar, atau berikan error
                }
            } else {
                $response['message'] = 'Jenis file gambar tidak diizinkan. Gunakan JPG, JPEG, PNG, atau GIF.';
                echo json_encode($response);
                exit();
            }
        }

        // Insert kelas baru ke database
        $stmt = $conn->prepare("INSERT INTO classes (name, code, teacher_id, image) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $code, $teacher_id, $imagePath])) {
            $response = ['success' => true, 'message' => 'Kelas berhasil dibuat!', 'class_code' => $code];
        } else {
            $response = ['success' => false, 'message' => 'Gagal membuat kelas.'];
        }

    } catch (PDOException $e) {
        error_log("Create Class PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat membuat kelas.'];
    } catch (Exception $e) {
        error_log("Create Class General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
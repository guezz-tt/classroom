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
    $class_id = $_POST['class_id'] ?? null;
    $teacher_id = $_POST['teacher_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $fileBase64 = $_POST['file_base64'] ?? ''; // Ambil Base64 string dari Android
    $fileExt = $_POST['file_ext'] ?? 'dat'; // Ambil ekstensi dari Android

    if (empty($class_id) || empty($teacher_id) || empty($title) || empty($fileBase64)) {
        $response['message'] = 'Data tidak lengkap.';
        echo json_encode($response);
        exit();
    }

    try {
        // Verifikasi kepemilikan kelas oleh guru
        $stmt_verify = $conn->prepare("SELECT COUNT(*) FROM classes WHERE id = ? AND teacher_id = ?");
        $stmt_verify->execute([$class_id, $teacher_id]);
        if ($stmt_verify->fetchColumn() == 0) {
            $response['message'] = 'Anda tidak memiliki akses untuk mengunggah materi di kelas ini.';
            echo json_encode($response);
            exit();
        }

        $filePath = 'uploads/materi/default.dat'; // Path default jika upload gagal
        $decodedFile = base64_decode($fileBase64);

        if ($decodedFile === false) {
            $response['message'] = 'Data file tidak valid (Base64 decode gagal).';
            echo json_encode($response);
            exit();
        }

        // Tentukan sub-folder upload berdasarkan ekstensi file
        $upload_sub_dir = '';
        $allowed_image_ext = ['jpg', 'jpeg', 'png', 'gif'];
        $allowed_video_ext = ['mp4', 'mov', 'avi']; // Tambahkan jika ada format video lain
        $allowed_document_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx']; // Tambahkan jika ada format dokumen lain

        if (in_array($fileExt, $allowed_image_ext)) {
            $upload_sub_dir = 'images/';
        } elseif (in_array($fileExt, $allowed_video_ext)) {
            $upload_sub_dir = 'videos/';
        } elseif (in_array($fileExt, $allowed_document_ext)) {
            $upload_sub_dir = 'documents/';
        } else {
            $upload_sub_dir = 'others/'; // Untuk ekstensi yang tidak dikategorikan
        }

        // --- Periksa dan buat folder jika belum ada ---
        $target_base_dir = __DIR__ . '/../../uploads/materi/'; // SESUAIKAN PATH INI
        $target_dir = $target_base_dir . $upload_sub_dir;

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // Buat folder rekursif dengan izin penuh
        }
        // --- Akhir pembuatan folder ---

        $fileName = time() . '_' . uniqid() . '.' . $fileExt;
        $target_file = $target_dir . $fileName;

        if (file_put_contents($target_file, $decodedFile)) {
            $filePath = 'uploads/materi/' . $upload_sub_dir . $fileName; // Path relatif untuk disimpan di DB
        } else {
            error_log("Failed to save Base64 file for material: " . $fileName);
            // Lanjutkan dengan gambar default jika gagal menyimpan atau berikan error
            $response['message'] = 'Gagal menyimpan file di server.';
            echo json_encode($response);
            exit();
        }

        // Insert materi baru ke database
        $stmt_insert = $conn->prepare("INSERT INTO materials (class_id, teacher_id, title, file_path) VALUES (?, ?, ?, ?)");
        if ($stmt_insert->execute([$class_id, $teacher_id, $title, $filePath])) {
            $response = ['success' => true, 'message' => 'Materi berhasil diunggah!'];
        } else {
            $response = ['success' => false, 'message' => 'Gagal menyimpan data materi di database.'];
        }

    } catch (PDOException $e) {
        error_log("Upload Material PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat mengunggah materi.'];
    } catch (Exception $e) {
        error_log("Upload Material General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
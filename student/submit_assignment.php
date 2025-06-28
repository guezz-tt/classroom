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
    $assignment_id = $_POST['assignment_id'] ?? null;
    $student_id = $_POST['student_id'] ?? null;
    $fileBase64 = $_POST['file_base64'] ?? '';
    $fileExt = $_POST['file_ext'] ?? 'dat';

    if (empty($assignment_id) || empty($student_id) || empty($fileBase64)) {
        $response['message'] = 'Data tidak lengkap.';
        echo json_encode($response);
        exit();
    }

    try {
        // Cek apakah siswa sudah mengumpulkan tugas ini sebelumnya
        $stmt_check = $conn->prepare("SELECT file_path FROM submissions WHERE assignment_id = ? AND student_id = ?");
        $stmt_check->execute([$assignment_id, $student_id]);
        $existing_submission = $stmt_check->fetch(PDO::FETCH_ASSOC);

        $decodedFile = base64_decode($fileBase64);
        if ($decodedFile === false) {
            $response['message'] = 'Data file tidak valid (Base64 decode gagal).';
            echo json_encode($response);
            exit();
        }

        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt']; // Ekstensi yang diizinkan
        if (!in_array($fileExt, $allowed_ext)) {
            $response['message'] = 'Jenis file tidak diizinkan.';
            echo json_encode($response);
            exit();
        }

        // Tentukan sub-folder upload
        $upload_sub_dir = 'assignments/'; // Semua submisi tugas masuk ke folder ini
        
        $target_base_dir = __DIR__ . '/../../uploads/submissions/'; // SESUAIKAN PATH INI
        $target_dir = $target_base_dir . $upload_sub_dir;

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $fileName = time() . '_' . uniqid() . '.' . $fileExt;
        $target_file = $target_dir . $fileName;

        if (file_put_contents($target_file, $decodedFile)) {
            $filePath = 'uploads/submissions/' . $upload_sub_dir . $fileName; // Path relatif untuk disimpan di DB

            if ($existing_submission) {
                // Jika sudah ada, update
                // Hapus file lama jika ada dan bukan default/kosong
                if ($existing_submission['file_path'] && file_exists(__DIR__ . '/../../' . $existing_submission['file_path'])) {
                    @unlink(__DIR__ . '/../../' . $existing_submission['file_path']);
                }
                $stmt_update = $conn->prepare("UPDATE submissions SET file_path = ?, submitted_at = NOW() WHERE assignment_id = ? AND student_id = ?");
                if ($stmt_update->execute([$filePath, $assignment_id, $student_id])) {
                    $response = ['success' => true, 'message' => 'Tugas berhasil diperbarui.'];
                } else {
                    $response = ['success' => false, 'message' => 'Gagal memperbarui tugas.'];
                }
            } else {
                // Jika belum ada, insert baru
                $stmt_insert = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, file_path) VALUES (?, ?, ?)");
                if ($stmt_insert->execute([$assignment_id, $student_id, $filePath])) {
                    $response = ['success' => true, 'message' => 'Tugas berhasil dikirim.'];
                } else {
                    $response = ['success' => false, 'message' => 'Gagal mengirim tugas.'];
                }
            }
        } else {
            error_log("Failed to save Base64 file for submission: " . $fileName);
            $response = ['success' => false, 'message' => 'Gagal menyimpan file tugas di server.'];
        }

    } catch (PDOException $e) {
        error_log("Submit Assignment PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat mengirim tugas.'];
    } catch (Exception $e) {
        error_log("Submit Assignment General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
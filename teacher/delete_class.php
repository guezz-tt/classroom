<?php
// Izinkan akses dari origin mana pun
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db.php'; // SESUAIKAN PATH INI KE db.php ANDA

$response = ['success' => false, 'message' => 'Operasi gagal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_id = $_POST['class_id'] ?? null;
    $teacher_id = $_POST['teacher_id'] ?? null; // ID guru yang request hapus

    if (empty($class_id) || empty($teacher_id)) {
        $response['message'] = 'ID Kelas atau ID Guru tidak valid.';
        echo json_encode($response);
        exit();
    }

    try {
        // --- Verifikasi Otorisasi: Pastikan guru ini yang punya kelas ini ---
        $stmt_verify = $conn->prepare("SELECT COUNT(*) FROM classes WHERE id = ? AND teacher_id = ?");
        $stmt_verify->execute([$class_id, $teacher_id]);
        if ($stmt_verify->fetchColumn() == 0) {
            $response['message'] = 'Anda tidak diizinkan menghapus kelas ini.';
            echo json_encode($response);
            exit();
        }

        $conn->beginTransaction(); // Mulai transaksi

        // 1. Hapus file-file terkait (materi, submisi, gambar kelas)
        // Hapus file materi
        $stmt_materials = $conn->prepare("SELECT file_path FROM materials WHERE class_id = ?");
        $stmt_materials->execute([$class_id]);
        foreach ($stmt_materials->fetchAll() as $m) {
            $filePath = __DIR__ . '/../../' . $m['file_path']; // Sesuaikan path uploads
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // Hapus file tugas siswa
        $stmt_submissions = $conn->prepare("SELECT s.file_path FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE a.class_id = ?");
        $stmt_submissions->execute([$class_id]);
        foreach ($stmt_submissions->fetchAll() as $s) {
            $filePath = __DIR__ . '/../../' . $s['file_path']; // Sesuaikan path uploads
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // Hapus foto kelas (kecuali default.jpg)
        $stmt_class_image = $conn->prepare("SELECT image FROM classes WHERE id = ?");
        $stmt_class_image->execute([$class_id]);
        $class_data = $stmt_class_image->fetch(PDO::FETCH_ASSOC);
        if ($class_data && !empty($class_data['image']) && $class_data['image'] != 'uploads/class_images/default.jpg') {
            $imagePath = __DIR__ . '/../../' . $class_data['image']; // Sesuaikan path uploads
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }


        // 2. Hapus data dari tabel-tabel anak (sesuai urutan foreign key)
        $conn->prepare("DELETE FROM submissions WHERE assignment_id IN (SELECT id FROM assignments WHERE class_id = ?)")->execute([$class_id]);
        $conn->prepare("DELETE FROM assignments WHERE class_id = ?")->execute([$class_id]);
        $conn->prepare("DELETE FROM materials WHERE class_id = ?")->execute([$class_id]);
        $conn->prepare("DELETE FROM class_students WHERE class_id = ?")->execute([$class_id]);
        $conn->prepare("DELETE FROM class_members WHERE class_id = ?")->execute([$class_id]); // Jika class_members digunakan
        $conn->prepare("DELETE FROM class_chat WHERE class_id = ?")->execute([$class_id]); // Jika class_chat digunakan

        // 3. Hapus kelas itu sendiri
        $stmt_delete_class = $conn->prepare("DELETE FROM classes WHERE id = ?");
        if ($stmt_delete_class->execute([$class_id])) {
            $response = ['success' => true, 'message' => 'Kelas dan semua kontennya berhasil dihapus.'];
            $conn->commit(); // Commit transaksi jika semua berhasil
        } else {
            $response = ['success' => false, 'message' => 'Gagal menghapus kelas.'];
            $conn->rollBack(); // Rollback jika ada error
        }

    } catch (PDOException $e) {
        $conn->rollBack(); // Rollback jika terjadi error PDO
        error_log("Delete Class PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat menghapus kelas.'];
    } catch (Exception $e) {
        $conn->rollBack(); // Rollback jika terjadi error umum
        error_log("Delete Class General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
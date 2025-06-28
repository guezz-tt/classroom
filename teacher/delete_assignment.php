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
    $assignment_id = $_POST['assignment_id'] ?? null;
    $teacher_id = $_POST['teacher_id'] ?? null;
    $class_id = $_POST['class_id'] ?? null; // Untuk verifikasi tambahan

    if (empty($assignment_id) || empty($teacher_id) || empty($class_id)) {
        $response['message'] = 'ID Tugas, ID Guru, atau ID Kelas tidak valid.';
        echo json_encode($response);
        exit();
    }

    try {
        $conn->beginTransaction();

        // Verifikasi kepemilikan tugas dan kelas oleh guru
        $stmt_verify = $conn->prepare("SELECT a.class_id FROM assignments a JOIN classes c ON a.class_id = c.id WHERE a.id = ? AND c.teacher_id = ? AND a.class_id = ?");
        $stmt_verify->execute([$assignment_id, $teacher_id, $class_id]);
        $verified_assignment = $stmt_verify->fetch(PDO::FETCH_ASSOC);

        if (!$verified_assignment) {
            $response['message'] = 'Tugas tidak ditemukan atau Anda tidak diizinkan menghapusnya.';
            $conn->rollBack();
            echo json_encode($response);
            exit();
        }

        // Hapus file-file submisi terkait tugas ini
        $stmt_submissions = $conn->prepare("SELECT file_path FROM submissions WHERE assignment_id = ?");
        $stmt_submissions->execute([$assignment_id]);
        foreach ($stmt_submissions->fetchAll() as $s) {
            $filePath = __DIR__ . '/../../' . $s['file_path']; // Sesuaikan path uploads
            if ($s['file_path'] && file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // Hapus submisi dari database
        $conn->prepare("DELETE FROM submissions WHERE assignment_id = ?")->execute([$assignment_id]);

        // Hapus tugas itu sendiri
        $stmt_delete_assignment = $conn->prepare("DELETE FROM assignments WHERE id = ?");
        if ($stmt_delete_assignment->execute([$assignment_id])) {
            $response = ['success' => true, 'message' => 'Tugas dan semua submisi terkait berhasil dihapus.'];
            $conn->commit();
        } else {
            $response = ['success' => false, 'message' => 'Gagal menghapus tugas dari database.'];
            $conn->rollBack();
        }

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Delete Assignment PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database.'];
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Delete Assignment General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan.'];
}

echo json_encode($response);
?>
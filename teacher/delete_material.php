<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db.php'; // SESUAIKAN PATH INI

$response = ['success' => false, 'message' => 'Operasi gagal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $material_id = $_POST['material_id'] ?? null;
    $teacher_id = $_POST['teacher_id'] ?? null;
    $class_id = $_POST['class_id'] ?? null; // Untuk verifikasi tambahan

    if (empty($material_id) || empty($teacher_id) || empty($class_id)) {
        $response['message'] = 'ID Materi, ID Guru, atau ID Kelas tidak valid.';
        echo json_encode($response);
        exit();
    }

    try {
        $conn->beginTransaction();

        // Verifikasi materi dan kepemilikan guru atas kelas
        $stmt_verify = $conn->prepare("SELECT m.file_path, m.teacher_id, m.class_id FROM materials m JOIN classes c ON m.class_id = c.id WHERE m.id = ? AND c.teacher_id = ? AND m.class_id = ?");
        $stmt_verify->execute([$material_id, $teacher_id, $class_id]);
        $material_data = $stmt_verify->fetch(PDO::FETCH_ASSOC);

        if (!$material_data) {
            $response['message'] = 'Materi tidak ditemukan atau Anda tidak diizinkan menghapusnya.';
            $conn->rollBack();
            echo json_encode($response);
            exit();
        }

        // Hapus file fisik jika ada
        if ($material_data['file_path'] && file_exists(__DIR__ . '/../../' . $material_data['file_path'])) {
            @unlink(__DIR__ . '/../../' . $material_data['file_path']); // Sesuaikan path uploads
        }

        // Hapus dari database
        $stmt_delete = $conn->prepare("DELETE FROM materials WHERE id = ?");
        if ($stmt_delete->execute([$material_id])) {
            $response = ['success' => true, 'message' => 'Materi berhasil dihapus.'];
            $conn->commit();
        } else {
            $response = ['success' => false, 'message' => 'Gagal menghapus materi dari database.'];
            $conn->rollBack();
        }

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Delete Material PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database.'];
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Delete Material General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan.'];
}

echo json_encode($response);
?>
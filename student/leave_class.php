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
    $student_id = $_POST['student_id'] ?? null;
    $class_id   = $_POST['class_id'] ?? null;

    if (empty($student_id) || empty($class_id)) {
        $response['message'] = 'ID Siswa atau ID Kelas tidak valid.';
        echo json_encode($response);
        exit();
    }

    try {
        // Hapus entri dari class_students
        $stmt_delete = $conn->prepare("DELETE FROM class_students WHERE class_id = ? AND student_id = ?");
        if ($stmt_delete->execute([$class_id, $student_id])) {
            // Cek apakah ada baris yang terpengaruh (artinya memang ada entri untuk dihapus)
            if ($stmt_delete->rowCount() > 0) {
                $response = ['success' => true, 'message' => 'Berhasil keluar dari kelas.'];
            } else {
                $response = ['success' => false, 'message' => 'Anda tidak terdaftar di kelas ini.'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Gagal keluar dari kelas.'];
        }

    } catch (PDOException $e) {
        error_log("Leave Class PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat keluar kelas.'];
    } catch (Exception $e) {
        error_log("Leave Class General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
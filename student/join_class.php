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
    $code       = strtoupper(trim($_POST['code'] ?? ''));

    if (empty($student_id) || empty($code)) {
        $response['message'] = 'ID Siswa atau Kode kelas tidak valid.';
        echo json_encode($response);
        exit();
    }

    try {
        // Cari kelas berdasarkan kode
        $stmt_class = $conn->prepare("SELECT id FROM classes WHERE code = ?");
        $stmt_class->execute([$code]);
        $class = $stmt_class->fetch(PDO::FETCH_ASSOC);

        if ($class) {
            $class_id = $class['id'];

            // Cek apakah siswa sudah tergabung di kelas ini
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM class_students WHERE class_id = ? AND student_id = ?");
            $stmt_check->execute([$class_id, $student_id]);

            if ($stmt_check->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Anda sudah tergabung di kelas ini.'];
            } else {
                // Tambahkan siswa ke kelas
                $stmt_insert = $conn->prepare("INSERT INTO class_students (class_id, student_id) VALUES (?, ?)");
                if ($stmt_insert->execute([$class_id, $student_id])) {
                    $response = ['success' => true, 'message' => 'Berhasil bergabung ke kelas.'];
                } else {
                    $response = ['success' => false, 'message' => 'Gagal bergabung ke kelas.'];
                }
            }
        } else {
            $response = ['success' => false, 'message' => 'Kode kelas tidak ditemukan.'];
        }

    } catch (PDOException $e) {
        error_log("Join Class PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat bergabung kelas.'];
    } catch (Exception $e) {
        error_log("Join Class General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
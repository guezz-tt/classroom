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
    $description = trim($_POST['description'] ?? '');
    $deadline = $_POST['deadline'] ?? null; // Format YYYY-MM-DD HH:MM

    if (empty($class_id) || empty($teacher_id) || empty($title) || empty($description) || empty($deadline)) {
        $response['message'] = 'Data tidak lengkap.';
        echo json_encode($response);
        exit();
    }

    try {
        // Verifikasi kepemilikan kelas oleh guru
        $stmt_verify = $conn->prepare("SELECT COUNT(*) FROM classes WHERE id = ? AND teacher_id = ?");
        $stmt_verify->execute([$class_id, $teacher_id]);
        if ($stmt_verify->fetchColumn() == 0) {
            $response['message'] = 'Anda tidak memiliki akses untuk membuat tugas di kelas ini.';
            echo json_encode($response);
            exit();
        }

        // Insert tugas baru
        $stmt_insert = $conn->prepare("INSERT INTO assignments (class_id, teacher_id, title, description, deadline) VALUES (?, ?, ?, ?, ?)");
        if ($stmt_insert->execute([$class_id, $teacher_id, $title, $description, $deadline])) {
            $response = ['success' => true, 'message' => 'Tugas berhasil dibuat!'];
        } else {
            $response = ['success' => false, 'message' => 'Gagal membuat tugas.'];
        }

    } catch (PDOException $e) {
        error_log("Create Assignment PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat membuat tugas.'];
    } catch (Exception $e) {
        error_log("Create Assignment General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
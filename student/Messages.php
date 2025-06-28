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
    $user_id = $_POST['user_id'] ?? null;
    $role = $_POST['role'] ?? null;
    $message_text = trim($_POST['message'] ?? '');

    if (empty($class_id) || empty($user_id) || empty($role) || empty($message_text)) {
        $response['message'] = 'Data tidak lengkap.';
        echo json_encode($response);
        exit();
    }

    // Optional: Verifikasi user_id dan role (misal user_id ini memang ada dan rolenya sesuai)
    // Untuk saat ini, kita anggap user_id dan role dari aplikasi valid.

    try {
        $stmt_insert_chat = $conn->prepare("INSERT INTO class_chat (class_id, user_id, role, message) VALUES (?, ?, ?, ?)");
        if ($stmt_insert_chat->execute([$class_id, $user_id, $role, $message_text])) {
            $response = ['success' => true, 'message' => 'Pesan berhasil dikirim.'];
        } else {
            $response = ['success' => false, 'message' => 'Gagal mengirim pesan chat.'];
        }

    } catch (PDOException $e) {
        error_log("Send Chat PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat mengirim pesan.'];
    } catch (Exception $e) {
        error_log("Send Chat General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan.'];
}

echo json_encode($response);
?>
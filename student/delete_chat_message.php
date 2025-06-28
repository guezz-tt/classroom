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
    $chat_id = $_POST['chat_id'] ?? null;
    $user_id = $_POST['user_id'] ?? null; // ID user yang menghapus
    $class_id = $_POST['class_id'] ?? null; // Untuk verifikasi tambahan

    if (empty($chat_id) || empty($user_id) || empty($class_id)) {
        $response['message'] = 'ID Pesan, ID Pengguna, atau ID Kelas tidak valid.';
        echo json_encode($response);
        exit();
    }

    try {
        // Verifikasi bahwa pesan chat ini milik user yang request hapus
        // Atau setidaknya guru yang menghapus, harus punya akses ke kelas ini
        $stmt_verify = $conn->prepare("
            SELECT cc.user_id, c.teacher_id, cc.class_id
            FROM class_chat cc
            JOIN classes c ON cc.class_id = c.id
            WHERE cc.id = ? AND cc.class_id = ?
        ");
        $stmt_verify->execute([$chat_id, $class_id]);
        $chat_data = $stmt_verify->fetch(PDO::FETCH_ASSOC);

        if (!$chat_data) {
            $response['message'] = 'Pesan tidak ditemukan di kelas ini.';
            echo json_encode($response);
            exit();
        }

        // IZIN HAPUS: Hanya pemilik pesan yang bisa hapus, ATAU guru pemilik kelas yang bisa hapus
        if ($chat_data['user_id'] == $user_id || ($chat_data['teacher_id'] == $user_id && $chat_data['role'] == 'teacher')) { // asumsi guru bisa hapus chatnya sendiri/orang lain
            $stmt_delete_chat = $conn->prepare("DELETE FROM class_chat WHERE id = ?");
            if ($stmt_delete_chat->execute([$chat_id])) {
                $response = ['success' => true, 'message' => 'Pesan chat berhasil dihapus.'];
            } else {
                $response = ['success' => false, 'message' => 'Gagal menghapus pesan chat.'];
            }
        } else {
            $response['message'] = 'Anda tidak diizinkan menghapus pesan ini.';
        }

    } catch (PDOException $e) {
        error_log("Delete Chat PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat menghapus pesan.'];
    } catch (Exception $e) {
        error_log("Delete Chat General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan.'];
}

echo json_encode($response);
?>
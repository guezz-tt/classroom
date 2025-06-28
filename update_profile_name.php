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

require_once __DIR__ . 'db.php'; // SESUAIKAN PATH INI KE db.php ANDA

$response = ['success' => false, 'message' => 'Operasi gagal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $new_name = trim($_POST['new_name'] ?? '');

    if (empty($user_id) || empty($new_name)) {
        $response['message'] = 'ID pengguna atau nama baru tidak valid.';
        echo json_encode($response);
        exit();
    }

    try {
        $stmt_update_name = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
        if ($stmt_update_name->execute([$new_name, $user_id])) {
            $response = ['success' => true, 'message' => 'Nama berhasil diperbarui.', 'new_name' => $new_name];
        } else {
            $response = ['success' => false, 'message' => 'Gagal memperbarui nama di database.'];
        }

    } catch (PDOException $e) {
        error_log("Update Profile Name PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat memperbarui nama.'];
    } catch (Exception $e) {
        error_log("Update Profile Name General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
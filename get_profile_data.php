<?php
// Izinkan akses dari origin mana pun
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json'); // Responsnya JSON
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Izinkan POST dan OPTIONS
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Izinkan Content-Type

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php'; // SESUAIKAN PATH INI KE db.php ANDA (keluar 1 level dari api)

$response = ['success' => false, 'message' => 'Terjadi kesalahan tidak dikenal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $role    = $_POST['role'] ?? null;

    if (empty($user_id) || empty($role)) {
        $response = ['success' => false, 'message' => 'ID pengguna atau peran tidak valid.'];
        echo json_encode($response);
        exit();
    }

    try {
        // Ambil data pengguna dari tabel users
        $stmt_user = $conn->prepare("SELECT id, name, email, role, profile_picture FROM users WHERE id = ? AND role = ?");
        $stmt_user->execute([$user_id, $role]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $profile_picture_url = '';
            // URL gambar profil
            if (!empty($user_data['profile_picture'])) {
                // GANTI IP INI SESUAI TARGET ANDA
                $profile_picture_url = 'http://10.0.2.2/classroom/' . $user_data['profile_picture'];
            } else {
                // GANTI IP INI SESUAI TARGET ANDA
                $profile_picture_url = 'http://10.0.2.2/classroom/uploads/profiles/default.jpeg';
            }

            $response = [
                'success' => true,
                'message' => 'Data profil berhasil dimuat.',
                'user_profile' => [
                    'id' => $user_data['id'],
                    'name' => $user_data['name'],
                    'email' => $user_data['email'],
                    'role' => $user_data['role'],
                    'profile_picture_url' => $profile_picture_url
                ]
            ];
        } else {
            $response = ['success' => false, 'message' => 'Pengguna tidak ditemukan atau peran tidak cocok.'];
        }

    } catch (PDOException $e) {
        error_log("Get Profile Data PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat mengambil data profil.'];
    } catch (Exception $e) {
        error_log("Get Profile Data General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
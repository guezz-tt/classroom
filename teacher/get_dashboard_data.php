<?php
// Izinkan akses dari origin mana pun (PENTING untuk pengembangan. Amankan di produksi!)
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json'); // Beri tahu bahwa responsnya JSON
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Izinkan metode POST dan OPTIONS
header('Access-Control-Allow-Headers: Content-Type'); // Izinkan header Content-Type

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// SESUAIKAN PATH INI KE db.php ANDA
// Asumsi: login.php ada di htdocs/classroom/api/teacher/get_dashboard_data.php
// Asumsi: db.php ada di htdocs/classroom/db.php
require_once __DIR__ . '/../db.php';

$response = ['success' => false, 'message' => 'Terjadi kesalahan tidak dikenal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $teacher_id = $_POST['user_id'] ?? null;
    $role       = $_POST['role'] ?? null;

    // Verifikasi dasar (ini bukan otentikasi kuat, hanya untuk MVP)
    if (empty($teacher_id) || $role !== 'teacher') {
        $response = ['success' => false, 'message' => 'Akses tidak diizinkan. Peran atau ID tidak valid.'];
        echo json_encode($response);
        exit();
    }

    try {
        // Ambil data user (nama dan profile_picture)
        $stmt_user = $conn->prepare("SELECT name, profile_picture FROM users WHERE id = ? AND role = 'teacher'");
        $stmt_user->execute([$teacher_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        $profile_picture_url = '';
        if ($user_data && !empty($user_data['profile_picture'])) {
            // --- BAGIAN INI YANG PERLU ANDA SESUAIKAN IP-NYA ---
            $profile_picture_url = 'http://ruangbelajar.42web.io/api/' . $user_data['profile_picture'];
            // JIKA PAKAI PERANGKAT FISIK, GANTI 10.0.2.2 DENGAN IP KOMPUTER ANDA, misal:
            // $profile_picture_url = 'http://192.168.1.5/classroom/' . $user_data['profile_picture'];
            // --- AKHIR BAGIAN PENYESUAIAN ---
        } else {
            // URL gambar default
            // --- BAGIAN INI YANG PERLU ANDA SESUAIKAN IP-NYA ---
            $profile_picture_url = 'http://ruangbelajar.42web.io/api/uploads/profiles/default.jpeg';
            // JIKA PAKAI PERANGKAT FISIK, GANTI 10.0.2.2 DENGAN IP KOMPUTER ANDA, misal:
            // $profile_picture_url = 'http://192.168.1.5/classroom/uploads/profiles/default.jpeg';
            // --- AKHIR BAGIAN PENYESUAIAN ---
        }

        // Ambil kelas milik guru
        $stmt_classes = $conn->prepare("SELECT id, name, description, code, image FROM classes WHERE teacher_id = ?");
        $stmt_classes->execute([$teacher_id]);
        $classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

        // Ubah path gambar kelas menjadi URL penuh
        foreach ($classes as $key => $class) {
            $imageUrl = '';
            if (!empty($class['image'])) {
                // --- BAGIAN INI YANG PERLU ANDA SESUAIKAN IP-NYA ---
                $imageUrl = 'http://10.0.2.2/classroom/' . $class['image'];
                // JIKA PAKAI PERANGKAT FISIK, GANTI 10.0.2.2 DENGAN IP KOMPUTER ANDA, misal:
                // $imageUrl = 'http://192.168.1.5/classroom/' . $class['image'];
                // --- AKHIR BAGIAN PENYESUAIAN ---
            } else {
                // URL gambar kelas default
                // --- BAGIAN INI YANG PERLU ANDA SESUAIKAN IP-NYA ---
                $imageUrl = 'http://10.0.2.2/classroom/uploads/class_images/default.jpg';
                // JIKA PAKAI PERANGKAT FISIK, GANTI 10.0.2.2 DENGAN IP KOMPUTER ANDA, misal:
                // $imageUrl = 'http://192.168.1.5/classroom/uploads/class_images/default.jpg';
                // --- AKHIR BAGIAN PENYESUAIAN ---
            }
            $classes[$key]['image_url'] = $imageUrl;
            // Hapus kolom 'image' karena kita sudah kirim 'image_url'
            unset($classes[$key]['image']);
        }


        $response = [
            'success' => true,
            'message' => 'Data dashboard berhasil dimuat.',
            'teacher_data' => [
                'name' => $user_data['name'] ?? 'Guru',
                'profile_picture_url' => $profile_picture_url
            ],
            'classes' => $classes
        ];

    } catch (PDOException $e) {
        error_log("Teacher Dashboard PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat mengambil data.'];
    } catch (Exception $e) {
        error_log("Teacher Dashboard General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
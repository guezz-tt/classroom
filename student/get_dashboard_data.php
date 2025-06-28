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

$response = ['success' => false, 'message' => 'Terjadi kesalahan tidak dikenal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['user_id'] ?? null;
    $role       = $_POST['role'] ?? null;

    if (empty($student_id) || $role !== 'student') {
        $response = ['success' => false, 'message' => 'Akses tidak diizinkan. Peran atau ID tidak valid.'];
        echo json_encode($response);
        exit();
    }

    try {
        // Ambil data user (nama dan profile_picture)
        $stmt_user = $conn->prepare("SELECT name, profile_picture FROM users WHERE id = ? AND role = 'student'");
        $stmt_user->execute([$student_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        $profile_picture_url = '';
        if ($user_data && !empty($user_data['profile_picture'])) {
            // GANTI IP INI SESUAI TARGET ANDA
            $profile_picture_url = 'http://ruangbelajar.42web.io/api/' . $user_data['profile_picture'];
        } else {
            // GANTI IP INI SESUAI TARGET ANDA
            $profile_picture_url = 'http://ruangbelajar.42web.io/api/uploads/profiles/default.jpeg';
        }

        // Ambil kelas yang diikuti murid beserta gambar kelas
        $stmt_classes = $conn->prepare("
            SELECT c.id, c.name, c.description, c.code, c.image FROM classes c
            JOIN class_students cs ON c.id = cs.class_id
            WHERE cs.student_id = ?
        ");
        $stmt_classes->execute([$student_id]);
        $classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

        // Ubah path gambar kelas menjadi URL penuh
        foreach ($classes as $key => $class) {
            $imageUrl = '';
            if (!empty($class['image'])) {
                // GANTI IP INI SESUAI TARGET ANDA
                $imageUrl = 'http://ruangbelajar.42web.io/api/' . $class['image'];
            } else {
                // GANTI IP INI SESUAI TARGET ANDA
                $imageUrl = 'http://ruangbelajar.42web.io/api/uploads/class_images/default.jpg';
            }
            $classes[$key]['image_url'] = $imageUrl;
            unset($classes[$key]['image']); // Hapus kolom 'image' jika tidak ingin dikirim ke frontend
        }


        $response = [
            'success' => true,
            'message' => 'Data dashboard siswa berhasil dimuat.',
            'student_data' => [
                'name' => $user_data['name'] ?? 'Murid',
                'profile_picture_url' => $profile_picture_url
            ],
            'classes' => $classes
        ];

    } catch (PDOException $e) {
        error_log("Student Dashboard PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat mengambil data.'];
    } catch (Exception $e) {
        error_log("Student Dashboard General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
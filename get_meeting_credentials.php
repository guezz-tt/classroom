<?php
// Izinkan akses dari origin mana pun
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php'; // SESUAIKAN PATH INI KE db.php ANDA

$response = ['success' => false, 'message' => 'Terjadi kesalahan tidak dikenal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $room_code = $_POST['room_code'] ?? null; // Perbaikan: Sekarang menerima room_code
    $user_id = $_POST['user_id'] ?? null;
    $user_role = $_POST['user_role'] ?? null;
    $user_name = $_POST['user_name'] ?? 'Pengguna'; // Tambah user_name untuk Agora UID

    if (empty($room_code) || empty($user_id) || empty($user_role)) {
        $response['message'] = 'Kode ruangan, ID Pengguna, atau Peran tidak valid.';
        echo json_encode($response);
        exit();
    }

    try {
        // --- Cari Kelas berdasarkan Kode Ruangan (room_code) ---
        $stmt_class = $conn->prepare("SELECT id, name, code, teacher_id FROM classes WHERE code = ?");
        $stmt_class->execute([$room_code]);
        $class_info = $stmt_class->fetch(PDO::FETCH_ASSOC);

        if (!$class_info) {
            $response['message'] = 'Kode ruangan tidak ditemukan.';
            echo json_encode($response);
            exit();
        }

        $class_id = $class_info['id']; // Dapatkan class_id dari kode ruangan

        // --- Verifikasi Akses Kelas (PENTING!) ---
        // Pastikan user ini (baik guru atau siswa) memang terdaftar di kelas ini
        $is_member = false;
        if ($user_role === 'teacher') {
            if ($class_info['teacher_id'] == $user_id) $is_member = true; // Guru adalah pemilik kelas
        } elseif ($user_role === 'student') {
            $stmt_student_member = $conn->prepare("SELECT COUNT(*) FROM class_students WHERE class_id = ? AND student_id = ?");
            $stmt_student_member->execute([$class_id, $user_id]);
            if ($stmt_student_member->fetchColumn() > 0) $is_member = true;
        }

        if (!$is_member) {
            $response['message'] = 'Anda tidak memiliki akses ke kelas ini untuk meeting. Gabung kelas terlebih dahulu.';
            echo json_encode($response);
            exit();
        }

        // --- Logika Mendapatkan Kredensial Meeting (dari database atau hardcode) ---
        // IDEALNYA: Kredensial Meeting (App ID, App Certificate) disembunyikan dan Token dihasilkan oleh server.
        $agora_app_id = "YOUR_AGORA_APP_ID"; // GANTI DENGAN AGORA APP ID ANDA!
        $agora_app_certificate = "YOUR_AGORA_APP_CERTIFICATE"; // GANTI DENGAN AGORA APP CERTIFICATE ANDA!

        $rtcToken = ""; // Biarkan kosong jika tidak pakai token di Agora Dashboard (mode testing)
                        // Untuk produksi, Anda perlu generate token di sini menggunakan App Certificate Agora
                        // Contoh: require_once 'agora/RtcTokenBuilder.php'; $rtcToken = RtcTokenBuilder::buildToken(...);

        $agora_uid = $user_id; // Gunakan user_id sebagai UID Agora

        $response = [
            'success' => true,
            'message' => 'Kredensial meeting berhasil dimuat.',
            'meeting_info' => [
                'appId' => $agora_app_id,
                'channelName' => $room_code, // Channel name adalah kode ruangan
                'rtcToken' => $rtcToken,
                'uid' => $agora_uid,
                'userName' => $user_name // Kirim nama pengguna untuk ditampilkan di meeting
            ]
        ];

    } catch (PDOException $e) {
        error_log("Get Meeting Creds PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat mengambil kredensial meeting.'];
    } catch (Exception $e) {
        error_log("Get Meeting Creds General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
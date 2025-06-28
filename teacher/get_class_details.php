<?php
// Izinkan akses dari origin mana pun (PENTING untuk pengembangan. Amankan di produksi!)
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json'); // Beri tahu bahwa responsnya JSON
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Izinkan metode POST dan OPTIONS
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Izinkan header ini

// Handle preflight request (khususnya untuk aplikasi mobile/browser modern)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ====================================================================
// --- SANGAT PENTING: SESUAIKAN PATH INI KE FILE db.php ANDA ---
// Asumsi: get_class_details.php ada di htdocs/classroom/api/teacher/
// Asumsi: db.php ada di htdocs/classroom/db.php
require_once __DIR__ . '/../db.php';
// Jika db.php Anda ada di htdocs/classroom/api/db.php (jarang):
// require_once __DIR__ . '/../db.php'; // keluar satu folder ke api/, lalu cari db.php
// Jika db.php Anda ada di htdocs/classroom/api/teacher/db.php (sangat jarang):
// require_once __DIR__ . '/db.php';
// ====================================================================

$response = ['success' => false, 'message' => 'Terjadi kesalahan tidak dikenal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_id = $_POST['class_id'] ?? null;
    $teacher_id = $_POST['teacher_id'] ?? null; // ID guru yang request
    $role = $_POST['role'] ?? null;

    // Verifikasi dasar (ini bukan otentikasi kuat, hanya untuk MVP)
    if (empty($class_id) || empty($teacher_id) || $role !== 'teacher') {
        $response = ['success' => false, 'message' => 'Akses tidak diizinkan. ID kelas atau peran tidak valid.'];
        echo json_encode($response);
        exit();
    }

    try {
        // --- Ambil Data Kelas ---
        $stmt_class = $conn->prepare("SELECT id, name, description, code, image, teacher_id FROM classes WHERE id = ?");
        $stmt_class->execute([$class_id]);
        $class_data = $stmt_class->fetch(PDO::FETCH_ASSOC);

        if (!$class_data) {
            $response = ['success' => false, 'message' => 'Kelas tidak ditemukan.'];
            echo json_encode($response);
            exit();
        }

        // Verifikasi apakah guru ini pemilik kelasnya
        if ($class_data['teacher_id'] != $teacher_id) {
            $response = ['success' => false, 'message' => 'Anda tidak memiliki akses ke kelas ini.'];
            echo json_encode($response);
            exit();
        }

        // URL Gambar Kelas
        $class_image_url = '';
        if (!empty($class_data['image'])) {
            // ====================================================================
            // --- SANGAT PENTING: GANTI IP INI SESUAI TARGET ANDA ---
            // Jika menguji di EMULATOR: http://10.0.2.2/classroom/
            // Jika menguji di PERANGKAT FISIK: http://IP_KOMPUTER_ANDA/classroom/ (misal: http://192.168.1.5/classroom/)
            $class_image_url = 'http://ruangbelajar.42web.io/api/' . $class_data['image'];
            // ====================================================================
        } else {
            // URL gambar default
            // ====================================================================
            // --- SANGAT PENTING: GANTI IP INI SESUAI TARGET ANDA ---
            $class_image_url = 'http://ruangbelajar.42web.io/api/uploads/class_images/default.jpg';
            // ====================================================================
        }
        $class_data['image_url'] = $class_image_url;
        unset($class_data['image']); // Hapus field asli jika sudah ada image_url

        // --- Ambil Materi ---
        $stmt_materials = $conn->prepare("SELECT id, title, file_path, uploaded_at FROM materials WHERE class_id = ? ORDER BY uploaded_at DESC");
        $stmt_materials->execute([$class_id]);
        $materials = $stmt_materials->fetchAll(PDO::FETCH_ASSOC);
        foreach ($materials as $key => $m) {
            // ====================================================================
            // --- SANGAT PENTING: GANTI IP INI SESUAI TARGET ANDA ---
            $materials[$key]['file_full_url'] = 'http://ruangbelajar.42web.io/api/' . $m['file_path'];
            // ====================================================================
            unset($materials[$key]['file_path']);
        }

        // --- Ambil Tugas ---
        $stmt_assignments = $conn->prepare("SELECT id, title, description, deadline, created_at FROM assignments WHERE class_id = ? ORDER BY created_at DESC");
        $stmt_assignments->execute([$class_id]);
        $assignments = $stmt_assignments->fetchAll(PDO::FETCH_ASSOC);

        // --- Ambil Submisi Tugas dari Siswa ---
        $stmt_submissions = $conn->prepare("
            SELECT s.id, s.assignment_id, s.student_id, s.file_path, s.submitted_at,
                   u.name AS student_name, u.profile_picture AS student_profile_picture,
                   a.title AS assignment_title
            FROM submissions s
            JOIN users u ON u.id = s.student_id
            JOIN assignments a ON a.id = s.assignment_id
            WHERE a.class_id = ?
            ORDER BY s.submitted_at DESC
        ");
        $stmt_submissions->execute([$class_id]);
        $submissions = $stmt_submissions->fetchAll(PDO::FETCH_ASSOC);
        foreach ($submissions as $key => $s) {
            // ====================================================================
            // --- SANGAT PENTING: GANTI IP INI SESUAI TARGET ANDA ---
            $submissions[$key]['file_full_url'] = 'http://ruangbelajar.42web.io/api/' . $s['file_path'];
            // ====================================================================
            // URL Gambar Profil Siswa
            $student_profile_picture_url = '';
            if (!empty($s['student_profile_picture'])) {
                // ====================================================================
                // --- SANGAT PENTING: GANTI IP INI SESUAI TARGET ANDA ---
                $student_profile_picture_url = 'http://ruangbelajar.42web.io/api/' . $s['student_profile_picture'];
                // ====================================================================
            } else {
                // ====================================================================
                // --- SANGAT PENTING: GANTI IP INI SESUAI TARGET ANDA ---
                $student_profile_picture_url = 'http://ruangbelajar.42web.io/api/uploads/profiles/default.jpeg';
                // ====================================================================
            }
            $submissions[$key]['student_profile_picture_url'] = $student_profile_picture_url;
            unset($submissions[$key]['file_path']);
            unset($submissions[$key]['student_profile_picture']);
        }

        // --- Ambil Daftar Siswa di Kelas ---
        $stmt_students = $conn->prepare("
            SELECT u.id, u.name, u.email, u.profile_picture
            FROM users u
            JOIN class_students cs ON u.id = cs.student_id
            WHERE cs.class_id = ? AND u.role = 'student'
            ORDER BY u.name ASC
        ");
        $stmt_students->execute([$class_id]);
        $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
        foreach ($students as $key => $s) {
            $student_profile_picture_url = '';
            if (!empty($s['profile_picture'])) {
                // ====================================================================
                // --- SANGAT PENTING: GANTI IP INI SESUAI TARGET ANDA ---
                $student_profile_picture_url = 'http://ruangbelajar.42web.io/api/' . $s['profile_picture'];
                // ====================================================================
            } else {
                // ====================================================================
                // --- SANGAT PENTING: GANTI IP INI SESUAI TARGET ANDA ---
                $student_profile_picture_url = 'http://ruangbelajar.42web.io/api/uploads/profiles/default.jpeg';
                // ====================================================================
            }
            $students[$key]['profile_picture_url'] = $student_profile_picture_url;
            unset($students[$key]['profile_picture']);
        }

        // --- Ambil Pesan Chat ---
        $stmt_get_chat = $conn->prepare("
            SELECT cc.*, u.name, u.profile_picture
            FROM class_chat cc
            JOIN users u ON cc.user_id = u.id
            WHERE cc.class_id = ?
            ORDER BY cc.created_at ASC
        ");
        $stmt_get_chat->execute([$class_id]);
        $chats = $stmt_get_chat->fetchAll(PDO::FETCH_ASSOC);
        foreach ($chats as $key => $c) {
            $chat_profile_picture_url = '';
            if (!empty($c['profile_picture'])) {
                // ====================================================================
                // --- SANGAT PENTING: GANTI IP INI SESUAI TARGET ANDA ---
                $chat_profile_picture_url = 'http://ruangbelajar.42web.io/api/' . $c['profile_picture'];
                // ====================================================================
            } else {
                // ====================================================================
                // --- SANGAT PENTING: GANTI IP INI SESUAI TARGET ANDA ---
                $chat_profile_picture_url = 'http://ruangbelajar.42web.io/api/uploads/profiles/default.jpeg';
                // ====================================================================
            }
            $chats[$key]['profile_picture_url'] = $chat_profile_picture_url;
            unset($chats[$key]['profile_picture']);
        }

        $response = [
            'success' => true,
            'message' => 'Data kelas berhasil dimuat.',
            'class_info' => $class_data,
            'materials' => $materials,
            'assignments' => $assignments,
            'submissions' => $submissions,
            'students' => $students,
            'chats' => $chats
        ];

    } catch (PDOException $e) {
        error_log("Get Class Details PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat mengambil data kelas.'];
    } catch (Exception $e) {
        error_log("Get Class Details General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
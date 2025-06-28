<?php
// Izinkan akses dari origin mana pun
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json'); // Responsnya JSON
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Izinkan POST dan OPTIONS
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db.php'; // SESUAIKAN PATH INI KE db.php ANDA

$response = ['success' => false, 'message' => 'Terjadi kesalahan tidak dikenal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_id = $_POST['class_id'] ?? null;
    $student_id = $_POST['student_id'] ?? null; // ID siswa yang request
    $role = $_POST['role'] ?? null;

    if (empty($class_id) || empty($student_id) || $role !== 'student') {
        $response = ['success' => false, 'message' => 'Akses tidak diizinkan. ID kelas atau peran tidak valid.'];
        echo json_encode($response);
        exit();
    }

    try {
        // --- Verifikasi: Siswa ini harus terdaftar di kelas ini ---
        $stmt_verify_enrollment = $conn->prepare("SELECT COUNT(*) FROM class_students WHERE class_id = ? AND student_id = ?");
        $stmt_verify_enrollment->execute([$class_id, $student_id]);
        if ($stmt_verify_enrollment->fetchColumn() == 0) {
            $response = ['success' => false, 'message' => 'Anda tidak terdaftar di kelas ini.'];
            echo json_encode($response);
            exit();
        }

        // --- Ambil Data Kelas ---
        $stmt_class = $conn->prepare("SELECT id, name, description, code, image, teacher_id FROM classes WHERE id = ?");
        $stmt_class->execute([$class_id]);
        $class_data = $stmt_class->fetch(PDO::FETCH_ASSOC);

        if (!$class_data) {
            $response = ['success' => false, 'message' => 'Kelas tidak ditemukan.'];
            echo json_encode($response);
            exit();
        }

        // URL Gambar Kelas
        $class_image_url = '';
        if (!empty($class_data['image'])) {
            $class_image_url = 'http://ruangbelajar.42web.io/api//' . $class_data['image']; // Ganti IP dan domain
        } else {
            $class_image_url = 'http://ruangbelajar.42web.io/api/uploads/class_images/default.jpg'; // Ganti IP dan domain
        }
        $class_data['image_url'] = $class_image_url;
        unset($class_data['image']);

        // --- Ambil Materi ---
        $stmt_materials = $conn->prepare("SELECT id, title, file_path, uploaded_at FROM materials WHERE class_id = ? ORDER BY uploaded_at DESC");
        $stmt_materials->execute([$class_id]);
        $materials = $stmt_materials->fetchAll(PDO::FETCH_ASSOC);
        foreach ($materials as $key => $m) {
            $materials[$key]['file_full_url'] = 'http://10.0.2.2/classroom/' . $m['file_path']; // Ganti IP dan domain
            unset($materials[$key]['file_path']);
        }

        // --- Ambil Tugas dan Cek Status Pengiriman ---
        $stmt_assignments = $conn->prepare("SELECT id, title, description, deadline, created_at FROM assignments WHERE class_id = ? ORDER BY created_at DESC");
        $stmt_assignments->execute([$class_id]);
        $assignments = $stmt_assignments->fetchAll(PDO::FETCH_ASSOC);

        // Cek status pengiriman untuk setiap tugas oleh siswa ini
        foreach ($assignments as $key => $assignment) {
            $stmt_submission_status = $conn->prepare("SELECT COUNT(*) FROM submissions WHERE assignment_id = ? AND student_id = ?");
            $stmt_submission_status->execute([$assignment['id'], $student_id]);
            $assignments[$key]['submission_status'] = ($stmt_submission_status->fetchColumn() > 0) ? 'submitted' : 'not_submitted';
        }

        // --- Ambil Informasi Guru Pembuat Kelas ---
        $stmt_teacher_info = $conn->prepare("
            SELECT u.id, u.name, u.profile_picture FROM users u
            WHERE u.id = ? AND u.role = 'teacher'
        ");
        $stmt_teacher_info->execute([$class_data['teacher_id']]);
        $teacher_info = $stmt_teacher_info->fetch(PDO::FETCH_ASSOC);

        $teacher_profile_picture_url = '';
        if ($teacher_info && !empty($teacher_info['profile_picture'])) {
            $teacher_profile_picture_url = 'http://10.0.2.2/classroom/' . $teacher_info['profile_picture']; // Ganti IP
        } else {
            $teacher_profile_picture_url = 'http://10.0.2.2/classroom/uploads/profiles/default.jpeg'; // Ganti IP
        }
        if ($teacher_info) {
             $teacher_info['profile_picture_url'] = $teacher_profile_picture_url;
             unset($teacher_info['profile_picture']);
        }


        // --- Ambil Daftar Siswa di Kelas (termasuk diri sendiri) ---
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
                $student_profile_picture_url = 'http://10.0.2.2/classroom/' . $s['profile_picture']; // Ganti IP
            } else {
                $student_profile_picture_url = 'http://10.0.2.2/classroom/uploads/profiles/default.jpeg'; // Ganti IP
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
                $chat_profile_picture_url = 'http://10.0.2.2/classroom/' . $c['profile_picture']; // Ganti IP
            } else {
                $chat_profile_picture_url = 'http://10.0.2.2/classroom/uploads/profiles/default.jpeg'; // Ganti IP
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
            'teacher_info' => $teacher_info, // Informasi guru
            'students' => $students,
            'chats' => $chats
        ];

    } catch (PDOException $e) {
        error_log("Get Student Class Details PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat mengambil data kelas siswa.'];
    } catch (Exception $e) {
        error_log("Get Student Class Details General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
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
    $fileBase64 = $_POST['file_base64'] ?? '';
    $fileExt = $_POST['file_ext'] ?? 'jpg';

    if (empty($user_id) || empty($fileBase64)) {
        $response['message'] = 'Data tidak lengkap.';
        echo json_encode($response);
        exit();
    }

    try {
        // Ambil data user lama untuk menghapus foto lama
        $stmt_old_pic = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt_old_pic->execute([$user_id]);
        $old_profile_picture = $stmt_old_pic->fetchColumn();

        $decodedImage = base64_decode($fileBase64);
        if ($decodedImage === false) {
            $response['message'] = 'Data gambar tidak valid (Base64 decode gagal).';
            echo json_encode($response);
            exit();
        }

        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExt, $allowed_ext)) {
            $response['message'] = 'Jenis file gambar tidak diizinkan. Gunakan JPG, JPEG, PNG, atau GIF.';
            echo json_encode($response);
            exit();
        }

        $imageName = time() . '_' . uniqid() . '.' . $fileExt;
        $target_dir = __DIR__ . '/../uploads/profiles/'; // SESUAIKAN PATH INI ke folder uploads/profiles
        $target_file = $target_dir . $imageName;

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        if (file_put_contents($target_file, $decodedImage)) {
            $new_profile_picture_path_db = 'uploads/profiles/' . $imageName; // Path relatif untuk DB

            // Hapus foto lama jika bukan default dan file-nya ada
            if ($old_profile_picture && $old_profile_picture !== 'uploads/profiles/default.jpeg' && file_exists(__DIR__ . '/../' . $old_profile_picture)) {
                @unlink(__DIR__ . '/../' . $old_profile_picture);
            }

            // Update database
            $stmt_update = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            if ($stmt_update->execute([$new_profile_picture_path_db, $user_id])) {
                // GANTI IP INI SESUAI TARGET ANDA
                $new_profile_picture_url = 'http://ruangbelajar.42web.io/api/' . $new_profile_picture_path_db;

                $response = ['success' => true, 'message' => 'Foto profil berhasil diperbarui.', 'new_profile_picture_url' => $new_profile_picture_url];
            } else {
                $response = ['success' => false, 'message' => 'Gagal memperbarui foto profil di database.'];
            }
        } else {
            error_log("Failed to save Base64 file for profile: " . $imageName);
            $response = ['success' => false, 'message' => 'Gagal menyimpan file gambar di server.'];
        }

    } catch (PDOException $e) {
        error_log("Upload Profile Pic PDO Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan database saat mengunggah foto.'];
    } catch (Exception $e) {
        error_log("Upload Profile Pic General Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
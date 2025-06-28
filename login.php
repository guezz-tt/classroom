<?php
session_start(); // Tetap bisa digunakan untuk session di sisi server jika perlu, tapi aplikasi mobile tidak akan pakai ini
require_once __DIR__ . 'db.php'; // SESUAIKAN PATH INI KE db.php ANDA

// --- PENTING: CORS Headers untuk Aplikasi Mobile ---
header('Access-Control-Allow-Origin: *'); // Izinkan akses dari origin mana pun (untuk pengembangan)
header('Content-Type: application/json'); // Beri tahu bahwa responsnya JSON
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Izinkan metode POST dan OPTIONS
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Izinkan header yang mungkin dikirim

// Handle preflight request (khususnya untuk aplikasi mobile/browser modern)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Respon dengan OK
    exit(); // Hentikan eksekusi script PHP
}
// --- AKHIR CORS Headers ---

$response = ['success' => false, 'message' => 'Terjadi kesalahan tidak dikenal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Karena Volley Anda menggunakan getParams() yang mengirim data sebagai form-urlencoded
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $response = ['success' => false, 'message' => 'Email dan password harus diisi.'];
    } else {
        try {
            // Query ke database untuk mencari user
            $stmt = $conn->prepare("SELECT id, name, email, password, role, profile_picture FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    // Login berhasil
                    // Data yang dikirimkan ke aplikasi Android
                    $response = [
                        "success" => true,
                        "message" => "Login berhasil!",
                        "user" => [
                            "id" => $user['id'],
                            "name" => $user['name'],
                            "email" => $user['email'],
                            "role" => $user['role'],
                            "profile_picture" => $user['profile_picture'] // Tambahkan ini jika ada
                        ]
                    ];
                    // Anda bisa juga set session di sini jika server PHP Anda perlu mempertahankan sesi untuk API lain
                    // $_SESSION['user_id'] = $user['id'];
                    // $_SESSION['role'] = $user['role'];

                } else {
                    // Password salah
                    $response = ["success" => false, "message" => "Email atau password salah."];
                }
            } else {
                // Email tidak ditemukan
                $response = ["success" => false, "message" => "Email atau password salah."];
            }

        } catch (PDOException $e) {
            // Tangani error database
            error_log("Login PDO Error: " . $e->getMessage()); // Log detail error ke file log server
            $response = ["success" => false, "message" => "Terjadi kesalahan server database."];
        } catch (Exception $e) {
            // Tangani error umum
            error_log("Login General Error: " . $e->getMessage());
            $response = ["success" => false, "message" => "Terjadi kesalahan server."];
        }
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
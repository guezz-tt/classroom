<?php
// Izinkan akses dari origin mana pun (PENTING untuk pengembangan. Amankan di produksi!)
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json'); // Beri tahu bahwa responsnya JSON
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Hanya izinkan metode POST dan OPTIONS
header('Access-Control-Allow-Headers: Content-Type'); // Izinkan header Content-Type

// Handle preflight request (khususnya untuk aplikasi mobile/browser modern)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Sertakan file koneksi database Anda
require_once __DIR__ . '/db.php'; // SESUAIKAN PATH INI KE db.php ANDA

$response = ['success' => false, 'message' => 'Terjadi kesalahan tidak dikenal.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data POST
    $name     = $_POST['name'] ?? '';
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'student'; // Default role jika tidak dikirim

    // Validasi input dasar
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $response = ['success' => false, 'message' => 'Semua field harus diisi.'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ['success' => false, 'message' => 'Format email tidak valid.'];
    } elseif (strlen($password) < 6) { // Validasi panjang password
        $response = ['success' => false, 'message' => 'Password minimal 6 karakter.'];
    } else {
        try {
            // Cek apakah email sudah terdaftar
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt_check->bindParam(':email', $email);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Email sudah terdaftar. Silakan gunakan email lain.'];
            } else {
                // Hash password sebelum menyimpan
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert data pengguna baru
                $stmt_insert = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)");
                $stmt_insert->bindParam(':name', $name);
                $stmt_insert->bindParam(':email', $email);
                $stmt_insert->bindParam(':password', $hashed_password);
                $stmt_insert->bindParam(':role', $role);

                if ($stmt_insert->execute()) {
                    $response = ['success' => true, 'message' => 'Registrasi berhasil! Silakan login.'];
                } else {
                    $response = ['success' => false, 'message' => 'Registrasi gagal. Coba lagi.'];
                }
            }
        } catch (PDOException $e) {
            error_log("Register PDO Error: " . $e->getMessage()); // Log error ke server
            $response = ['success' => false, 'message' => 'Terjadi kesalahan database.'];
        } catch (Exception $e) {
            error_log("Register General Error: " . $e->getMessage());
            $response = ['success' => false, 'message' => 'Terjadi kesalahan server.'];
        }
    }
} else {
    $response = ['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan POST.'];
}

echo json_encode($response);
?>
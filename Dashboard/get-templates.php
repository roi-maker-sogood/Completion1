<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Not authenticated", "data" => []]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=LLogin;charset=utf8mb4", "root", "");
    $stmt = $pdo->prepare("SELECT id, template_name, file_path, uploaded_at FROM custom_templates WHERE user_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["status" => "success", "data" => $templates]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
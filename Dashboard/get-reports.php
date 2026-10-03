<?php
session_start();
// Set header to return JSON responses
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated', 'reports' => []]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=LLogin;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT report_data FROM reports WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reports = [];
    foreach ($rows as $row) {
        // Decode the stored JSON string back into a proper PHP/JavaScript object structure
        $reportData = json_decode($row['report_data'], true);
        if ($reportData) {
            $reports[] = $reportData;
        }
    }

    echo json_encode([
        'success' => true,
        'reports' => $reports
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'reports' => []
    ]);
}
?>
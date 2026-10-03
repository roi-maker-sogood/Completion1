<?php
session_start();
// Set header to return JSON responses
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// 1. Read the incoming raw JSON string sent from fetch()
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid or empty JSON received.']);
    exit;
}

// 2. Extract key fields for quick database sorting/columns
$reportId    = $data['id'] ?? '';
$clientName  = $data['cover']['clientName'] ?? 'Untitled Client';
$monthYear   = ($data['cover']['month'] ?? '') . ' ' . ($data['cover']['year'] ?? '');
$fullJson    = json_encode($data); // Stores your entire application state JSON package

try {
    $pdo = new PDO("mysql:host=localhost;dbname=LLogin;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT id, report_data FROM reports WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $existingId = null;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stored = json_decode($row['report_data'], true);
        if (($stored['id'] ?? '') === $reportId) {
            $existingId = $row['id'];
            break;
        }
    }
    
    if ($existingId !== null) {
        $update = $pdo->prepare("UPDATE reports SET title = ?, report_data = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $update->execute([$monthYear ?: $clientName, $fullJson, $existingId, $_SESSION['user_id']]);
    } else {
        $insert = $pdo->prepare("INSERT INTO reports (user_id, title, report_data) VALUES (?, ?, ?)");
        $insert->execute([$_SESSION['user_id'], $monthYear ?: $clientName, $fullJson]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}

// 4. Send a success response back to your JavaScript toast notification
echo json_encode([
    'success' => true,
    'message' => 'Saved to database successfully for: ' . ($clientName ?: 'Client')
]);
?>
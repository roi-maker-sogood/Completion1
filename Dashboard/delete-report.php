<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$reportId = is_array($data) ? trim((string) ($data['id'] ?? '')) : '';

if ($reportId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing report id']);
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=LLogin;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $select = $pdo->prepare('SELECT id, report_data FROM reports WHERE user_id = ?');
    $select->execute([$_SESSION['user_id']]);

    $databaseId = null;
    foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $report = json_decode($row['report_data'], true);
        if (is_array($report) && (string) ($report['id'] ?? '') === $reportId) {
            $databaseId = (int) $row['id'];
            break;
        }
    }

    if ($databaseId === null) {
        echo json_encode(['success' => false, 'message' => 'Draft not found']);
        exit;
    }

    $delete = $pdo->prepare('DELETE FROM reports WHERE id = ? AND user_id = ?');
    $delete->execute([$databaseId, $_SESSION['user_id']]);

    echo json_encode(['success' => true, 'message' => 'Draft deleted']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

<?php
require_once 'includes/database.php';
session_start();

// Garante que apenas usuários logados possam acessar
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    echo json_encode(['error' => 'Acesso negado. Faça o login.']);
    exit();
}

$crbm = isset($_GET['crbm']) ? $_GET['crbm'] : '';

if (empty($crbm)) {
    http_response_code(400); // Requisição Inválida
    echo json_encode(['error' => 'CRBM não fornecido.']);
    exit();
}

$pilotos = [];
// CORREÇÃO: Usa a ordenação hierárquica correta, igual à usada em outras listagens de pilotos.
$sql = "SELECT id, nome_completo, posto_graduacao 
        FROM pilotos 
        WHERE status_piloto = 'ativo' AND crbm_piloto = ? 
        ORDER BY
            CASE posto_graduacao
                WHEN 'Cel. QOBM' THEN 1
                WHEN 'Ten. Cel. QOBM' THEN 2
                WHEN 'Maj. QOBM' THEN 3
                WHEN 'Cap. QOBM' THEN 4
                WHEN '1º Ten. QOBM' THEN 5
                WHEN '2º Ten. QOBM' THEN 6
                WHEN 'Asp. Oficial' THEN 7
                WHEN 'Sub. Ten. QPBM' THEN 8
                WHEN '1º Sgt. QPBM' THEN 9
                WHEN '2º Sgt. QPBM' THEN 10
                WHEN '3º Sgt. QPBM' THEN 11
                WHEN 'Cb. QPBM' THEN 12
                WHEN 'Sd. QPBM' THEN 13
                ELSE 14
            END,
            nome_completo ASC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $crbm);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $pilotos[] = [
            'id' => $row['id'],
            'nome_completo' => htmlspecialchars($row['posto_graduacao'] . ' ' . $row['nome_completo'])
        ];
    }
    $stmt->close();
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($pilotos);
?>
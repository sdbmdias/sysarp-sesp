<?php
session_start();
$message = "";
$message_type = "";
$token_valido = false;
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $message = "Token de redefinição não fornecido. Por favor, use o link enviado para o seu e-mail.";
    $message_type = "error";
} else {
    // Configurações do banco de dados (ajustado para o banco de dados principal)
    $servername = "localhost";
    $username = "flyltm00_soarp";
    $password = "$1JKLjkl1$123";
    $dbname = "flyltm00_soarp";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    // Verifica o token e se ele não expirou
    $stmt = $conn->prepare("SELECT id FROM pilotos WHERE reset_token = ? AND reset_token_expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $token_valido = true;

        // Processa o formulário de nova senha
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $senha = $_POST['senha'];
            $senha_confirm = $_POST['senha_confirm'];

            if ($senha !== $senha_confirm) {
                $message = "As senhas não coincidem. Tente novamente.";
                $message_type = "error";
            } elseif (strlen($senha) < 6) { // Regra de senha simples
                $message = "A senha deve ter pelo menos 6 caracteres.";
                $message_type = "error";
            } else {
                // Tudo certo, atualiza a senha e invalida o token
                $nova_senha_hashed = password_hash($senha, PASSWORD_DEFAULT);
                
                $update_stmt = $conn->prepare("UPDATE pilotos SET senha = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
                $update_stmt->bind_param("si", $nova_senha_hashed, $user_id);
                
                if ($update_stmt->execute()) {
                    $message = "Sua senha foi redefinida com sucesso! Você já pode fazer o login com a nova senha.";
                    $message_type = "success";
                    $token_valido = false; // Oculta o formulário após o sucesso
                } else {
                    $message = "Ocorreu um erro ao atualizar sua senha. Tente novamente.";
                    $message_type = "error";
                }
                $update_stmt->close();
            }
        }
    } else {
        $message = "Link inválido ou expirado. Por favor, solicite um novo link de recuperação.";
        $message_type = "error";
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Redefinir Senha</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* (CSS similar ao da página 'esqueci_senha.php') */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f0f2f5; color: #333; }
        .recovery-container { background-color: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); width: 400px; text-align: center; }
        .recovery-container h1 { color: #2c3e50; margin-bottom: 15px; font-size: 26px; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        .input-group input { width: calc(100% - 22px); padding: 12px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 16px; color: #333; }
        .input-group input:focus { border-color: #3498db; outline: none; box-shadow: 0 0 5px rgba(52, 152, 219, 0.5); }
        .action-button { background-color: #3498db; color: white; padding: 14px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 18px; width: 100%; transition: background-color 0.3s ease; }
        .action-button:hover { background-color: #2980b9; }
        .message-box { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: left; }
        .message-box.success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
        .message-box.error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .back-link { display: block; margin-top: 20px; font-size: 14px; color: #555; text-decoration: none; }
        .back-link:hover { text-decoration: underline; color: #3498db; }
    </style>
</head>
<body>
    <div class="recovery-container">
        <h1>Redefinir Senha</h1>

        <?php if (!empty($message)): ?>
            <div class="message-box <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($token_valido): ?>
            <form action="redefinir_senha.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
                <div class="input-group">
                    <label for="senha"><i class="fas fa-lock"></i> Nova Senha:</label>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua nova senha" required>
                </div>
                <div class="input-group">
                    <label for="senha_confirm"><i class="fas fa-lock"></i> Confirme a Nova Senha:</label>
                    <input type="password" id="senha_confirm" name="senha_confirm" placeholder="Confirme sua nova senha" required>
                </div>
                <button type="submit" class="action-button">Salvar Nova Senha</button>
            </form>
        <?php else: ?>
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar para o Login</a>
        <?php endif; ?>
    </div>
</body>
</html>
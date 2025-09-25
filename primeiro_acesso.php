<?php
session_start();

// Se o usuário não estiver logado ou não for forçado a redefinir a senha, redireciona.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['force_password_reset'])) {
    header('Location: index.php');
    exit();
}

$message = "";
$message_type = ""; // 'success' ou 'error'

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $senha = $_POST['senha'];
    $senha_confirm = $_POST['senha_confirm'];
    $user_id = $_SESSION['user_id'];

    if (empty($senha) || empty($senha_confirm)) {
        $message = "Por favor, preencha ambos os campos de senha.";
        $message_type = "error";
    } elseif ($senha !== $senha_confirm) {
        $message = "As senhas não coincidem. Tente novamente.";
        $message_type = "error";
    } elseif (strlen($senha) < 6) {
        $message = "A senha deve ter no mínimo 6 caracteres.";
        $message_type = "error";
    } else {
        // Tudo certo para atualizar a senha
        $nova_senha_hashed = password_hash($senha, PASSWORD_DEFAULT);

        // Configurações do banco de dados
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "drones_db";

        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            die("Falha na conexão: " . $conn->connect_error);
        }

        // Atualiza a senha e marca a redefinição como concluída (senha_redefinida = 1)
        $stmt = $conn->prepare("UPDATE pilotos SET senha = ?, senha_redefinida = 1 WHERE id = ?");
        $stmt->bind_param("si", $nova_senha_hashed, $user_id);

        if ($stmt->execute()) {
            // Sucesso! Remove a flag da sessão e redireciona para o dashboard
            unset($_SESSION['force_password_reset']);
            header('Location: dashboard.php');
            exit();
        } else {
            $message = "Ocorreu um erro ao atualizar sua senha. Tente novamente.";
            $message_type = "error";
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Primeiro Acesso</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f0f2f5; color: #333; }
        .container { background-color: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); width: 400px; text-align: center; }
        .container h1 { color: #2c3e50; margin-bottom: 15px; font-size: 26px; }
        .container p.instructions { color: #555; margin-bottom: 30px; font-size: 16px; line-height: 1.5; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        .input-group input { width: calc(100% - 22px); padding: 12px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 16px; color: #333; }
        .input-group input:focus { border-color: #3498db; outline: none; box-shadow: 0 0 5px rgba(52, 152, 219, 0.5); }
        .action-button { background-color: #28a745; color: white; padding: 14px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 18px; width: 100%; transition: background-color 0.3s ease; }
        .action-button:hover { background-color: #218838; }
        .message-box { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: left; }
        .message-box.error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .logout-link { display: inline-block; margin-top: 20px; font-size: 14px; color: #555; text-decoration: none; }
        .logout-link:hover { text-decoration: underline; color: #e74c3c; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Primeiro Acesso</h1>
        <p class="instructions">
            Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['user_name']); ?>! Por segurança, você deve criar uma nova senha pessoal para continuar.
        </p>

        <?php if (!empty($message)): ?>
            <div class="message-box <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form action="primeiro_acesso.php" method="POST">
            <div class="input-group">
                <label for="senha"><i class="fas fa-lock"></i> Nova Senha:</label>
                <input type="password" id="senha" name="senha" placeholder="Mínimo de 6 caracteres" required>
            </div>
            <div class="input-group">
                <label for="senha_confirm"><i class="fas fa-lock"></i> Confirme a Nova Senha:</label>
                <input type="password" id="senha_confirm" name="senha_confirm" placeholder="Repita a nova senha" required>
            </div>
            <button type="submit" class="action-button">Salvar Senha e Acessar</button>
        </form>
        
        <a href="index.php" class="logout-link">Sair</a>
    </div>
</body>
</html>
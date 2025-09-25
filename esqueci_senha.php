<?php
// Inclui as classes do PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Se estiver usando Composer, o autoload.php faz o trabalho.
// Se não, você precisará dar o require nos arquivos da biblioteca manualmente.
require 'vendor/autoload.php'; // Ajuste o caminho se necessário

session_start();
$message = "";
$message_type = ""; // 'success' ou 'error'

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Configurações do banco de dados
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "drones_db";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    $identifier = $conn->real_escape_string($_POST['identifier']);

    // Busca o piloto pelo CPF ou E-mail
    $sql = "SELECT id, email, nome_completo FROM pilotos WHERE cpf = '$identifier' OR email = '$identifier'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Gera um token seguro
        $token = bin2hex(random_bytes(32));
        
        // Define o tempo de expiração (ex: 1 hora a partir de agora)
        $expires_at = new DateTime();
        $expires_at->add(new DateInterval('PT1H'));
        $expires_at_str = $expires_at->format('Y-m-d H:i:s');

        // Salva o token e a data de expiração no banco de dados
        $stmt = $conn->prepare("UPDATE pilotos SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
        $stmt->bind_param("ssi", $token, $expires_at_str, $user['id']);
        
        if ($stmt->execute()) {
            // Lógica de envio de e-mail com PHPMailer
            $mail = new PHPMailer(true);
            try {
                // Configurações do Servidor SMTP (substitua com seus dados)
                $mail->isSMTP();
                $mail->Host       = 'smtp.example.com'; // Ex: smtp.gmail.com
                $mail->SMTPAuth   = true;
                $mail->Username   = 'seu-email@example.com'; // Seu e-mail de envio
                $mail->Password   = 'sua-senha-de-app';    // Sua senha (ou senha de aplicativo)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Destinatários
                $mail->setFrom('seu-email@example.com', 'SOARP - CBMPR');
                $mail->addAddress($user['email'], $user['nome_completo']);

                // Conteúdo do E-mail
                $reset_link = "http://localhost/seu_projeto/redefinir_senha.php?token=" . $token; // Altere para o URL real do seu site
                
                $mail->isHTML(true);
                $mail->Subject = 'Recuperacao de Senha - SOARP';
                $mail->Body    = "Olá " . $user['nome_completo'] . ",<br><br>Recebemos uma solicitação para redefinir sua senha no sistema SOARP. Clique no link abaixo para criar uma nova senha:<br><br><a href='" . $reset_link . "'>" . $reset_link . "</a><br><br>Este link é válido por 1 hora.<br><br>Se você não solicitou isso, por favor, ignore este e-mail.<br><br>Atenciosamente,<br>Equipe SOARP - CBMPR";
                $mail->AltBody = "Para redefinir sua senha, copie e cole este link em seu navegador: " . $reset_link;

                $mail->send();
                $message = 'Se uma conta para os dados informados existir, um e-mail de recuperação foi enviado.';
                $message_type = 'success';

            } catch (Exception $e) {
                $message = "Não foi possível enviar o e-mail. Mailer Error: {$mail->ErrorInfo}";
                $message_type = 'error';
            }
        } else {
            $message = "Erro ao gerar o token de recuperação. Tente novamente.";
            $message_type = 'error';
        }
        $stmt->close();
    } else {
        // Mensagem genérica por segurança, não informa se o usuário existe ou não
        $message = 'Se uma conta para os dados informados existir, um e-mail de recuperação foi enviado.';
        $message_type = 'success';
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Recuperação de Senha</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* (CSS da página 'esqueci_senha.php' já fornecido anteriormente) */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f0f2f5; color: #333; }
        .recovery-container { background-color: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); width: 400px; text-align: center; }
        .recovery-container h1 { color: #2c3e50; margin-bottom: 15px; font-size: 26px; }
        .recovery-container p.instructions { color: #555; margin-bottom: 30px; font-size: 16px; line-height: 1.5; }
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
        <h1>Recuperação de Senha</h1>
        
        <?php if (empty($message)): ?>
            <p class="instructions">
                Insira seu CPF ou e-mail cadastrado abaixo para receber um link de redefinição de senha.
            </p>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <p class="message-box <?php echo $message_type; ?>"><?php echo $message; ?></p>
        <?php endif; ?>

        <form action="esqueci_senha.php" method="POST">
            <div class="input-group">
                <label for="identifier"><i class="fas fa-user-circle"></i> CPF ou E-mail:</label>
                <input type="text" id="identifier" name="identifier" placeholder="Digite seu CPF ou e-mail" required>
            </div>
            <button type="submit" class="action-button">Enviar Link de Recuperação</button>
        </form>

        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar para o Login</a>
    </div>
</body>
</html>
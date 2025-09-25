<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require_once 'includes/database.php';

// Iniciar a sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = $_POST['identifier'];

    // Usar prepared statements para prevenir SQL Injection
    $sql = "SELECT id, email, nome_completo FROM pilotos WHERE cpf = ? OR email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $email = $user['email'];
        $nome_completo = $user['nome_completo'];

        $token = bin2hex(random_bytes(50));
        $expires = date("U") + 1800; // Token expira em 30 minutos

        // Deletar tokens antigos para o mesmo usuário
        $sql = "DELETE FROM password_resets WHERE user_id = ?";
        $stmt_delete = $conn->prepare($sql);
        $stmt_delete->bind_param("i", $user_id);
        $stmt_delete->execute();
        
        // Inserir novo token
        $sql = "INSERT INTO password_resets (user_id, token, expires) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($sql);
        $stmt_insert->bind_param("iss", $user_id, $token, $expires);
        
        if ($stmt_insert->execute()) {
            $mail = new PHPMailer(true);

            try {
                // Configurações do servidor de e-mail
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'seu_email@gmail.com'; // Substituir pelo seu e-mail
                $mail->Password   = 'sua_senha_de_app'; // Substituir pela sua senha de app
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Destinatários
                $mail->setFrom('seu_email@gmail.com', 'Sistema de Aeronaves Remotamente Pilotadas');
                $mail->addAddress($email, $nome_completo);

                // Conteúdo do e-mail
                $mail->isHTML(true);
                $mail->Subject = 'Redefinicao de Senha';
                $reset_link = "http://localhost/sysarp/redefinir_senha.php?token=" . $token;
                $mail->Body    = "Olá $nome_completo,<br><br>Clique no link a seguir para redefinir sua senha: <a href='$reset_link'>$reset_link</a><br><br>Se você não solicitou a redefinição de senha, por favor, ignore este e-mail.";
                $mail->AltBody = "Olá $nome_completo,\n\nCopie e cole o seguinte link em seu navegador para redefinir sua senha: $reset_link\n\nSe você não solicitou a redefinição de senha, por favor, ignore este e-mail.";

                $mail->send();
                $message = 'Um e-mail foi enviado para você com instruções para redefinir sua senha.';
            } catch (Exception $e) {
                $message = "O e-mail não pôde ser enviado. Erro do Mailer: {$mail->ErrorInfo}";
            }
        } else {
            $message = "Erro ao salvar o token de redefinição de senha.";
        }
    } else {
        $message = "Nenhum usuário encontrado com este CPF ou e-mail.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Esqueci Minha Senha - SARP</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body {
            background-image: url('background_image.png');
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo img {
            max-width: 150px;
        }

        h2 {
            text-align: center;
            color: #333;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-bottom: 5px;
            color: #555;
        }

        input[type="text"] {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        input[type="submit"] {
            padding: 10px;
            border: none;
            border-radius: 5px;
            background-color: #007bff;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #0056b3;
        }

        .message {
            text-align: center;
            margin-top: 15px;
            color: #333;
        }

        a {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #007bff;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="logo_soarp.png" alt="Logo SARP">
        </div>
        <h2>Esqueci Minha Senha</h2>
        <form action="esqueci_senha.php" method="post">
            <label for="identifier">CPF ou E-mail:</label>
            <input type="text" id="identifier" name="identifier" required>
            <input type="submit" value="Redefinir Senha">
        </form>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <a href="index.php">Voltar para o Login</a>
    </div>
</body>
</html>
<?php
/**
 * Arquivo de configuração do banco de dados.
 * Estabelece a conexão com o MySQL usando mysqli.
 */

// Configurações do banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "drones_db";

// Cria a conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Define o charset para UTF-8 para evitar problemas com acentuação
$conn->set_charset("utf8");

// Verifica se a conexão falhou
if ($conn->connect_error) {
    // Interrompe a execução e exibe uma mensagem de erro genérica por segurança
    die("Falha na conexão com o banco de dados. Por favor, tente novamente mais tarde.");
}
?>
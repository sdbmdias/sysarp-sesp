<?php
echo "Iniciando o teste...<br>";

// 1. Define o caminho para o autoload.php
$autoload_path = __DIR__ . '/vendor/autoload.php';
echo "Caminho do autoloader: " . $autoload_path . "<br>";

// 2. Verifica se o ficheiro existe
if (!file_exists($autoload_path)) {
    die("ERRO: O ficheiro 'autoload.php' não foi encontrado no caminho especificado. Verifique se a pasta 'vendor' existe e se a instalação do Composer foi concluída.");
}

// 3. Inclui o autoloader
require_once $autoload_path;
echo "Autoloader incluído com sucesso.<br>";

// 4. Tenta usar a classe Mpdf
echo "Tentando usar a classe Mpdf...<br>";

if (class_exists(\Mpdf\Mpdf::class)) {
    echo "SUCESSO! A classe Mpdf foi encontrada e está pronta para ser usada.";
} else {
    echo "FALHA: A classe Mpdf não foi encontrada, mesmo após incluir o autoloader. Verifique a sua instalação do Composer e a configuração do PHP.";
}

?>
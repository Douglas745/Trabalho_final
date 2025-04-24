<?php
// Verifica se é admin (exemplo simples)
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    die("Acesso negado.");
}

// Configurações
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'final';
$backup_dir = __DIR__ . '/backups/';
$data = date("Y-m-d_H-i-s");
$nome_arquivo = $backup_dir . "backup_{$database}_{$data}.sql";

// Cria diretório
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Caminho para mysqldump
$mysqldump = '"C:\\xampp\\mysql\\bin\\mysqldump.exe"';
$comando = "$mysqldump --user=$username --password=$password --host=$host $database > \"$nome_arquivo\"";

system($comando, $resultado);

if ($resultado === 0) {
    echo "✅ Backup criado com sucesso: $nome_arquivo";
} else {
    echo "❌ Erro ao criar o backup.";
}
?>

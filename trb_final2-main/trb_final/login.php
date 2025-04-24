<?php
session_start();
require_once 'db.php';

// Função para obter IP do cliente
function getClientIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']))
        return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

// Função para registrar logs de auditoria
function registrar_log($conn, $usuario_id, $acao)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'IP desconhecido';
    $navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'Navegador desconhecido';

    $stmt = $conn->prepare("INSERT INTO logs (usuario_id, acao, ip, navegador) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $usuario_id, $acao, $ip, $navegador);
    $stmt->execute();
}

$ip = getClientIP();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $senha = $_POST['senha'];

    // Verifica tentativas de login nos últimos 3 minutos
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as total 
        FROM login_tentativas 
        WHERE ip_address = ? 
        AND tentativa_time > (NOW() - INTERVAL 3 MINUTE)
    ");

    if (!$stmt) {
        die("Erro na preparação da query de verificação de tentativas: " . $mysqli->error);
    }

    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data['total'] >= 3) {
        $_SESSION['error'] = "Muitas tentativas de login. Tente novamente em alguns minutos.";
        header('Location: login.php');
        exit();
    } elseif ($data['total'] == 2) {
        $_SESSION['error'] = "Última tentativa! Após isso, o acesso será temporariamente bloqueado.";
    } elseif ($data['total'] == 1) {
        $_SESSION['error'] = "Restam mais 2 tentativas.";
    }

    // Consulta usuário no banco de dados
    $stmt = $mysqli->prepare("SELECT * FROM usuarios WHERE username = ?");
    if (!$stmt) {
        die("Erro na preparação da query de login: " . $mysqli->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($senha, $row['senha'])) {
            // Login bem-sucedido
            $_SESSION['userid'] = $row['id'];
            $_SESSION['username'] = $row['username'];

            // Limpa tentativas após login bem-sucedido
            $del = $mysqli->prepare("DELETE FROM login_tentativas WHERE ip_address = ?");
            if ($del) {
                $del->bind_param("s", $ip);
                $del->execute();
            }

            // Registra login bem-sucedido nos logs
            registrar_log($mysqli, $row['id'], "Login bem-sucedido");

            // Redireciona ao dashboard ou para autenticação 2FA
            if ($row['autenticacao_habilitada']) {
                // Redirecionar para página de 2FA se necessário
            }

            header('Location: dashboard.php');
            exit();
        } else {
            $_SESSION['error'] = "Senha incorreta!";

            // Log de tentativa com senha incorreta
            registrar_log($mysqli, null, "Tentativa de login com senha incorreta para usuario: $username");
        }
    } else {
        $_SESSION['error'] = "Usuário não encontrado!";

        // Log de tentativa com usuário inexistente
        registrar_log($mysqli, null, "Tentativa de login com usuario inexistente: $username");
    }

    // Registra tentativa falha
    $stmt = $mysqli->prepare("INSERT INTO login_tentativas (ip_address, tentativa_time) VALUES (?, NOW())");
    if ($stmt) {
        $stmt->bind_param("s", $ip);
        $stmt->execute();
    }

    header('Location: login.php');
    exit();
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/css.css">
    <title>Login de Usuário</title>
</head>

<body>
    <h2>Login de Usuário</h2>

    <form action="login.php" method="post">
        <label for="username">Nome de Usuário:</label><br>
        <input type="text" id="username" name="username" required><br><br>
        <label for="senha">Senha:</label><br>
        <input type="password" id="senha" name="senha"  required> 
        <button type="button" onclick="mostrarSenha()">👁️</button>
        <br><br>
        
        <input type="submit" value="Login">
    </form>

    <form action="rec_senha.php" method="get">
        <button type="submit">recuperar senha</button>
    </form>

    <form action="register.php" method="get" style="margin-top: 20px;">
        <button type="submit"
            style="padding: 10px 15px; background: #555; color: white; border: none; border-radius: 4px; cursor: pointer;">registrar-se</button>
    </form>

    <a href="index.html" class="login-btn">SAIR</a>



    <?php if (isset($_SESSION['error'])): ?>
        <script>
            alert("<?= addslashes($_SESSION['error']) ?>");
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    
<script>
function mostrarSenha() {
  var input = document.getElementById("senha");
  if (input.type === "password") {
    input.type = "text";
  } else {
    input.type = "password";
  }
}
</script>

</body>

</html>
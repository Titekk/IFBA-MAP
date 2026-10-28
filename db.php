<?php

$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "banco_IFBA";

$conexao= new mysqli($host, $user, $pass, $db);
$conexao->set_charset("utf8mb4");

if ($conexao->connect_error) {
    die("Falha na conexão: " . $conexao->connect_error);
}

?>

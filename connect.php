<?php
// $serverName = "DESKTOP-858G2H7\SQLEXPRESS";
// $database = "menshakova_publicUtilities";
// $username = "sa";
// $password = "Student1234";

// try {
//     $connect = new PDO("sqlsrv:Server=$serverName;Database=$database", $username, $password);
//     $connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//     echo "Соединение успешно установлено";
// } catch (PDOException $e) {
//     echo "Ошибка соединения: " . $e->getMessage();
// }
$serverName = "DESKTOP-858G2H7\SQLEXPRESS";
$connectionOptions = array(
    "Database" => "menshakova_publicUtilities",
    "Uid" => "sa",
    "PWD" => "Student1234"
);
// Установка соединения
$connect = sqlsrv_connect($serverName, $connectionOptions);
if ($connect === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>
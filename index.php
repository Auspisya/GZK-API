<?php
    header("Content-Type:application/json");
    require_once 'connect.php';
    require_once 'functions.php';
    $actionMethod = $_SERVER['REQUEST_METHOD'];
    $paramUrl = explode("/", $_GET['q']);
    $typeUrl = $paramUrl[0];
    // $typeId = $paramUrl[1];
    // $property = $paramUrl[2];

    switch ($actionMethod) {
        case 'GET':
                switch ($typeUrl) {
                    case 'sign-in':
                        auth($connect, $_GET['login'], $_GET['password']);
                        break;
                    default:
                        http_response_code(404);
                        echo "Данный метод в API не используется. Интерфейс не реализован.";     
                        break;
                }
                break;
        default:
        http_response_code(404);
        echo "Данный метод в API не используется. Интерфейс не реализован.";     
        break;
    }
?>

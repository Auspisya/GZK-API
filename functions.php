<?php
$memcache_obj = new Memcached();
$memcache_obj->addServer("127.0.0.1", 11211);
$attempts = $memcache_obj->get('attempts');
$block_time = $memcache_obj->get('block_time');
$allAttempts = $memcache_obj->get('allAttempts');
function auth($connect, $login, $password)
{
    global $memcache_obj;
    global $attempts;
    global $block_time;
    global $idUser;
    global $allAttempts;
    if (strlen($password) > 25)
    {
        $response = [
            "status" => false,
            "description" => 'Вы превысили максимальную длину пароля!'
        ];
        http_response_code(403);
        echo json_encode($response);
        exit;
    }
    if (strlen($login) > 25)
    {
        $response = [
            "status" => false,
            "description" => 'Вы превысили максимальную длину логина!'
        ];
        http_response_code(403);
        echo json_encode($response);
        exit;
    }
    if ($login === "")
    {
        $response = [
            "status" => false,
            "description" => 'Логин не может быть пустым.'
        ];
        http_response_code(403);
        echo json_encode($response);
        exit;
    }
    if ($password === "")
    {
        $response = [
            "status" => false,
            "description" => 'Пароль не может быть пустым.'
        ];
        http_response_code(403);
        echo json_encode($response);
        exit;
    }
    if ($block_time && time() < $block_time) {
        $response = [
            "status" => false,
            "description" => 'Слишком много неудачных попыток входа. Попробуйте снова через ' . ($block_time - time()) . ' секунд.'
        ];
        http_response_code(429);
        echo json_encode($response);
        exit;
    }
    $hash = strtoupper(md5($password));
    $q = "SELECT [User].id AS UserId, [UserRole].roleName AS RoleName, [User].userStatusId AS StatusId
    FROM [User] INNER JOIN [UserRole] ON [User].userRoleId = [UserRole].id
    WHERE [User].login = ? AND [User].password = ? COLLATE SQL_Latin1_General_CP1_CS_AS";
    $params = array($login, $hash);
    $user = sqlsrv_query($connect, $q, $params);
    if ($user === false) { 
        http_response_code(404);
        $response = [
            "status" => false,
            "description" => print_r(sqlsrv_errors(), true)
        ];
        echo print_r(sqlsrv_errors(), true);
        return;
    }
    $data = sqlsrv_fetch_array($user, SQLSRV_FETCH_ASSOC);
    if($data == null){
        $response = [
            "status" => false,
            "description" => "Такого пользователя нет.",
        ];
        if ($attempts == false)
        {$attempts = 1;}
        else {$attempts++;}
        $allAttempts++;
        if ($attempts >= 3) {
            $attempts = 0;
            $block_time = time() + 60; 
        }
        $response["cnt"] = $attempts;
        http_response_code(404);
        echo json_encode($response);
    }
    else{
        if ($data["StatusId"] == 3)
        {
            http_response_code(403);
            $response = [
                "status" => false,
                "description" => "Пользователь заблокирован."
            ];
            echo json_encode($response);
        }
        else
        {
            if ($allAttempts > 30)
            {
                $idUser = $data['UserId'];
                http_response_code(403);
                $changeStatus = "UPDATE [User] SET [User].userStatusId = ? WHERE [User].id = ?";
                $statusParams = array(3, $idUser);
                sqlsrv_query($connect, $changeStatus, $statusParams);
                $response = [
                    "status" => false,
                    "description" => "Превышено максимальное количество попыток за последние 4 дня. Пользователь заблокирован."
                ];
                echo json_encode($response);
            }
            else 
            {

                $qSumAttempts = "SELECT SUM(authAttempts) AS AuthAttempts from [Session] WHERE userId = ? and sessionStart >= DATEADD (day, -4, getdate())";
                $qSumParams = array($idUser);
                $qSum = sqlsrv_query($connect, $qSumAttempts, $qSumParams);
                $qSumData = sqlsrv_fetch_array($qSum, SQLSRV_FETCH_ASSOC);
                if (($qSumData['AuthAttempts'] + $allAttempts) > 30)
                {
                    http_response_code(403);
                    $changeStatus = "UPDATE [User] SET [User].userStatusId = ? WHERE [User].id = ?";
                    $statusParams = array(3, $idUser);
                    sqlsrv_query($connect, $changeStatus, $statusParams);
                    $response = [
                        "status" => false,
                        "description" => "Превышено максимальное количество попыток за последние 4 дня. Пользователь заблокирован."
                    ];
                    echo json_encode($response);
                    sqlsrv_free_stmt($qSum);
                }
                else
                {
                    http_response_code(200);
                    $idUser = $data['UserId'];
                    $sessionStart = date('Y-d-m H:i:s');
                    $addSession = "INSERT INTO [Session](userId, sessionStart, authAttempts) VALUES (?, ?, ?)";
                    $sessionParams = array($idUser, $sessionStart, $allAttempts);
                    sqlsrv_query($connect, $addSession, $sessionParams);
                    $changeStatus = "UPDATE [User] SET [User].userStatusId = ? WHERE [User].id = ?";
                    $statusParams = array(1, $idUser);
                    sqlsrv_query($connect, $changeStatus, $statusParams);
                    $allAttempts = 0;
                    $response = [
                        "status" => true,
                        "description" => "Авторизация прошла успешно!",
                        "RoleUser" => $data["RoleName"],
                        "IdUser" => $data["UserId"]
                    ];
                    echo json_encode($response);
                }
            }
        }
    }
    $memcache_obj->set('attempts', $attempts);
    $memcache_obj->set('block_time', $block_time);
    $memcache_obj->set('allAttempts', $allAttempts);
    sqlsrv_free_stmt($user);
}
?>

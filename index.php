<?php
// Принимаем запрос
$data = json_decode(file_get_contents('php://input'), TRUE);
/*file_put_contents('file.txt', '$data: '.print_r($data, 1)."\n", FILE_APPEND);*/

// Обрабатываем ручной ввод или нажатие на кнопку
$func = $data['callback_query'] ? $data['callback_query'] : $data['message'];

// Важные константы
define('TOKEN', '6029265853:AAFd8vC7iBY2RpOcr9w_o89UsPswCH9GZfo');
define('BOTID', '6029265853');
$lastBotMessage = GetLastBotMessage($data);
// Записываем сообщение пользователя
$message = mb_strtolower(($func['text'] ? $func['text'] : $func['data']),'utf-8');

$emptySkillCategoryButtonText = "Вы выбрали все навыки из этой категории.";
$emptyIntsCategoryButtonText  = "Тут больше нет интересов.";
$revealCost = 1;
include('bd.php');

// Команда start запускает проверку
if (strpos($message, '/start') !== false) {
    // Проверяем есть ли такой пользователь по id пользователя
    $user = $data['message']['from']['id'];
    $chatCheck = mysqli_query ($con, "SELECT `userID` FROM `MainInfo` WHERE userID='".$user."' ");
    $chatID = mysqli_fetch_array($chatCheck);

    // Если такого пользователя в БД нет, то запрашиваем номер телефона для регистрации
    if (empty($chatID)) {
        $response = [
            'chat_id' => $user,
            'text' => "👋 *Приветствуем тебя в нашем SMART пространстве!*\n*В качестве приветственного бонуса, мы начислили тебе 100 монет*",
            'parse_mode' => 'markdown'
        ];
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);

        $response = [
            'chat_id' => $user,
            'text' => "*Попробуй демо нашего бота без регистрации*",
            "parse_mode" => "Markdown",
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => '🔎🚲 С кем интересно провести время', 'callback_data' => 'demoEnterestsFinder']
                    ],
                    [
                        ['text' => '🔎💪 С кем вместе обучаться', 'callback_data' => 'demoLearnFinder']
                    ],
                    [
                        ['text' => '🔎❤️ Вторую половинку', 'callback_data' => 'demoNeedsFinder']
                    ],
                    [
                        ['text' => '🔎🧑‍💻 Поиск специалиста', 'callback_data' => 'demoSkillsFinder']
                    ],
                    [
                        ['text' => '🔎👥 Клиентов', 'callback_data' => 'demoClientsFinder']
                    ]
                ]
            ])
        ];
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);

        // Удаление сообщения "/start"
        $send_data['message_id'] = $func['message_id'];
        $send_data['chat_id'] = $func['chat']['id'];
        sendTelegram('deleteMessage', $send_data);

        # Пушим первую инфу пользователя в БД #
        // Создаем реферальную ссылку
        $refURL = 'https://t.me/SMARTSYNCBOT?start=' . $func['from']['id'];

        $tgUserName = "@" . $func['from']['username'];
            // Добавляем пользователя в ОСНОВНУЮ таблицу
        mysqli_query ($con, "INSERT INTO `MainInfo` (`userID`, `tgUserName`, `name`, `surname`, `inviteLink`, `coins`, `regDate`) VALUES ('".$func['from']['id']."','".$tgUserName."','".$func['from']['first_name']."', '".$func['from']['last_name']."', '".$refURL."', 100, NOW())");
            // Добавляем пользователя в таблицу ИНТЕРЕСОВ
        mysqli_query ($con, "INSERT INTO `Interests` (`userID`) VALUES ('".$func['from']['id']."' )");
            // Добавляем пользователя в таблицу ЦЕННОСТЕЙ
        mysqli_query ($con, "INSERT INTO `Needs` (`userID`) VALUES ('".$func['from']['id']."' )");
            // Добавляем пользователя в таблицу НАВЫКОВ
        mysqli_query ($con, "INSERT INTO `Skills` (`userID`) VALUES ('".$func['from']['id']."' )");
            // Добавляем пользователя в таблицу СОЦИАЛЬНЫХ СЕТЕЙ
        mysqli_query ($con, "INSERT INTO `Socials` (`userID`) VALUES ('".$func['from']['id']."' )");
            // Добавляем пользователя в таблицу ТРЕКИНГА
        mysqli_query ($con, "INSERT INTO `TrackingMenu` (`userID`) VALUES ('".$func['from']['id']."' )");
            // Добавляем пользователя в таблицу с наградами
        mysqli_query ($con, "INSERT INTO `userRewards` (`userID`, `tgUserName`, `SkillsReward`, `InterestsReward`, `NeedsReward`) VALUES ('".$func['from']['id']."', '".$tgUserName."', 0, 0, 0) ");

        // Проверяем реферальный переход и если это он, то...
        if (strpos($message, ' ') !== false){

            ## РАБОТА С ПРИГЛАСИВШИМ ##
            // Вычисляем id пользователя, который пригласил
            $msgArray = explode(" ", $message);
            $id = $msgArray[1];

            // Сначала получаем число рефералов и монет из БД
            $insert = mysqli_query ($con, "SELECT `coins`, `referals` FROM `MainInfo` WHERE userID='".$id."' ");
            $user = mysqli_fetch_array($insert);

            // Прибавляем плюшки к кол-ву монет и увеличиваем кол-во рефералов
            $coins = $user['coins'] + 1000;
            $referals = $user['referals'] + 1;

            // Пушим в БД новые значения
            $updateDB = mysqli_query ($con, "UPDATE `MainInfo` SET referals = ".$referals.", coins = ".$coins." WHERE userID=".$id." ");

            ## РАБОТА С ПРИГЛАШЕННЫМ ##
            // Пушим в БД новые значения(id пригласившего)
            $user = $func['from']['id'];
            $updateDB = mysqli_query ($con, "UPDATE `MainInfo` SET inviter = ".$id." WHERE userID=".$user." ");
        }
        return;
    }

    // Если такой пользователь есть в базе, то даем ему доступ в меню
    else{
        // Узнаем зареган ли человек
        $user = $data['message']['from']['id'];
        $insert = mysqli_query ($con, "SELECT `isRegistered` FROM `MainInfo` WHERE userID='".$user."' ");
        $user = mysqli_fetch_array($insert);

        if ($user['isRegistered'] == 1) {
            // Выводим человека из всех меню
            mysqli_query($con, "UPDATE `TrackingMenu` SET whichMenu = '' WHERE userID = '".$user."' ");
            $method = 'sendMessage';
            $send_data = [
                'text' => '👋 *Привет! Давно не виделись!*',
                'parse_mode' => 'markdown'
            ];  
            $send_data['chat_id'] = $func['chat']['id'];
            sendTelegram($method, $send_data);

            // Удаление сообщения "/start"
            $send_data['message_id'] = $func['message_id'];
            $send_data['chat_id'] = $func['chat']['id'];
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => '📋 *Главное меню:*',
                'parse_mode' => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '😁 Мой профиль', 'callback_data' => 'profile']  
                        ],
                        [
                            ['text' => '🔎 Поиск людей', 'callback_data' => 'peopleFinder']
                        ],
                        /*[
                            ['text' => '👑 Моя подписка', 'callback_data' => 'mySubscription']
                        ],*/
                        [
                            ['text' => '💰 Монеты', 'callback_data' => 'myCoins']
                        ],
                        [
                            ['text' => '📈 Моя статистика', 'callback_data' => 'myStats']  
                        ],
                        /*[
                            ['text' => '📊 Топ пользователей', 'callback_data' => 'stat']
                        ],*/
                        [
                            ['text' => '🗣️ Сообщить об идее/ошибке', 'callback_data' => 'feedback']
                        ],
                        [
                            ['text' => 'FAQ', 'callback_data' => 'faq']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['chat']['id'];
            sendTelegram($method, $send_data); 
        }else{
            $user = $data['message']['from']['id'];
            $response = [
                'chat_id' => $user,
                'text' => "👋 *Приветствуем тебя в нашем SMART пространстве!*\n*В качестве приветственного бонуса, мы начислили тебе 100 монет*",
                'parse_mode' => 'markdown'
            ];
            $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_exec($ch);
            curl_close($ch);

            $response = [
                'chat_id' => $user,
                'text' => "*Попробуй демо нашего бота без регистрации*",
                "parse_mode" => "Markdown",
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>[
                        [
                            ['text' => '🔎🚲 С кем интересно провести время', 'callback_data' => 'demoEnterestsFinder']
                        ],
                        [
                            ['text' => '🔎💪 С кем вместе обучаться', 'callback_data' => 'demoLearnFinder']
                        ],
                        [
                            ['text' => '🔎❤️ Вторую половинку', 'callback_data' => 'demoNeedsFinder']
                        ],
                        [
                            ['text' => '🔎🧑‍💻 Поиск специалиста', 'callback_data' => 'demoSkillsFinder']
                        ],
                        [
                            ['text' => '🔎👥 Клиентов', 'callback_data' => 'demoClientsFinder']
                        ]
                    ]
                ])
            ];
            $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_exec($ch);
            curl_close($ch);

            // Удаление сообщения "/start"
            $send_data['message_id'] = $func['message_id'];
            $send_data['chat_id'] = $func['chat']['id'];
            sendTelegram('deleteMessage', $send_data);
        }       
    }
}

if ($data['callback_query']['data'] == "demoBot") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Демо версия* \n_📋 Главное меню_",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => '😁 Профиль', 'callback_data' => 'demoProfile']  
                ],
                [
                    ['text' => '🔎 Поиск людей', 'callback_data' => 'demoPeopleFinder']  
                ],
                [
                    ['text' => '💰 Монеты', 'callback_data' => 'demoCoins']
                ],
                [
                    ['text' => 'FAQ', 'callback_data' => 'demoFAQ']
                ],
                [
                    ['text' => 'Регистрация', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if (strpos($data['callback_query']['data'], 'nextDemoProfile') !== false) {
    // Достаем id прошлого профиля
    $newid = preg_replace("/nextDemoProfile/i", "", $data['callback_query']['data']);
    $newid = trim($newid);
    // Достаем из БД id найденных профилей
    $user = $func['from']['id'];
    $profIDs = mysqli_query ($con, "SELECT `searchIDs` FROM `TrackingMenu` WHERE userID='".$user."' ");
    $ids = mysqli_fetch_array($profIDs);

    // Создаем массив из id найденных профилей 
    $allIDs = explode(",", $ids['searchIDs']);

    // Кол-во профилей
    $counter = count($allIDs);

    foreach ($allIDs as $key => $value) {
        if ($value == $newid) {
            $num = $key + 1;
            break;
        }
    }

    $id = $allIDs[$num];
    $num += 1;
    
    // Лезем в базу за инфой о новом пользователе
    $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userPhoto` FROM `MainInfo` WHERE id='".$id."' ");

    $prof = mysqli_fetch_array($profCheck);

    // Удаляем сообщение, на которое нажали
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);

    // Если это последний профиль в списке, то не даем возможности листать дальше
    if ($num == $counter) {
        $response = [
            'chat_id' => $user,
            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b>",
            "parse_mode" => "html",
            'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Далее 👉', 'callback_data' => 'demoReg1']
                    ],
                    [
                        ['text' => 'Подробнее о '.$prof['name'], 'callback_data' => 'aboutDemoProfile ' . $id] 
                    ],
                    [
                        ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile ' . $id] 
                    ],
                    [
                        ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                    ]
                ]
            ])
        ];
            
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }else{
        $response = [
            'chat_id' => $user,
            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ",
            "parse_mode" => "html",
            'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => '👈 Предыдущий профиль', 'callback_data' => 'prevDemoProfile ' . $id],
                        ['text' => 'Следующий профиль 👉', 'callback_data' => 'nextDemoProfile ' . $id]  
                    ],
                    [
                        ['text' => 'Подробнее о '.$prof['name'], 'callback_data' => 'aboutDemoProfile ' . $id] 
                    ],
                    [
                        ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile ' . $id] 
                    ],
                    [
                        ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                    ]
                ]
            ])
        ];
                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
}
if (strpos($data['callback_query']['data'], 'prevDemoProfile') !== false) {
    // Достаем id прошлого профиля
    $newid = preg_replace("/prevDemoProfile/i", "", $data['callback_query']['data']);
    $newid = trim($newid);
    // Достаем из БД id найденных профилей
    $user = $func['from']['id'];
    $profIDs = mysqli_query ($con, "SELECT `searchIDs` FROM `TrackingMenu` WHERE userID='".$user."' ");
    $ids = mysqli_fetch_array($profIDs);

    // Создаем массив из id найденных профилей 
    $allIDs = explode(",", $ids['searchIDs']);

    // Кол-во профилей
    $counter = count($allIDs);

    foreach ($allIDs as $key => $value) {
        if ($value == $newid) {
            $num = $key - 1;
            break;
        }
    }

    $id = $allIDs[$num];
    $num += 1;
    
    // Лезем в базу за инфой о новом пользователе
    $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userPhoto` FROM `MainInfo` WHERE id='".$id."' ");

    $prof = mysqli_fetch_array($profCheck);

    // Удаляем сообщение, на которое нажали
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);

    // Если это последний профиль в списке, то не даем возможности листать дальше
    if ($num == 1) {
        $response = [
            'chat_id' => $user,
            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b>",
            "parse_mode" => "html",
            'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Следующий профиль 👉', 'callback_data' => 'nextDemoProfile ' . $id] 
                    ],
                    [
                        ['text' => 'Подробнее о '.$prof['name'], 'callback_data' => 'aboutDemoProfile ' . $id] 
                    ],
                    [
                        ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile ' . $id] 
                    ],
                    [
                        ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                    ]
                ]
            ])
        ];
            
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }else{
        $response = [
            'chat_id' => $user,
            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ",
            "parse_mode" => "html",
            'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => '👈 Предыдущий профиль', 'callback_data' => 'prevDemoProfile ' . $id],
                        ['text' => 'Следующий профиль 👉', 'callback_data' => 'nextDemoProfile ' . $id]  
                    ],
                    [
                        ['text' => 'Подробнее о '.$prof['name'], 'callback_data' => 'aboutDemoProfile ' . $id] 
                    ],
                    [
                        ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile ' . $id] 
                    ],
                    [
                        ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                    ]
                ]
            ])
        ];
                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
}
if (strpos($data['callback_query']['data'], 'aboutDemoProfile') !== false) {
    $user = $func['from']['id'];
    // Достаем id профиля
    $newid = preg_replace("/aboutDemoProfile/i", "", $data['callback_query']['data']);
    $newid = trim($newid);

    // Удаляем старое сообщение
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);

    // Подключаемся к БД и берем данные новой id
    $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto`, `tgUserName` FROM `MainInfo` WHERE id='".$newid."' ");
    $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE id='".$newid."' ");
    $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5`, `s6` FROM `Skills` WHERE id='".$newid."' ");
    $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5`, `n6` FROM `Needs` WHERE id='".$newid."' ");

    $prof = mysqli_fetch_array($profCheck);
    $skill = mysqli_fetch_row($skillsCheck);
    $need = mysqli_fetch_row($needsCheck);
    $int = mysqli_fetch_row($intsCheck);

    $msgText1 = "";
    $msgText2 = "";
    $msgText3 = ""; 

    if (!empty($skill[0]) or !empty($skill[1]) or !empty($skill[2]) or !empty($skill[3]) or !empty($skill[4]) or !empty($skill[5])) {
        $msgText1 = "\n🧑‍💻 <i>Навыки:</i> \n";
        // Выводим скиллы в правильном виде
        foreach ($skill as $key => $value) {
            if ($key == 0 and !empty($value)) {
                $msgText1 .= "\r<b>" . trim($value) . "</b>";
            }
            if ($key == 1 and !empty($value)) {
                $msgText1 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 2 and !empty($value)) {
                $msgText1 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 3 and !empty($value)) {
                $msgText1 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 4 and !empty($value)) {
                $msgText1 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 5 and !empty($value)) {
                $msgText1 .= ", <b>" . trim($value) . "</b>";
            }
        }
    }

    if (!empty($need[0]) or !empty($need[1]) or !empty($need[2]) or !empty($need[3]) or !empty($need[4]) or !empty($need[5])) {
        $msgText2 = "\n📝 <i>Ценности:</i> \n";
        // Выводим ценности в правильном виде
        foreach ($need as $key => $value) {
            if ($key == 0 and !empty($value)) {
                $msgText2 .= "\r<b>" . trim($value) . "</b>";
            }
            if ($key == 1 and !empty($value)) {
                $msgText2 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 2 and !empty($value)) {
                $msgText2 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 3 and !empty($value)) {
                $msgText2 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 4 and !empty($value)) {
                $msgText2 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 5 and !empty($value)) {
                $msgText2 .= ", <b>" . trim($value) . "</b>";
            }
        }
    }    
                
    if (!empty($int[0]) or !empty($int[1]) or !empty($int[2]) or !empty($int[3]) or !empty($int[4]) or !empty($int[5])) {
        $msgText3 = "\n🚲 <i>Интересы:</i> \n";
        // Выводим ценности в правильном виде
        foreach ($int as $key => $value) {
            if ($key == 0 and !empty($value)) {
                $msgText3 .= "\r<b>" . trim($value) . "</b>";
            }
            if ($key == 1 and !empty($value)) {
                $msgText3 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 2 and !empty($value)) {
                $msgText3 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 3 and !empty($value)) {
                $msgText3 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 4 and !empty($value)) {
                $msgText3 .= ", <b>" . trim($value) . "</b>";
            }
            if ($key == 5 and !empty($value)) {
                $msgText3 .= ", <b>" . trim($value) . "</b>";
            }
        }
    }

    if (!empty($prof['userAge'])) {
        $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
    }

    // Проверяем какая по счету учетка
    // Если первая
    if ($newid == 2) {
        $response = [
            'chat_id' => $user,
            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3,
            "parse_mode" => "html",
            'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Следующий профиль 👉', 'callback_data' => 'nextDemoProfile ' . $newid] 
                    ],
                    [
                        ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile ' . $newid] 
                    ],
                    [
                        ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                    ]
                ]
            ])
        ];
                            
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }else if ($newid < 7 and $newid > 2) {
        $response = [
            'chat_id' => $user,
            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3,
            "parse_mode" => "html",
            'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => '👈 Предыдущий профиль', 'callback_data' => 'prevDemoProfile ' . $newid],
                        ['text' => 'Следующий профиль 👉', 'callback_data' => 'nextDemoProfile ' . $newid]  
                    ],
                    [
                        ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile ' . $newid] 
                    ],
                    [
                        ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                    ]
                ]
            ])
        ];
                            
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }else{
        $response = [
            'chat_id' => $user,
            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3,
            "parse_mode" => "html",
            'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => '👈 Предыдущий профиль', 'callback_data' => 'prevDemoProfile ' . $newid]
                    ],
                    [
                        ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile ' . $newid] 
                    ],
                    [
                        ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                    ]
                ]
            ])
        ];
                            
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }  
}
if (strpos($data['callback_query']['data'], 'contactDemoProfile') !== false) {
    $user = $func['from']['id'];

    // Удаляем старое сообщение
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);

    // Выдаем сообщение, что для связи с человеком нужно зарегистрироваться
    $response = [
        'chat_id' => $user,
        'text' => "*Чтоб связаться с человеком, нужно зарегистрироваться*",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Зарегистрироваться', 'callback_data' => 'demoReg1']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoReg1") {
    $user = $func['from']['id'];

    // Удаляем старое сообщение
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);

    // Выдаем сообщение, что для связи с человеком нужно зарегистрироваться
    $response = [
        'chat_id' => $user,
        'caption' => "Чтобы найти других людей,сначала нужно зарегистрироваться\n_Начнем с добавления_ *местоположения*_, чтоб помогать находить людей из_ *твоего города*",
        'photo' => curl_file_create("../tgBot/BotPic/post_338.jpg"),
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Поделиться геометкой', 'callback_data' => 'send1Geo2Automatically']
                ],
                [
                    ['text' => 'Выбрать из списка', 'callback_data' => 'send3Geo4From5List']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;       
}   
if ($data['callback_query']['data'] == "demoPeopleFinder") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Попробуй демо нашего бота без регистрации*",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => '🔎🚲 С кем интересно провести время', 'callback_data' => 'demoEnterestsFinder']
                ],
                [
                    ['text' => '🔎💪 С кем вместе обучаться', 'callback_data' => 'demoLearnFinder']
                ],
                [
                    ['text' => '🔎❤️ Вторую половинку', 'callback_data' => 'demoNeedsFinder']
                ],
                [
                    ['text' => '🔎🧑‍💻 Поиск специалиста', 'callback_data' => 'demoSkillsFinder']
                ],
                [
                    ['text' => '🔎👥 Клиентов', 'callback_data' => 'demoClientsFinder']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoEnterestsFinder") {
    $user = $func['from']['id'];
    // Удаляем сообщение, на которое нажали
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Пушим в базу id демо пользователей
    mysqli_query($con, "UPDATE `TrackingMenu` SET searchIDs = '2,3,4,5,6,7' WHERE userID = '".$user."' ");
    // Выводим данные 1 демо пользователя
    $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto` FROM `MainInfo` WHERE userID='demoProfile1' ");
            
    $prof = mysqli_fetch_array($profCheck);

    if (!empty($prof['userAge'])) {
        $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
    }

    $response = [
        'chat_id' => $user,
        'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ",
        "parse_mode" => "html",
        'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Следующий профиль 👉', 'callback_data' => 'nextDemoProfile 2']  
                ],
                [
                    ['text' => 'Подробнее о '.$prof['name'], 'callback_data' => 'aboutDemoProfile 2'] 
                ],
                [
                    ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile 2'] 
                ],
                [
                    ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                ]
            ]
        ])
    ];
                    
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoLearnFinder") {
    $user = $func['from']['id'];
    // Удаляем сообщение, на которое нажали
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Пушим в базу id демо пользователей
    mysqli_query($con, "UPDATE `TrackingMenu` SET searchIDs = '2,3,4,5,6,7' WHERE userID = '".$user."' ");
    // Выводим данные 1 демо пользователя
    $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto` FROM `MainInfo` WHERE userID='demoProfile1' ");
            
    $prof = mysqli_fetch_array($profCheck);

    if (!empty($prof['userAge'])) {
        $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
    }

    $response = [
        'chat_id' => $user,
        'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ",
        "parse_mode" => "html",
        'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Следующий профиль 👉', 'callback_data' => 'nextDemoProfile 2']  
                ],
                [
                    ['text' => 'Подробнее о '.$prof['name'], 'callback_data' => 'aboutDemoProfile 2'] 
                ],
                [
                    ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile 2'] 
                ],
                [
                    ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                ]
            ]
        ])
    ];
                    
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoNeedsFinder") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Выбери пол, который тебе интересен*",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Ищу девушку', 'callback_data' => 'demoJenskiyPol']  
                ],
                [
                    ['text' => 'Ищу парня', 'callback_data' => 'demoMuzhskoyPol']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoJenskiyPol") {
    $user = $func['from']['id'];
    // Удаляем сообщение, на которое нажали
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Пушим в базу id демо пользователей
    mysqli_query($con, "UPDATE `TrackingMenu` SET searchIDs = '4,6,7' WHERE userID = '".$user."' ");
    // Выводим данные 1 демо пользователя
    $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto` FROM `MainInfo` WHERE userID='demoProfile3' ");
            
    $prof = mysqli_fetch_array($profCheck);

    if (!empty($prof['userAge'])) {
        $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
    }

    $response = [
        'chat_id' => $user,
        'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ",
        "parse_mode" => "html",
        'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Следующий профиль 👉', 'callback_data' => 'nextDemoProfile 4']  
                ],
                [
                    ['text' => 'Подробнее о '.$prof['name'], 'callback_data' => 'aboutDemoProfile 4'] 
                ],
                [
                    ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile 4'] 
                ],
                [
                    ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                ]
            ]
        ])
    ];
                    
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoMuzhskoyPol") {
    $user = $func['from']['id'];
    // Удаляем сообщение, на которое нажали
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Пушим в базу id демо пользователей
    mysqli_query($con, "UPDATE `TrackingMenu` SET searchIDs = '2,3,5' WHERE userID = '".$user."' ");
    // Выводим данные 1 демо пользователя
    $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto` FROM `MainInfo` WHERE userID='demoProfile1' ");
            
    $prof = mysqli_fetch_array($profCheck);

    if (!empty($prof['userAge'])) {
        $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
    }

    $response = [
        'chat_id' => $user,
        'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ",
        "parse_mode" => "html",
        'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Следующий профиль 👉', 'callback_data' => 'nextDemoProfile 2']  
                ],
                [
                    ['text' => 'Подробнее о '.$prof['name'], 'callback_data' => 'aboutDemoProfile 2'] 
                ],
                [
                    ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile 2'] 
                ],
                [
                    ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                ]
            ]
        ])
    ];
                    
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoSkillsFinder") {
    $user = $func['from']['id'];
    // Удаляем сообщение, на которое нажали
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Пушим в базу id демо пользователей
    mysqli_query($con, "UPDATE `TrackingMenu` SET searchIDs = '2,3,4,5,6,7' WHERE userID = '".$user."' ");
    // Выводим данные 1 демо пользователя
    $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto` FROM `MainInfo` WHERE userID='demoProfile1' ");
            
    $prof = mysqli_fetch_array($profCheck);

    if (!empty($prof['userAge'])) {
        $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
    }

    $response = [
        'chat_id' => $user,
        'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ",
        "parse_mode" => "html",
        'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Следующий профиль 👉', 'callback_data' => 'nextDemoProfile 2']  
                ],
                [
                    ['text' => 'Подробнее о '.$prof['name'], 'callback_data' => 'aboutDemoProfile 2'] 
                ],
                [
                    ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile 2'] 
                ],
                [
                    ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                ]
            ]
        ])
    ];
                    
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoClientsFinder") {
    $user = $func['from']['id'];
    // Удаляем сообщение, на которое нажали
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Пушим в базу id демо пользователей
    mysqli_query($con, "UPDATE `TrackingMenu` SET searchIDs = '2,3,4,5,6,7' WHERE userID = '".$user."' ");
    // Выводим данные 1 демо пользователя
    $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto` FROM `MainInfo` WHERE userID='demoProfile1' ");
            
    $prof = mysqli_fetch_array($profCheck);

    if (!empty($prof['userAge'])) {
        $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
    }

    $response = [
        'chat_id' => $user,
        'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ",
        "parse_mode" => "html",
        'photo' => curl_file_create("../tgBot/BotPic/".$prof['userPhoto']),
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Следующий профиль 👉', 'callback_data' => 'nextDemoProfile 2']  
                ],
                [
                    ['text' => 'Подробнее о '.$prof['name'], 'callback_data' => 'aboutDemoProfile 2'] 
                ],
                [
                    ['text' => 'Связаться с '.$prof['name'], 'callback_data' => 'contactDemoProfile 2'] 
                ],
                [
                    ['text' => '👈 К поиску', 'callback_data' => 'demoPeopleFinder']
                ]
            ]
        ])
    ];
                    
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoFAQ") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Демо версия* \n_FAQ_",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Зачем указывать свои навыки, интересы и ценности?', 'callback_data' => 'demoQ1']  
                ],
                [
                    ['text' => 'Почему навыки, интересы и ценности нужно указывать в определенном порядке?', 'callback_data' => 'demoQ2']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свой номер телефона?', 'callback_data' => 'demoQ3']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свое местоположение?', 'callback_data' => 'demoQ4']  
                ],
                [
                    ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'demoQ5']  
                ],
                [
                    ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'demoQ6']  
                ],
                [
                    ['text' => 'Как узнать, что специалист добросовестный?', 'callback_data' => 'demoQ7']  
                ],
                [
                    ['text' => '👈 Вернуться назад', 'callback_data' => 'demoBot']  
                ],
                [
                    ['text' => 'Регистрация', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoQ1") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Демо версия\nЗачем указывать свои навыки, интересы и ценности?*\n_После регистрации вам будут доступен поиск нужных вам людей по многим критериям. Например, вы сможете искать друзей, вторую половинку, спутников или партнеров по интересам, навыкам и даже ценностям. А так же вас смогут находить другие пользователи.Например вам нужны клиенты и заказчики, значит вам нужно указать свои профессиональные навыки и уровень вашего опыта._",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Зачем указывать свои навыки, интересы и ценности?', 'callback_data' => 'demoQ1']  
                ],
                [
                    ['text' => 'Почему навыки, интересы и ценности нужно указывать в определенном порядке?', 'callback_data' => 'demoQ2']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свой номер телефона?', 'callback_data' => 'demoQ3']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свое местоположение?', 'callback_data' => 'demoQ4']  
                ],
                [
                    ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'demoQ5']  
                ],
                [
                    ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'demoQ6']  
                ],
                [
                    ['text' => 'Как узнать, что специалист добросовестный?', 'callback_data' => 'demoQ7']  
                ],
                [
                    ['text' => '👈 Вернуться назад', 'callback_data' => 'demoBot']  
                ],
                [
                    ['text' => 'Регистрация', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoQ2") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Демо версия\nПочему навыки, интересы и ценности нужно указывать в определенном порядке?*\n_Поиск будет подбирать вам людей именно по важности ваших интересов. Анкеты людей будут подтягиваться сначала по первому приоритету, по второму и так далее._",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Зачем указывать свои навыки, интересы и ценности?', 'callback_data' => 'demoQ1']  
                ],
                [
                    ['text' => 'Почему навыки, интересы и ценности нужно указывать в определенном порядке?', 'callback_data' => 'demoQ2']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свой номер телефона?', 'callback_data' => 'demoQ3']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свое местоположение?', 'callback_data' => 'demoQ4']  
                ],
                [
                    ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'demoQ5']  
                ],
                [
                    ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'demoQ6']  
                ],
                [
                    ['text' => 'Как узнать, что специалист добросовестный?', 'callback_data' => 'demoQ7']  
                ],
                [
                    ['text' => '👈 Вернуться назад', 'callback_data' => 'demoBot']  
                ],
                [
                    ['text' => 'Регистрация', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoQ3") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Демо версия\nЗачем нужно указывать свой номер телефона?*\n_Номер телефона является главным идентификатором пользователя. Все данные шифруются и закрыты от других пользователей, если только вы сами их им не откроете._",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Зачем указывать свои навыки, интересы и ценности?', 'callback_data' => 'demoQ1']  
                ],
                [
                    ['text' => 'Почему навыки, интересы и ценности нужно указывать в определенном порядке?', 'callback_data' => 'demoQ2']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свой номер телефона?', 'callback_data' => 'demoQ3']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свое местоположение?', 'callback_data' => 'demoQ4']  
                ],
                [
                    ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'demoQ5']  
                ],
                [
                    ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'demoQ6']  
                ],
                [
                    ['text' => 'Как узнать, что специалист добросовестный?', 'callback_data' => 'demoQ7']  
                ],
                [
                    ['text' => '👈 Вернуться назад', 'callback_data' => 'demoBot']  
                ],
                [
                    ['text' => 'Регистрация', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoQ4") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Демо версия\nЗачем нужно указывать свое местоположение?*\n_Поиск будет подбирать вам людей согласно вашему местоположению. Чтобы вас находили и вы могли искать людей рядом с вами. В поиске будут сначала те кто ближе к вам, но другим пользователям не видно ни вашего местоположения, ни как далеко вы от них._",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Зачем указывать свои навыки, интересы и ценности?', 'callback_data' => 'demoQ1']  
                ],
                [
                    ['text' => 'Почему навыки, интересы и ценности нужно указывать в определенном порядке?', 'callback_data' => 'demoQ2']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свой номер телефона?', 'callback_data' => 'demoQ3']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свое местоположение?', 'callback_data' => 'demoQ4']  
                ],
                [
                    ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'demoQ5']  
                ],
                [
                    ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'demoQ6']  
                ],
                [
                    ['text' => 'Как узнать, что специалист добросовестный?', 'callback_data' => 'demoQ7']  
                ],
                [
                    ['text' => '👈 Вернуться назад', 'callback_data' => 'demoBot']  
                ],
                [
                    ['text' => 'Регистрация', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoQ5") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Демо версия\nКакая ценность ваших монет?*\n_Монеты - основная валюта, с помощью которой вы можете искать людей, скрывать свой профиль, рекламировать свой профиль, покупать различные бусты или же просто собирать их, ведь рейтинг выстраивается на основе кол-ва заработанных вами монет монет. Заработать монеты можно выполняя задания в разделе Монеты/Как заработать монеты? Если задание уже выполнено - возле него появится значок(галочка), что является индикатором завершенного задания._",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Зачем указывать свои навыки, интересы и ценности?', 'callback_data' => 'demoQ1']  
                ],
                [
                    ['text' => 'Почему навыки, интересы и ценности нужно указывать в определенном порядке?', 'callback_data' => 'demoQ2']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свой номер телефона?', 'callback_data' => 'demoQ3']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свое местоположение?', 'callback_data' => 'demoQ4']  
                ],
                [
                    ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'demoQ5']  
                ],
                [
                    ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'demoQ6']  
                ],
                [
                    ['text' => 'Как узнать, что специалист добросовестный?', 'callback_data' => 'demoQ7']  
                ],
                [
                    ['text' => '👈 Вернуться назад', 'callback_data' => 'demoBot']  
                ],
                [
                    ['text' => 'Регистрация', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoQ6") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Демо версия\nПо каким параметрам мне подберут друга?*\n_Вы добавляете свои ценности и интересы по степени важности, а мы подбираем людей с максимальной схожестью важности интересов и ценностей. Также вы можете пройти тест на определение психотипа, чтобы подбирать единомышленников в разных сферах для бизнеса, дружбы и отношений._",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Зачем указывать свои навыки, интересы и ценности?', 'callback_data' => 'demoQ1']  
                ],
                [
                    ['text' => 'Почему навыки, интересы и ценности нужно указывать в определенном порядке?', 'callback_data' => 'demoQ2']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свой номер телефона?', 'callback_data' => 'demoQ3']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свое местоположение?', 'callback_data' => 'demoQ4']  
                ],
                [
                    ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'demoQ5']  
                ],
                [
                    ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'demoQ6']  
                ],
                [
                    ['text' => 'Как узнать, что специалист добросовестный?', 'callback_data' => 'demoQ7']  
                ],
                [
                    ['text' => '👈 Вернуться назад', 'callback_data' => 'demoBot']  
                ],
                [
                    ['text' => 'Регистрация', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoQ7") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Демо версия\nКак узнать, что специалист добросовестный?*\n_Вам видны рекомендации других людей, и в первую очередь вы видите рекомендации ваших контактов, а значит сможете у них уточнить нужные вам детали о специалисте._",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Зачем указывать свои навыки, интересы и ценности?', 'callback_data' => 'demoQ1']  
                ],
                [
                    ['text' => 'Почему навыки, интересы и ценности нужно указывать в определенном порядке?', 'callback_data' => 'demoQ2']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свой номер телефона?', 'callback_data' => 'demoQ3']  
                ],
                [
                    ['text' => 'Зачем нужно указывать свое местоположение?', 'callback_data' => 'demoQ4']  
                ],
                [
                    ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'demoQ5']  
                ],
                [
                    ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'demoQ6']  
                ],
                [
                    ['text' => 'Как узнать, что специалист добросовестный?', 'callback_data' => 'demoQ7']  
                ],
                [
                    ['text' => '👈 Вернуться назад', 'callback_data' => 'demoBot']  
                ],
                [
                    ['text' => 'Регистрация', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoCoins") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'text' => "*Демо версия* \n_💰 Монеты - основная валюта, с помощью которой вы можете искать людей, скрывать свой профиль, рекламировать свой профиль, покупать различные бусты или же просто собирать их, ведь рейтинг выстраивается на основе кол-ва заработанных вами монет монет. Заработать монеты можно выполняя задания в разделе Монеты/Как заработать монеты?. Если задание уже выполнено - возле него появится значок(галочка), что является индикатором завершенного задания._",
        "parse_mode" => "Markdown",
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => '👈 Вернуться назад', 'callback_data' => 'demoBot']  
                ],
                [
                    ['text' => 'Регистрация', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
if ($data['callback_query']['data'] == "demoProfile") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);

    // Подключаемся к БД и берем все данные демо пользователя
    $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto` FROM `MainInfo` WHERE userID='demoProfile' ");
    $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='demoProfile' ");
    $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5`, `s6` FROM `Skills` WHERE userID='demoProfile' ");
    $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5`, `n6` FROM `Needs` WHERE userID='demoProfile' ");
    $socialCheck = mysqli_query ($con, "SELECT `inst`, `tiktok`, `viber`, `whatsapp`, `facebook`, `anotherSocials` FROM `Socials` WHERE userID='demoProfile' ");
            
    $prof = mysqli_fetch_array($profCheck);
    $skill = mysqli_fetch_row($skillsCheck);
    $need = mysqli_fetch_row($needsCheck);
    $int = mysqli_fetch_row($intsCheck);
    $socials = mysqli_fetch_array($socialCheck);

    $msgText1 = "";
    $msgText2 = "";
    $msgText3 = "";

        if (!empty($skill)) {
            $msgText1 = "\n🧑‍💻 <i>Мои навыки:</i> \n";
                // Выводим скиллы в правильном виде
            foreach ($skill as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText1 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText1 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText1 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText1 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText1 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText1 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        if (!empty($need)) {
            $msgText2 = "\n📝 <i>Мои ценности:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($need as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText2 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }    
        
        if (!empty($int)) {
            $msgText3 = "\n🚲 <i>Мои интересы:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($int as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        $soc = "";

        if (!empty($prof['userAge'])) {
            $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
        }

        if (!empty($socials['inst'])) {
            $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b>";
            if ($soc == "") {
                $soc = $inst;
            }else{
                $soc .= ", ".$inst;
            }
        }else{
            $inst = "";
        }

        if (!empty($socials['tiktok'])) {
            $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b>";
            if ($soc == "") {
                $soc = $tiktok;
            }else{
                $soc .= ", ".$tiktok;
            }
        }else{
            $tiktok = "";
        }

        if (!empty($socials['facebook'])) {
            $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b>";
            if ($soc == "") {
                $soc = $facebook;
            }else{
                $soc .= ", ".$facebook;
            }
        }else{
            $facebook = "";
        }

        if (!empty($socials['viber'])) {
            $viber = "<b>Viber - ".$socials['viber']."</b>";
            if ($soc == "") {
                $soc = $viber;
            }else{
                $soc .= ", ".$viber;
            }
        }else{
            $viber = "";
        }

        if (!empty($socials['whatsapp'])) {
            $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b>";
            if ($soc == "") {
                $soc = $whatsapp;
            }else{
                $soc .= ", ".$whatsapp;
            }
        }else{
            $whatsapp = "";
        }

        if (!empty($socials['anotherSocials'])) {
            $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b>";
            if ($soc == "") {
                $soc = $anotherSocials;
            }else{
                $soc .= ", ".$anotherSocials;
            }
        }else{
            $anotherSocials = "";
        }

    // Выдаем кнопки ограниченного меню
    $response = [
        'chat_id' => $user,
        'caption' => "<b>Демо версия</b>\n<i>😁 Мой профиль</i>\n\n<b>".$prof['name']." ".$prof['surname']."</b>".$age.$msgText1.$msgText2.$msgText3."\n".$soc,
        "parse_mode" => "html",
        'photo' => curl_file_create("../tgBot/BotPic/DemoProfilePhoto.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => '👈 Вернуться назад', 'callback_data' => 'demoBot']  
                ],
                [
                    ['text' => 'Регистрация', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
                    
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}

# Первые кнопки #
if ($data['callback_query']['data'] == "FirsTmenu") {

    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);

    $response = [
        'chat_id' => $user,
        'caption' => "👋 *Приветствуем тебя в нашем SMART пространстве. Выбери кого ты ищешь:*",
        "parse_mode" => "Markdown",
        'photo' => curl_file_create("../tgBot/BotPic/post_334.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => '🔎🚲 С кем интересно провести время', 'callback_data' => '1chFirst']  
                ],
                [
                    ['text' => '🔎💪 С кем вместе обучаться', 'callback_data' => '2chFirst']
                ],
                [
                    ['text' => '🔎❤️ Вторую половинку', 'callback_data' => '3chFirst']
                ],
                [
                    ['text' => '🔎🧑‍💻 Специалиста', 'callback_data' => '4chFirst']  
                ],
                [
                    ['text' => '🔎👥 Клиентов', 'callback_data' => '5chFirst']
                ]
            ]
        ])
    ];
                            
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}

# Обработчик первых кнопок #
// 1 кнопка
if ($data['callback_query']['data'] == "1chFirst") {

    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    // Вывод интересов пользователя
    $interests = mysqli_query ($con, "SELECT * FROM `Interests` WHERE userID='".$user."' ");
    $ui = mysqli_fetch_array($interests);

    // Переменная для вывода в сообщение
    $msgArray = "";

    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);

    if (empty($ui['interest1'])) {
        $intQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestCategories`");
        $finalButtonsArray = GenerateButtonsPage($intQuery," 1",1,99,' 🔻');
        array_push($finalButtonsArray,array(
            array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu')
        ));
        $response = [
            'chat_id' => $user,
            'caption' => "*Сейчас у тебя ничего не указано*\nУкажите 5 своих интересов, начиная с самого важного\n\nВыбери категорию:",
            "parse_mode" => "Markdown",
            'photo' => curl_file_create("../tgBot/BotPic/post_333.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];
                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }else{
        // Выводим интересы в правильном виде
        if (!empty($ui['interest1'])) {
            $msgArray .= "\u{0031}\u{FE0F}\u{20E3}" . " - " . $ui['interest1'] . "\n";
        }
        if (!empty($ui['interest2'])) {
            $msgArray .= "\u{0032}\u{FE0F}\u{20E3}" . " - " . $ui['interest2'] . "\n";
        }    
        if (!empty($ui['interest3'])) {
            $msgArray .= "\u{0033}\u{FE0F}\u{20E3}" . " - " . $ui['interest3'] . "\n";
        }
        if (!empty($ui['interest4'])) {
            $msgArray .= "\u{0034}\u{FE0F}\u{20E3}" . " - " . $ui['interest4'] . "\n";
        }
        if (!empty($ui['interest5'])) {
            $msgArray .= "\u{0035}\u{FE0F}\u{20E3}" . " - " . $ui['interest5'] . "\n";
        }

        $intQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestCategories`");
        $finalButtonsArray = GenerateButtonsPage($intQuery," 1",1,99,' 🔻');
        array_push($finalButtonsArray,array(
            array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu')
        ));

        $response = [
            'chat_id' => $user,
            'caption' => "*Укажите 5 своих интересов, начиная с самого важного\n\nСейчас у тебя указано:*\n" . $msgArray,
            "parse_mode" => "Markdown",
            'photo' => curl_file_create("../tgBot/BotPic/post_333.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];
                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
}

if ($data['callback_query']['data'] == "funInts 1") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Вывод интересов пользователя
    $interests = mysqli_query ($con, "SELECT * FROM `Interests` WHERE userID='".$user."' ");
    $ui = mysqli_fetch_array($interests);

    $funIntsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'funInts'");
    $funInts      = mysqli_fetch_array($funIntsQuery);
    $pageNum = 1;
    $intsPerPage = 9;

    $userInterestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
    $userInterests      = mysqli_fetch_array($userInterestsCheck);

    $finalButtonsArray = GenerateButtonsPageWithExeptions($funIntsQuery," tni",$userInterests,"interest");
    //Выбираем только 1 страницу
    $finalButtonsArray = array_slice($finalButtonsArray,($pageNum-1) * $intsPerPage,9);

    array_push($finalButtonsArray,array(
        array('text' => 'Следующая страница 👉', 'callback_data' => 'funInts 2')
    ));

    array_push($finalButtonsArray,array(
        array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst')
    ));
    // Переменная для вывода в сообщение
    $msgArray = "";

    if (empty($ui['interest1'])) {
        $response = [
            'chat_id' => $user,
            'caption' => "*Выбери свой интерес:*",
            "parse_mode" => "Markdown",
            'photo' => curl_file_create("../tgBot/BotPic/post_333.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];
                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }else{
        // Выводим интересы в правильном виде
        if (!empty($ui['interest1'])) {
            $msgArray .= "\u{0031}\u{FE0F}\u{20E3}" . " - " . $ui['interest1'] . "\n";
        }
        if (!empty($ui['interest2'])) {
            $msgArray .= "\u{0032}\u{FE0F}\u{20E3}" . " - " . $ui['interest2'] . "\n";
        }    
        if (!empty($ui['interest3'])) {
            $msgArray .= "\u{0033}\u{FE0F}\u{20E3}" . " - " . $ui['interest3'] . "\n";
        }
        if (!empty($ui['interest4'])) {
            $msgArray .= "\u{0034}\u{FE0F}\u{20E3}" . " - " . $ui['interest4'] . "\n";
        }
        if (!empty($ui['interest5'])) {
            $msgArray .= "\u{0035}\u{FE0F}\u{20E3}" . " - " . $ui['interest5'] . "\n";
        }

    $funIntsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'funInts'");
    $funInts      = mysqli_fetch_array($funIntsQuery);
    $pageNum = 1;
    $intsPerPage = 9;

    $userInterestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
    $userInterests      = mysqli_fetch_array($userInterestsCheck);

    $finalButtonsArray = GenerateButtonsPageWithExeptions($funIntsQuery," tni",$userInterests,"interest");
    //Выбираем только 1 страницу
    $finalButtonsArray = array_slice($finalButtonsArray,($pageNum-1) * $intsPerPage,9);

    array_push($finalButtonsArray,array(
        array('text' => 'Следующая страница 👉', 'callback_data' => 'funInts 2')
    ));

    array_push($finalButtonsArray,array(
        array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst')
    ));

        $response = [
            'chat_id' => $user,
            'caption' => "*Выбери свой интерес:\n\nСейчас у тебя указано:* \n" . $msgArray,
            "parse_mode" => "Markdown",
            'photo' => curl_file_create("../tgBot/BotPic/post_333.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];
                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }    
}
if ($data['callback_query']['data'] == "funInts 2") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Вывод интересов пользователя
    $interests = mysqli_query ($con, "SELECT * FROM `Interests` WHERE userID='".$user."' ");
    $ui = mysqli_fetch_array($interests);

    $funIntsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'funInts'");
    $funInts      = mysqli_fetch_array($funIntsQuery);
    $pageNum = 2;
    $intsPerPage = 9;

    $userInterestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
    $userInterests      = mysqli_fetch_array($userInterestsCheck);

    $finalButtonsArray = GenerateButtonsPageWithExeptions($funIntsQuery," tni",$userInterests,"interest");
    //Выбираем только 1 страницу
    $finalButtonsArray = array_slice($finalButtonsArray,($pageNum-1) * $intsPerPage,9);

    array_push($finalButtonsArray,array(
        array('text' => '👈 Прошлая страница', 'callback_data' => 'funInts 1')
    ));

    array_push($finalButtonsArray,array(
        array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst')
    ));

    // Переменная для вывода в сообщение
    $msgArray = "";

    if (empty($ui['interest1'])) {
        $response = [
            'chat_id' => $user,
            'caption' => "*Выбери свой интерес:*",
            "parse_mode" => "Markdown",
            'photo' => curl_file_create("../tgBot/BotPic/post_333.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];
                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }else{
        // Выводим интересы в правильном виде
        if (!empty($ui['interest1'])) {
            $msgArray .= "\u{0031}\u{FE0F}\u{20E3}" . " - " . $ui['interest1'] . "\n";
        }
        if (!empty($ui['interest2'])) {
            $msgArray .= "\u{0032}\u{FE0F}\u{20E3}" . " - " . $ui['interest2'] . "\n";
        }    
        if (!empty($ui['interest3'])) {
            $msgArray .= "\u{0033}\u{FE0F}\u{20E3}" . " - " . $ui['interest3'] . "\n";
        }
        if (!empty($ui['interest4'])) {
            $msgArray .= "\u{0034}\u{FE0F}\u{20E3}" . " - " . $ui['interest4'] . "\n";
        }
        if (!empty($ui['interest5'])) {
            $msgArray .= "\u{0035}\u{FE0F}\u{20E3}" . " - " . $ui['interest5'] . "\n";
        }
        $funIntsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'funInts'");
        $funInts      = mysqli_fetch_array($funIntsQuery);
        $pageNum = 2;
        $intsPerPage = 9;

        $userInterestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
        $userInterests      = mysqli_fetch_array($userInterestsCheck);

        $finalButtonsArray = GenerateButtonsPageWithExeptions($funIntsQuery," tni",$userInterests,"interest");
        //Выбираем только 1 страницу
        $finalButtonsArray = array_slice($finalButtonsArray,($pageNum-1) * $intsPerPage,9);

        array_push($finalButtonsArray,array(
            array('text' => '👈 Прошлая страница', 'callback_data' => 'funInts 1')
        ));

        array_push($finalButtonsArray,array(
            array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst')
        ));

        $response = [
            'chat_id' => $user,
            'caption' => "*Выбери свой интерес:\n\nСейчас у тебя указано:* \n" . $msgArray,
            "parse_mode" => "Markdown",
            'photo' => curl_file_create("../tgBot/BotPic/post_333.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];
                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }    
}
if ($data['callback_query']['data'] == "businessInts 1") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Вывод интересов пользователя
    $interests = mysqli_query ($con, "SELECT * FROM `Interests` WHERE userID='".$user."' ");
    $ui = mysqli_fetch_array($interests);

    $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'businessInts'");
    $finalButtonsArray = GenerateButtonsPage($intsQuery,' tni',1,99);

    array_push($finalButtonsArray,array(
        array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst')
    ));

    // Переменная для вывода в сообщение
    $msgArray = "";

    if (empty($ui['interest1'])) {
        $response = [
            'chat_id' => $user,
            'caption' => "*Выбери свой интерес:*",
            "parse_mode" => "Markdown",
            'photo' => curl_file_create("../tgBot/BotPic/post_333.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];
                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }else{
        // Выводим интересы в правильном виде
        if (!empty($ui['interest1'])) {
            $msgArray .= "\u{0031}\u{FE0F}\u{20E3}" . " - " . $ui['interest1'] . "\n";
        }
        if (!empty($ui['interest2'])) {
            $msgArray .= "\u{0032}\u{FE0F}\u{20E3}" . " - " . $ui['interest2'] . "\n";
        }    
        if (!empty($ui['interest3'])) {
            $msgArray .= "\u{0033}\u{FE0F}\u{20E3}" . " - " . $ui['interest3'] . "\n";
        }
        if (!empty($ui['interest4'])) {
            $msgArray .= "\u{0034}\u{FE0F}\u{20E3}" . " - " . $ui['interest4'] . "\n";
        }
        if (!empty($ui['interest5'])) {
            $msgArray .= "\u{0035}\u{FE0F}\u{20E3}" . " - " . $ui['interest5'] . "\n";
        }
        
        $businessIntsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'businessInts'");
        $businessInts      = mysqli_fetch_array($businessIntsQuery);
        $pageNum = 1;
        $intsPerPage = 99;

        $userInterestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
        $userInterests      = mysqli_fetch_array($userInterestsCheck);

        $finalButtonsArray = GenerateButtonsPageWithExeptions($businessIntsQuery," tni",$userInterests,"interest");
        //Выбираем только 1 страницу
        $finalButtonsArray = array_slice($finalButtonsArray,($pageNum-1) * $intsPerPage,9);

        array_push($finalButtonsArray,array(
            array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst')
        ));

        $response = [
            'chat_id' => $user,
            'caption' => "*Выбери свой интерес:\n\nСейчас у тебя указано:* \n" . $msgArray,
            "parse_mode" => "Markdown",
            'photo' => curl_file_create("../tgBot/BotPic/post_333.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];
                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }    
}
if ($data['callback_query']['data'] == "sportInts 1") {
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    // Вывод интересов пользователя
    $interests = mysqli_query ($con, "SELECT * FROM `Interests` WHERE userID='".$user."' ");
    $ui = mysqli_fetch_array($interests);

    $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'sportInts'");
    $finalButtonsArray = GenerateButtonsPage($intsQuery,' tni',1,99);

    array_push($finalButtonsArray,array(
        array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst')
    ));
    // Переменная для вывода в сообщение
    $msgArray = "";

    if (empty($ui['interest1'])) {

        $response = [
            'chat_id' => $user,
            'caption' => "*Выбери свой интерес:*",
            "parse_mode" => "Markdown",
            'photo' => curl_file_create("../tgBot/BotPic/post_333.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];
                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }else{
        // Выводим интересы в правильном виде
        if (!empty($ui['interest1'])) {
            $msgArray .= "\u{0031}\u{FE0F}\u{20E3}" . " - " . $ui['interest1'] . "\n";
        }
        if (!empty($ui['interest2'])) {
            $msgArray .= "\u{0032}\u{FE0F}\u{20E3}" . " - " . $ui['interest2'] . "\n";
        }    
        if (!empty($ui['interest3'])) {
            $msgArray .= "\u{0033}\u{FE0F}\u{20E3}" . " - " . $ui['interest3'] . "\n";
        }
        if (!empty($ui['interest4'])) {
            $msgArray .= "\u{0034}\u{FE0F}\u{20E3}" . " - " . $ui['interest4'] . "\n";
        }
        if (!empty($ui['interest5'])) {
            $msgArray .= "\u{0035}\u{FE0F}\u{20E3}" . " - " . $ui['interest5'] . "\n";
        }

        $businessIntsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'sportInts'");
        $businessInts      = mysqli_fetch_array($businessIntsQuery);
        $pageNum = 1;
        $intsPerPage = 99;

        $userInterestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
        $userInterests      = mysqli_fetch_array($userInterestsCheck);

        $finalButtonsArray = GenerateButtonsPageWithExeptions($businessIntsQuery," tni",$userInterests,"interest");
        //Выбираем только 1 страницу
        $finalButtonsArray = array_slice($finalButtonsArray,($pageNum-1) * $intsPerPage,9);

        array_push($finalButtonsArray,array(
            array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst')
        ));
        $response = [
            'chat_id' => $user,
            'caption' => "*Выбери свой интерес:\n\nСейчас у тебя указано:* " . $msgArray,
            "parse_mode" => "Markdown",
            'photo' => curl_file_create("../tgBot/BotPic/post_333.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];
                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }    
}

// 2 кнопка
if ($data['callback_query']['data'] == "2chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");
    
    $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories`");
    $finalButtonsArray = GenerateButtonsPage($categoriesArray,' first',1,9);
    array_push($finalButtonsArray,
    array(
        array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'),
        array('text' => '2 страница 👉', 'callback_data' => '2.1chFirst')
    )
    );

    $response = [
        'chat_id' => $user,
        'photo' => curl_file_create("../tgBot/BotPic/post_335.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=> $finalButtonsArray
        ])
    ];

    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
// 2 кнопка
if ($data['callback_query']['data'] == "2.1chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");
    
    $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories` ");
    $finalButtonsArray = GenerateButtonsPage($categoriesArray,' first',2,9);
    array_push($finalButtonsArray,
    array(
        array('text' => '👈 1 страница', 'callback_data' => '2chFirst'),
        array('text' => '3 страница 👉', 'callback_data' => '2.2chFirst')
    )
    );


    $response = [
        'chat_id' => $user,
        'photo' => curl_file_create("../tgBot/BotPic/post_335.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=> $finalButtonsArray
        ])
    ];

    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
// 2 кнопка
if ($data['callback_query']['data'] == "2.2chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");
    
    $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories` ");
    $finalButtonsArray = GenerateButtonsPage($categoriesArray,' first',3,9);
    array_push($finalButtonsArray,
    array(
        array('text' => '👈 2 страница', 'callback_data' => '2.1chFirst')
    )
    );

    $response = [
        'chat_id' => $user,
        'photo' => curl_file_create("../tgBot/BotPic/post_335.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=> $finalButtonsArray
        ])
    ];

    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}

// 3 кнопка
if ($data['callback_query']['data'] == "3chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    $response = [
        'chat_id' => $user,
        'photo' => curl_file_create("../tgBot/BotPic/post_100.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [
                    ['text' => 'Женский', 'callback_data' => 'Женский SexOnReg']
                ],
                [
                    ['text' => 'Мужской', 'callback_data' => 'Мужской SexOnReg']
                ],
                [
                    ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu']
                ]
            ]
        ])
    ];
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}

// 4 кнопка
if ($data['callback_query']['data'] == "4chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories` ");
    $finalButtonsArray = GenerateButtonsPage($categoriesArray,' second',1,9);
    array_push($finalButtonsArray,
    array(
        array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'),
        array('text' => '2 страница 👉', 'callback_data' => '4.1chFirst')
    )
    );

    $response = [
        'chat_id' => $user,
        'photo' => curl_file_create("../tgBot/BotPic/post_336.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=> $finalButtonsArray
        ])
    ];

    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
// 4 кнопка
if ($data['callback_query']['data'] == "4.1chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories` ");
    $finalButtonsArray = GenerateButtonsPage($categoriesArray,' second',2,9);
    array_push($finalButtonsArray,
    array(
        array('text' => '👈 1 страница', 'callback_data' => '4chFirst'),
        array('text' => '3 страница 👉', 'callback_data' => '4.2chFirst')
    )
    );

    $response = [
        'chat_id' => $user,
        'photo' => curl_file_create("../tgBot/BotPic/post_336.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=> $finalButtonsArray
        ])
    ];

    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
// 4 кнопка
if ($data['callback_query']['data'] == "4.2chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories` ");
    $finalButtonsArray = GenerateButtonsPage($categoriesArray,' second',3,9);
    array_push($finalButtonsArray,
    array(
        array('text' => '👈 2 страница', 'callback_data' => '4.1chFirst')
    )
    );

    $response = [
        'chat_id' => $user,
        'photo' => curl_file_create("../tgBot/BotPic/post_336.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=> $finalButtonsArray
        ])
    ];

    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}

// 5 кнопка
if ($data['callback_query']['data'] == "5chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories` ");
    $finalButtonsArray = GenerateButtonsPage($categoriesArray,' third',1,9);
    array_push($finalButtonsArray,
    array(
        array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'),
        array('text' => '2 страница 👉', 'callback_data' => '5.1chFirst')
    )
    );

    $response = [
        'chat_id' => $user,
        'photo' => curl_file_create("../tgBot/BotPic/post_337.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=> $finalButtonsArray
        ])
    ];

    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
// 5 кнопка
if ($data['callback_query']['data'] == "5.1chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories` ");
    $finalButtonsArray = GenerateButtonsPage($categoriesArray,' third',2,9);
    array_push($finalButtonsArray,
    array(
        array('text' => '👈 1 страница', 'callback_data' => '5chFirst'),
        array('text' => '3 страница 👉', 'callback_data' => '5.2chFirst')
    )
    );

    $response = [
        'chat_id' => $user,
        'photo' => curl_file_create("../tgBot/BotPic/post_337.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=> $finalButtonsArray
        ])
    ];

    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
// 5 кнопка
if ($data['callback_query']['data'] == "5.2chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $send_data['message_id'] = $data['callback_query']['message']['message_id'];
    $send_data['chat_id'] = $user;
    sendTelegram('deleteMessage', $send_data);
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");
    $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories` ");
    $finalButtonsArray = GenerateButtonsPage($categoriesArray,' third',3,9);
    array_push($finalButtonsArray,
    array(
        array('text' => '👈 2 страница', 'callback_data' => '5.1chFirst')
    )
    );

    $response = [
        'chat_id' => $user,
        'photo' => curl_file_create("../tgBot/BotPic/post_337.jpg"),
        'reply_markup'=>json_encode([
            'inline_keyboard'=> $finalButtonsArray
        ])
    ];

    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}

// Если мы получили локацию
if($func['location'] != ""){
    $user = $func['from']['id'];

    // Удаление локации
    $send_data['message_id'] = $func['message_id'];
    $send_data['chat_id'] = $func['chat']['id'];
    sendTelegram('deleteMessage', $send_data);

    // Удаление запроса на локацию
    $send_data['message_id'] = $func['reply_to_message']['message_id'];
    sendTelegram('deleteMessage', $send_data);

    /*// Проверяем в какой ветке дать пользователю возможность писать
    if (strpos($func['reply_to_message']['text'], "общения") {
        
    }else if (strpos($func['reply_to_message']['text'], "обучения"){
        
    }else if (strpos($func['reply_to_message']['text'], "знакомств"){
        
    }else if (strpos($func['reply_to_message']['text'], "специалиста"){
        
    }else{

    }*/

   $response = [
        'chat_id' => $user,
        'caption' => "👌 Отлично, чат в твоем городе я уже нашел, но для полной регистрации мне нужно знать твой номер. \nНажми на кнопку ниже 👇",
        'parse_mode' => "Markdown",
        
        'photo' => curl_file_create("../tgBot/BotPic/post_192.png"),
        'reply_markup'=>json_encode([
            resize_keyboard =>true,
            one_time_keyboard => true,
            'keyboard' => [
                [
                    ['text' => '📱 Поделиться номером', request_contact => true]
                ]
            ]
        ])
    ];                 
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}

// Если мы получили номер телефона
if($func['contact']['phone_number'] != ""){
    $user = $func['from']['id'];
        // Получаем id нашего главного сообщения
    $mainID = mysqli_query ($con, "SELECT `mesToChange` FROM `TrackingMenu` WHERE userID='".$user."' ");
    $mes = mysqli_fetch_array($mainID);
        // Удаление номера
    $send_data['message_id'] = $func['message_id'];
    $send_data['chat_id'] = $func['chat']['id'];
    sendTelegram('deleteMessage', $send_data);
        // Удаление запроса номера
    $send_data['message_id'] = $func['reply_to_message']['message_id'];
    sendTelegram('deleteMessage', $send_data);
        // Удаление первого меню
    $send_data['message_id'] = $mes['mesToChange'];
    sendTelegram('deleteMessage', $send_data);
        // Пушим номер в БД
    mysqli_query ($con, "UPDATE `MainInfo` SET userNum = '".$func['contact']['phone_number']."', isRegistered = '1' WHERE userID=".$user." ");
    /*// Проверяем в какой ветке дать пользователю возможность писать
    if (strpos($func['reply_to_message']['text'], "общения") {
        
    }else if (strpos($func['reply_to_message']['text'], "обучения"){
        
    }else if (strpos($func['reply_to_message']['text'], "знакомств"){
        
    }else if (strpos($func['reply_to_message']['text'], "специалиста"){
        
    }else{

    }*/
    $response = [
        'chat_id' => $user,
        'caption' => '[Деловая Одесса](https://t.me/+8mMjL5dm2c0zYTVi)',
        'parse_mode' => "Markdown",
        'disable_web_page_preview' => true,
        
        'photo' => curl_file_create("../tgBot/BotPic/post_237.jpg")
    ];                 
    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
    curl_setopt($ch, CURLOPT_POST, 1);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);

        // Выводим человека из всех меню
    $user = $func['from']['id'];
    mysqli_query($con, "UPDATE `TrackingMenu` SET whichMenu = '' WHERE userID = '".$user."' ");
        // Даем доступ к меню
    $method = 'sendMessage';
    $send_data = [
        'text' => '📋 *Главное меню:*',
        'parse_mode' => 'markdown',
        'reply_markup' => [
            'inline_keyboard' => [
                [
                    ['text' => '😁 Мой профиль', 'callback_data' => 'profile']  
                ],
                [
                    ['text' => '🔎 Поиск людей', 'callback_data' => 'peopleFinder']
                ],
                /*[
                    ['text' => '👑 Моя подписка', 'callback_data' => 'mySubscription']
                ],*/
                [
                    ['text' => '💰 Монеты', 'callback_data' => 'myCoins']
                ],
                [
                    ['text' => '📈 Моя статистика', 'callback_data' => 'myStats']  
                ],
                /*[
                    ['text' => '📊 Топ пользователей', 'callback_data' => 'stat']
                ],*/
                [
                    ['text' => '🗣️ Сообщить об идее/ошибке', 'callback_data' => 'feedback']
                ],
                [
                    ['text' => 'FAQ', 'callback_data' => 'faq']
                ]
            ]
        ]
    ];
    $send_data['chat_id'] = $func['chat']['id'];
    sendTelegram($method, $send_data);
}

// Если мы получили сообщение и оно не отработало выше
if ($data['message']['text']) {
    $user = $func['from']['id'];
    $mesID = $func['message_id'];
        // Подключаемся к базе и ищем в каком меню находится пользователь и все остальное
    $MainCheck = mysqli_query ($con, "SELECT * FROM `MainInfo` WHERE userID='".$user."' ");
    $TrackCheck = mysqli_query ($con, "SELECT * FROM `TrackingMenu` WHERE userID='".$user."' ");
    $SocialCheck = mysqli_query ($con, "SELECT * FROM `Socials` WHERE userID='".$user."' ");
    $rewardsCheck = mysqli_query ($con, "SELECT `SkillsReward`, `InterestsReward`, `NeedsReward` FROM `userRewards` WHERE userID='".$user."' ");
        // Обрабатываем запросы
    $main = mysqli_fetch_array($MainCheck);
    $track = mysqli_fetch_array($TrackCheck);
    $social = mysqli_fetch_array($SocialCheck);
    $reward = mysqli_fetch_array($rewardsCheck);

    if ($track['whichMenu'] == "ФИДБЭК") {
            // Пушим сообщение пользователя в БД
        $user = $func['from']['id'];
        $a = mysqli_query ($con, "INSERT INTO `feedback` (`message`, `userid`) VALUES ('".$data['message']['text']."', '".$user."' )");
            // Получаем id сообщения, которое будем менять из БД
        $mesToChange = mysqli_query ($con, "SELECT `mesToChange` FROM `TrackingMenu` WHERE userID='".$user."' ");
        $mes = mysqli_fetch_array($mesToChange);
            // Удаляем сообщение
        $send_data['message_id'] = $data['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
            // Выводим благодарность
        $method = 'sendMessage';
        $send_data = [
            'text' => '*Спасибо большое! Благодаря тебе, я становлюсь лучше с каждым днем!*',
            'parse_mode' => 'markdown',
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => '👈 Вернуться в главное меню', 'callback_data' => 'mainMenu']  
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $data['message']['chat']['id'];
        $send_data['message_id'] = $mes['mesToChange'];
        sendTelegram($method, $send_data);
    }

    else if ($track['whichMenu'] == "ДобавлениеФото" && $data['callback_query']['message']['from']['is_bot'] == 1){
        $user = $data['callback_query']['from']['id'];
        $mesID = $data['callback_query']['message']['message_id'];
        mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = '".$mesID."' WHERE userID = ".$user." ");
    }

    else if ($track['whichMenu'] == "ИмяФамилия") {
        // Проверяем первое ли это сообщение в БД
        if (empty($track['rowsToDel'])) {
            // Если да, тогда сразу пушим этот id и содержание сообщения в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$mesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `TrackingMenu` SET oldName = '".$main['userName']."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `MainInfo` SET name = '".$data['message']['text']."' WHERE userID = ".$user." ");
        }else{
            // Если же там что-то было, тогда плюсуем новый id к старым
            $newMesID = $track['rowsToDel'] . " , " . $mesID;

            // Пушим в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$newMesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `MainInfo` SET name = '".$data['message']['text']."' WHERE userID = ".$user." ");
        }
    }

    elseif ($track['whichMenu'] == "ФамилияИмя") {
        // Проверяем первое ли это сообщение в БД
        if (empty($track['rowsToDel'])) {
            // Если да, тогда сразу пушим этот id и содержание сообщения в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$mesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `TrackingMenu` SET oldName = '".$main['userName']."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `MainInfo` SET surname = '".$data['message']['text']."' WHERE userID = ".$user." ");
        }else{
            // Если же там что-то было, тогда плюсуем новый id к старым
            $newMesID = $track['rowsToDel'] . " , " . $mesID;

            // Пушим в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$newMesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `MainInfo` SET surname = '".$data['message']['text']."' WHERE userID = ".$user." ");
        }
    }

    else if ($track['whichMenu'] == "Изменить возраст") {
            // Проверяем первое ли это сообщение в БД
        if (empty($track['rowsToDel'])) {
                // Если да, тогда сразу пушим этот id и содержание сообщения в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$mesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `TrackingMenu` SET oldAge = '".$main['userAge']."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `MainInfo` SET userAge = '".$message."' WHERE userID = ".$user." ");
        }else{
                // Если же там что-то было, тогда плюсуем новый id к старым
            $newMesID = $track['rowsToDel'] . " , " . $mesID;
                // Пушим в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$newMesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `MainInfo` SET userAge = '".$message."' WHERE userID = ".$user." ");
        }
    }

    else if ($track['whichMenu'] == "инста"){
            // Проверяем первое ли это сообщение в БД
        if (empty($track['rowsToDel'])) {
                // Если да, тогда сразу пушим этот id и содержание сообщения в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$mesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `TrackingMenu` SET oldNeeds = '".$social['inst']."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `Socials` SET inst = '".$message."' WHERE userID = ".$user." ");
        }else{
                // Если же там что-то было, тогда плюсуем новый id к старым
            $newMesID = $track['rowsToDel'] . " , " . $mesID;
                // Пушим в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$newMesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `Socials` SET inst = '".$message."' WHERE userID = ".$user." ");
        }
    }

    else if ($track['whichMenu'] == "tiktok"){
            // Проверяем первое ли это сообщение в БД
        if (empty($track['rowsToDel'])) {
                // Если да, тогда сразу пушим этот id и содержание сообщения в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$mesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `TrackingMenu` SET oldNeeds = '".$social['tiktok']."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `Socials` SET tiktok = '".$message."' WHERE userID = ".$user." ");
        }else{
                // Если же там что-то было, тогда плюсуем новый id к старым
            $newMesID = $track['rowsToDel'] . " , " . $mesID;
                // Пушим в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$newMesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `Socials` SET tiktok = '".$message."' WHERE userID = ".$user." ");
        }
    }

    else if ($track['whichMenu'] == "facebook"){
            // Проверяем первое ли это сообщение в БД
        if (empty($track['rowsToDel'])) {
                // Если да, тогда сразу пушим этот id и содержание сообщения в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$mesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `TrackingMenu` SET oldNeeds = '".$social['facebook']."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `Socials` SET facebook = '".$message."' WHERE userID = ".$user." ");
        }else{
                // Если же там что-то было, тогда плюсуем новый id к старым
            $newMesID = $track['rowsToDel'] . " , " . $mesID;
                // Пушим в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$newMesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `Socials` SET facebook = '".$message."' WHERE userID = ".$user." ");
        }
    }

    else if ($track['whichMenu'] == "viber"){
            // Проверяем первое ли это сообщение в БД
        if (empty($track['rowsToDel'])) {
                // Если да, тогда сразу пушим этот id и содержание сообщения в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$mesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `TrackingMenu` SET oldNeeds = '".$social['viber']."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `Socials` SET viber = '".$message."' WHERE userID = ".$user." ");
        }else{
                // Если же там что-то было, тогда плюсуем новый id к старым
            $newMesID = $track['rowsToDel'] . " , " . $mesID;
                // Пушим в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$newMesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `Socials` SET viber = '".$message."' WHERE userID = ".$user." ");
        }
    }

    else if ($track['whichMenu'] == "whatsapp"){
            // Проверяем первое ли это сообщение в БД
        if (empty($track['rowsToDel'])) {
                // Если да, тогда сразу пушим этот id и содержание сообщения в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$mesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `TrackingMenu` SET oldNeeds = '".$social['whatsapp']."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `Socials` SET whatsapp = '".$message."' WHERE userID = ".$user." ");
        }else{
                // Если же там что-то было, тогда плюсуем новый id к старым
            $newMesID = $track['rowsToDel'] . " , " . $mesID;
                // Пушим в БД
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$newMesID."' WHERE userID = ".$user." ");
            mysqli_query ($con, "UPDATE `Socials` SET whatsapp = '".$message."' WHERE userID = ".$user." ");
        }
    }

    else if ($track['whichMenu'] == "anotherSocial"){
            // Проверяем первое ли это сообщение в БД
        if (empty($track['rowsToDel'])) {
                // Если да, тогда сразу пушим этот id и содержание сообщения в БД
            $updateRows = mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$mesID."' WHERE userID = ".$user." ");
            $saveOldInterests = mysqli_query ($con, "UPDATE `TrackingMenu` SET oldNeeds = '".$social['anotherSocial']."' WHERE userID = ".$user." ");
            $updateInterests = mysqli_query ($con, "UPDATE `Socials` SET anotherSocials = '".$message."' WHERE userID = ".$user." ");
        }else{
                // Если же там что-то было, тогда плюсуем новый id к старым
            $newMesID = $track['rowsToDel'] . " , " . $mesID;
                // Пушим в БД
            $updateRows = mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '".$newMesID."' WHERE userID = ".$user." ");
            $updateInterests = mysqli_query ($con, "UPDATE `Socials` SET anotherSocials = '".$message."' WHERE userID = ".$user." ");
        }
    }

    else if ($track['whichMenu'] == "ОтправляетСообщение"){
        // Удаляем сообщение, которое прислал пользователь
        $send_data['message_id'] = $mesID;
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
        mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToSend = '".$data['message']['text']."' WHERE userID = ".$user." ");
    }

    else if ($track['whichMenu'] == "send3Geo4From5List"){
        // Удаляем сообщение, которое прислал пользователь
        $send_data['message_id'] = $mesID;
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Достаем id сообщения, для изменения
        $idCheck = mysqli_query ($con, "SELECT `mesToChange` FROM `TrackingMenu` WHERE userID = ".$user." ");
        $ids = mysqli_fetch_row($idCheck);

        // Лезем в базу, чтоб найти нужный город или страну
        $cityCheck = mysqli_query ($con, "SELECT `City`, `Country` FROM `CitiesAndCountries` WHERE (City LIKE '%".$data['message']['text']."%') OR (Country LIKE '%".$data['message']['text']."%')" );
        $city = mysqli_fetch_row($cityCheck);

        $cities = "";
        $counter = 0;
        $buttons = array();
        $co = "";

        foreach ($cityCheck as $key => $value) {
            mysqli_fetch_array($value);
            foreach ($value as $key => $value) {
                $counter += 1;
               if ($cities == "") {
                   $cities = $value;
               }else{
                    $cities .= ", ".$value;
                    $co = explode(", ", $cities);
                    array_push($buttons, array(array('text' => $cities, 'callback_data' => $cities."1122")));
                    /*file_put_contents('cities.txt', print_r($cities, 1)."\n", FILE_APPEND);*/
                    $cities = "";
               }
            }
        }

        if ($counter == 0) {
            $method = 'editMessageText';
            $send_data = [
                'text' => "Упс. Я ничего не нашел. Попробуй написать иначе, возможно ты ошибся",
            ];
            $send_data['chat_id'] = $user;
            $send_data['message_id'] = $ids[0];
            sendTelegram($method, $send_data);
            return;
        }else{
            $method = 'editMessageText';
            $send_data = [
                'text' => "Я нашел: ",
                'reply_markup' => [
                    'inline_keyboard' => $buttons
                ]
            ];
            $send_data['chat_id'] = $user;
            $send_data['message_id'] = $ids[0];
            sendTelegram($method, $send_data);
            return;
        }
    }

    else{
        // Удаляем любое другое сообщение
        $send_data['message_id'] = $mesID;
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
    }
}

// Обработчик фото
if (isset($data['message']['photo'])) {
    // Проверяем, если человек в меню "Добавление фото", тогда действуем, если нет - удаляем
    $user = $func['from']['id'];
    $checkMenu = mysqli_query ($con, "SELECT `whichMenu`, `mesToChange` FROM `TrackingMenu` WHERE userID='".$user."' ");
    $menu = mysqli_fetch_array($checkMenu);
    if ($menu['whichMenu'] == "ДобавлениеФото") {
        $photo = $data['message']['photo'][3];
    
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/getFile');
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('file_id' => $photo['file_id']));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $res = json_decode($res, true);
        if ($res['ok']) {
            // Закидываем фотку в папку isApprove
            $src  = 'https://api.telegram.org/file/bot' . TOKEN . '/' . $res['result']['file_path'];
            $p = time() . '-' . basename($src);
            $dest = "../tgBot/isApproved/" . $p;
            copy($src, $dest);
            // В БД в таблицу newPhotos закидываем имя фотки, id тг пользователя
            mysqli_query ($con, "INSERT INTO `newPhotos`(`userID`, `photo`) VALUES ('".$user."','".$p."') ");

            #В админке 2 кнопки - да, нет
            #Если нажать на нет - срабатывает js скрипт, который вызовет php скрипт, который удалит эту фотку с сервера
            #Если нажать на да - срабатывает js скрипт, который вызовет php скрипт, который перенесет эту фотку с папки isAprove в userPhotos

            // Удаляем фотку
            $send_data['message_id'] = $func['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Удаляем собщение с инструкцией
            $send_data['message_id'] = $menu['mesToChange'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = '' WHERE userID = ".$user." ");

            // Получаем из БД все о пользователе
        $user = $func['from']['id'];
        $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto` FROM `MainInfo` WHERE userID='".$user."' ");
        $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
        $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5`, `s6` FROM `Skills` WHERE userID='".$user."' ");
        $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5`, `n6` FROM `Needs` WHERE userID='".$user."' ");
        $socialCheck = mysqli_query ($con, "SELECT `inst`, `tiktok`, `viber`, `whatsapp`, `facebook`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
            
        $prof = mysqli_fetch_array($profCheck);
        $skill = mysqli_fetch_row($skillsCheck);
        $need = mysqli_fetch_row($needsCheck);
        $int = mysqli_fetch_row($intsCheck);
        $socials = mysqli_fetch_array($socialCheck);

        $msgText1 = "";
        $msgText2 = "";
        $msgText3 = "";

        if (!empty($skill)) {
            $msgText1 = "\n🧑‍💻 <i>Мои навыки:</i> \n";
                // Выводим скиллы в правильном виде
            foreach ($skill as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText1 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText1 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText1 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText1 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText1 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText1 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        if (!empty($need)) {
            $msgText2 = "\n📝 <i>Мои ценности:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($need as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText2 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }    
        
        if (!empty($int)) {
            $msgText3 = "\n🚲 <i>Мои интересы:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($int as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        $soc = "";

        if (!empty($prof['userAge'])) {
            $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
        }

        if (!empty($socials['inst'])) {
            $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b>";
            if ($soc == "") {
                $soc = $inst;
            }else{
                $soc .= ", ".$inst;
            }
        }else{
            $inst = "";
        }

        if (!empty($socials['tiktok'])) {
            $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b>";
            if ($soc == "") {
                $soc = $tiktok;
            }else{
                $soc .= ", ".$tiktok;
            }
        }else{
            $tiktok = "";
        }

        if (!empty($socials['facebook'])) {
            $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b>";
            if ($soc == "") {
                $soc = $facebook;
            }else{
                $soc .= ", ".$facebook;
            }
        }else{
            $facebook = "";
        }

        if (!empty($socials['viber'])) {
            $viber = "<b>Viber - ".$socials['viber']."</b>";
            if ($soc == "") {
                $soc = $viber;
            }else{
                $soc .= ", ".$viber;
            }
        }else{
            $viber = "";
        }

        if (!empty($socials['whatsapp'])) {
            $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b>";
            if ($soc == "") {
                $soc = $whatsapp;
            }else{
                $soc .= ", ".$whatsapp;
            }
        }else{
            $whatsapp = "";
        }

        if (!empty($socials['anotherSocials'])) {
            $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b>";
            if ($soc == "") {
                $soc = $anotherSocials;
            }else{
                $soc .= ", ".$anotherSocials;
            }
        }else{
            $anotherSocials = "";
        }

            $response = [
                    'chat_id' => $user,
                    'text' => "😁 <b>Мой профиль\n\n".$prof['name']." ".$prof['surname']."</b>".$age.$msgText1.$msgText2.$msgText3."\n".$soc,
                    "parse_mode" => "html",
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => '🤴 Личные данные', 'callback_data' => 'myNameAge']  
                            ],
                            [
                                ['text' => '🧑‍💻 Мои навыки', 'callback_data' => 'mySkills']
                            ],
                            [
                                ['text' => '🚲 Мои интересы', 'callback_data' => 'myInterests']
                            ],
                            [
                                ['text' => '📝 Мои ценности', 'callback_data' => 'myNeeds']
                            ],
                            [
                                ['text' => 'Мои соцсети', 'callback_data' => 'mySocial']
                            ],
                            [
                                ['text' => '🗣 Реферальная ссылка', 'callback_data' => 'myAffiliate']
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];
                    
            $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');  
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_exec($ch);
            curl_close($ch);
            return;
        }else{
            $send_data['message_id'] = $func['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Упс, проблемка(",
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
        }
    }else{
        // Удаляем любое другое сообщение
        $send_data['message_id'] = $func['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
    }
}

    // Обработчик меню
if (isset($data['callback_query'])) {
        // Подключаемся к базе наград, чтобы знать выдавать награды или нет
    $rewardsCheck = mysqli_query ($con, "SELECT `SkillsReward`, `InterestsReward`, `NeedsReward` FROM `userRewards` WHERE userID='".$func['from']['id']."' ");
    $rewards = mysqli_fetch_array($rewardsCheck);

        // Обработка и пуш навыков в БД
    if (strpos($data['callback_query']['data'], 'Trainee') !== false || strpos($data['callback_query']['data'], 'Junior') !== false || strpos($data['callback_query']['data'], 'Middle') !== false || strpos($data['callback_query']['data'], 'Senior') !== false) {

        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

            // Вычисляем профессию и навык
        $msgArray = explode(",", $data['callback_query']['data']);
        $skillsQuery = mysqli_query($con,"SELECT `name` FROM `SkillsList` WHERE `callbackData` ='".trim($msgArray[1])."' ");
        $skillToAdd = mysqli_fetch_array($skillsQuery);
        $newSkill = trim($skillToAdd['name']);
        $level = trim($msgArray[0]);
        $addNewSkill = $level . " - " . $newSkill;

            // Получаем из БД все навыки
        $skillCheck = mysqli_query ($con, "SELECT * FROM `Skills` WHERE userID='".$user."' ");
        $skill = mysqli_fetch_array($skillCheck);

        $lvlCheck = mysqli_query ($con, "SELECT * FROM `SkillAdds` WHERE userID='".$user."' ");
        $lvl = mysqli_fetch_array($lvlCheck);

        $msgArray = "";

                // Проверяем есть ли выбранный навык в базе
            if ($skill['s1'] == $newSkill or $skill['s2'] == $newSkill or $skill['s3'] == $newSkill or $skill['s4'] == $newSkill or $skill['s5'] == $newSkill or strpos($skill['s6'], $newSkill)) {
                    // Выводим интересы в правильном виде
                if (!empty($skill['s1'])) {
                    $msgArray .= "\u{0031}\u{FE0F}\u{20E3}" . " - " . $skill['s1'] . "\n";
                }
                if (!empty($skill['s2'])) {
                    $msgArray .= "\u{0032}\u{FE0F}\u{20E3}" . " - " . $skill['s2'] . "\n";
                }    
                if (!empty($skill['s3'])) {
                    $msgArray .= "\u{0033}\u{FE0F}\u{20E3}" . " - " . $skill['s3'] . "\n";
                }
                if (!empty($skill['s4'])) {
                    $msgArray .= "\u{0034}\u{FE0F}\u{20E3}" . " - " . $skill['s4'] . "\n";
                }
                if (!empty($skill['s5'])) {
                    $msgArray .= "\u{0035}\u{FE0F}\u{20E3}" . " - " . $skill['s5'] . "\n";
                }
                if (!empty($skill['s6'])) {
                    $msgArray .= $skill['s6'] . "\n";
                }

                $method = 'sendMessage';
                $send_data = [
                    'text' => "_Упс. У вас в профиле уже есть навык_ *" . $newSkill . "*\n\n_Сейчас список ваших навыков выглядит так:_ *\n" . $msgArray."*",
                    'parse_mode' => 'markdown',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Выбрать другой навык', 'callback_data' => 'choiceSkills']
                            ],
                            [
                                ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                // Если это первый навык
                if (empty($skill['s1'])) {
                    // Пушим в БД новую профессию
                    $updateDB = mysqli_query ($con, "UPDATE `Skills` SET s1 = '".$newSkill."', lvl1 = '".$level."' WHERE userID = ".$user." ");

                    $method = 'sendMessage';
                    $send_data = [
                        'text' => "_Вы добавили профессию:_ *" . $newSkill . "*\n_С уровнем владения:_ *" . $level ."*",
                        'parse_mode' => 'markdown',
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                ],
                                [
                                    ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                                ]
                            ]
                        ]
                    ];
                    $send_data['chat_id'] = $func['message']['chat']['id'];
                    $send_data['message_id'] = $func['message']['message_id'];
                    sendTelegram($method, $send_data);
                    return;
                }else{
                        // Выводим интересы в правильном виде
                    if (!empty($skill['s1'])) {
                        $msgArray .= "\u{0031}\u{FE0F}\u{20E3}" . " - " . $skill['s1'] . "\n";
                    }
                    if (!empty($skill['s2'])) {
                        $msgArray .= "\u{0032}\u{FE0F}\u{20E3}" . " - " . $skill['s2'] . "\n";
                    }    
                    if (!empty($skill['s3'])) {
                        $msgArray .= "\u{0033}\u{FE0F}\u{20E3}" . " - " . $skill['s3'] . "\n";
                    }
                    if (!empty($skill['s4'])) {
                        $msgArray .= "\u{0034}\u{FE0F}\u{20E3}" . " - " . $skill['s4'] . "\n";
                    }
                    if (!empty($skill['s5'])) {
                        $msgArray .= "\u{0035}\u{FE0F}\u{20E3}" . " - " . $skill['s5'] . "\n";
                    }
                    if (!empty($skill['s6'])) {
                        $msgArray .="\n*Дополнительные навыки: *" . $skill['s6'] . "\n";
                    }

                    if (empty($skill['s2'])) {
                        // Пушим в БД новую профессию
                        $updateDB = mysqli_query ($con, "UPDATE `Skills` SET s2 = '".$newSkill."', lvl2 = '".$level."' WHERE userID = ".$user." ");

                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "_Вы добавили профессию:_ *" . $newSkill . "*\n_С уровнем владения:_ *" . $level . "*\n\n_Сейчас список ваших навыков выглядит так:_ *" . $msgArray."*",
                            'parse_mode' => 'markdown',
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                    ],
                                    [
                                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }
                    else if (empty($skill['s3'])) {
                        // Пушим в БД новую профессию
                        $updateDB = mysqli_query ($con, "UPDATE `Skills` SET s3 = '".$newSkill."', lvl3 = '".$level."' WHERE userID = ".$user." ");

                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "_Вы добавили профессию:_ *" . $newSkill . "*\n_С уровнем владения:_ *" . $level . "*\n\n_Сейчас список ваших навыков выглядит так:_ *" . $msgArray."*",
                            'parse_mode' => 'markdown',
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                    ],
                                    [
                                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }
                    else if (empty($skill['s4'])) {
                        // Пушим в БД новую профессию
                        $updateDB = mysqli_query ($con, "UPDATE `Skills` SET s4 = '".$newSkill."', lvl4 = '".$level."' WHERE userID = ".$user." ");

                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "_Вы добавили профессию:_ *" . $newSkill . "*\n_С уровнем владения:_ *" . $level . "*\n\n_Сейчас список ваших навыков выглядит так:_ *" . $msgArray."*",
                            'parse_mode' => 'markdown',
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                    ],
                                    [
                                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }
                    else if (empty($skill['s5'])) {
                        // Пушим в БД новую профессию
                        $updateDB = mysqli_query ($con, "UPDATE `Skills` SET s5 = '".$newSkill."', lvl5 = '".$level."' WHERE userID = ".$user." ");

                        if ($rewards['SkillsReward'] == 0) {
                            // Пушим, что дали награду
                            mysqli_query ($con, "UPDATE `userRewards` SET SkillsReward = 1 WHERE userID = ".$user." ");

                            // Получаем кол-во монет пользователя
                            $selectCoins = mysqli_query ($con, "SELECT `coins` FROM `MainInfo` WHERE userID='".$user."' ");
                            $coins = mysqli_fetch_array($selectCoins);

                            // Плюсуем к монетам награду
                            $coins = $coins['coins'] + 100;

                            // Выдаем монеты
                            mysqli_query ($con, "UPDATE `MainInfo` SET coins = '".$coins."' WHERE userID = ".$user." ");

                            $response = [
                                'chat_id' => $user,
                                'caption' => "_Вы добавили профессию:_ *" . $newSkill . "*\n_С уровнем владения:_ *" . $level . "*\n\n_Сейчас список ваших навыков выглядит так:_ \n*" . $msgArray."\u{0035}\u{FE0F}\u{20E3}" . " - " . $newSkill . "\n*" . "\n_Вы получили_ *100 монет за добавление 5 навыков*_. Узнать кол-во монет и как их получить, вы можете нажав на кнопку 'Монеты' в главном меню_",
                                "parse_mode" => "Markdown",
                                
                                'photo' => curl_file_create("../tgBot/BotPic/post_199.jpg"),
                                'reply_markup'=>json_encode([
                                    'inline_keyboard'=>[
                                        [
                                            ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                        ],
                                        [
                                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                                        ]
                                    ]
                                ])
                            ];                 
                            $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                            curl_setopt($ch, CURLOPT_POST, 1);  
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HEADER, false);
                            curl_exec($ch);
                            curl_close($ch);
                            return;
                        }else{
                            $response = [
                                'chat_id' => $user,
                                'caption' => "_Вы добавили профессию:_ *" . $newSkill . "*\n_С уровнем владения:_ *" . $level . "*\n\n_Сейчас список ваших навыков выглядит так:_ *" . $msgArray."*",
                                "parse_mode" => "Markdown",
                                
                                'photo' => curl_file_create("../tgBot/BotPic/post_199.jpg"),
                                'reply_markup'=>json_encode([
                                    'inline_keyboard'=>[
                                        [
                                            ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                        ],
                                        [
                                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                                        ]
                                    ]
                                ])
                            ];                 
                            $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                            curl_setopt($ch, CURLOPT_POST, 1);  
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HEADER, false);
                            curl_exec($ch);
                            curl_close($ch);
                            return;
                        }  
                    }
                    else {
                        if (empty($skill['s6'])) {
                            // Пушим в БД новую профессию
                            $push = "(".$level.")".$newSkill;
                            mysqli_query ($con, "UPDATE `Skills` SET s6 = '".$push."' WHERE userID = ".$user." "); 
                        }else{
                            // Пушим в БД новую профессию
                            $push = $skill['s6'] . ", " . "(".$level.")".$newSkill;
                            mysqli_query ($con, "UPDATE `Skills` SET s6 = '".$push."' WHERE userID = ".$user." "); 
                        }
                    }

                    $method = 'sendMessage';
                    $send_data = [
                        'text' => "_Вы добавили профессию:_ *" . $newSkill . "*\n_С уровнем владения:_ *" . $level . "*\n\n_Сейчас список ваших навыков выглядит так:_ *" . $msgArray."*",
                        'parse_mode' => 'markdown',
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                ],
                                [
                                    ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                                ]
                            ]
                        ]
                    ];
                    $send_data['chat_id'] = $func['message']['chat']['id'];
                    $send_data['message_id'] = $func['message']['message_id'];
                    sendTelegram($method, $send_data);
                    return;
                }
            }    
    }
    // Удаление интересов
    else if (strpos($data['callback_query']['data'], '1135') !== false) {
        // Достаем что человек хочет удалить
        $wordData = preg_replace("/1135/i", "", $data['callback_query']['data']);
        $wordData = trim($wordData);
        $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE callbackData ='".$wordData."'");
        $interest = mysqli_fetch_array($intsQuery);
        $word = trim($interest['name']);

        // Достаем из базы все Интересы
        $user = $func['from']['id'];
        $profCheck = mysqli_query($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        if (trim($prof[0]) == $word) {
            mysqli_query($con, "UPDATE `Interests` SET interest1 = '' WHERE userID = ".$user." ");
        }else if (trim($prof[1]) == $word) {
            mysqli_query($con, "UPDATE `Interests` SET interest2 = '' WHERE userID = ".$user." ");
        }else if (trim($prof[2]) == $word) {
            mysqli_query($con, "UPDATE `Interests` SET interest3 = '' WHERE userID = ".$user." ");
        }else if (trim($prof[3]) == $word) {
            mysqli_query($con, "UPDATE `Interests` SET interest4 = '' WHERE userID = ".$user." ");
        }else if (trim($prof[4]) == $word) {
            mysqli_query($con, "UPDATE `Interests` SET interest5 = '' WHERE userID = ".$user." ");
        }else{
            $trimmedS6 = trim($prof[5]);
            $ar = explode("," , $trimmedS6);
            $arr = "";
            foreach ($ar as $key => $value) {
                if (trim($value) == $word) {
                    $arr .= "";
                }else{
                    if ($arr == "") {
                        $arr .= $value;
                    }else{
                        $arr .= ", " . trim($value);
                    }
                }
            }
            mysqli_query($con, "UPDATE `Interests` SET interest6 = '".$arr."' WHERE userID = ".$user." ");
        }

        // Удаляем сообщение по которому нажали
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        // Отправляем новое сообщение, если скилов в профиле больше нет
        if (empty($prof[0]) and empty($prof[1]) and empty($prof[2]) and empty($prof[3]) and empty($prof[4]) and empty($prof[5])) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "🚲 *Мои интересы:*",
                'parse_mode' => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '➕ Добавить интересы', 'callback_data' => 'pushInterests']  
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']  
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }
        // Отправляем новое сообщение, если в профиле еще есть другие скилы
        else{
            $arrTo6 = array();
            $msgText3 = "";
            $btnsArray = array();
            array_push($btnsArray, array(array('text' => '➕ Добавить интересы', 'callback_data' => 'pushInterests')));
            // Выводим ценности в правильном виде
            foreach ($prof as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $skills6 = explode("," , $value);
                    foreach ($skills6 as $key => $value1) {
                        $msgText3 .= trim($value1) . "\n";
                        array_push($arrTo6, trim($value1));
                    }
                }
            }

            foreach ($prof as $key => $value) {
                if (!empty($value) and $key < 5) {
                    $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE name = '".$value."'");
                    $interest = mysqli_fetch_array($intsQuery);
                    array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value), 'callback_data' => $interest['callbackData']." 2333")));
                }else{
                    if (!empty($value)) {
                        foreach ($arrTo6 as $key => $value) {
                            $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE name = '".$value1."'");
                            $interest = mysqli_fetch_array($intsQuery);
                            array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value1), 'callback_data' => $interest['callbackData']." 2333")));
                        }
                    }
                }
            }

            array_push($btnsArray, array(array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')));
            $method = 'sendMessage';
            $send_data = [
                'text' => "🚲 *Мои интересы:*\n\n" . $msgText3,
                'parse_mode' => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => $btnsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }
    }
    // Изменение интересов
    else if (strpos($data['callback_query']['data'], '3332') !== false) {
        // Удаляем сообщение по которому нажали
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        // Достаем что человек хочет заменить
        $wordData = preg_replace("/3332/i", "", $data['callback_query']['data']);
        $wordData = trim($wordData);
        $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE callbackData ='".$wordData."'");
        $interests = mysqli_fetch_array($intsQuery);
        $word = trim($interests['name']);


        // Достаем из базы все Интересы
        $user = $func['from']['id'];
        $profCheck = mysqli_query($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        if (trim($prof[0]) == $word) {
            mysqli_query($con, "UPDATE `Interests` SET interest1 = '' WHERE userID = ".$user." ");
        }else if (trim($prof[1]) == $word) {
            mysqli_query($con, "UPDATE `Interests` SET interest2 = '' WHERE userID = ".$user." ");
        }else if (trim($prof[2]) == $word) {
            mysqli_query($con, "UPDATE `Interests` SET interest3 = '' WHERE userID = ".$user." ");
        }else if (trim($prof[3]) == $word) {
            mysqli_query($con, "UPDATE `Interests` SET interest4 = '' WHERE userID = ".$user." ");
        }else if (trim($prof[4]) == $word) {
            mysqli_query($con, "UPDATE `Interests` SET interest5 = '' WHERE userID = ".$user." ");
        }else{
            $trimmedS6 = trim($prof[5]);
            $ar = explode("," , $trimmedS6);
            $arr = "";
            foreach ($ar as $key => $value) {
                if (trim($value) == $word) {
                    $arr .= "";
                }else{
                    if ($arr == "") {
                        $arr .= $value;
                    }else{
                        $arr .= ", " . trim($value);
                    }
                }
            }
            mysqli_query($con, "UPDATE `Interests` SET interest6 = '".$arr."' WHERE userID = ".$user." ");
        }

        // Отправляем пользователю сообщение с выбором нового интереса
        $interestCategoriesQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestCategories`");
        $finalButtonsArray   = GenerateButtonsPage($interestCategoriesQuery,'',1,9,' 🔻');

        array_push($finalButtonsArray,array(
            array('text' => '👈 Вернуться в "Мои интересы"', 'callback_data' => 'myInterests'),
            array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu')
        ));

        $method = 'sendMessage';
        $send_data = [
            'text' => "🖊Замена интереса" . "\nВыбери категорию:" ,
            'reply_markup' => [
                'inline_keyboard' => $finalButtonsArray
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
        return;
    }
    // Настройки интересов
    else if (strpos($data['callback_query']['data'], '2333') !== false) {
        // Удаляем сообщение по которому нажали
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        // Достаем из базы все Интересы
        $user = $func['from']['id'];
        $profCheck = mysqli_query($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        // Достаем что человек хочет удалить
        $wordData = preg_replace("/2333/i", "", $data['callback_query']['data']);
        $wordData = trim($wordData);
        $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE callbackData = '".$wordData."' ");
        $interest      = mysqli_fetch_array($intsQuery);
        $wordName = trim($interest['name']);

        $method = 'sendMessage';
        $send_data = [
            'text' => "⚙️ Настройки ".$wordName,
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => '❌ Удалить '.$wordName, 'callback_data' => $wordData.' 1135']
                    ],
                    [
                        ['text' => '🖊 Заменить '.$wordName, 'callback_data' => $wordData.' 3332']
                    ],
                    [
                        ['text' => '👈 Вернуться в "Мои навыки"', 'callback_data' => 'mySkills']  
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
        return;
    }
    // Настройки скиллов
    else if (strpos($data['callback_query']['data'], '1333') !== false) {
        // Удаляем сообщение по которому нажали
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        // Достаем из базы все скиллы
        $user = $func['from']['id'];
        $profCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID = '".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        // Достаем что человек хочет удалить
        $wordData = preg_replace("/1333/i", "", $data['callback_query']['data']);
        $wordData = trim($wordData);
        $skillQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE callbackData = '".$wordData."' ");
        $skill      = mysqli_fetch_array($skillQuery);
        $wordName = trim($skill['name']);

        $method = 'sendMessage';
        $send_data = [
            'text' => "⚙️ Настройки ".$wordName,
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => '❌ Удалить '.$wordName, 'callback_data' => $wordData.' 1133']
                    ],
                    [
                        ['text' => '🖊 Заменить '.$wordName, 'callback_data' => $wordData.' 3331']
                    ],
                    [
                        ['text' => '👈 Вернуться в "Мои навыки"', 'callback_data' => 'mySkills']  
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
        return;
    }
    // Изменение скиллов
    else if (strpos($data['callback_query']['data'], '3331') !== false) {
        // Удаляем сообщение по которому нажали
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        // Достаем что человек хочет заменить
        $wordData = preg_replace("/3331/i", "", $data['callback_query']['data']);
        $wordData = trim($wordData);
        $skillQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE callbackData ='".$wordData."'");
        $skill = mysqli_fetch_array($skillQuery);
        $word = trim($skill['name']);

        // Достаем из базы все скиллы
        $user = $func['from']['id'];
        $profCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        // Очищаем скилл из базы
        if ($prof[0] == $word) {
            mysqli_query($con, "UPDATE `Skills` SET s1 = '' WHERE userID = ".$user." ");
            mysqli_query($con, "UPDATE `Skills` SET lvl1 = '' WHERE userID = ".$user." ");
        }else if ($prof[1] == $word) {
            mysqli_query($con, "UPDATE `Skills` SET s2 = '' WHERE userID = ".$user." ");
            mysqli_query($con, "UPDATE `Skills` SET lvl2 = '' WHERE userID = ".$user." ");
        }else if ($prof[2] == $word) {
            mysqli_query($con, "UPDATE `Skills` SET s3 = '' WHERE userID = ".$user." ");
            mysqli_query($con, "UPDATE `Skills` SET lvl3 = '' WHERE userID = ".$user." ");
        }else if ($prof[3] == $word) {
            mysqli_query($con, "UPDATE `Skills` SET s4 = '' WHERE userID = ".$user." ");
            mysqli_query($con, "UPDATE `Skills` SET lvl4 = '' WHERE userID = ".$user." ");
        }else if ($prof[4] == $word) {
            mysqli_query($con, "UPDATE `Skills` SET s5 = '' WHERE userID = ".$user." ");
            mysqli_query($con, "UPDATE `Skills` SET lvl5 = '' WHERE userID = ".$user." ");
        }else{
            $trimmedS6 = trim($prof[5]);
            $ar = explode("," , $trimmedS6);
            $arr = "";
            foreach ($ar as $key => $value) {
                $prof = explode(")", $value);
                if (trim($prof[1]) == trim($word)) {
                    $arr .= "";
                }else{
                    if ($arr == "") {
                        $arr .= $value;
                    }else{
                        $arr .= ", " . $value;
                    }
                }
            }
            mysqli_query($con, "UPDATE `Skills` SET s6 = '".$arr."' WHERE userID = ".$user." ");
        }

        // Отправляем пользователю сообщение с выбором нового скила
        $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ITSkill'");

        $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
        $skills = mysqli_fetch_array($skillsCheck);

        $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,'',$skills);
        array_push($finalButtonsArray,array(
            array('text' => '👈 Вернуться в "Мои навыки"', 'callback_data' => 'mySkills'),
            array('text' => '2 страница 👉', 'callback_data' => 'choiceSkills2')
        ));
        $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери категорию:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
    }
    // Удаление скиллов
    else if (strpos($data['callback_query']['data'], '1133') !== false) {
        // Достаем что человек хочет удалить
        $wordData = preg_replace("/1133/i", "", $data['callback_query']['data']);
        $wordData = trim($wordData);
        $skillQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE callbackData ='".$wordData."'");
        $skill = mysqli_fetch_array($skillQuery);
        $word = trim($skill['name']);

        // Достаем из базы все скиллы
        $user = $func['from']['id'];
        $profCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        if ($prof[0] == $word) {
            mysqli_query($con, "UPDATE `Skills` SET s1 = '' WHERE userID = ".$user." ");
            mysqli_query($con, "UPDATE `Skills` SET lvl1 = '' WHERE userID = ".$user." ");
        }else if ($prof[1] == $word) {
            mysqli_query($con, "UPDATE `Skills` SET s2 = '' WHERE userID = ".$user." ");
            mysqli_query($con, "UPDATE `Skills` SET lvl2 = '' WHERE userID = ".$user." ");
        }else if ($prof[2] == $word) {
            mysqli_query($con, "UPDATE `Skills` SET s3 = '' WHERE userID = ".$user." ");
            mysqli_query($con, "UPDATE `Skills` SET lvl3 = '' WHERE userID = ".$user." ");
        }else if ($prof[3] == $word) {
            mysqli_query($con, "UPDATE `Skills` SET s4 = '' WHERE userID = ".$user." ");
            mysqli_query($con, "UPDATE `Skills` SET lvl4 = '' WHERE userID = ".$user." ");
        }else if ($prof[4] == $word) {
            mysqli_query($con, "UPDATE `Skills` SET s5 = '' WHERE userID = ".$user." ");
            mysqli_query($con, "UPDATE `Skills` SET lvl5 = '' WHERE userID = ".$user." ");
        }else{
            $trimmedS6 = trim($prof[5]);
            $ar = explode("," , $trimmedS6);
            $arr = "";
            foreach ($ar as $key => $value) {
                $prof = explode(")", $value);
                if (trim($prof[1]) == trim($word)) {
                    $arr .= "";
                }else{
                    if ($arr == "") {
                        $arr .= $value;
                    }else{
                        $arr .= ", " . $value;
                    }
                }
            }
            mysqli_query($con, "UPDATE `Skills` SET s6 = '".$arr."' WHERE userID = ".$user." ");
        }

        // Удаляем сообщение по которому нажали
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        // Отправляем новое сообщение, если скилов в профиле больше нет
        if (empty($prof[0]) and empty($prof[1]) and empty($prof[2]) and empty($prof[3]) and empty($prof[4]) and empty($prof[5])) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "🧑‍💻 Мои навыки" ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '➕ Добавить навыки', 'callback_data' => 'choiceSkills']  
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']  
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }
        // Отправляем новое сообщение, если в профиле еще есть другие скилы
        else{
            $profArrTo6 = array();
            $msgText3 = "";
            $btnsArray = array();
            array_push($btnsArray, array(array('text' => '➕ Добавить навыки', 'callback_data' => 'choiceSkills')));
            // Выводим скилы в правильном виде
            foreach ($prof as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $skills6 = explode("," , $value);
                    foreach ($skills6 as $key => $value) {
                        $skill6 = explode(")", $value);
                        $msgText3 .= trim($skill6[1]) . "\n";
                        array_push($profArrTo6, $skill6[1]);
                    }
                }
            }
            
            foreach ($prof as $key => $value) {
                if (!empty($value) and $key < 5) {
                $skillQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE name = '".$value."'");
                $skill = mysqli_fetch_array($skillQuery);
                array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value), 'callback_data' => $skill['callbackData']." 1333")));
                }else{
                    if (!empty($value)) {
                        foreach ($profArrTo6 as $key => $value1) {
                            $skillQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE name = '".$value1."'");
                            $skill = mysqli_fetch_array($skillQuery);
                            array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value1), 'callback_data' => $skill['callbackData']." 1333")));
                        }
                    }
                }
            }

            array_push($btnsArray, array(array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')));
            $method = 'sendMessage';
            $send_data = [
                'text' => "🧑‍💻 Мои навыки\n\n" . $msgText3,
                'reply_markup' => [
                    'inline_keyboard' => $btnsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }
    }
    // Настройки ценностей
    else if (strpos($data['callback_query']['data'], '4333') !== false) {
        // Удаляем сообщение по которому нажали
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        // Достаем из базы все ценности
        $user = $func['from']['id'];
        $profCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        // Достаем что человек хочет удалить
        $wordData = preg_replace("/4333/i", "", $data['callback_query']['data']);
        $wordData = trim($wordData);
        $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList` WHERE callbackData = '".$wordData."' ");
        $need      = mysqli_fetch_array($needsQuery);
        $wordName = trim($need['name']);

        $method = 'sendMessage';
        $send_data = [
            'text' => "⚙️ Настройки ".$wordName,
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => '❌ Удалить '.$wordName, 'callback_data' => $wordData.' 1134']
                    ],
                    [
                        ['text' => '🖊 Заменить '.$wordName, 'callback_data' => $wordData.' 3334']
                    ],
                    [
                        ['text' => '👈 Вернуться в "Мои навыки"', 'callback_data' => 'mySkills']  
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
        return;
    }
    // Изменение ценностей
    else if (strpos($data['callback_query']['data'], '3334') !== false) {
        // Удаляем сообщение по которому нажали
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        // Достаем что человек хочет заменить
        $wordData = preg_replace("/3334/i", "", $data['callback_query']['data']);
        $wordData = trim($wordData);
        $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList` WHERE callbackData ='".$wordData."'");
        $need = mysqli_fetch_array($needsQuery);
        $word = trim($need['name']);

        // Достаем из базы все ценности
        $user = $func['from']['id'];
        $profCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        if ($prof[0] == $word) {
            mysqli_query($con, "UPDATE `Needs` SET n1 = '' WHERE userID = ".$user." ");
        }else if ($prof[1] == $word) {
            mysqli_query($con, "UPDATE `Needs` SET n2 = '' WHERE userID = ".$user." ");
        }else if ($prof[2] == $word) {
            mysqli_query($con, "UPDATE `Needs` SET n3 = '' WHERE userID = ".$user." ");
        }else if ($prof[3] == $word) {
            mysqli_query($con, "UPDATE `Needs` SET n4 = '' WHERE userID = ".$user." ");
        }else if ($prof[4] == $word) {
            mysqli_query($con, "UPDATE `Needs` SET n5 = '' WHERE userID = ".$user." ");
        }else{
            $trimmedS6 = trim($prof[5]);
            $ar = explode("," , $trimmedS6);
            $arr = "";
            foreach ($ar as $key => $value) {
                if (trim($value) == $word) {
                    $arr .= "";
                }else{
                    if ($arr == "") {
                        $arr .= $value;
                    }else{
                        $arr .= ", " . trim($value);
                    }
                }
            }
            mysqli_query($con, "UPDATE `Needs` SET n6 = '".$arr."' WHERE userID = ".$user." ");
        }
        
        $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList`");

        $profCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID='".$user."' ");
        $userNeeds = mysqli_fetch_array($profCheck);

        $finalButtonsArray = GenerateButtonsPageWithExeptions($needsQuery," SexSer3ch",$userNeeds,"n");
        array_push($finalButtonsArray,array(
            array('text' => '👈 Вернуться в профиль', 'callback_data' => 'profile'),
            array('text' => '👈 Вернуться в ценности', 'callback_data' => 'myNeeds')
        ));
        // Отправляем пользователю сообщение с выбором новой ценности
        $method = 'sendMessage';
        $send_data = [
            'text' => "_Выбери нужную ценность_",
            "parse_mode" => "Markdown",
            'reply_markup' => [
                'inline_keyboard' => $finalButtonsArray
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
        return;
    }
    // Удаление ценностей
    else if (strpos($data['callback_query']['data'], '1134') !== false) {
        // Достаем что человек хочет удалить
        $wordData = preg_replace("/1134/i", "", $data['callback_query']['data']);
        $wordData = trim($wordData);
        $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList` WHERE callbackData ='".$wordData."'");
        $need = mysqli_fetch_array($needsQuery);
        $word = trim($need['name']);

        // Достаем из базы все ценности
        $user = $func['from']['id'];
        $profCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        if ($prof[0] == $word) {
            mysqli_query($con, "UPDATE `Needs` SET n1 = '' WHERE userID = ".$user." ");
        }else if ($prof[1] == $word) {
            mysqli_query($con, "UPDATE `Needs` SET n2 = '' WHERE userID = ".$user." ");
        }else if ($prof[2] == $word) {
            mysqli_query($con, "UPDATE `Needs` SET n3 = '' WHERE userID = ".$user." ");
        }else if ($prof[3] == $word) {
            mysqli_query($con, "UPDATE `Needs` SET n4 = '' WHERE userID = ".$user." ");
        }else if ($prof[4] == $word) {
            mysqli_query($con, "UPDATE `Needs` SET n5 = '' WHERE userID = ".$user." ");
        }else{
            $trimmedS6 = trim($prof[5]);
            $ar = explode("," , $trimmedS6);
            $arr = "";
            foreach ($ar as $key => $value) {
                if (trim($value) == $word) {
                    $arr .= "";
                }else{
                    if ($arr == "") {
                        $arr .= $value;
                    }else{
                        $arr .= ", " . trim($value);
                    }
                }
            }
            mysqli_query($con, "UPDATE `Needs` SET n6 = '".$arr."' WHERE userID = ".$user." ");
        }

        // Удаляем сообщение по которому нажали
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_row($profCheck);

        // Отправляем новое сообщение, если ценностей в профиле больше нет
        if (empty($prof[0]) and empty($prof[1]) and empty($prof[2]) and empty($prof[3]) and empty($prof[4]) and empty($prof[5])) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "📝 *Мои ценности*",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить ценности', 'callback_data' => 'pushNeeds']  
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']  
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }
        // Отправляем новое сообщение, если в профиле еще есть другие ценности
        else{
            $needs6 = array();
            $msgText3 = "";
            $btnsArray = array();
            array_push($btnsArray, array(array('text' => '➕ Добавить ценности', 'callback_data' => 'pushNeeds')));
            // Выводим ценности в правильном виде
            foreach ($prof as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $arr = explode("," , $value);
                    foreach ($arr as $key => $value1) {
                        $msgText3 .= trim($value1) . "\n";
                        array_push($needs6, trim($value1));
                    }
                }
            }

            foreach ($prof as $key => $value) {
                if (!empty($value) and $key < 5) {
                    $needQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList` WHERE name = '".$value."'");
                    $need = mysqli_fetch_array($needQuery);

                    array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value), 'callback_data' => $need['callbackData']." 4333")));
                }else{
                    if (!empty($value)) {
                        foreach ($needs6 as $key => $value1) {
                            $needQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList` WHERE name = '".$value1."'");
                            $need = mysqli_fetch_array($needQuery);

                            array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value1), 'callback_data' => $need['callbackData']." 4333")));
                        }
                    }
                }
            }

            array_push($btnsArray, array(array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')));
            $method = 'sendMessage';
            $send_data = [
                'text' => "📝 *Мои ценности*\n\n" . $msgText3,
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $btnsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }
    }
    else if ($data['callback_query']['data'] == 'profile') {
        // Удаляем сообщение по которому нажали, чтоб отправить профиль
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = '' WHERE userID = ".$user." ");

            // Получаем из БД все о пользователе
        $user = $func['from']['id'];
        $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto` FROM `MainInfo` WHERE userID='".$user."' ");
        $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
        $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5`, `s6` FROM `Skills` WHERE userID='".$user."' ");
        $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5`, `n6` FROM `Needs` WHERE userID='".$user."' ");
        $socialCheck = mysqli_query ($con, "SELECT `inst`, `tiktok`, `viber`, `whatsapp`, `facebook`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
            
        $prof = mysqli_fetch_array($profCheck);
        $skill = mysqli_fetch_row($skillsCheck);
        $need = mysqli_fetch_row($needsCheck);
        $int = mysqli_fetch_row($intsCheck);
        $socials = mysqli_fetch_array($socialCheck);

        $msgText1 = "";
        $msgText2 = "";
        $msgText3 = "";

        if (!empty($skill)) {
            $msgText1 = "\n🧑‍💻 <i>Мои навыки:</i> \n";
                // Выводим скиллы в правильном виде
            foreach ($skill as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText1 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText1 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText1 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText1 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText1 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText1 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        if (!empty($need)) {
            $msgText2 = "\n📝 <i>Мои ценности:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($need as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText2 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }    
        
        if (!empty($int)) {
            $msgText3 = "\n🚲 <i>Мои интересы:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($int as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        $soc = "";

        if (!empty($prof['userAge'])) {
            $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
        }

        if (!empty($socials['inst'])) {
            $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b>";
            if ($soc == "") {
                $soc = $inst;
            }else{
                $soc .= ", ".$inst;
            }
        }else{
            $inst = "";
        }

        if (!empty($socials['tiktok'])) {
            $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b>";
            if ($soc == "") {
                $soc = $tiktok;
            }else{
                $soc .= ", ".$tiktok;
            }
        }else{
            $tiktok = "";
        }

        if (!empty($socials['facebook'])) {
            $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b>";
            if ($soc == "") {
                $soc = $facebook;
            }else{
                $soc .= ", ".$facebook;
            }
        }else{
            $facebook = "";
        }

        if (!empty($socials['viber'])) {
            $viber = "<b>Viber - ".$socials['viber']."</b>";
            if ($soc == "") {
                $soc = $viber;
            }else{
                $soc .= ", ".$viber;
            }
        }else{
            $viber = "";
        }

        if (!empty($socials['whatsapp'])) {
            $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b>";
            if ($soc == "") {
                $soc = $whatsapp;
            }else{
                $soc .= ", ".$whatsapp;
            }
        }else{
            $whatsapp = "";
        }

        if (!empty($socials['anotherSocials'])) {
            $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b>";
            if ($soc == "") {
                $soc = $anotherSocials;
            }else{
                $soc .= ", ".$anotherSocials;
            }
        }else{
            $anotherSocials = "";
        }    

            if (empty($prof['userPhoto'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "😁 <b>Мой профиль\n\n".$prof['name']." ".$prof['surname']."</b>".$age.$msgText1.$msgText2.$msgText3."\n".$soc,
                    "parse_mode" => "html",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '🤴 Личные данные', 'callback_data' => 'myNameAge']  
                            ],
                            [
                                ['text' => '🧑‍💻 Мои навыки', 'callback_data' => 'mySkills']
                            ],
                            [
                                ['text' => '🚲 Мои интересы', 'callback_data' => 'myInterests']
                            ],
                            [
                                ['text' => '📝 Мои ценности', 'callback_data' => 'myNeeds']
                            ],
                            [
                                ['text' => 'Мои соцсети', 'callback_data' => 'mySocial']
                            ],
                            [
                                ['text' => '🗣 Реферальная ссылка', 'callback_data' => 'myAffiliate']
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                $response = [
                    'chat_id' => $user,
                    'caption' => "😁 <b>Мой профиль\n\n".$prof['name']." ".$prof['surname']."</b>".$age.$msgText1.$msgText2.$msgText3."\n".$soc,
                    "parse_mode" => "html",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => '🤴 Личные данные', 'callback_data' => 'myNameAge']  
                            ],
                            [
                                ['text' => '🧑‍💻 Мои навыки', 'callback_data' => 'mySkills']
                            ],
                            [
                                ['text' => '🚲 Мои интересы', 'callback_data' => 'myInterests']
                            ],
                            [
                                ['text' => '📝 Мои ценности', 'callback_data' => 'myNeeds']
                            ],
                            [
                                ['text' => 'Мои соцсети', 'callback_data' => 'mySocial']
                            ],
                            [
                                ['text' => '🗣 Реферальная ссылка', 'callback_data' => 'myAffiliate']
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];
                    
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }
    }
    // Скрипт поиска по навыкам
    elseif (strpos($data['callback_query']['data'], 'поиск') !== false) {

        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Поиск в БД
        $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5` FROM `Skills` WHERE userID = ".$user." ");
        $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5` FROM `Needs` WHERE userID = ".$user." ");
        $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5` FROM `Interests` WHERE userID = ".$user." ");
        $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `sex`, `userAge` FROM `MainInfo` WHERE userID = ".$user." ");

        $skills = mysqli_fetch_array($skillsCheck);
        $needs = mysqli_fetch_array($needsCheck);
        $ints = mysqli_fetch_array($intsCheck);
        $prof = mysqli_fetch_array($profCheck);

        $needToComplete = "";

        // Подготавливаем перечень для заполнения пустых ячеек в профиле
        if (empty($ints)) {
            if ($needToComplete == "") {
                $needToComplete .= "интересы";
            }else{
                $needToComplete .= ", интересы";
            }    
        }
        if (empty($needs)) {
            if ($needToComplete == "") {
                $needToComplete .= "ценности";
            }else{
                $needToComplete .= ", ценности";
            }  
        }
        if (empty($prof['name'])) {
            if ($needToComplete == "") {
                $needToComplete .= "имя";
            }else{
                $needToComplete .= ", имя";
            } 
        }
        if (empty($prof['surname'])) {
            if ($needToComplete == "") {
                $needToComplete .= "фамилия";
            }else{
                $needToComplete .= ", фамилия";
            } 
        }
        if (empty($skills)) {
            if ($needToComplete == "") {
                $needToComplete .= "навыки";
            }else{
                $needToComplete .= ", навыки";
            } 
        }
        if (empty($prof['sex'])) {
            if ($needToComplete == "") {
                $needToComplete .= "пол";
            }else{
                $needToComplete .= ", пол";
            } 
        }
        if (empty($prof['userAge'])) {
            if ($needToComplete == "") {
                $needToComplete .= "возраст";
            }else{
                $needToComplete .= ", возраст";
            } 
        }

        // Узнаем что человек искал
        $word = $data['callback_query']['data'];
        $skillData = preg_replace("/поиск/i", "", $word);
        $skillQuery = mysqli_query($con,"SELECT `name` FROM `SkillsList` WHERE callbackData= '".trim($skillData)."' ");
        $row = mysqli_fetch_array($skillQuery);
        $search = $row['name'];

        // Если в профиле хоть что-то не заполнено, тогда даем человеку возможность видеть только новых людей и выводим сообщение с кнопкой ведущей в профиль
        if (empty($prof['userAge']) or empty($prof['sex']) or empty($skills) or empty($needs) or empty($ints) or empty($prof['name'])) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "Мы запомнили ваш поиск и когда будут появляться люди с таким навыком, мы вас оповестим\n\nЕсли вы хотите искать людей самостоятельно, тогда вам нужно заполнить еще: " . $needToComplete,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Заполнить данные', 'callback_data' => 'profile']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Поиск людей"', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{
            // Поиск в БД по запросу
            $skillCheck = mysqli_query ($con, "SELECT `userID` FROM `Skills` WHERE (s1 LIKE '%".$search."%') OR (s2 LIKE '%".$search."%') OR (s3 LIKE '%".$search."%') OR (s4 LIKE '%".$search."%') OR (s5 LIKE '%".$search."%') ");
            $skill = mysqli_fetch_row($skillCheck);

            $userNames = "";
            $counter = 0;

            foreach ($skillCheck as $key => $value) {
                mysqli_fetch_array($value);
                foreach ($value as $key => $value) {
                    $userTable = mysqli_query ($con, "SELECT isPrivate FROM MainInfo WHERE userID='".$value."' ");
                    $userData = mysqli_fetch_array($userTable);
                    if ($value != $user && $userData['isPrivate'] == 0) {
                        if ($userNames == "") {
                            $userNames = $value;
                            $counter += 1;
                        }else{
                            $userNames .= "," . $value;
                            $counter += 1;
                        }
                    }                    
                }
            }

            // Удаляем выбор в поиске
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Делаем проверку. Если не нашлось ничего, то выводим сообщение, что никого не нашли, но когда будут появляться люди - мы напишем
            if (empty($userNames)) {
                $response = [
                    'chat_id' => $user,
                    'caption' => "_Мы не нашли людей по запросу_ *".$search."* _,но когда они появятся - вы получите уведомление_",
                    "parse_mode" => "Markdown",
                    
                    'photo' => curl_file_create("../tgBot/BotPic/post_219.png"),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];                 
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }else{
                // Пушим список айдишек в БД
                mysqli_query ($con, "UPDATE `TrackingMenu` SET searchIDs = '".$userNames."' WHERE userID = ".$user." ");

                $ids = explode(',', $userNames);

                // Выводим данные первого человека
                $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userPhoto`, `tgUserName`, `userAge` FROM `MainInfo` WHERE userID='".$ids[0]."' ");
                $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$ids[0]."' ");
                $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5`, `s6` FROM `Skills` WHERE userID='".$ids[0]."' ");
                $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5`, `n6` FROM `Needs` WHERE userID='".$ids[0]."' ");
                $socialCheck = mysqli_query ($con, "SELECT `inst`, `tiktok`, `viber`, `whatsapp`, `facebook`, `anotherSocials` FROM `Socials` WHERE userID='".$ids[0]."' ");

                $prof = mysqli_fetch_array($profCheck);
                $skill = mysqli_fetch_row($skillsCheck);
                $need = mysqli_fetch_row($needsCheck);
                $int = mysqli_fetch_row($intsCheck);
                $socials = mysqli_fetch_array($socialCheck);

                $msgText1 = "";
                $msgText2 = "";
                $msgText3 = "";

                if (!empty($skill[0]) or !empty($skill[1]) or !empty($skill[2]) or !empty($skill[3]) or !empty($skill[4]) or !empty($skill[5])) {
                    $msgText1 = "\n🧑‍💻 <i>Навыки:</i> \n";
                        // Выводим скиллы в правильном виде
                    foreach ($skill as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText1 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText1 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText1 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText1 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText1 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText1 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }

                if (!empty($need[0]) or !empty($need[1]) or !empty($need[2]) or !empty($need[3]) or !empty($need[4]) or !empty($need[5])) {
                    $msgText2 = "\n📝 <i>Ценности:</i> \n";
                        // Выводим ценности в правильном виде
                    foreach ($need as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText2 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }    
                
                if (!empty($int[0]) or !empty($int[1]) or !empty($int[2]) or !empty($int[3]) or !empty($int[4]) or !empty($int[5])) {
                    $msgText3 = "\n🚲 <i>Интересы:</i> \n";
                        // Выводим ценности в правильном виде
                    foreach ($int as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText3 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }

                $soc = "";

                if (!empty($prof['userAge'])) {
                    $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
                }

                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $inst;
                    }else{
                        $soc .= ", ".$inst;
                    }
                }else{
                    $inst = "";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $tiktok;
                    }else{
                        $soc .= ", ".$tiktok;
                    }
                }else{
                    $tiktok = "";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $facebook;
                    }else{
                        $soc .= ", ".$facebook;
                    }
                }else{
                    $facebook = "";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b>Viber - ".$socials['viber']."</b>";
                    if ($soc == "") {
                        $soc = "\n" . $viber;
                    }else{
                        $soc .= ", ".$viber;
                    }
                }else{
                    $viber = "";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $whatsapp;
                    }else{
                        $soc .= ", ".$whatsapp;
                    }
                }else{
                    $whatsapp = "";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $anotherSocials;
                    }else{
                        $soc .= ", ".$anotherSocials;
                    }
                }else{
                    $anotherSocials = "";
                }

                // Если кол-во найденных профилей = 1
                if ($counter == 1) {
                    if ($prof['userPhoto'] == "") {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }else{
                        $response = [
                            'chat_id' => $user,
                            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ])
                        ];
                                
                        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                        curl_setopt($ch, CURLOPT_POST, 1);  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_exec($ch);
                        curl_close($ch);
                        return;
                    }
                }else{
                    if (empty($prof['userPhoto'])) {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }else{
                        $response = [
                            'chat_id' => $user,
                            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ])
                        ];
                                
                        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                        curl_setopt($ch, CURLOPT_POST, 1);  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_exec($ch);
                        curl_close($ch);
                        return;
                    }
                }
            }
        }
    }
    else if (strpos($data['callback_query']['data'], 'SexSer3ch') !== false) {
        // Поиск в БД такой ценности
        $user = $func['from']['id'];
        $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID = '".$user."' ");
        $needs = mysqli_fetch_row($needsCheck);

        // Удаляем SexSer3ch из ценностей
        $chWord = $data['callback_query']['data'];
        $needData = preg_replace("/SexSer3ch/i", "", $chWord);

        $needNameQuery = mysqli_query($con,"SELECT `name` FROM `NeedsList` WHERE callbackData ='".$needData."' ");
        $need = mysqli_fetch_array($needNameQuery);
        $word = $need['name'];

        $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList`");

        // Если это будет первая ценность
        if (empty($needs)) {
            // Пушим новую ценность в БД
            $updateDB = mysqli_query ($con, "UPDATE `Needs` SET n1 = '".$word."' WHERE userID = ".$user." ");
            $needsList = mysqli_query($con,"SELECT * FROM `NeedsList`");

            $finalButtonsArray = GenerateButtonsPage($needsList,' SexSer3ch',1,99);
            array_push($finalButtonsArray,
            array(array('text' => '👈 Вернуться в профиль', 'callback_data' => 'profile')));

            $method = 'editMessageText';
            $send_data = [
                'text' => "📝 *Мои ценности\n\nСейчас у вас указано:\n* \u{0031}\u{FE0F}\u{20E3}".$word."\n\n_Просмотрите все ценности и найдите самую важную для вас!\nВыберите 5 ценностей начиная с самой важной:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{
            if ($needs[0] == "") {
                mysqli_query ($con, "UPDATE `Needs` SET n1 = '".$word."' WHERE userID = ".$user." ");
            }
            else if ($needs[1] == "") {
                mysqli_query ($con, "UPDATE `Needs` SET n2 = '".$word."' WHERE userID = ".$user." ");
            }
            else if ($needs[2] == "") {
                mysqli_query ($con, "UPDATE `Needs` SET n3 = '".$word."' WHERE userID = ".$user." ");
            }
            else if ($needs[3] == "") {
                mysqli_query ($con, "UPDATE `Needs` SET n4 = '".$word."' WHERE userID = ".$user." ");
            }
            else if ($needs[4] == "") {
                mysqli_query ($con, "UPDATE `Needs` SET n5 = '".$word."' WHERE userID = ".$user." ");
                if ($rewards['NeedsReward'] == 0) {
                    // Пушим, что дали награду
                    mysqli_query ($con, "UPDATE `userRewards` SET NeedsReward = 1 WHERE userID = ".$user." ");

                    // Получаем кол-во монет пользователя
                    $selectCoins = mysqli_query ($con, "SELECT `coins` FROM `MainInfo` WHERE userID='".$user."' ");
                    $coins = mysqli_fetch_array($selectCoins);

                    // Плюсуем к монетам награду
                    $coins = $coins['coins'] + 100;

                    // Выдаем монеты
                    mysqli_query ($con, "UPDATE `MainInfo` SET coins = '".$coins."' WHERE userID = ".$user." ");

                    $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID='".$user."' ");
                    $needs = mysqli_fetch_row($needsCheck);
                    // Выводим ценности в правильном виде
                    $msgText2 = "";
                    foreach ($needs as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText2 .= "\n*Дополнительные ценности: *" . trim($value) . "\n";
                        }
                    }
                    
                    $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList`");
                    
                    $userNeedsQuery = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID = '".$user."' ");
                    $except = mysqli_fetch_array($userNeedsQuery);

                    $finalButtonsArray = GenerateButtonsPageWithExeptions($needsQuery," SexSer3ch",$except,"n");

                    if(empty($finalButtonsArray)){
                        $buttonText = $emptySkillCategoryButtonText;
                    }else{
                        $buttonText = "Сейчас у вас указано:_ \n*".$msgText2."*\n_Просмотри все ценности и найди самую важную для тебя!\nВыбери ценности начиная с самой важной:_";
                    }

                    array_push($finalButtonsArray,
                    array(array('text' => '👈 Вернуться в профиль', 'callback_data' => 'profile'))
                    );
                    
                    $method = 'editMessageText';
                    $send_data = [
                        'text' => "📝 *Мои ценности*\n\n_Вы добавили_ *".$word."* _и получили 100 монет за добавление 5 ценностей.\n\n!Узнать кол-во монет и как их получить, вы можете нажав на кнопку 'Монеты' в главном меню!\n\n".$buttonText,
                        "parse_mode" => 'markdown',
                        'reply_markup' => [
                            'inline_keyboard' => $finalButtonsArray
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                }
            }else{
                if ($needs[5] == '') {
                    mysqli_query ($con, "UPDATE `Needs` SET n6 = '".$word."' WHERE userID = ".$user." ");

                }else{
                    $needs[5] .= ", " . $word;
                    mysqli_query ($con, "UPDATE `Needs` SET n6 = '".$needs[5]."' WHERE userID = ".$user." "); 

                }
            }
            
            $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID = '".$user."' ");
            $needs = mysqli_fetch_row($needsCheck);
            // Выводим ценности в правильном виде
            
            $msgText2 = "";
            foreach ($needs as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText2 .= "\n*Дополнительные ценности: *" . trim($value) . "\n";
                }
            }            

            $needsQueryy = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID = '".$user."' ");
            $except = mysqli_fetch_array($needsQueryy);
    
            $finalButtonsArray = GenerateButtonsPageWithExeptions($needsQuery," SexSer3ch",$except,"n");
            
            if(empty($finalButtonsArray)){
                $buttonText = "Вы добавили все ценности!";
            }else{
                $buttonText = "📝 *Мои ценности*\n\n_Вы добавили_ *".$word."*\n\n_Сейчас у вас указано:_ \n*".$msgText2."*\n\n_Просмотри все ценности и найди самую важную для тебя!\nВыбери ценности начиная с самой важной:_";
            }

            array_push($finalButtonsArray,
            array(array('text' => '👈 Вернуться в профиль', 'callback_data' => 'profile'))
            );

            // Выводим новое сообщение
            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            
            return;
               
        }  
    }

    else if (strpos($data['callback_query']['data'], 'messageTo') !== false) {
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Удаляем messageTo из сообщения, чтоб узнать id получателя
        $word = $data['callback_query']['data'];
        $word = preg_replace("/messageTo/i", "", $word);
        $word = trim($word);

        // Узнаем имя получателя
        $checkName = mysqli_query($con,"SELECT `name` FROM MainInfo WHERE userID='".$word."' ");
        $name  = mysqli_fetch_array($checkName);

        // Ставим в trackingmenu отметку, что чел отправляет сообщение
        mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'ОтправляетСообщение' WHERE userID = ".$user." ");

        // Выводим сообщение
        $method = 'sendMessage';
        $send_data = [
            'text' => 'Отправьте мне сообщение и нажмите кнопку "Отправить", чтоб отправить сообщение для '.$name['name'],
            "parse_mode" => "Markdown",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Отправить', 'callback_data' => 'sendMesTo'.$word]
                    ],
                    [
                        ['text' => 'Вернуться в главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
        return;
    }
    else if (strpos($data['callback_query']['data'], 'sendMesTo') !== false) {
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Удаляем sendMesTo из сообщения, чтоб узнать id получателя
        $word = $data['callback_query']['data'];
        $word = preg_replace("/sendMesTo/i", "", $word);
        $word = trim($word);

        // Узнаем имя получателя
        $checkName = mysqli_query($con,"SELECT `name` FROM MainInfo WHERE userID='".$word."' ");
        $name  = mysqli_fetch_array($checkName);

        // Достаем сообщение из trackingmenu
        $checkMes = mysqli_query($con,"SELECT `mesToSend` FROM TrackingMenu WHERE userID='".$user."' ");
        $mes = mysqli_fetch_array($checkMes);

        $date = date("m.d.y");
        $time = date("H:i:s");

        // Заполняем строку информации о сообщении в БД
        mysqli_query ($con, "INSERT INTO `Messages`(`sender`, `recipient`, `message`, `status`, `data`, `time`) VALUES ('".$user."', '".$word."', '".$mes['mesToSend']."', '1', '".$date."', '".$time."') ");

        // Получаем id отправленного сообщения
        $checkMesID = mysqli_query($con,"SELECT `id` FROM Messages WHERE (sender='".$user."') AND (data='".$date."') AND (time='".$time."') ");
        $mesID = mysqli_fetch_array($checkMesID);

        // Выводим сообщение для отправителя
        $method = 'sendMessage';
        $send_data = [
            'text' => 'Ваше сообщение отправлено. Ждите ответа от '.$name['name'],
            "parse_mode" => "Markdown",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Вернуться в главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);

        // Отправляем сообщение для получателя
        $method = 'sendMessage';
        $send_data = [
            'text' => 'Вы получили новое сообщение',
            "parse_mode" => "Markdown",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Просмотреть', 'callback_data' => 'openMes'.$mesID['id']]
                    ],
                    [
                        ['text' => 'Отклонить', 'callback_data' => 'closeMes'.$mesID['id']]
                    ],
                ]
            ]
        ];
        $send_data['chat_id'] = $word;
        sendTelegram($method, $send_data);
        return;
    }
    else if (strpos($data['callback_query']['data'], 'closeMes') !== false) {
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Удаляем closeMes из сообщения, чтоб узнать id получателя
        $word = $data['callback_query']['data'];
        $word = preg_replace("/closeMes/i", "", $word);
        $word = trim($word);

        // Ставим статус сообщения в БД = 3
        mysqli_query ($con, "UPDATE `Messages` SET status = '3' WHERE recipient = ".$user." ");

        return;
    }
    else if (strpos($data['callback_query']['data'], 'openMes') !== false) {
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Удаляем openMes из сообщения, чтоб узнать id получателя
        $word = $data['callback_query']['data'];
        $word = preg_replace("/openMes/i", "", $word);
        $word = trim($word);

        // Получаем все данные по id отправленного сообщения
        $checkMesID = mysqli_query($con,"SELECT * FROM Messages WHERE id='".$word."' ");
        $mes = mysqli_fetch_array($checkMesID);

        // Выводим сообщение для отправителя
        $method = 'sendMessage';
        $send_data = [
            'text' => "*" . $mes['message'] . "*",
            "parse_mode" => "Markdown",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Ответить', 'callback_data' => 'messageTo'.$mes['sender']]
                    ],
                    [
                        ['text' => 'Профиль отправителя', 'callback_data' => 'Proffile'.$mes['sender']]
                    ],
                    [
                        ['text' => 'Удалить сообщение', 'callback_data' => 'closeMes'.$mes['id']]
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
        return;
    }
    else if (strpos($data['callback_query']['data'], 'Proffile') !== false) {
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Удаляем Proffile из сообщения, чтоб узнать id получателя
        $word = $data['callback_query']['data'];
        $word = preg_replace("/Proffile/i", "", $word);
        $word = trim($word);

        // Получаем из БД все о пользователе
        $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto` FROM `MainInfo` WHERE userID='".$word."' ");
        $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$word."' ");
        $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5`, `s6` FROM `Skills` WHERE userID='".$word."' ");
        $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5`, `n6` FROM `Needs` WHERE userID='".$word."' ");
        $socialCheck = mysqli_query ($con, "SELECT `inst`, `tiktok`, `viber`, `whatsapp`, `facebook`, `anotherSocials` FROM `Socials` WHERE userID='".$word."' ");
            
        $prof = mysqli_fetch_array($profCheck);
        $skill = mysqli_fetch_row($skillsCheck);
        $need = mysqli_fetch_row($needsCheck);
        $int = mysqli_fetch_row($intsCheck);
        $socials = mysqli_fetch_array($socialCheck);

        $msgText1 = "";
        $msgText2 = "";
        $msgText3 = "";

        if (!empty($skill)) {
            $msgText1 = "\n🧑‍💻 <i>Навыки:</i> \n";
                // Выводим скиллы в правильном виде
            foreach ($skill as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText1 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText1 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText1 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText1 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText1 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText1 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        if (!empty($need)) {
            $msgText2 = "\n📝 <i>Ценности:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($need as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText2 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }    
        
        if (!empty($int)) {
            $msgText3 = "\n🚲 <i>Интересы:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($int as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        $soc = "";

        if (!empty($prof['userAge'])) {
            $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
        }

        if (!empty($socials['inst'])) {
            $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b>";
            if ($soc == "") {
                $soc = $inst;
            }else{
                $soc .= ", ".$inst;
            }
        }else{
            $inst = "";
        }

        if (!empty($socials['tiktok'])) {
            $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b>";
            if ($soc == "") {
                $soc = $tiktok;
            }else{
                $soc .= ", ".$tiktok;
            }
        }else{
            $tiktok = "";
        }

        if (!empty($socials['facebook'])) {
            $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b>";
            if ($soc == "") {
                $soc = $facebook;
            }else{
                $soc .= ", ".$facebook;
            }
        }else{
            $facebook = "";
        }

        if (!empty($socials['viber'])) {
            $viber = "<b>Viber - ".$socials['viber']."</b>";
            if ($soc == "") {
                $soc = $viber;
            }else{
                $soc .= ", ".$viber;
            }
        }else{
            $viber = "";
        }

        if (!empty($socials['whatsapp'])) {
            $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b>";
            if ($soc == "") {
                $soc = $whatsapp;
            }else{
                $soc .= ", ".$whatsapp;
            }
        }else{
            $whatsapp = "";
        }

        if (!empty($socials['anotherSocials'])) {
            $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b>";
            if ($soc == "") {
                $soc = $anotherSocials;
            }else{
                $soc .= ", ".$anotherSocials;
            }
        }else{
            $anotherSocials = "";
        }    

            if (empty($prof['userPhoto'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "<b>".$prof['name']." ".$prof['surname']."</b>".$age.$msgText1.$msgText2.$msgText3."\n".$soc,
                    "parse_mode" => "html",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Ответить', 'callback_data' => 'messageTo'.$word]
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                $response = [
                    'chat_id' => $user,
                    'caption' => "<b>".$prof['name']." ".$prof['surname']."</b>".$age.$msgText1.$msgText2.$msgText3."\n".$soc,
                    "parse_mode" => "html",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Ответить', 'callback_data' => 'messageTo'.$word]
                            ]
                        ]
                    ])
                ];
                    
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }
    }

    else if (strpos($data['callback_query']['data'], 'add') !== false) {

        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Удаляем слово add из профессии
        $profData = $data['callback_query']['data'];
        $profData = preg_replace("/add/i", "", $profData);

        $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM SkillsList WHERE `callbackData`='".$profData."' ");
        $skillToAdd  = mysqli_fetch_array($skillsQuery);
        // Удаляем лишние пробелы
        $profName = $skillToAdd['name'];

        $response = [
            'chat_id' => $user,
            'caption' => "_Виберите уровень владения_ *" .$profName. "*",
            "parse_mode" => "Markdown",
            
            'photo' => curl_file_create("../tgBot/BotPic/post_209.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Trainee(Учусь)', 'callback_data' => 'Trainee,' . $profData]  
                    ],
                    [
                        ['text' => 'Junior(Начинающий)', 'callback_data' => 'Junior,' . $profData]  
                    ],
                    [
                        ['text' => 'Middle(Средний уровень)', 'callback_data' => 'Middle,' . $profData]  
                    ],
                    [
                        ['text' => 'Senior(Профессионал)', 'callback_data' => 'Senior,' . $profData]  
                    ],
                    [
                        ['text' => '👈 Вернуться к выбору навыка', 'callback_data' => 'mySkills']  
                    ]
                ]
            ])
        ];                 
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    // Поиск с кем вместе обучаться
    else if ($data['callback_query']['data'] == 'learnFinder'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
        
        $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM SkillCategories ");
        $finalButtonsArray = GenerateButtonsPage($categoriesArray,' s1erch',1,9);
        array_push($finalButtonsArray,
        array(
            array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder'),
            array('text' => '2 страница 👉', 'callback_data' => 'learnFinder2')
        )
        );

        $response = [
            'chat_id' => $user,
            
            'photo' => curl_file_create("../tgBot/BotPic/post_216.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];                 
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'learnFinder2'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM SkillCategories ");
        $finalButtonsArray = GenerateButtonsPage($categoriesArray,' s1erch',2,9);
        array_push($finalButtonsArray,
        array(
            array('text' => '👈 1 страница', 'callback_data' => 'learnFinder'),
            array('text' => '3 страница 👉', 'callback_data' => 'learnFinder3')
        )
        );

        $response = [
            'chat_id' => $user,
            
            'photo' => curl_file_create("../tgBot/BotPic/post_216.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];                 
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'learnFinder3'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM SkillCategories ");
        $finalButtonsArray = GenerateButtonsPage($categoriesArray,' s1erch',3,9);
        array_push($finalButtonsArray,
        array(
            array('text' => '👈 2 страница', 'callback_data' => 'learnFinder2')
        )
        );

        $response = [
            'chat_id' => $user,
            
            'photo' => curl_file_create("../tgBot/BotPic/post_216.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=> $finalButtonsArray
            ])
        ];                 
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    // Поиск с кем провести время
    else if ($data['callback_query']['data'] == 'enterestsFinder'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
        
        $interestCategoriesQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestCategories`");
        $finalButtonsArray   = GenerateButtonsPage($interestCategoriesQuery,' серч',1,9,' 🔻');

        array_push($finalButtonsArray,array(
            array('text' => '👈 Вернуться в "Мои интересы"', 'callback_data' => 'peopleFinder'),
            array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu')
        ));

        $response = [
            'chat_id' => $user,
            
            'photo' => curl_file_create("../tgBot/BotPic/post_217.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>$finalButtonsArray
            ])
        ];               
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'needsFinder'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $sexCheck = mysqli_query ($con, "SELECT `sex` FROM `MainInfo` WHERE userID='".$user."' ");
        $sex = mysqli_fetch_row($sexCheck);

        if (empty($sex)) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "🔎❤️ *Вторую половинку*\n\n_Укажите свой пол_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => "\u{2640} Женский", 'callback_data' => 'Женский SexSe1rch']
                        ],
                        [
                            ['text' => "\u{2642} Мужской", 'callback_data' => 'Мужской SexSe1rch']
                        ],
                        [
                            ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{
            $response = [
                'chat_id' => $user,
                
                'photo' => curl_file_create("../tgBot/BotPic/post_198.png"),
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>[
                        [
                            ['text' => "\u{2640} Женский", 'callback_data' => 'Женский SexSe2rch']
                        ],
                        [
                            ['text' => "\u{2642} Мужской", 'callback_data' => 'Мужской SexSe2rch']
                        ],
                        [
                            ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ])
            ];                 
            $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_exec($ch);
            curl_close($ch);
            return;
        }
    }
    else if ($data['callback_query']['data'] == 'myNameAge'){
        $user = $func['from']['id']; 
        $nameCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `sex` FROM `MainInfo` WHERE userID='".$user."' ");
        $name = mysqli_fetch_array($nameCheck);

        // Удаляем сообщение с профилем
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'caption' => "🤴 *Личные данные*\n\n_Имя:_ *" . $name['name']."*\n_Фамилия:_ *".$name['surname'] . "*\n_Возраст:_ *" . $name['userAge'] . "*\n_Пол:_ *" . $name['sex'] . "*",
            'parse_mode' => 'markdown',
            'photo' => curl_file_create("../tgBot/BotPic/post_313.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Изменить имя', 'callback_data' => 'changeName']
                    ],
                    [
                        ['text' => 'Изменить фамилию', 'callback_data' => 'changeSurname']
                    ],
                    [
                        ['text' => 'Изменить возраст', 'callback_data' => 'changeAge']
                    ],
                    [
                        ['text' => 'Указать пол', 'callback_data' => 'changeSex']
                    ],
                    [
                        ['text' => 'Добавить фото', 'callback_data' => 'plusPhoto']
                    ],
                    [
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]
            ])
        ];                 
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'Мужской SexSe2rch'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Проверяем сколько у человека выбрано ценностей и если меньше 5, даем добавить себе ценности
        $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5` FROM `Needs` WHERE userID='".$user."' ");
        $needs = mysqli_fetch_row($needsCheck);

        $count = 5;
        $n = 0;
        foreach ($needs as $key => $value) {
            if (!empty($value)) {
                $count -= 1;
                $n = $n + 1;
            }
        }
        
        if ($n < 5) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "_Для того чтобы искать вторую половинку, вам нужно добавить еще_ " ."*". $count ."*". " _ценностей_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить недостающие ценности', 'callback_data' => 'myNeeds']
                        ],
                        [
                            ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{
            $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList`");
            $finalButtonsArray = GenerateButtonsPage($needsQuery,' SexSe3rch',1,99);
            array_push($finalButtonsArray,
                array(array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder'))
            );

            $response = [
                'chat_id' => $user,
                
                'photo' => curl_file_create("../tgBot/BotPic/post_212.png"),
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>$finalButtonsArray
                ])
            ];                 
            $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_exec($ch);
            curl_close($ch);
            return;
        }
    }
    else if ($data['callback_query']['data'] == 'Женский SexSe2rch'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Проверяем сколько у человека выбрано ценностей и если меньше 5, даем добавить себе ценности
        $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5` FROM `Needs` WHERE userID='".$user."' ");
        $needs = mysqli_fetch_row($needsCheck);

        $count = 5;
        $n = 0;
        foreach ($needs as $key => $value) {
            if (!empty($value)) {
                $count -= 1;
                $n = $n + 1;
            }
        }
        
        if ($n < 5) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "_Для того чтобы искать вторую половинку, вам нужно добавить еще_ " ."*". $count ."*". " _ценностей_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить недостающие ценности', 'callback_data' => 'myNeeds']
                        ],
                        [
                            ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{
            $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList`");

            $finalButtonsArray = GenerateButtonsPage($needsQuery,' SexSe3rch',1,99);
            array_push($finalButtonsArray,
                array(array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder'))
            );
            $response = [
                'chat_id' => $user,
                
                'photo' => curl_file_create("../tgBot/BotPic/post_212.png"),
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>$finalButtonsArray
                ])
            ];                 
            $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_exec($ch);
            curl_close($ch);
            return;
        }
    }
    else if ($data['callback_query']['data'] == 'Мужской SexSe1rch'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $updateDB = mysqli_query ($con, "UPDATE `MainInfo` SET sex = 'Мужской' WHERE userID = ".$user." ");

        // Проверяем сколько у человека выбрано ценностей и если меньше 5, даем добавить себе ценности
        $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5` FROM `Needs` WHERE userID='".$user."' ");
        $needs = mysqli_fetch_row($needsCheck);

        $count = 5;
        $n = 0;
        foreach ($needs as $key => $value) {
            if (!empty($value)) {
                $count -= 1;
                $n = $n + 1;
            }
        }
        
        if ($n < 5) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "_Для того чтобы искать вторую половинку, вам нужно добавить еще_ " ."*". $count ."*". " _ценностей_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить недостающие ценности', 'callback_data' => 'myNeeds']
                        ],
                        [
                            ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{
            $response = [
                'chat_id' => $user,
                
                'photo' => curl_file_create("../tgBot/BotPic/post_198.png"),
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>[
                        [
                            ['text' => "\u{2640} Женский", 'callback_data' => 'Женский SexSe2rch']
                        ],
                        [
                            ['text' => "\u{2642} Мужской", 'callback_data' => 'Мужской SexSe2rch']
                        ],
                        [
                            ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ])
            ];                 
            $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_exec($ch);
            curl_close($ch);
            return;
        }
    }
    else if ($data['callback_query']['data'] == 'Женский SexSe1rch'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $updateDB = mysqli_query ($con, "UPDATE `MainInfo` SET sex = 'Женский' WHERE userID = ".$user." ");

        // Проверяем сколько у человека выбрано ценностей и если меньше 5, даем добавить себе ценности
        $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5` FROM `Needs` WHERE userID='".$user."' ");
        $needs = mysqli_fetch_row($needsCheck);

        $count = 5;
        $n = 0;
        foreach ($needs as $key => $value) {
            if (!empty($value)) {
                $count -= 1;
                $n = $n + 1;
            }
        }

        if ($n < 5) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "_Для того чтобы искать вторую половинку, вам нужно добавить еще_ " ."*". $count ."*". " _ценностей_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить недостающие ценности', 'callback_data' => 'myNeeds']
                        ],
                        [
                            ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{
            $response = [
                'chat_id' => $user,
                
                'photo' => curl_file_create("../tgBot/BotPic/post_198.png"),
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>[
                        [
                            ['text' => "\u{2640} Женский", 'callback_data' => 'Женский SexSe2rch']
                        ],
                        [
                            ['text' => "\u{2642} Мужской", 'callback_data' => 'Мужской SexSe2rch']
                        ],
                        [
                            ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ])
            ];                 
            $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_exec($ch);
            curl_close($ch);
            return;
        }
    }
    // Поиск клиентов
    else if ($data['callback_query']['data'] == 'clientsFinder'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
        
        $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM SkillCategories ");
        $finalButtonsArray = GenerateButtonsPage($categoriesArray,' ser1ch',1,9);
        array_push($finalButtonsArray,
        array(
            array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder'),
            array('text' => '2 страница 👉', 'callback_data' => 'clientsFinder2')
        )
        );

        $response = [
            'chat_id' => $user,
            
            'photo' => curl_file_create("../tgBot/BotPic/post_214.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>$finalButtonsArray
            ])
        ];                 
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'clientsFinder2'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM SkillCategories ");
        $finalButtonsArray = GenerateButtonsPage($categoriesArray,' ser1ch',2,9);
        array_push($finalButtonsArray,
        array(
            array('text' => '👈 1 страница', 'callback_data' => 'clientsFinder'),
            array('text' => '3 страница 👉', 'callback_data' => 'clientsFinder3')
        )
        );

        $response = [
            'chat_id' => $user,
            
            'photo' => curl_file_create("../tgBot/BotPic/post_214.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>$finalButtonsArray
            ])
        ];                      
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'clientsFinder3'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM SkillCategories ");
        $finalButtonsArray = GenerateButtonsPage($categoriesArray,' ser1ch',3,9);
        array_push($finalButtonsArray,
        array(
            array('text' => '👈 2 страница', 'callback_data' => 'clientsFinder2'),
        )
        );

        $response = [
            'chat_id' => $user,
            
            'photo' => curl_file_create("../tgBot/BotPic/post_214.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>$finalButtonsArray
            ])
        ];                    
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    // Поиск специалиста
    else if ($data['callback_query']['data'] == 'skillsFinder'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM SkillCategories ");
        $finalButtonsArray = GenerateButtonsPage($categoriesArray,'-find',1,9);
        array_push($finalButtonsArray,
        array(
            array('text' => '👈 Вернуться в "Поиск людей"', 'callback_data' => 'peopleFinder'),
            array('text' => '2 страница 👉', 'callback_data' => 'skillsFinder2')
        )
        );
        
        $response = [
            'chat_id' => $user,
            
            'photo' => curl_file_create("../tgBot/BotPic/post_215.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>$finalButtonsArray
            ])
        ];                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'skillsFinder2'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM SkillCategories ");
        $finalButtonsArray = GenerateButtonsPage($categoriesArray,'-find',2,9);
        array_push($finalButtonsArray,
        array(
            array('text' => '👈 1 страница', 'callback_data' => 'skillsFinder'),
            array('text' => '3 страница 👉', 'callback_data' => 'skillsFinder3')
        )
        );

        $response = [
            'chat_id' => $user,
            
            'photo' => curl_file_create("../tgBot/BotPic/post_215.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>$finalButtonsArray
            ])
        ];  

        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'skillsFinder3'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM SkillCategories ");
        $finalButtonsArray = GenerateButtonsPage($categoriesArray,'-find',3,9);
        array_push($finalButtonsArray,
        array(
            array('text' => '👈 2 страница', 'callback_data' => 'skillsFinder2')
        )
        );

        $response = [
            'chat_id' => $user,
            
            'photo' => curl_file_create("../tgBot/BotPic/post_215.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>$finalButtonsArray
            ])
        ];                      
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'peopleFinder'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            
            'photo' => curl_file_create("../tgBot/BotPic/post_213.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => '🔎🚲 С кем интересно провести время', 'callback_data' => 'enterestsFinder']
                    ],
                    [
                        ['text' => '🔎💪 С кем вместе обучаться', 'callback_data' => 'learnFinder']
                    ],
                    [
                        ['text' => '🔎❤️ Вторую половинку', 'callback_data' => 'needsFinder']
                    ],
                    [
                        ['text' => '🔎🧑‍💻 Поиск специалиста', 'callback_data' => 'skillsFinder']
                    ],
                    [
                        ['text' => '🔎👥 Клиентов', 'callback_data' => 'clientsFinder']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]
            ])
        ];                 
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    // Поиск клиентов
    else if (strpos($data['callback_query']['data'], 'Трейни') !== false || strpos($data['callback_query']['data'], 'Джуниор') !== false || strpos($data['callback_query']['data'], 'Мидл') !== false || strpos($data['callback_query']['data'], 'Сеньор') !== false){
        $user = $func['from']['id'];
        $lvl = explode("," , $data['callback_query']['data']);

        if ($lvl[0] == "Трейни") {
            $level = "Trainee";
        }else if($lvl[0] == "Джуниор"){
            $level = "Junior";
        }else if ($lvl[0] == "Мидл") {
            $level = "Middle";
        }else{
            $level = "Senior";
        }

        $push = $level . " - " . $lvl[1];

        mysqli_query ($con, "INSERT INTO `Searches`(`userID`, `searchClients`, `active`, `Date`) VALUES ('".$user."', '".$lvl[1]."', '1', NOW()) ");

        $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5` FROM `Interests` WHERE userID = ".$user." ");
        $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5` FROM `Needs` WHERE userID = ".$user." ");
        $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5` FROM `Skills` WHERE userID = ".$user." ");
        $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `sex`, `userAge` FROM `MainInfo` WHERE userID = ".$user." ");
        
        $prof = mysqli_fetch_array($profCheck);
        $needs = mysqli_fetch_array($needsCheck);
        $skills = mysqli_fetch_array($skillsCheck);
        $ints = mysqli_fetch_array($intsCheck);

        // Достаем имя через айди скилла
        $skillNameCheck = mysqli_query($con,"SELECT `name` FROM `SkillsList` WHERE callbackData ='".trim($lvl[1])."' ");
        $skillName = mysqli_fetch_array($skillNameCheck);
        $skillName = $skillName['name'];

        $needToComplete = "";

        // Подготавливаем перечень для заполнения пустых ячеек в профиле
        if (empty($ints)) {
            if ($needToComplete == "") {
                $needToComplete .= "интересы";
            }else{
                $needToComplete .= ", интересы";
            }    
        }
        if (empty($needs)) {
            if ($needToComplete == "") {
                $needToComplete .= "ценности";
            }else{
                $needToComplete .= ", ценности";
            }  
        }
        if (empty($prof['name'])) {
            if ($needToComplete == "") {
                $needToComplete .= "имя";
            }else{
                $needToComplete .= ", имя";
            } 
        }
        if (empty($prof['surname'])) {
            if ($needToComplete == "") {
                $needToComplete .= "фамилию";
            }else{
                $needToComplete .= ", фамилию";
            } 
        }
        if (empty($skills)) {
            if ($needToComplete == "") {
                $needToComplete .= "навыки";
            }else{
                $needToComplete .= ", навыки";
            } 
        }
        if (empty($ints['sex'])) {
            if ($needToComplete == "") {
                $needToComplete .= "пол";
            }else{
                $needToComplete .= ", пол";
            } 
        }
        if (empty($prof['userAge'])) {
            if ($needToComplete == "") {
                $needToComplete .= "возраст";
            }else{
                $needToComplete .= ", возраст";
            } 
        }

        // Если в профиле хоть что-то не заполнено, тогда даем человеку возможность видеть только новых людей и выводим сообщение с кнопкой ведущей в профиль
        if (empty($needs) or empty($prof['name']) or empty($prof['surname']) or empty($ints) or empty($skills) or empty($prof['sex']) or empty($prof['userAge'])) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "Мы запомнили ваш поиск и когда будут появляться люди с таким навыком, мы вас оповестим\n\nЕсли вы хотите искать людей самостоятельно, тогда вам нужно заполнить еще: " . $needToComplete,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Заполнить данные', 'callback_data' => 'profile']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Поиск людей"', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
        }else{
            // Поиск в БД по запросу
            $usersCheck = mysqli_query ($con, "SELECT `userID` FROM `Searches` WHERE `searchSpecialist` LIKE '%".$lvl[1]."%' ");
            $skill = mysqli_fetch_array($usersCheck);

            $userNames = "";
            $counter = 0;

            foreach ($usersCheck as $key => $value) {
                mysqli_fetch_array($value);
                foreach ($value as $key => $value) {
                    $userTable = mysqli_query ($con, "SELECT isPrivate FROM MainInfo WHERE userID='".$value."' ");
                    $userData = mysqli_fetch_array($userTable);
                    if ($value != $user && $userData['isPrivate'] == 0) {
                        if ($userNames == "") {
                            $userNames = $value;
                            $counter += 1;
                        }else{
                            $userNames .= "," . $value;
                            $counter += 1;
                        }
                    }                    
                }
            }

            // Удаляем выбор в поиске
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Делаем проверку. Если не нашлось ничего, то выводим сообщение, что никого не нашли, но когда будут появляться люди - мы напишем
            if (empty($userNames)) {
                $response = [
                    'chat_id' => $user,
                    'caption' => "_Мы не нашли людей, которым нужен_ *".$skillName."* _,но когда они появятся - вы получите уведомление_",
                    "parse_mode" => "Markdown",
                    
                    'photo' => curl_file_create("../tgBot/BotPic/post_220.png"),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];                 
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }else{
                // Пушим список айдишек в БД
                mysqli_query ($con, "UPDATE `TrackingMenu` SET searchIDs = '".$userNames."' WHERE userID = ".$user." ");

                $ids = explode(',', $userNames);

                // Выводим данные первого человека
                $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userPhoto`, `tgUserName`, `userAge` FROM `MainInfo` WHERE userID='".$ids[0]."' ");
                $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$ids[0]."' ");
                $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5`, `s6` FROM `Skills` WHERE userID='".$ids[0]."' ");
                $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5`, `n6` FROM `Needs` WHERE userID='".$ids[0]."' ");
                $socialCheck = mysqli_query ($con, "SELECT `inst`, `tiktok`, `viber`, `whatsapp`, `facebook`, `anotherSocials` FROM `Socials` WHERE userID='".$ids[0]."' ");

                $prof = mysqli_fetch_array($profCheck);
                $skill = mysqli_fetch_row($skillsCheck);
                $need = mysqli_fetch_row($needsCheck);
                $int = mysqli_fetch_row($intsCheck);
                $socials = mysqli_fetch_array($socialCheck);

                $msgText1 = "";
                $msgText2 = "";
                $msgText3 = "";

                if (!empty($skill[0]) or !empty($skill[1]) or !empty($skill[2]) or !empty($skill[3]) or !empty($skill[4]) or !empty($skill[5])) {
                    $msgText1 = "\n🧑‍💻 <i>Навыки:</i> \n";
                        // Выводим скиллы в правильном виде
                    foreach ($skill as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText1 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText1 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText1 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText1 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText1 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText1 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }

                if (!empty($need[0]) or !empty($need[1]) or !empty($need[2]) or !empty($need[3]) or !empty($need[4]) or !empty($need[5])) {
                    $msgText2 = "\n📝 <i>Ценности:</i> \n";
                        // Выводим ценности в правильном виде
                    foreach ($need as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText2 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }    
                
                if (!empty($int[0]) or !empty($int[1]) or !empty($int[2]) or !empty($int[3]) or !empty($int[4]) or !empty($int[5])) {
                    $msgText3 = "\n🚲 <i>Интересы:</i> \n";
                        // Выводим ценности в правильном виде
                    foreach ($int as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText3 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }

                $soc = "";

                if (!empty($prof['userAge'])) {
                    $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
                }

                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $inst;
                    }else{
                        $soc .= ", ".$inst;
                    }
                }else{
                    $inst = "";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $tiktok;
                    }else{
                        $soc .= ", ".$tiktok;
                    }
                }else{
                    $tiktok = "";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $facebook;
                    }else{
                        $soc .= ", ".$facebook;
                    }
                }else{
                    $facebook = "";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b>Viber - ".$socials['viber']."</b>";
                    if ($soc == "") {
                        $soc = "\n" . $viber;
                    }else{
                        $soc .= ", ".$viber;
                    }
                }else{
                    $viber = "";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $whatsapp;
                    }else{
                        $soc .= ", ".$whatsapp;
                    }
                }else{
                    $whatsapp = "";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $anotherSocials;
                    }else{
                        $soc .= ", ".$anotherSocials;
                    }
                }else{
                    $anotherSocials = "";
                }

                // Если кол-во найденных профилей = 1
                if ($counter == 1) {
                    if ($prof['userPhoto'] == "") {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }else{
                        $response = [
                            'chat_id' => $user,
                            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ])
                        ];
                                
                        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                        curl_setopt($ch, CURLOPT_POST, 1);  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_exec($ch);
                        curl_close($ch);
                        return;
                    }
                }else{
                    if (empty($prof['userPhoto'])) {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }else{
                        $response = [
                            'chat_id' => $user,
                            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ])
                        ];
                                
                        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                        curl_setopt($ch, CURLOPT_POST, 1);  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_exec($ch);
                        curl_close($ch);
                        return;
                    }
                }
            }
        }
    }
        else if (strpos($data['callback_query']['data'], 'ser2ch') !== false) {
            // Удаляем слово add из профессии
            $profData = $data['callback_query']['data'];
            $profData = preg_replace("/ser2ch/i", "", $profData);

            // Удаляем лишние пробелы
            $profData = trim($profData);

            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM SkillsList WHERE `callbackData`='".$profData."' ");
            $skillToAdd  = mysqli_fetch_array($skillsQuery);
            // Удаляем лишние пробелы
            $profName = trim($skillToAdd['name']);

            $response = [
                'chat_id' => $user,
                'caption' => "_Виберите уровень владения_ " ."*". $profName ."*",
                "parse_mode" => "Markdown",
                
                'photo' => curl_file_create("../tgBot/BotPic/post_209.png"),
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>[
                        [
                            ['text' => 'Trainee(Учусь)', 'callback_data' => 'Трейни,' . $profData]  
                        ],
                        [
                            ['text' => 'Junior(Начинающий)', 'callback_data' => 'Джуниор,' . $profData]  
                        ],
                        [
                            ['text' => 'Middle(Средний уровень)', 'callback_data' => 'Мидл,' . $profData]  
                        ],
                        [
                            ['text' => 'Senior(Профессионал)', 'callback_data' => 'Сеньор,' . $profData]  
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder']  
                        ]
                    ]
                ])
            ];                 
            $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_exec($ch);
            curl_close($ch);
            return;
    }
    else if (strpos($data['callback_query']['data'], '1122') !== false) {
        // Удаляем сообщение по которому нажали
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        // Достаем город и страну человека
        $user = $func['from']['id'];
        $city = preg_replace("/1122/i", "", $data['callback_query']['data']);
        $city = trim($city);

        mysqli_query($con, "UPDATE `MainInfo` SET location = '".$city."' WHERE userID = ".$user." ");
        mysqli_query($con, "UPDATE `TrackingMenu` SET whichMenu = '' WHERE userID = ".$user." ");

        $response = [
            'chat_id' => $user,
            'caption' => "👌 Отлично, чат в твоем городе я уже нашел, но для полной регистрации мне нужно знать твой номер. \nНажми на кнопку ниже 👇",
            'parse_mode' => "Markdown",
            
            'photo' => curl_file_create("../tgBot/BotPic/post_192.png"),
            'reply_markup'=>json_encode([
                resize_keyboard =>true,
                one_time_keyboard => true,
                'keyboard' => [
                    [
                        ['text' => '📱 Поделиться номером', request_contact => true]
                    ]
                ]
            ])
        ];                 
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == "sendGeoFrom5List1"){
        // Удаляем сообщение по которому нажали
        $user = $func['from']['id'];

        // Пушим в БД, чтоб человек мог писать в чат
        mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'send3Geo4From5List', mesToChange = '".$data['callback_query']['message']['message_id']."'  WHERE userID = ".$user." ");

        $method = 'editMessageText';
        $send_data = [
            'text' => "_Напиши мне название своего_ *города* _или_ *страну* _и выбери правильный из появившегося списка_",
            "parse_mode" => "Markdown"
            ];
    }
    else if ($data['callback_query']['data'] == "send3Geo4From5List"){
        // Удаляем сообщение по которому нажали
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        // Пишем человеку инструкцию, что нужно написать название своего города и из появившегося списка - выбрать его
        $method = 'sendMessage';
        $send_data = [
            'text' => "*Инструкция*\n\n_В следующем меню тебе нужно будет написать мне название своего_ *города* _или_ *страну* _и выбрать правильный из появившегося списка_",
            "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Далее', 'callback_data' => 'sendGeoFrom5List1']  
                        ]
                    ]
                ]
            ];
    }
    else if ($data['callback_query']['data'] == "send1Geo2Automatically"){
        // Удаляем сообщение по которому нажали
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'caption' => "_Внизу, где у тебя обычно клавиатура, появилась кнопка_ *'Поделиться местоположением'*_. Нажми на нее, чтоб автоматически отправить мне свою геопозицию._",
            'parse_mode' => "Markdown",
            
            'photo' => curl_file_create("../tgBot/BotPic/post_236.jpg"),
            'reply_markup'=>json_encode([
                resize_keyboard =>true,
                one_time_keyboard => true,
                'keyboard' => [
                    [
                        ['text' => 'Поделиться местоположением', request_location => true]
                    ]
                ]
            ])
        ];                 
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'myCoins'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $userCoins = mysqli_query ($con, "SELECT `coins` FROM `MainInfo` WHERE userID='".$user."' ");
        $coins = mysqli_fetch_array($userCoins);

        if ($coins['coins'] == "") {
            $coins = 0;
        }else{
            $coins = $coins['coins'];
        }

        $response = [
            'chat_id' => $user,
            'caption' => "_У вас на счету:_ " . "*" . $coins . "*" . ' монет',
            'parse_mode' => "Markdown",
            
            'photo' => curl_file_create("../tgBot/BotPic/post_234.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Как заработать монеты?', 'callback_data' => 'howToMakeCoins']
                    ],
                    [
                        ['text' => 'Потратить монеты', 'callback_data' => 'shop']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]
            ])
        ];                 
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    // Поиск с кем обучаться в регистрации
    else if (strpos($data['callback_query']['data'], 'firstch') !== false) {
        $user = $func['from']['id'];
        // Удаляем ch из профессии
        $chWord = $data['callback_query']['data'];
        $word = preg_replace("/firstch/i", "", $chWord);

        $skill = trim($word);

        // Пушим в БД
        mysqli_query ($con, "UPDATE `Skills` SET s1 = '".$skill."', lvl1 = 'Trainee' WHERE userID = ".$user." ");
        mysqli_query ($con, "UPDATE `SkillAdds` SET search1 = 'С кем обучаться' WHERE userID = ".$user." ");
        mysqli_query ($con, "INSERT INTO `Searches`(`userID`, `searchLearn`, `active`, `Date`) VALUES ('".$user."', '".$skill."', '1', NOW()) ");

        // Выводим человеку сообщение об успешности операции и Спрашиваем локацию
        $method = 'editMessageText';
        $send_data = [
            'text' => "_Отлично! Теперь мне нужно узнать_ *твое местоположение*_, чтоб добавить тебя в_ *чат для обучения* _и помогать находить людей из_ *твоего города*",
            "parse_mode" => "Markdown",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Поделиться геометкой', 'callback_data' => 'send1Geo2Automatically']
                    ],
                    [
                        ['text' => 'Выбрать из списка', 'callback_data' => 'send3Geo4From5List']
                    ]
                ]
            ]
        ];
    }
    // Поиск специалиста в регистрации
    else if (strpos($data['callback_query']['data'], 'secondch') !== false) {
        $user = $func['from']['id'];
        // Удаляем ch из профессии
        $chWord = $data['callback_query']['data'];
        $word = preg_replace("/secondch/i", "", $chWord);

        $skill = trim($word);

        // Пушим в БД
        mysqli_query ($con, "UPDATE `Skills` SET s1 = '".$skill."', lvl1 = 'Senior' WHERE userID = ".$user." ");
        mysqli_query ($con, "UPDATE `SkillAdds` SET search1 = 'Ищу клиентов' WHERE userID = ".$user." ");
        mysqli_query ($con, "INSERT INTO `Searches`(`userID`, `searchSpecialist`, `active`, `Date`) VALUES ('".$user."', '".$skill."', '1', NOW()) ");

        // Выводим человеку сообщение об успешности операции и Спрашиваем локацию
        $method = 'editMessageText';
        $send_data = [
            'text' => "_Отлично! Теперь мне нужно узнать_ *твое местоположение*_, чтоб добавить тебя в_ *чат для обучения* _и помогать находить людей из_ *твоего города*",
            "parse_mode" => "Markdown",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Поделиться геометкой', 'callback_data' => 'send1Geo2Automatically']
                    ],
                    [
                        ['text' => 'Выбрать из списка', 'callback_data' => 'send3Geo4From5List']
                    ]
                ]
            ]
        ];
    }
    // Поиск клиентов в регистрации
    else if (strpos($data['callback_query']['data'], 'thirdch') !== false) {
        $user = $func['from']['id'];
        // Удаляем ch из профессии
        $chWord = $data['callback_query']['data'];
        $word = preg_replace("/thirdch/i", "", $chWord);

        $skill = trim($word);

        // Пушим кого человек ищет в БД
        mysqli_query ($con, "INSERT INTO `Searches`(`userID`, `searchClients`, `active`, `Date`) VALUES ('".$user."', '".$skill."', '1', NOW()) ");

        // Выводим человеку сообщение об успешности операции и Спрашиваем локацию
        $method = 'editMessageText';
        $send_data = [
            'text' => "_Отлично! Теперь мне нужно узнать_ *твое местоположение*_, чтоб добавить тебя в_ *чат для обучения* _и помогать находить людей из_ *твоего города*",
            "parse_mode" => "Markdown",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Поделиться геометкой', 'callback_data' => 'send1Geo2Automatically']
                    ],
                    [
                        ['text' => 'Выбрать из списка', 'callback_data' => 'send3Geo4From5List']
                    ]
                ]
            ]
        ];
    }
    elseif (strpos($data['callback_query']['data'], 'fourthch') !== false) {
        // Поиск в БД такой ценности
        $user = $func['from']['id'];
        $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID = ".$user." ");
        $needs = mysqli_fetch_row($needsCheck);

        $user = $func['from']['id']; 
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Удаляем ch из ценностей
        $chWord = $data['callback_query']['data'];
        $wordData = preg_replace("/fourthch/i", "", $chWord);
        $wordData = mysqli_query($con,"SELECT `name` FROM `NeedsList` WHERE callbackData ='".$wordData."' ");
        $wordData = mysqli_fetch_array($wordData);
        $word = trim($wordData['name']);

        $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList`");
    // Если это будет первая ценность в профиле
        if (empty($needs[0]) and empty($needs[1]) and empty($needs[2]) and empty($needs[3]) and empty($needs[4]) and empty($needs[5])) {
            
            // Пушим новую ценность в БД
            mysqli_query ($con, "UPDATE `Needs` SET n1 = '".$word."' WHERE userID = ".$user." ");                
            
            $needsList = mysqli_query($con,"SELECT * FROM `NeedsList`");

            $finalButtonsArray = GenerateButtonsPage($needsList,' fourthch',1,99);
            array_push($finalButtonsArray,
            array(array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu')));

            $method = 'sendMessage';
            $send_data = [
                'text' => "Просмотрите все ценности и найдите самую важную для вас!\n\nМои ценности:\n" . "\u{0031}\u{FE0F}\u{20E3}" . " - " . trim($word) . "\n\nВыберите 5 ценностей начиная с самой важной:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
        }
        // Если у человека уже были ценности
        else{
            if (empty($needs[0])) {
                // Пушим новую ценность в БД
                mysqli_query ($con, "UPDATE `Needs` SET n1 = '".$word."' WHERE userID = ".$user." ");
            }else if (empty($needs[1])) {
                // Пушим новую ценность в БД
                mysqli_query ($con, "UPDATE `Needs` SET n2 = '".$word."' WHERE userID = ".$user." ");
            }else if (empty($needs[2])) {
                // Пушим новую ценность в БД
                mysqli_query ($con, "UPDATE `Needs` SET n3 = '".$word."' WHERE userID = ".$user." ");
            }else if (empty($needs[3])) {
                // Пушим новую ценность в БД
                mysqli_query ($con, "UPDATE `Needs` SET n4 = '".$word."' WHERE userID = ".$user." ");
            }else if (empty($needs[4])) {
                // Пушим новую ценность в БД
                mysqli_query ($con, "UPDATE `Needs` SET n5 = '".$word."' WHERE userID = ".$user." ");
                // Проверяем кол-во ценностей, если = 5 и человек не получал награду, то 
            if ($rewards['NeedsReward'] == 0) {
                // Пушим, что дали награду
                mysqli_query ($con, "UPDATE `userRewards` SET NeedsReward = 1 WHERE userID = ".$user." ");

                // Получаем кол-во монет пользователя
                $selectCoins = mysqli_query ($con, "SELECT `coins` FROM `MainInfo` WHERE userID='".$user."' ");
                $coins = mysqli_fetch_array($selectCoins);

                // Плюсуем к монетам награду
                $coins = $coins['coins'] + 100;

                // Выдаем монеты
                mysqli_query ($con, "UPDATE `MainInfo` SET coins = '".$coins."' WHERE userID = ".$user." ");

                $method = 'sendMessage';
                $send_data = [
                    'text' => "Мои ценности:\n" . $msgArray . "\n\nВы получили 100 монет за добавление 5 ценностей. Узнать кол-во монет и как их получить, вы можете нажав на кнопку 'Монеты' в главном меню",
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                // Выводим человеку сообщение об успешности операции и Спрашиваем локацию
                $method = 'sendMessage';
                $send_data = [
                    'text' => "_Отлично! Теперь мне нужно узнать_ *твое местоположение*_, чтоб добавить тебя в_ *чат для обучения* _и помогать находить людей из_ *твоего города*",
                    "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Поделиться геометкой', 'callback_data' => 'send1Geo2Automatically']
                            ],
                            [
                                ['text' => 'Выбрать из списка', 'callback_data' => 'send3Geo4From5List']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                if (empty($needs[5])) {
                    // Пушим новую ценность в БД
                    mysqli_query ($con, "UPDATE `Needs` SET n6 = '".$word."' WHERE userID = ".$user." "); 
                }else{
                    $newN6 = $needs[5] . ", " . $word;
                    // Пушим новую ценность в БД
                    mysqli_query ($con, "UPDATE `Needs` SET n6 = '".$newN6."' WHERE userID = ".$user." ");
                }
            }
            $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID = ".$user." ");
            $needs = mysqli_fetch_row($needsCheck);

            // Выводим ценности в правильном виде
            foreach ($needs as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgArray .= "\u{0031}\u{FE0F}\u{20E3}" . " - " . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgArray .= "\u{0032}\u{FE0F}\u{20E3}" . " - " . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgArray .= "\u{0033}\u{FE0F}\u{20E3}" . " - " . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgArray .= "\u{0034}\u{FE0F}\u{20E3}" . " - " . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgArray .= "\u{0035}\u{FE0F}\u{20E3}" . " - " . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgArray .= "\n*Дополнительные ценности: *" . trim($value) . "\n";
                }
            }

            $needsQueryy = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID = '".$user."' ");
            $except = mysqli_fetch_array($needsQueryy);
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($needsQuery," fourthch",$except,"n");

            if(empty($finalButtonsArray)){
                $buttonText = "Вы добавили все ценности.";
            }else{
                $buttonText = "Просмотрите все ценности и найдите самую важную для вас!\n\nМои ценности:\n" . $msgArray . "\n\nВыберите 5 ценностей начиная с самой важной:";
            }

            array_push($finalButtonsArray,
            array(array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'))
            );
            $method = 'sendMessage';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
        }
    }
}
else if (strpos($data['callback_query']['data'], 'tni') !== false) {
        $user = $func['from']['id'];
        // Поиск в БД такого навыка
        $intsCheck = mysqli_query ($con, "SELECT `interest1`,`interest2`,`interest3`,`interest4`,`interest5` FROM `Interests` WHERE userID = ".$user." ");
        $ints = mysqli_fetch_row($intsCheck);

        // Удаляем слово int из профессии
        $word = $data['callback_query']['data'];
        $intData = preg_replace("/tni/i", "", $word);
        $intQuery = mysqli_query($con,"SELECT `name` FROM `InterestsList` WHERE callbackData= '".$intData."' ");
        $intRow = mysqli_fetch_array($intQuery);
        $int = $intRow['name'];
        
        $msgArray = "";
        $str = "";

        foreach ($ints as $key => $value) {
            if ($str == "") {
                $str .= $value;
            }else if(!empty($value)){
                $str .= "," . $value;
            }
        }
        // Если это будет первый интерес в профиле
        if (empty($ints[0])) {
            // Пушим новый интерес в БД
            mysqli_query ($con, "UPDATE `Interests` SET interest1 = '".trim($int)."' WHERE userID = '".$user."' ");

            $interestCategoriesQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestCategories`");
            $finalButtonsArray   = GenerateButtonsPage($interestCategoriesQuery,' 1',1,9,' 🔻');

            array_push($finalButtonsArray,array(
                array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu')
            ));
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Укажите 5 своих интересов, начиная с самого важного\n\nСейчас у вас указано:\n" . "\u{0031}\u{FE0F}\u{20E3}" . " - " . trim($int) . "\n\nВыбери категорию:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else if (empty($ints[1])) {
            // Пушим новый интерес в БД
            mysqli_query ($con, "UPDATE `Interests` SET interest2 = '".trim($int)."' WHERE userID = ".$user." ");
        }else if (empty($ints[2])) {
            // Пушим новый интерес в БД
            mysqli_query ($con, "UPDATE `Interests` SET interest3 = '".trim($int)."' WHERE userID = ".$user." ");
        }else if(empty($ints[3])){
            // Пушим новый интерес в БД
            mysqli_query ($con, "UPDATE `Interests` SET interest4 = '".trim($int)."' WHERE userID = ".$user." ");
        }else if (empty($ints[4])){
            $isRewardedQuery = mysqli_fetch_array(mysqli_query($con,"SELECT `InterestsReward` FROM `userRewards` WHERE userID =".$user." "));
            $isRewarded = $isRewardedQuery['InterestsReward'];
            // Пушим новый интерес в БД
            mysqli_query ($con, "UPDATE `Interests` SET interest5 = '".trim($int)."' WHERE userID = ".$user." ");
        
            // Пушим, что дали награду
            mysqli_query ($con, "UPDATE `userRewards` SET InterestsReward = 1 WHERE userID = ".$user." ");
            
            // Получаем кол-во монет пользователя
            $selectCoins = mysqli_query ($con, "SELECT `coins` FROM `MainInfo` WHERE userID='".$user."' ");
            $coins = mysqli_fetch_array($selectCoins);

            // Плюсуем к монетам награду
            if($isRewarded){
                $coins = $coins['coins'] + 100;
            }

            // Выдаем монеты
            mysqli_query ($con, "UPDATE `MainInfo` SET coins = '".$coins."' WHERE userID = ".$user." ");

            // Выводим человеку сообщение об успешности операции и Спрашиваем локацию
            $method = 'sendMessage';
            $send_data = [
                'text' => "*Ты получил 100 монет за добавление 5 интересов*. _Узнать кол-во монет и как их получить, ты можешь нажав на кнопку 'Монеты' в главном меню_"."\n_Отлично! Теперь мне нужно узнать_ *твое местоположение*_, чтоб добавить тебя в_ *чат для обучения* _и помогать находить людей из_ *твоего города*",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Поделиться геометкой', 'callback_data' => 'send1Geo2Automatically']
                        ],
                        [
                            ['text' => 'Выбрать из списка', 'callback_data' => 'send3Geo4From5List']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }

        $interestCategoriesQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestCategories`");
        $finalButtonsArray   = GenerateButtonsPage($interestCategoriesQuery,' 1',1,9,' 🔻');

        array_push($finalButtonsArray,array(
            array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu')
        ));
        $method = 'sendMessage';
        $send_data = [
            'text' => "Укажите 5 своих интересов, начиная с самого важного\n\nСейчас у вас указано:\n" . $str . "\n\nВыбери категорию:",
            'reply_markup' => [
                'inline_keyboard' => $finalButtonsArray
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);       
        return;
    }
    // Поиск с кем вместе обучаться
    else if (strpos($data['callback_query']['data'], 's2erch') !== false) {
        // Узнаем сколько у человека навыков введено в профиле
        $user = $func['from']['id'];
        $intsCheck = mysqli_query($con, "SELECT `name`, `surname`, `sex`, `userAge` FROM `MainInfo` WHERE userID = " . $user . " ");
        $ints = mysqli_fetch_array($intsCheck);

        $interestsCheck = mysqli_query($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5` FROM `Interests` WHERE userID = " . $user . " ");
        $interests = mysqli_fetch_array($interestsCheck);

        $skillsCheck = mysqli_query($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5` FROM `Skills` WHERE userID = " . $user . " ");
        $skills = mysqli_fetch_array($skillsCheck);

        $needsCheck = mysqli_query($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5` FROM `Needs` WHERE userID = " . $user . " ");
        $needs = mysqli_fetch_array($needsCheck);

            // Если навыков меньше 5, тогда выводим сообщение, что нужно еще ввести интересы, чтоб 
        if (empty($skills)) {
            $number = 0;
            foreach ($skills as $key => $value) {
                if ($value == "") {
                    $number += 1;
                }
            }
            $method = 'sendMessage';
            $send_data = [
                'text' => "_Для того чтобы искать людей, вам нужно добавить еще_ " . "*" . $number . "*" . " _навыков_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Выбрать недостающие навыки', 'callback_data' => 'mySkills']
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
        } else {
            $needToComplete = "";

                // Подготавливаем перечень для заполнения пустых ячеек в профиле
            if (empty($interests)) {
                if ($needToComplete == "") {
                    $needToComplete .= "интересы";
                } else {
                    $needToComplete .= ", интересы";
                }
            }
            if (empty($needs)) {
                if ($needToComplete == "") {
                    $needToComplete .= "ценности";
                } else {
                    $needToComplete .= ", ценности";
                }
            }
            if (empty($ints['name'])) {
                if ($needToComplete == "") {
                    $needToComplete .= "имя";
                } else {
                    $needToComplete .= ", имя";
                }
            }
            if (empty($ints['surname'])) {
                if ($needToComplete == "") {
                    $needToComplete .= "фамилию";
                } else {
                    $needToComplete .= ", фамилию";
                }
            }
            if (empty($skills)) {
                if ($needToComplete == "") {
                    $needToComplete .= "навыки";
                } else {
                    $needToComplete .= ", навыки";
                }
            }
            if (empty($ints['sex'])) {
                if ($needToComplete == "") {
                    $needToComplete .= "пол";
                } else {
                    $needToComplete .= ", пол";
                }
            }
            if (empty($ints['userAge'])) {
                if ($needToComplete == "") {
                    $needToComplete .= "возраст";
                } else {
                    $needToComplete .= ", возраст";
                }
            }

                // Узнаем что человек искал
            $searchData = preg_replace("/s2erch/i", "", $data['callback_query']['data']);
            $searchSkill = mysqli_query($con,"SELECT `name` FROM `SkillsList` WHERE `callbackData`='".trim($searchData)."' ");
            $searchSkill = mysqli_fetch_array($searchSkill);

            $search = $searchSkill['name'];

                // Если в профиле хоть что-то не заполнено, тогда даем человеку возможность видеть только новых людей и выводим сообщение с кнопкой ведущей в профиль
            if (empty($needs) or empty($ints['name']) or empty($ints['surname']) or empty($interests) or empty($skills) or empty($ints['sex']) or empty($ints['userAge'])) {
                $method = 'editMessageText';
                $send_data = [
                    'text' => "_Мы запомнили ваш поиск и когда будут появляться люди с такими навыками, мы вас оповестим\nЕсли вы хотите искать людей самостоятельно, тогда вам нужно заполнить еще:_ " . $needToComplete,
                    "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Заполнить данные', 'callback_data' => 'profile']
                            ],
                            [
                                ['text' => '👈 Вернуться в "Поиск людей"', 'callback_data' => 'peopleFinder']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
            } else {
                    // Поиск в БД по запросу

                $skillCheck = mysqli_query($con, "SELECT `userID` FROM `Skills` WHERE (`s1` LIKE '%" . $search . "%') OR (`s2` LIKE '%" . $search . "%') OR (`s3` LIKE '%" . $search . "%') OR (`s4` LIKE '%" . $search . "%') OR (`s5` LIKE '%" . $search . "%') ");
                $skill = mysqli_fetch_array($skillCheck);

                $userNames = "";
                $counter = 0;

                foreach ($skillCheck as $key => $value) {
                    mysqli_fetch_array($value);
                    foreach ($value as $key => $value) {
                        $userTable = mysqli_query ($con, "SELECT isPrivate FROM MainInfo WHERE userID='".$value."' ");
                        $userData = mysqli_fetch_array($userTable);
                        if ($value != $user && $userData['isPrivate'] == 0) {
                            if ($userNames == "") {
                                $userNames = $value;
                                $counter += 1;
                            }else{
                                $userNames .= "," . $value;
                                $counter += 1;
                            }
                        }                    
                    }
                }

                // Удаляем выбор в поиске
                $send_data['message_id'] = $data['callback_query']['message']['message_id'];
                $send_data['chat_id'] = $user;
                sendTelegram('deleteMessage', $send_data);
                // Делаем проверку. Если не нашлось ничего, то выводим сообщение, что никого не нашли, но когда будут появляться люди - мы напишем
                if (empty($userNames)) {
                    $response = [
                        'chat_id' => $user,
                        'caption' => "_Мы не нашли людей с навыком_ *" . $search . "* _,но когда они появятся - вы получите уведомление_",
                        "parse_mode" => "Markdown",
                        
                        'photo' => curl_file_create("../tgBot/BotPic/post_218.png"),
                        'reply_markup'=>json_encode([
                            'inline_keyboard'=>[
                                [
                                    ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                ]
                            ]
                        ])
                    ];
                            
                    $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                    curl_setopt($ch, CURLOPT_POST, 1);  
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_exec($ch);
                    curl_close($ch);
                    return;
                } else {
                    // Пушим список айдишек в БД
                mysqli_query ($con, "UPDATE `TrackingMenu` SET searchIDs = '".$userNames."' WHERE userID = ".$user." ");

                $ids = explode(',', $userNames);

                // Выводим данные первого человека
                $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userPhoto`, `tgUserName`, `userAge` FROM `MainInfo` WHERE userID='".$ids[0]."' ");
                $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$ids[0]."' ");
                $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5`, `s6` FROM `Skills` WHERE userID='".$ids[0]."' ");
                $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5`, `n6` FROM `Needs` WHERE userID='".$ids[0]."' ");
                $socialCheck = mysqli_query ($con, "SELECT `inst`, `tiktok`, `viber`, `whatsapp`, `facebook`, `anotherSocials` FROM `Socials` WHERE userID='".$ids[0]."' ");

                $prof = mysqli_fetch_array($profCheck);
                $skill = mysqli_fetch_row($skillsCheck);
                $need = mysqli_fetch_row($needsCheck);
                $int = mysqli_fetch_row($intsCheck);
                $socials = mysqli_fetch_array($socialCheck);

                $msgText1 = "";
                $msgText2 = "";
                $msgText3 = "";

                if (!empty($skill[0]) or !empty($skill[1]) or !empty($skill[2]) or !empty($skill[3]) or !empty($skill[4]) or !empty($skill[5])) {
                    $msgText1 = "\n🧑‍💻 <i>Навыки:</i> \n";
                        // Выводим скиллы в правильном виде
                    foreach ($skill as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText1 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText1 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText1 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText1 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText1 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText1 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }

                if (!empty($need[0]) or !empty($need[1]) or !empty($need[2]) or !empty($need[3]) or !empty($need[4]) or !empty($need[5])) {
                    $msgText2 = "\n📝 <i>Ценности:</i> \n";
                        // Выводим ценности в правильном виде
                    foreach ($need as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText2 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }    
                
                if (!empty($int[0]) or !empty($int[1]) or !empty($int[2]) or !empty($int[3]) or !empty($int[4]) or !empty($int[5])) {
                    $msgText3 = "\n🚲 <i>Интересы:</i> \n";
                        // Выводим ценности в правильном виде
                    foreach ($int as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText3 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }

                $soc = "";

                if (!empty($prof['userAge'])) {
                    $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
                }

                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $inst;
                    }else{
                        $soc .= ", ".$inst;
                    }
                }else{
                    $inst = "";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $tiktok;
                    }else{
                        $soc .= ", ".$tiktok;
                    }
                }else{
                    $tiktok = "";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $facebook;
                    }else{
                        $soc .= ", ".$facebook;
                    }
                }else{
                    $facebook = "";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b>Viber - ".$socials['viber']."</b>";
                    if ($soc == "") {
                        $soc = "\n" . $viber;
                    }else{
                        $soc .= ", ".$viber;
                    }
                }else{
                    $viber = "";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $whatsapp;
                    }else{
                        $soc .= ", ".$whatsapp;
                    }
                }else{
                    $whatsapp = "";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $anotherSocials;
                    }else{
                        $soc .= ", ".$anotherSocials;
                    }
                }else{
                    $anotherSocials = "";
                }

                // Если кол-во найденных профилей = 1
                if ($counter == 1) {
                    if ($prof['userPhoto'] == "") {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }else{
                        $response = [
                            'chat_id' => $user,
                            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ])
                        ];
                                
                        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                        curl_setopt($ch, CURLOPT_POST, 1);  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_exec($ch);
                        curl_close($ch);
                        return;
                    }
                }else{
                    if (empty($prof['userPhoto'])) {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }else{
                        $response = [
                            'chat_id' => $user,
                            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ])
                        ];
                                
                        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                        curl_setopt($ch, CURLOPT_POST, 1);  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_exec($ch);
                        curl_close($ch);
                        return;
                    }
                }
                }
            }
        }
    }
    else if ($data['callback_query']['data'] == "q1"){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_224.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'q1']  
                    ],
                    [
                        ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'q2']  
                    ],
                    [
                        ['text' => 'Как узнать что специалист добросовестный?', 'callback_data' => 'q3']  
                    ],
                    [
                        ['text' => 'Что такое соционика?', 'callback_data' => 'q4']  
                    ],
                    [
                        ['text' => 'Для чего разделение по психо-типам?', 'callback_data' => 'q5']  
                    ],
                    [
                        ['text' => 'Как поможет соционика найти нужного человека?', 'callback_data' => 'q6']  
                    ],
                    [
                        ['text' => 'Как использовать приложение для поиска специалиста?', 'callback_data' => 'q7']  
                    ],
                    [
                        ['text' => 'Зачем заполнять профиль, добавлять свои навыки, хобби и ценности?', 'callback_data' => 'q8']  
                    ],
                    [
                        ['text' => 'Какие еще есть функции помимо поиска людей?', 'callback_data' => 'q9']  
                    ],
                    [
                        ['text' => '👈 Вернуться в "FAQ"', 'callback_data' => 'faq']  
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == "q2"){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_225.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'q1']  
                    ],
                    [
                        ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'q2']  
                    ],
                    [
                        ['text' => 'Как узнать что специалист добросовестный?', 'callback_data' => 'q3']  
                    ],
                    [
                        ['text' => 'Что такое соционика?', 'callback_data' => 'q4']  
                    ],
                    [
                        ['text' => 'Для чего разделение по психо-типам?', 'callback_data' => 'q5']  
                    ],
                    [
                        ['text' => 'Как поможет соционика найти нужного человека?', 'callback_data' => 'q6']  
                    ],
                    [
                        ['text' => 'Как использовать приложение для поиска специалиста?', 'callback_data' => 'q7']  
                    ],
                    [
                        ['text' => 'Зачем заполнять профиль, добавлять свои навыки, хобби и ценности?', 'callback_data' => 'q8']  
                    ],
                    [
                        ['text' => 'Какие еще есть функции помимо поиска людей?', 'callback_data' => 'q9']  
                    ],
                    [
                        ['text' => '👈 Вернуться в "FAQ"', 'callback_data' => 'faq']  
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == "q3"){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_226.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'q1']  
                    ],
                    [
                        ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'q2']  
                    ],
                    [
                        ['text' => 'Как узнать что специалист добросовестный?', 'callback_data' => 'q3']  
                    ],
                    [
                        ['text' => 'Что такое соционика?', 'callback_data' => 'q4']  
                    ],
                    [
                        ['text' => 'Для чего разделение по психо-типам?', 'callback_data' => 'q5']  
                    ],
                    [
                        ['text' => 'Как поможет соционика найти нужного человека?', 'callback_data' => 'q6']  
                    ],
                    [
                        ['text' => 'Как использовать приложение для поиска специалиста?', 'callback_data' => 'q7']  
                    ],
                    [
                        ['text' => 'Зачем заполнять профиль, добавлять свои навыки, хобби и ценности?', 'callback_data' => 'q8']  
                    ],
                    [
                        ['text' => 'Какие еще есть функции помимо поиска людей?', 'callback_data' => 'q9']  
                    ],
                    [
                        ['text' => '👈 Вернуться в "FAQ"', 'callback_data' => 'faq']  
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == "q4"){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_227.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'q1']  
                    ],
                    [
                        ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'q2']  
                    ],
                    [
                        ['text' => 'Как узнать что специалист добросовестный?', 'callback_data' => 'q3']  
                    ],
                    [
                        ['text' => 'Что такое соционика?', 'callback_data' => 'q4']  
                    ],
                    [
                        ['text' => 'Для чего разделение по психо-типам?', 'callback_data' => 'q5']  
                    ],
                    [
                        ['text' => 'Как поможет соционика найти нужного человека?', 'callback_data' => 'q6']  
                    ],
                    [
                        ['text' => 'Как использовать приложение для поиска специалиста?', 'callback_data' => 'q7']  
                    ],
                    [
                        ['text' => 'Зачем заполнять профиль, добавлять свои навыки, хобби и ценности?', 'callback_data' => 'q8']  
                    ],
                    [
                        ['text' => 'Какие еще есть функции помимо поиска людей?', 'callback_data' => 'q9']  
                    ],
                    [
                        ['text' => '👈 Вернуться в "FAQ"', 'callback_data' => 'faq']  
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == "q5"){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_228.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'q1']  
                    ],
                    [
                        ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'q2']  
                    ],
                    [
                        ['text' => 'Как узнать что специалист добросовестный?', 'callback_data' => 'q3']  
                    ],
                    [
                        ['text' => 'Что такое соционика?', 'callback_data' => 'q4']  
                    ],
                    [
                        ['text' => 'Для чего разделение по психо-типам?', 'callback_data' => 'q5']  
                    ],
                    [
                        ['text' => 'Как поможет соционика найти нужного человека?', 'callback_data' => 'q6']  
                    ],
                    [
                        ['text' => 'Как использовать приложение для поиска специалиста?', 'callback_data' => 'q7']  
                    ],
                    [
                        ['text' => 'Зачем заполнять профиль, добавлять свои навыки, хобби и ценности?', 'callback_data' => 'q8']  
                    ],
                    [
                        ['text' => 'Какие еще есть функции помимо поиска людей?', 'callback_data' => 'q9']  
                    ],
                    [
                        ['text' => '👈 Вернуться в "FAQ"', 'callback_data' => 'faq']  
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == "q6"){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_229.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'q1']  
                    ],
                    [
                        ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'q2']  
                    ],
                    [
                        ['text' => 'Как узнать что специалист добросовестный?', 'callback_data' => 'q3']  
                    ],
                    [
                        ['text' => 'Что такое соционика?', 'callback_data' => 'q4']  
                    ],
                    [
                        ['text' => 'Для чего разделение по психо-типам?', 'callback_data' => 'q5']  
                    ],
                    [
                        ['text' => 'Как поможет соционика найти нужного человека?', 'callback_data' => 'q6']  
                    ],
                    [
                        ['text' => 'Как использовать приложение для поиска специалиста?', 'callback_data' => 'q7']  
                    ],
                    [
                        ['text' => 'Зачем заполнять профиль, добавлять свои навыки, хобби и ценности?', 'callback_data' => 'q8']  
                    ],
                    [
                        ['text' => 'Какие еще есть функции помимо поиска людей?', 'callback_data' => 'q9']  
                    ],
                    [
                        ['text' => '👈 Вернуться в "FAQ"', 'callback_data' => 'faq']  
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == "q7"){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_230.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'q1']  
                    ],
                    [
                        ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'q2']  
                    ],
                    [
                        ['text' => 'Как узнать что специалист добросовестный?', 'callback_data' => 'q3']  
                    ],
                    [
                        ['text' => 'Что такое соционика?', 'callback_data' => 'q4']  
                    ],
                    [
                        ['text' => 'Для чего разделение по психо-типам?', 'callback_data' => 'q5']  
                    ],
                    [
                        ['text' => 'Как поможет соционика найти нужного человека?', 'callback_data' => 'q6']  
                    ],
                    [
                        ['text' => 'Как использовать приложение для поиска специалиста?', 'callback_data' => 'q7']  
                    ],
                    [
                        ['text' => 'Зачем заполнять профиль, добавлять свои навыки, хобби и ценности?', 'callback_data' => 'q8']  
                    ],
                    [
                        ['text' => 'Какие еще есть функции помимо поиска людей?', 'callback_data' => 'q9']  
                    ],
                    [
                        ['text' => '👈 Вернуться в "FAQ"', 'callback_data' => 'faq']  
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == "q8"){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_231.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'q1']  
                    ],
                    [
                        ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'q2']  
                    ],
                    [
                        ['text' => 'Как узнать что специалист добросовестный?', 'callback_data' => 'q3']  
                    ],
                    [
                        ['text' => 'Что такое соционика?', 'callback_data' => 'q4']  
                    ],
                    [
                        ['text' => 'Для чего разделение по психо-типам?', 'callback_data' => 'q5']  
                    ],
                    [
                        ['text' => 'Как поможет соционика найти нужного человека?', 'callback_data' => 'q6']  
                    ],
                    [
                        ['text' => 'Как использовать приложение для поиска специалиста?', 'callback_data' => 'q7']  
                    ],
                    [
                        ['text' => 'Зачем заполнять профиль, добавлять свои навыки, хобби и ценности?', 'callback_data' => 'q8']  
                    ],
                    [
                        ['text' => 'Какие еще есть функции помимо поиска людей?', 'callback_data' => 'q9']  
                    ],
                    [
                        ['text' => '👈 Вернуться в "FAQ"', 'callback_data' => 'faq']  
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == "q9"){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_232.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'q1']  
                    ],
                    [
                        ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'q2']  
                    ],
                    [
                        ['text' => 'Как узнать что специалист добросовестный?', 'callback_data' => 'q3']  
                    ],
                    [
                        ['text' => 'Что такое соционика?', 'callback_data' => 'q4']  
                    ],
                    [
                        ['text' => 'Для чего разделение по психо-типам?', 'callback_data' => 'q5']  
                    ],
                    [
                        ['text' => 'Как поможет соционика найти нужного человека?', 'callback_data' => 'q6']  
                    ],
                    [
                        ['text' => 'Как использовать приложение для поиска специалиста?', 'callback_data' => 'q7']  
                    ],
                    [
                        ['text' => 'Зачем заполнять профиль, добавлять свои навыки, хобби и ценности?', 'callback_data' => 'q8']  
                    ],
                    [
                        ['text' => 'Какие еще есть функции помимо поиска людей?', 'callback_data' => 'q9']  
                    ],
                    [
                        ['text' => '👈 Вернуться в "FAQ"', 'callback_data' => 'faq']  
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    // Как заработать монеты
    else if ($data['callback_query']['data'] == 'howToMakeCoins'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $userCoins = mysqli_query ($con, "SELECT `SkillsReward`, `InterestsReward`, `NeedsReward` FROM `userRewards` WHERE userID='".$user."' ");
        $coins = mysqli_fetch_array($userCoins);

        $text = "";

        if ($coins['SkillsReward'] == 0) {
            $text .= "\nЗаполните 5 своих навыков и получите 100 монет";
        }else{
            $text .= "\n✅ Заполните 5 своих навыков и получите 100 монет";
        }
        if ($coins['InterestsReward'] == 0) {
            $text .= "\nЗаполните 5 своих интересов и получите 100 монет";
        }else{
            $text .= "\n✅ Заполните 5 своих интересов и получите 100 монет";
        }
        if ($coins['NeedsReward'] == 0) {
            $text .= "\nЗаполните 5 своих ценностей и получите 100 монет";
        }else{
            $text .= "\n✅ Заполните 5 своих ценностей и получите 100 монет";
        }

        $response = [
            'chat_id' => $user,
            'caption' => $text,
            'photo' => curl_file_create("../tgBot/BotPic/post_191.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться к "Монеты"', 'callback_data' => 'myCoins']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if($data['callback_query']['data'] == 'changeAge'){
        $user = $func['from']['id'];
        // Удаляем сообщение по которому нажали
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $func['from']['id'];
        sendTelegram('deleteMessage', $send_data);

        mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'Изменить возраст' WHERE userID = ".$user." ");

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_107.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Сохранить', 'callback_data' => 'Сохранить возраст']  
                    ],
                    [
                        ['text' => 'Отмена', 'callback_data' => 'Отменить возраст']  
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    // Личная статистика пользователя
    else if ($data['callback_query']['data'] == 'myStats'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $statCheck = mysqli_query ($con, "SELECT `coins`, `referals`, `regDate`, `rank` FROM `MainInfo` WHERE userID=".$user." ");
        $stat = mysqli_fetch_array($statCheck);
        
        $response = [
            'chat_id' => $user,
            'caption' => "📈 Моя статистика:\n\nМой ранг: ".$stat['userRank']."\nМои монеты: ".$stat['coins']."\nМои рефералы: ".$stat['referals']."\nДата регистрации: ".$stat['regDate'],
            'photo' => curl_file_create("../tgBot/BotPic/post_223.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    // Смена пола
    else if ($data['callback_query']['data'] == 'changeSex'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
       
        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_100.jpg"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Женский', 'callback_data' => 'Женский Sex']
                    ],
                    [
                        ['text' => 'Мужской', 'callback_data' => 'Мужской Sex']
                    ],
                    [
                        ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'myNameAge']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'plusPhoto'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        /*$send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);*/

        mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'ДобавлениеФото', mesToChange = '".$data['callback_query']['message']['message_id']."' WHERE userID = ".$user." ");
       
        $args1 = [
            'chat_id' => $user,
            'message_id' => $lastBotMessage['message_id'],
            'caption' => " ",
            'reply_markup'=> json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];
        
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/editMessageCaption');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);

        // Меняем картинку
        $args2 = [
            'chat_id' => $user,
            'message_id' => $lastBotMessage['message_id'],
            'media' => json_encode([
                'type' => 'photo',
                'media' => 'attach://post_101.jpg'
                ]),
            'post_101.jpg' => new CURLFile("../tgBot/BotPic/post_101.jpg"),
            'reply_markup'=> json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];
        
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/editMessageMedia');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'mySocial'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
        $socials = mysqli_fetch_array($userSocials);

        if (!empty($socials['inst'])) {
            $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
        }else{
            $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
        }

        if (!empty($socials['tiktok'])) {
            $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
        }else{
            $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
        }

        if (!empty($socials['facebook'])) {
            $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
        }else{
            $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
        }

        if (!empty($socials['viber'])) {
            $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
        }else{
            $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
        }

        if (!empty($socials['whatsapp'])) {
            $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
        }else{
            $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
        }

        if (!empty($socials['anotherSocials'])) {
            $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
        }else{
            $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
        }
       
        $response = [
            'chat_id' => $user,
            'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
            'parse_mode' => "html",
            'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'tikSocial'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `tiktok` FROM `Socials` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_array($profCheck);
       
        if (empty($prof['tiktok'])) {
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Добавить tiktok:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить', 'callback_data' => 'Добавить tiktok']  
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                            ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
            }else{
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Изменить мой tiktok ' . $prof['tiktok'],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Изменить', 'callback_data' => 'Добавить tiktok']  
                            ],
                            [
                                ['text' => 'Удалить', 'callback_data' => 'Удалить tiktok']  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                                ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }
    }
    else if ($data['callback_query']['data'] == 'fbSocial'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `facebook` FROM `Socials` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_array($profCheck);
       
        if (empty($prof['facebook'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Добавить facebook:',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Добавить', 'callback_data' => 'Добавить facebook']  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                                ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                            ]
                        ]
                    ]
                ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
            }else{
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Изменить мой facebook ' . $prof['facebook'],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Изменить', 'callback_data' => 'Добавить facebook']  
                            ],
                            [
                                ['text' => 'Удалить', 'callback_data' => 'Удалить facebook']  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                                ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }
    }
    else if ($data['callback_query']['data'] == 'viberSocial'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `viber` FROM `Socials` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_array($profCheck);
       
        if (empty($prof['viber'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Добавить viber:',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Добавить', 'callback_data' => 'Добавить viber']  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                                ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                            ]
                        ]
                    ]
                ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
            }else{
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Изменить мой viber ' . $prof['viber'],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Изменить', 'callback_data' => 'Добавить viber']  
                            ],
                            [
                                ['text' => 'Удалить', 'callback_data' => 'Удалить viber']  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                                ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }
    }
    else if ($data['callback_query']['data'] == 'wtsSocial'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `whatsapp` FROM `Socials` WHERE userID='".$user."' ");
            $prof = mysqli_fetch_array($profCheck);

            if (empty($prof['whatsapp'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Добавить whatsapp:',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Добавить', 'callback_data' => 'Добавить whatsapp']  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                                ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                            ]
                        ]
                    ]
                ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
            }else{
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Изменить мой whatsapp ' . $prof['whatsapp'],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Изменить', 'callback_data' => 'Добавить whatsapp']  
                            ],
                            [
                                ['text' => 'Удалить', 'callback_data' => 'Удалить whatsapp']  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                                ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }
    }
    else if ($data['callback_query']['data'] == 'anotherSocial'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_array($profCheck);

        if (empty($prof['anotherSocials'])) {
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Добавить другую социальную сеть:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить', 'callback_data' => 'Добавить anotherSocial']  
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                            ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Изменить другую социальную сеть ' . $prof['anotherSocials'],
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Изменить', 'callback_data' => 'Добавить anotherSocial']  
                        ],
                        [
                            ['text' => 'Удалить', 'callback_data' => 'Удалить anotherSocial']  
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                            ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }
    }
    else if ($data['callback_query']['data'] == 'Добавить инсту'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'инста' WHERE userID = ".$user." ");

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_102.jpg"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Сохранить', 'callback_data' => 'Сохранить инстаграм']  
                    ],
                    [
                        ['text' => 'Отменить', 'callback_data' => 'Отменить инстаграм']  
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'Добавить whatsapp'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'whatsapp' WHERE userID = ".$user." ");

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_103.jpg"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Сохранить', 'callback_data' => 'Сохранить whatsapp']  
                    ],
                    [
                        ['text' => 'Отменить', 'callback_data' => 'Отменить whatsapp']  
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'Добавить viber'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'viber' WHERE userID = ".$user." ");

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_104.jpg"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Сохранить', 'callback_data' => 'Сохранить viber']  
                    ],
                    [
                        ['text' => 'Отменить', 'callback_data' => 'Отменить viber']  
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'Добавить facebook'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'facebook' WHERE userID = ".$user." ");

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_105.jpg"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Сохранить', 'callback_data' => 'Сохранить facebook']  
                    ],
                    [
                        ['text' => 'Отменить', 'callback_data' => 'Отменить facebook']  
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'Добавить anotherSocial'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'anotherSocial' WHERE userID = ".$user." ");

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_106.jpg"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Сохранить', 'callback_data' => 'Сохранить anotherSocial']  
                    ],
                    [
                        ['text' => 'Отменить', 'callback_data' => 'Отменить anotherSocial']  
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'Добавить tiktok'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'tiktok' WHERE userID = ".$user." ");

        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_238.jpg"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Сохранить', 'callback_data' => 'Сохранить tiktok']  
                    ],
                    [
                        ['text' => 'Отменить', 'callback_data' => 'Отменить tiktok']  
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'Сохранить anotherSocial'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `rowsToDel` FROM `TrackingMenu` WHERE userID='".$user."' ");
        $prof = mysqli_fetch_array($profCheck);

            if (empty($prof['rowsToDel'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Упс! Кажется мне нечего сохранять. Отправь мне сообщение со своим никнеймом',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Сохранить', 'callback_data' => 'Сохранить anotherSocial']  
                            ],
                            [
                                ['text' => 'Отменить', 'callback_data' => 'Отменить anotherSocial']  
                            ]
                        ]
                    ]
                ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{

                // Создаем массив из строк для удаления
                $rowArray = explode(" , ", $prof['rowsToDel']);

                // Удаляем все сообщения в чате
                $send_data['chat_id'] = $user;
                foreach ($rowArray as $value) {
                    $send_data['message_id'] = $value;
                    sendTelegram('deleteMessage', $send_data);
                }

                    // Удаляем информацию о том, в каком меню человек, а также убираем все id сообщений из БД и старые интересы
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
                
                $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
                $socials = mysqli_fetch_array($userSocials);

                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
                    'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
                    
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                            ],
                            [
                                ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                            ],
                            [
                                ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                            ],
                            [
                                ['text' => 'Viber', 'callback_data' => 'viberSocial']
                            ],
                            [
                                ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                            ],
                            [
                                ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                                ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                            ]
                        ]            
                    ])
                ];                                
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
        }
    }
    else if ($data['callback_query']['data'] == 'Сохранить tiktok'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `rowsToDel` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $prof = mysqli_fetch_array($profCheck);

            if (empty($prof['rowsToDel'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Упс! Кажется мне нечего сохранять. Отправь мне сообщение со своим никнеймом',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Сохранить', 'callback_data' => 'Сохранить tiktok']  
                            ],
                            [
                                ['text' => 'Отменить', 'callback_data' => 'Отменить tiktok']  
                            ]
                        ]
                    ]
                ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{

                // Создаем массив из строк для удаления
                $rowArray = explode(" , ", $prof['rowsToDel']);

                // Удаляем все сообщения в чате
                $send_data['chat_id'] = $user;
                foreach ($rowArray as $value) {
                    $send_data['message_id'] = $value;
                    sendTelegram('deleteMessage', $send_data);
                }

                // Удаляем информацию о том, в каком меню человек, а также убираем все id сообщений из БД и старые интересы
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
                
                $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
                $socials = mysqli_fetch_array($userSocials);

                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
        }
    }
    else if ($data['callback_query']['data'] == 'Сохранить viber'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `rowsToDel` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $prof = mysqli_fetch_array($profCheck);

            if (empty($prof['rowsToDel'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Упс! Кажется мне нечего сохранять. Отправь мне сообщение со своим никнеймом',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Сохранить', 'callback_data' => 'Сохранить viber']  
                            ],
                            [
                                ['text' => 'Отменить', 'callback_data' => 'Отменить viber']  
                            ]
                        ]
                    ]
                ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{

                // Создаем массив из строк для удаления
                $rowArray = explode(" , ", $prof['rowsToDel']);

                // Удаляем все сообщения в чате
                $send_data['chat_id'] = $user;
                foreach ($rowArray as $value) {
                    $send_data['message_id'] = $value;
                    sendTelegram('deleteMessage', $send_data);
                }

                // Удаляем информацию о том, в каком меню человек, а также убираем все id сообщений из БД и старые интересы
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
                
                $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
                $socials = mysqli_fetch_array($userSocials);

                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
        }
    }
    else if ($data['callback_query']['data'] == 'Сохранить whatsapp'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `rowsToDel` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $prof = mysqli_fetch_array($profCheck);

            if (empty($prof['rowsToDel'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Упс! Кажется мне нечего сохранять. Отправь мне сообщение со своим никнеймом',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Сохранить', 'callback_data' => 'Сохранить whatsapp']  
                            ],
                            [
                                ['text' => 'Отменить', 'callback_data' => 'Отменить whatsapp']  
                            ]
                        ]
                    ]
                ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{

                // Создаем массив из строк для удаления
                $rowArray = explode(" , ", $prof['rowsToDel']);

                // Удаляем все сообщения в чате
                $send_data['chat_id'] = $user;
                foreach ($rowArray as $value) {
                    $send_data['message_id'] = $value;
                    sendTelegram('deleteMessage', $send_data);
                }

                // Удаляем информацию о том, в каком меню человек, а также убираем все id сообщений из БД и старые интересы
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
                
                $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
                $socials = mysqli_fetch_array($userSocials);

                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
        }
    }
    else if ($data['callback_query']['data'] == 'Сохранить инстаграм'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `rowsToDel` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $prof = mysqli_fetch_array($profCheck);

            // Удаляем сообщение с профилем
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            if (empty($prof['rowsToDel'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Упс! Кажется мне нечего сохранять. Отправь мне сообщение с твоим никнеймом',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Сохранить', 'callback_data' => 'Сохранить инстаграм']  
                            ],
                            [
                                ['text' => 'Отменить', 'callback_data' => 'Отменить инстаграм']  
                            ]
                        ]
                    ]
                ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{

                // Создаем массив из строк для удаления
                $rowArray = explode(" , ", $prof['rowsToDel']);

                // Удаляем все сообщения в чате
                $send_data['chat_id'] = $user;
                foreach ($rowArray as $value) {
                    $send_data['message_id'] = $value;
                    sendTelegram('deleteMessage', $send_data);
                }

                // Удаляем информацию о том, в каком меню человек, а также убираем все id сообщений из БД и старые интересы
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
                
                $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
                $socials = mysqli_fetch_array($userSocials);

                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
        }
    }
    else if ($data['callback_query']['data'] == 'Сохранить facebook'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $profCheck = mysqli_query ($con, "SELECT `rowsToDel` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $prof = mysqli_fetch_array($profCheck);

            if (empty($prof['rowsToDel'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Упс! Кажется мне нечего сохранять. Отправь мне сообщение со своим никнеймом',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Сохранить', 'callback_data' => 'Сохранить facebook']  
                            ],
                            [
                                ['text' => 'Отменить', 'callback_data' => 'Отменить facebook']  
                            ]
                        ]
                    ]
                ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{

                // Создаем массив из строк для удаления
                $rowArray = explode(" , ", $prof['rowsToDel']);

                // Удаляем все сообщения в чате
                $send_data['chat_id'] = $user;
                foreach ($rowArray as $value) {
                    $send_data['message_id'] = $value;
                    sendTelegram('deleteMessage', $send_data);
                }

                // Удаляем информацию о том, в каком меню человек, а также убираем все id сообщений из БД и старые интересы
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
                
                $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
                $socials = mysqli_fetch_array($userSocials);

                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
        }
    }
    else if ($data['callback_query']['data'] == 'Отменить anotherSocial'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
        // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldNeeds` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

                // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

                // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            if (empty($row['oldNeeds'])) {
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");
            }else{
                    // Убираем все удаленные сообщения из БД и информацию о том, в каком меню человек
                mysqli_query ($con, "UPDATE `Socials` SET anotherSocials = '".$row['oldNeeds']."' WHERE userID = ".$user." ");
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
            }
                // Получаем из БД все о пользователе
            $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
            $socials = mysqli_fetch_array($userSocials);

                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;

    }
    else if ($data['callback_query']['data'] == 'Отменить tiktok'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
        // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldNeeds` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            if (empty($row['oldNeeds'])) {
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");
            }else{
                // Убираем все удаленные сообщения из БД и информацию о том, в каком меню человек
                mysqli_query ($con, "UPDATE `Socials` SET tiktok = '".$row['oldNeeds']."' WHERE userID = ".$user." ");
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
            }
            // Получаем из БД все о пользователе
            $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
            $socials = mysqli_fetch_array($userSocials);
                
                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;

    }
    else if ($data['callback_query']['data'] == 'Отменить viber'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
        // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldNeeds` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            if (empty($row['oldNeeds'])) {
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");
            }else{
                // Убираем все удаленные сообщения из БД и информацию о том, в каком меню человек
                mysqli_query ($con, "UPDATE `Socials` SET viber = '".$row['oldNeeds']."' WHERE userID = ".$user." ");
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
            }
            // Получаем из БД все о пользователе
            $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
            $socials = mysqli_fetch_array($userSocials);
            
                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;

    }
    else if ($data['callback_query']['data'] == 'Отменить whatsapp'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
        // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldNeeds` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            if (empty($row['oldNeeds'])) {
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");
            }else{
                // Убираем все удаленные сообщения из БД и информацию о том, в каком меню человек
                mysqli_query ($con, "UPDATE `Socials` SET whatsapp = '".$row['oldNeeds']."' WHERE userID = ".$user." ");
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
            }
            // Получаем из БД все о пользователе
            $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
            $socials = mysqli_fetch_array($userSocials);
            
                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;

    }
    else if ($data['callback_query']['data'] == 'Отменить инстаграм'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
        // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldNeeds` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            if (empty($row['oldNeeds'])) {
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");
            }else{
                // Убираем все удаленные сообщения из БД и информацию о том, в каком меню человек
                mysqli_query ($con, "UPDATE `Socials` SET inst = '".$row['oldNeeds']."' WHERE userID = ".$user." ");
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
            }
            // Получаем из БД все о пользователе
            $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
            $socials = mysqli_fetch_array($userSocials);
            
                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;

    }
    else if ($data['callback_query']['data'] == 'Отменить facebook'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
        // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldNeeds` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            if (empty($row['oldNeeds'])) {
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");
            }else{
                // Убираем все удаленные сообщения из БД и информацию о том, в каком меню человек
                mysqli_query ($con, "UPDATE `Socials` SET facebook = '".$row['oldNeeds']."' WHERE userID = ".$user." ");
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");
            }
            // Получаем из БД все о пользователе
            $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
            $socials = mysqli_fetch_array($userSocials);
            
                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b> - <b>" . $socials['inst']."</b>";
                }else{
                    $inst = "<b>Instagram</b> - <b>" . $socials['inst']."</b>";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b> - <b>" . $socials['tiktok']."</b>";
                }else{
                    $tiktok = "<b>Tik-Tok</b> - <b>" . $socials['tiktok']."</b>";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b> - <b>" . $socials['facebook']."</b>";
                }else{
                    $facebook = "<b>Facebook</b> - <b>" . $socials['facebook']."</b>";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b><a href='viber://chat?number=+".$socials['viber']."/'>Viber</a></b> - <b>" . $socials['viber']."</b>";
                }else{
                    $viber = "<b>Viber</b> - <b>" . $socials['viber']."</b>";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b> - <b>" . $socials['whatsapp']."</b>";
                }else{
                    $whatsapp = "<b>WhatsApp</b> - <b>" . $socials['whatsapp']."</b>";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b> - <b>" . $socials['anotherSocials']."</b>";
                }else{
                    $anotherSocials = "<b>Другая</b> - <b>" . $socials['anotherSocials']."</b>";
                }
               
                $response = [
                    'chat_id' => $user,
                    'caption' => $inst . "\n" . $tiktok . "\n" . $facebook . "\n" . $viber . "\n" . $whatsapp . "\n" . $anotherSocials,
                    'parse_mode' => "html",
                    'disable_web_page_preview' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Instagram', 'callback_data' => 'instSocial']  
                    ],
                    [
                        ['text' => 'Tik-Tok', 'callback_data' => 'tikSocial']
                    ],
                    [
                        ['text' => 'Facebook', 'callback_data' => 'fbSocial']
                    ],
                    [
                        ['text' => 'Viber', 'callback_data' => 'viberSocial']
                    ],
                    [
                        ['text' => 'WhatsApp', 'callback_data' => 'wtsSocial']
                    ],
                    [
                        ['text' => 'Другая', 'callback_data' => 'anotherSocial']
                    ],
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                        ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    else if ($data['callback_query']['data'] == 'feedback'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Записываем, что человек находится в меню ФИДБЭК
        mysqli_query($con, "UPDATE `TrackingMenu` SET whichMenu = 'ФИДБЭК', mesToChange = '".$data['callback_query']['message']['message_id']."' WHERE userID = '".$user."' ");
        
        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_233.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                    ]
                ]            
            ])
        ];                                
        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
        return;
    }
    // Поиск второй половинки
    else if (strpos($data['callback_query']['data'], 'SexSe3rch') !== false) {
        // Узнаем сколько у человека навыков введено в профиле
        $user = $func['from']['id'];
        $intsCheck = mysqli_query($con, "SELECT `name`, `surname`, `sex`, `userAge` FROM `MainInfo` WHERE userID = " . $user . " ");
        $ints = mysqli_fetch_array($intsCheck);

        $interestsCheck = mysqli_query($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5` FROM `Interests` WHERE userID = " . $user . " ");
        $interests = mysqli_fetch_array($interestsCheck);

        $skillsCheck = mysqli_query($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5` FROM `Skills` WHERE userID = " . $user . " ");
        $skills = mysqli_fetch_array($skillsCheck);

        $needsCheck = mysqli_query($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5` FROM `Needs` WHERE userID = " . $user . " ");
        $needs = mysqli_fetch_array($needsCheck);

        $needToComplete = "";

        // Подготавливаем перечень для заполнения пустых ячеек в профиле
        if (empty($interests)) {
            if ($needToComplete == "") {
                $needToComplete .= "интересы";
            } else {
                $needToComplete .= ", интересы";
            }
        }
        if (empty($needs)) {
            if ($needToComplete == "") {
                $needToComplete .= "ценности";
            } else {
                $needToComplete .= ", ценности";
            }
        }
        if (empty($ints['name'])) {
            if ($needToComplete == "") {
                $needToComplete .= "имя";
            } else {
                $needToComplete .= ", имя";
            }
        }
        if (empty($ints['surname'])) {
            if ($needToComplete == "") {
                $needToComplete .= "фамилию";
            } else {
                $needToComplete .= ", фамилию";
            }
        }
        if (empty($skills)) {
            if ($needToComplete == "") {
                $needToComplete .= "навыки";
            } else {
                $needToComplete .= ", навыки";
            }
        }
        if (empty($ints['sex'])) {
            if ($needToComplete == "") {
                $needToComplete .= "пол";
            } else {
                $needToComplete .= ", пол";
            }
        }
        if (empty($ints['userAge'])) {
            if ($needToComplete == "") {
                $needToComplete .= "возраст";
            } else {
                $needToComplete .= ", возраст";
            }
        }

        // Узнаем что человек искал
        $searchData = preg_replace("/SexSe3rch/i", "", $data['callback_query']['data']);
        $searchData = mysqli_query($con,"SELECT `name` FROM `NeedsList` WHERE callbackData='".trim($searchData)."' ");
        $searchData = mysqli_fetch_array($searchData);
        $search = trim($searchData['name']);

        // Если в профиле хоть что-то не заполнено, тогда даем человеку возможность видеть только новых людей и выводим сообщение с кнопкой ведущей в профиль
        if (empty($needs) or empty($ints['name']) or empty($ints['surname']) or empty($interests) or empty($skills) or empty($ints['sex']) or empty($ints['userAge'])) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "_Мы запомнили ваш поиск и когда будут появляться люди с такой ценностью, мы вас оповестим\nЕсли вы хотите искать людей самостоятельно, тогда вам нужно заполнить еще:_ " . $needToComplete,
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Заполнить данные', 'callback_data' => 'profile']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Поиск людей"', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
        }else{
            // Поиск в БД по запросу
            $skillCheck = mysqli_query($con, "SELECT `userID` FROM `Needs` WHERE (`n1` LIKE '%" . $search . "%') OR (`n2` LIKE '%" . $search . "%') OR (`n3` LIKE '%" . $search . "%') OR (`n4` LIKE '%" . $search . "%') OR (`n5` LIKE '%" . $search . "%') ");
            $skill = mysqli_fetch_array($skillCheck);

            $userNames = "";
            $counter = 0;

            foreach ($skillCheck as $key => $value) {
                mysqli_fetch_array($value);
                foreach ($value as $key => $value) {
                    $userTable = mysqli_query ($con, "SELECT isPrivate FROM MainInfo WHERE userID='".$value."' ");
                    $userData = mysqli_fetch_array($userTable);
                    if ($value != $user && $userData['isPrivate'] == 0) {
                        if ($userNames == "") {
                            $userNames = $value;
                            $counter += 1;
                        }else{
                            $userNames .= "," . $value;
                            $counter += 1;
                        }
                    }                    
                }
            }

            // Удаляем выбор в поиске
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Делаем проверку. Если не нашлось ничего, то выводим сообщение, что никого не нашли, но когда будут появляться люди - мы напишем
            if (empty($userNames)) {
                $response = [
                    'chat_id' => $user,
                    'caption' => "_Мы не нашли людей с ценностью_ *".$search."* _,но когда они появятся - вы получите уведомление_",
                    "parse_mode" => "Markdown",
                    'photo' => curl_file_create("../tgBot/BotPic/post_221.png"),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];                 
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }else{
                // Пушим список айдишек в БД
                mysqli_query ($con, "UPDATE `TrackingMenu` SET searchIDs = '".$userNames."' WHERE userID = ".$user." ");

                $ids = explode(',', $userNames);

                // Выводим данные первого человека
                $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userPhoto`, `tgUserName`, `userAge` FROM `MainInfo` WHERE userID='".$ids[0]."' ");
                $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$ids[0]."' ");
                $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5`, `s6` FROM `Skills` WHERE userID='".$ids[0]."' ");
                $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5`, `n6` FROM `Needs` WHERE userID='".$ids[0]."' ");
                $socialCheck = mysqli_query ($con, "SELECT `inst`, `tiktok`, `viber`, `whatsapp`, `facebook`, `anotherSocials` FROM `Socials` WHERE userID='".$ids[0]."' ");

                $prof = mysqli_fetch_array($profCheck);
                $skill = mysqli_fetch_row($skillsCheck);
                $need = mysqli_fetch_row($needsCheck);
                $int = mysqli_fetch_row($intsCheck);
                $socials = mysqli_fetch_array($socialCheck);

                $msgText1 = "";
                $msgText2 = "";
                $msgText3 = "";

                if (!empty($skill[0]) or !empty($skill[1]) or !empty($skill[2]) or !empty($skill[3]) or !empty($skill[4]) or !empty($skill[5])) {
                    $msgText1 = "\n🧑‍💻 <i>Навыки:</i> \n";
                        // Выводим скиллы в правильном виде
                    foreach ($skill as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText1 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText1 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText1 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText1 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText1 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText1 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }

                if (!empty($need[0]) or !empty($need[1]) or !empty($need[2]) or !empty($need[3]) or !empty($need[4]) or !empty($need[5])) {
                    $msgText2 = "\n📝 <i>Ценности:</i> \n";
                        // Выводим ценности в правильном виде
                    foreach ($need as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText2 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }    
                
                if (!empty($int[0]) or !empty($int[1]) or !empty($int[2]) or !empty($int[3]) or !empty($int[4]) or !empty($int[5])) {
                    $msgText3 = "\n🚲 <i>Интересы:</i> \n";
                        // Выводим ценности в правильном виде
                    foreach ($int as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $msgText3 .= "<b>" . trim($value) . "</b>\n";
                        }
                    }
                }

                $soc = "";

                if (!empty($prof['userAge'])) {
                    $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
                }

                if (!empty($socials['inst'])) {
                    $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $inst;
                    }else{
                        $soc .= ", ".$inst;
                    }
                }else{
                    $inst = "";
                }

                if (!empty($socials['tiktok'])) {
                    $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $tiktok;
                    }else{
                        $soc .= ", ".$tiktok;
                    }
                }else{
                    $tiktok = "";
                }

                if (!empty($socials['facebook'])) {
                    $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $facebook;
                    }else{
                        $soc .= ", ".$facebook;
                    }
                }else{
                    $facebook = "";
                }

                if (!empty($socials['viber'])) {
                    $viber = "<b>Viber - ".$socials['viber']."</b>";
                    if ($soc == "") {
                        $soc = "\n" . $viber;
                    }else{
                        $soc .= ", ".$viber;
                    }
                }else{
                    $viber = "";
                }

                if (!empty($socials['whatsapp'])) {
                    $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $whatsapp;
                    }else{
                        $soc .= ", ".$whatsapp;
                    }
                }else{
                    $whatsapp = "";
                }

                if (!empty($socials['anotherSocials'])) {
                    $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b>";
                    if ($soc == "") {
                        $soc = "\n" . $anotherSocials;
                    }else{
                        $soc .= ", ".$anotherSocials;
                    }
                }else{
                    $anotherSocials = "";
                }

                // Если кол-во найденных профилей = 1
                if ($counter == 1) {
                    if ($prof['userPhoto'] == "") {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }else{
                        $response = [
                            'chat_id' => $user,
                            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ])
                        ];
                                
                        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                        curl_setopt($ch, CURLOPT_POST, 1);  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_exec($ch);
                        curl_close($ch);
                        return;
                    }
                }else{
                    if (empty($prof['userPhoto'])) {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }else{
                        $response = [
                            'chat_id' => $user,
                            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ])
                        ];
                                
                        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                        curl_setopt($ch, CURLOPT_POST, 1);  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_exec($ch);
                        curl_close($ch);
                        return;
                    }
                }
            }
        }
    }

    else if (strpos($data['callback_query']['data'], 'int') !== false) {
        // Поиск в БД такого интереса
        $user = $func['from']['id'];
        $intsCheck = mysqli_query ($con, "SELECT `interest1`,`interest2`,`interest3`,`interest4`,`interest5`,`interest6` FROM `Interests` WHERE userID = ".$user." ");
        $ints = mysqli_fetch_row($intsCheck);

        // Удаляем слово int из профессии
        $word = $data['callback_query']['data'];
        $intData = preg_replace("/int/i", "", $word);
        $intQuery = mysqli_query($con,"SELECT `name` FROM `InterestsList` WHERE callbackData= '".$intData."' ");
        $intRow = mysqli_fetch_array($intQuery);
        $int = $intRow['name'];

        $interestCategoriesQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestCategories`");
        $finalButtonsArray   = GenerateButtonsPage($interestCategoriesQuery,'',1,9,' 🔻');

        

        // Если такое хобби у человека уже есть
        if ($ints[0] == $int or $ints[1] == $int or $ints[2] == $int or $ints[3] == $int or $ints[4] == $int or strpos($ints[5], $int) !== false) {
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Упс! Кажется ' . trim($int) . " уже есть у вас в профиле\nВаши интересы:\n".$msgText3."\n\n Вам нужно добавить 5 интересов\nВыберите категорию интереса",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data); 
        }else{
            // Если это будет первый интерес в профиле
            if (empty($ints[0])) {
                // Пушим новый интерес в БД
                $updateDB = mysqli_query ($con, "UPDATE `Interests` SET interest1 = '".$int."' WHERE userID = ".$user." ");

                array_push($finalButtonsArray,array(
                    array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu')
                ));

                // Выводим человеку сообщение об успешности операции и даем возможность добавить еще интересы
                $method = 'editMessageText';
                $send_data = [
                    'text' => "Отлично! Вы добавили ".$int." в список своих интересов\nСейчас ваши интересы выглядят так:\n\u{0031}\u{FE0F}\u{20E3}".$int."\n\n Вам нужно добавить 5 интересов\nВыберите категорию интереса",
                    'reply_markup' => [
                        'inline_keyboard' => $finalButtonsArray
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);  
                
            }
            // Если какие-то интересы в профиле у человека уже были
            else{
                if (empty($ints[1])) {
                    // Пушим новый интерес в БД
                    $updateDB = mysqli_query ($con, "UPDATE `Interests` SET interest2 = '".$int."' WHERE userID = ".$user." ");

                    $intsCheck = mysqli_query ($con, "SELECT `interest1`,`interest2`,`interest3`,`interest4`,`interest5`,`interest6` FROM `Interests` WHERE userID = ".$user." ");
                    $ints = mysqli_fetch_row($intsCheck);
                    $msgText3 = "";
                    // Выводим интересы в правильном виде
                    foreach ($ints as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $arr = explode("," , $value);
                            $msgText3 .= "\n*Дополнительные интересы: *";
                            foreach ($arr as $key => $value1) {
                                $msgText3 .= trim($value1) . "\n";
                            }
                        }
                    }

                    array_push($finalButtonsArray,array(
                        array('text' => '👈 Вернуться назад', 'callback_data' => 'myInterests')
                    ));
                    array_push($finalButtonsArray,array(
                        array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu')
                    ));

                    $method = 'editMessageText';
                    $send_data = [
                        'text' => "Отлично! Вы добавили ".$int." в список своих интересов\nСейчас ваши интересы выглядят так:\n".$msgText3."\n\n Вам нужно добавить 5 интересов\nВыберите категорию интереса",
                        'reply_markup' => [
                            'inline_keyboard' => $finalButtonsArray
                        ]
                    ];
                    $send_data['chat_id'] = $func['message']['chat']['id'];
                    $send_data['message_id'] = $func['message']['message_id'];
                    sendTelegram($method, $send_data);
                    return;
                }else if (empty($ints[2])) {
                    // Пушим новый интерес в БД
                    $updateDB = mysqli_query ($con, "UPDATE `Interests` SET interest3 = '".$int."' WHERE userID = ".$user." ");

                    $intsCheck = mysqli_query ($con, "SELECT `interest1`,`interest2`,`interest3`,`interest4`,`interest5`,`interest6` FROM `Interests` WHERE userID = ".$user." ");
                    $ints = mysqli_fetch_row($intsCheck);
                    $msgText3 = "";
                    // Выводим интересы в правильном виде
                    foreach ($ints as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $arr = explode("," , $value);
                            $msgText3 .= "\n*Дополнительные интересы: *";
                            foreach ($arr as $key => $value1) {
                                $msgText3 .= trim($value1) . "\n";
                            }
                        }
                    }

                    $interestCategoriesQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestCategories`");
                    $finalButtonsArray   = GenerateButtonsPage($interestCategoriesQuery,'',1,9,' 🔻');

                    array_push($finalButtonsArray,array(
                        array('text' => '👈 Вернуться в "Мои интересы"', 'callback_data' => 'myInterests'),
                        array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu')
                    ));

                    $method = 'editMessageText';
                    $send_data = [
                        'text' => "Отлично! Вы добавили ".$int." в список своих интересов\nСейчас ваши интересы выглядят так:\n".$msgText3."\n\n Вам нужно добавить 5 интересов\nВыберите категорию интереса",
                        'reply_markup' => [
                            'inline_keyboard' => $finalButtonsArray
                        ]
                    ];
                    $send_data['chat_id'] = $func['message']['chat']['id'];
                    $send_data['message_id'] = $func['message']['message_id'];
                    sendTelegram($method, $send_data);
                    return;
                }else if (empty($ints[3])) {
                    // Пушим новый интерес в БД
                    $updateDB = mysqli_query ($con, "UPDATE `Interests` SET interest4 = '".$int."' WHERE userID = ".$user." ");

                    $intsCheck = mysqli_query ($con, "SELECT `interest1`,`interest2`,`interest3`,`interest4`,`interest5`,`interest6` FROM `Interests` WHERE userID = ".$user." ");
                    $ints = mysqli_fetch_row($intsCheck);
                    $msgText3 = "";
                    // Выводим интересы в правильном виде
                    foreach ($ints as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $arr = explode("," , $value);
                            $msgText3 .= "\n*Дополнительные интересы: *";
                            foreach ($arr as $key => $value1) {
                                $msgText3 .= trim($value1) . "\n";
                            }
                        }
                    }

                    $interestCategoriesQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestCategories`");
                    $finalButtonsArray   = GenerateButtonsPage($interestCategoriesQuery,'',1,9,' 🔻');

                    array_push($finalButtonsArray,array(
                        array('text' => '👈 Вернуться в "Мои интересы"', 'callback_data' => 'myInterests'),
                        array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu')
                    ));
                    $method = 'editMessageText';
                    $send_data = [
                        'text' => "Отлично! Вы добавили ".$int." в список своих интересов\nСейчас ваши интересы выглядят так:\n".$msgText3."\n\n Вам нужно добавить 5 интересов\nВыберите категорию интереса",
                        'reply_markup' => [
                            'inline_keyboard' => $finalButtonsArray
                        ]
                    ];
                    $send_data['chat_id'] = $func['message']['chat']['id'];
                    $send_data['message_id'] = $func['message']['message_id'];
                    sendTelegram($method, $send_data);
                    return;
                }else if (empty($ints[4])) {
                    // Пушим новый интерес в БД
                    $updateDB = mysqli_query ($con, "UPDATE `Interests` SET interest5 = '".$int."' WHERE userID = ".$user." ");
                    $intsCheck = mysqli_query ($con, "SELECT `interest1`,`interest2`,`interest3`,`interest4`,`interest5`,`interest6` FROM `Interests` WHERE userID = ".$user." ");
                    $ints = mysqli_fetch_row($intsCheck);
                    $msgText3 = "";
                    // Выводим интересы в правильном виде
                    foreach ($ints as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $arr = explode("," , $value);
                            $msgText3 .= "\n*Дополнительные интересы: *";
                            foreach ($arr as $key => $value1) {
                                $msgText3 .= trim($value1) . "\n";
                            }
                        }
                    }

                    if ($rewards['InterestsReward'] == 0) {
                        // Пушим, что дали награду
                        mysqli_query ($con, "UPDATE `userRewards` SET InterestsReward = 1 WHERE userID = ".$user." ");

                        // Получаем кол-во монет пользователя
                        $selectCoins = mysqli_query ($con, "SELECT `coins` FROM `MainInfo` WHERE userID='".$user."' ");
                        $coins = mysqli_fetch_array($selectCoins);

                        // Плюсуем к монетам награду
                        $coins = $coins['coins'] + 100;

                        // Выдаем монеты
                        mysqli_query ($con, "UPDATE `MainInfo` SET coins = '".$coins."' WHERE userID = ".$user." ");

                        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
                        $send_data['chat_id'] = $user;
                        sendTelegram('deleteMessage', $send_data);

                        $interestsCategories = mysqli_fetch_array($interestCategoriesQuery);
                        $finalButtonsArray   = GenerateButtonsPage($interestCategoriesQuery,'',1,9,' 🔻');
                        
                        array_push($finalButtonsArray,
                        array(array('text' => '👈 Вернуться назад', 'callback_data' => 'myInterests')),
                        array(array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu'))
                        );
                        // Выводим человеку сообщение об успешности операции и даем возможность добавить еще интересы
                        $response = [
                            'chat_id' => $user,
                            'caption' => "_Отлично! Вы добавили_ *".$int."* _в список своих интересов\n\n_*Вы получили 100 монет за добавление 5 интересов*_. Узнать кол-во монет и как их получить, вы можете нажав на кнопку_ *'Монеты'* _в главном меню_\nСейчас ваши интересы выглядят так:\n".$msgText3,
                            "parse_mode" => "Markdown",
                            'photo' => curl_file_create("../tgBot/BotPic/post_330.jpg"),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>$finalButtonsArray
                            ])
                        ];
                                
                        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                        curl_setopt($ch, CURLOPT_POST, 1);  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_exec($ch);
                        curl_close($ch);
                        return;
                    }
                }else{
                    if (empty($ints[5])) {
                        // Пушим новый интерес в БД
                        mysqli_query ($con, "UPDATE `Interests` SET interest6 = '".$int."' WHERE userID = ".$user." ");
                    }else{
                        $pints = $ints[5] . "," . $int;
                        // Пушим новый интерес в БД
                        mysqli_query ($con, "UPDATE `Interests` SET interest6 = '".$pints."' WHERE userID = ".$user." ");
                    }
                    $intsCheck = mysqli_query ($con, "SELECT `interest1`,`interest2`,`interest3`,`interest4`,`interest5`,`interest6` FROM `Interests` WHERE userID = ".$user." ");
                    $ints = mysqli_fetch_row($intsCheck);
                    $msgText3 = "";
                    // Выводим интересы в правильном виде
                    foreach ($ints as $key => $value) {
                        if ($key == 0 and !empty($value)) {
                            $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 1 and !empty($value)) {
                            $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 2 and !empty($value)) {
                            $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 3 and !empty($value)) {
                            $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 4 and !empty($value)) {
                            $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                        }
                        if ($key == 5 and !empty($value)) {
                            $arr = explode("," , $value);
                            $msgText3 .= "\nДополнительные интересы: \n";
                            foreach ($arr as $key => $value1) {
                                $msgText3 .= trim($value1) . "\n";
                            }
                        }
                    }

                    $interestsCategories = mysqli_fetch_array($interestCategoriesQuery);
                    $finalButtonsArray   = GenerateButtonsPage($interestCategoriesQuery,'',1,9,' 🔻');
                        
                    array_push($finalButtonsArray,
                    array(array('text' => '👈 Вернуться назад', 'callback_data' => 'myInterests')),
                    array(array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu'))
                    );

                    $method = 'editMessageText';
                    $send_data = [
                        'text' => "Отлично! Вы добавили ".$int." в список своих интересов\nСейчас ваши интересы выглядят так:\n".$msgText3,
                        'reply_markup' => [
                            'inline_keyboard' => $finalButtonsArray
                        ]
                    ];
                }
            } 
        }
    }

    // Поиск с кем интересно провести время
    else if (strpos($data['callback_query']['data'], 'serch') !== false) {
        // Узнаем сколько у человека навыков введено в профиле
        $user = $func['from']['id'];
        $intsCheck = mysqli_query($con, "SELECT `name`, `surname`, `sex`, `userAge`, `location` FROM `MainInfo` WHERE userID = " . $user . " ");
        $ints = mysqli_fetch_array($intsCheck);

        $interestsCheck = mysqli_query($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5` FROM `Interests` WHERE userID = " . $user . " ");
        $interests = mysqli_fetch_array($interestsCheck);

        $skillsCheck = mysqli_query($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5` FROM `Skills` WHERE userID = " . $user . " ");
        $skills = mysqli_fetch_array($skillsCheck);

        $needsCheck = mysqli_query($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5` FROM `Needs` WHERE userID = " . $user . " ");
        $needs = mysqli_fetch_array($needsCheck);

        $needToComplete = "";

        // Подготавливаем перечень для заполнения пустых ячеек в профиле
        if (empty($interests)) {
            if ($needToComplete == "") {
                $needToComplete .= "интересы";
            } else {
                $needToComplete .= ", интересы";
            }
        }
        if (empty($needs)) {
            if ($needToComplete == "") {
                $needToComplete .= "ценности";
            } else {
                $needToComplete .= ", ценности";
            }
        }
        if (empty($ints['name'])) {
            if ($needToComplete == "") {
                $needToComplete .= "имя";
            } else {
                $needToComplete .= ", имя";
            }
        }
        if (empty($ints['surname'])) {
            if ($needToComplete == "") {
                $needToComplete .= "фамилию";
            } else {
                $needToComplete .= ", фамилию";
            }
        }
        if (empty($skills)) {
            if ($needToComplete == "") {
                $needToComplete .= "навыки";
            } else {
                $needToComplete .= ", навыки";
            }
        }
        if (empty($ints['sex'])) {
            if ($needToComplete == "") {
                $needToComplete .= "пол";
            } else {
                $needToComplete .= ", пол";
            }
        }
        if (empty($ints['userAge'])) {
            if ($needToComplete == "") {
                $needToComplete .= "возраст";
            } else {
                $needToComplete .= ", возраст";
            }
        }
        if(empty($ints['location'])){
            if($needToComplete == ""){
                $needToComplete .= "местоположение";
            }else{
                $needToComplete .= ", местоположение";
            }
        }

        // Узнаем что человек искал
        $word = $data['callback_query']['data'];
        $intData = preg_replace("/serch/i", "", $word);
        $intQuery = mysqli_query($con,"SELECT `name` FROM `InterestsList` WHERE callbackData= '".$intData."' ");
        $intRow = mysqli_fetch_array($intQuery);
        $search = $intRow['name'];


        // Если в профиле хоть что-то не заполнено, тогда даем человеку возможность видеть только новых людей и выводим сообщение с кнопкой ведущей в профиль
        if (empty($needs) or empty($ints['name']) or empty($ints['surname']) or empty($interests) or empty($skills) or empty($ints['sex']) or empty($ints['userAge'] or empty($ints['location']))) {
            $method = 'sendMessage';
            $send_data = [
                'text' => "Мы запомнили ваш поиск и когда будут появляться люди с таким интересом, мы вас оповестим\nЕсли вы хотите искать людей самостоятельно, тогда вам нужно заполнить еще: " . $needToComplete,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Заполнить данные', 'callback_data' => 'profile']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Поиск людей"', 'callback_data' => 'peopleFinder']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{
            // Поиск в БД по запросу
            $skillCheck = mysqli_query ($con, "SELECT `userID` FROM `Interests` WHERE (`interest1` LIKE '%" . $search . "%') OR (`interest2` LIKE '%" . $search . "%') OR (`interest3` LIKE '%" . $search . "%') OR (`interest4` LIKE '%" . $search . "%') OR (`interest5` LIKE '%" . $search . "%') ");
            $skill = mysqli_fetch_array($skillCheck);

            $userNames = "";
            $counter = 0;

            foreach ($skillCheck as $key => $value) {
                mysqli_fetch_array($value);
                foreach ($value as $key => $value) {
                    $userTable = mysqli_query ($con, "SELECT isPrivate FROM MainInfo WHERE userID='".$value."' ");
                    $userData = mysqli_fetch_array($userTable);
                    if ($value != $user && $userData['isPrivate'] == 0) {
                        if ($userNames == "") {
                            $userNames = $value;
                            $counter += 1;
                        }else{
                            $userNames .= "," . $value;
                            $counter += 1;
                        }
                    }                    
                }
            }

            // Удаляем выбор в поиске
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Делаем проверку. Если не нашлось ничего, то выводим сообщение, что никого не нашли, но когда будут появляться люди - мы напишем
            if (empty($userNames)) {
                $response = [
                    'chat_id' => $user,
                    'caption' => "_Мы не нашли людей с интересом_ *".$search."* _,но когда они появятся - вы получите уведомление_",
                    "parse_mode" => "Markdown",
                    'photo' => curl_file_create("../tgBot/BotPic/post_222.png"),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];                 
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }else{
                // Пушим список айдишек в БД
                mysqli_query ($con, "UPDATE `TrackingMenu` SET searchIDs = '".$userNames."' WHERE userID = ".$user." ");

                $ids = explode(',', $userNames);

                // Выводим данные первого человека
                $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userPhoto`, `tgUserName`, `userAge`, `location` FROM `MainInfo` WHERE userID='".$ids[0]."' ");
                $prof = mysqli_fetch_array($profCheck);

                if (!empty($prof['location'])) {
                    $sliceLocation = explode(",",$prof['location']);
                    $location = "\nГород: <b>".trim($sliceLocation[0])."</b>\n";
                }else{
                    $location = "";
                }
        
                if (!empty($prof['userAge'])) {
                    $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
                }

                // Если кол-во найденных профилей = 1
                if ($counter == 1) {
                    if ($prof['userPhoto'] == "") {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$location."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }else{
                        $response = [
                            'chat_id' => $user,
                            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$location."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ])
                        ];
                                
                        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                        curl_setopt($ch, CURLOPT_POST, 1);  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_exec($ch);
                        curl_close($ch);
                        return;
                    }
                }else{
                    if (empty($prof['userPhoto'])) {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$location."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$ids[0]]  
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }else{
                        $response = [
                            'chat_id' => $user,
                            'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$location."\n\n🔎 <i>Профиль</i> <b>1</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                            "parse_mode" => "html",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$ids[0]]
                                    ],
                                    [
                                        ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                                    ]
                                ]
                            ])
                        ];
                                
                        $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  

                        curl_setopt($ch, CURLOPT_POST, 1);  
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_exec($ch);
                        curl_close($ch);
                        return;
                    }
                }
            }
        }
    }
    else if (strpos($data['callback_query']['data'], 'revealUser') !== false) {
        $user = $func['from']['id'];

        //Берем айди искомого человека
        $id = preg_replace("/revealUser/i", "", $data['callback_query']['data']);
        $id = trim($id);

        //Проверяем хватит ли монет на полную информацию аккаунта
        $userCheck = mysqli_query($con,"SELECT `coins` FROM `MainInfo` WHERE userID='".$user."' ");
        $userAccount = mysqli_fetch_array($userCheck);
        if(!empty($userAccount['coins'])){
            $coins = $userAccount['coins'];
        }else{
            $coins = 0;
        }
        //Если не хватает монет
        if($coins < $revealCost){
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
                $send_data = [
                    'text' => "Похоже вам не хватает монет.\nСтоимость поиска: <b>".$revealCost." монет</b>.",
                    "parse_mode" => "html",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Как заработать монеты?', 'callback_data' => 'howToMakeCoins']
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $lastBotMessage['message_id'];
                sendTelegram($method, $send_data);
                return;
        }else{
            $newCoinsAmount = $coins - $revealCost;
            mysqli_query($con,"UPDATE `MainInfo` SET coins =".$newCoinsAmount." WHERE userID='".$user."' ");
        }

        // Достаем из БД id найденных профилей что установить кол-во найденных профилей 
        $profIDs = mysqli_query ($con, "SELECT `searchIDs` FROM `TrackingMenu` WHERE userID='".$user."' ");
        $ids = mysqli_fetch_array($profIDs);

        // Создаем массив из id найденных профилей 
        $allIDs = explode(",", $ids['searchIDs']);

        foreach ($allIDs as $key => $value) {
            if ($value == $id) {
                $num = $key;
                break;
            }
        }
        
        //Для отображения не как индекса массива а как номера в списке анкет
        $num += 1;

        // Кол-во профилей
        $counter = count($allIDs);

        // Выводим данные первого человека
        $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userPhoto`, `tgUserName`, `userAge`, `coins`,`location` FROM `MainInfo` WHERE userID='".$id."' ");
        $intsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$id."' ");
        $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5`, `s6` FROM `Skills` WHERE userID='".$id."' ");
        $needsCheck = mysqli_query ($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5`, `n6` FROM `Needs` WHERE userID='".$id."' ");
        $socialCheck = mysqli_query ($con, "SELECT `inst`, `tiktok`, `viber`, `whatsapp`, `facebook`, `anotherSocials` FROM `Socials` WHERE userID='".$id."' ");

        $prof = mysqli_fetch_array($profCheck);
        $skill = mysqli_fetch_row($skillsCheck);
        $need = mysqli_fetch_row($needsCheck);
        $int = mysqli_fetch_row($intsCheck);
        $socials = mysqli_fetch_array($socialCheck);

        $msgText1 = "";
        $msgText2 = "";
        $msgText3 = "";

        if (!empty($skill[0]) or !empty($skill[1]) or !empty($skill[2]) or !empty($skill[3]) or !empty($skill[4]) or !empty($skill[5])) {
            $msgText1 = "\n🧑‍💻 <i>Навыки:</i> \n";
                // Выводим скиллы в правильном виде
            foreach ($skill as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText1 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText1 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText1 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText1 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText1 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText1 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        if (!empty($need[0]) or !empty($need[1]) or !empty($need[2]) or !empty($need[3]) or !empty($need[4]) or !empty($need[5])) {
            $msgText2 = "\n📝 <i>Ценности:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($need as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText2 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }    
        
        if (!empty($int[0]) or !empty($int[1]) or !empty($int[2]) or !empty($int[3]) or !empty($int[4]) or !empty($int[5])) {
            $msgText3 = "\n🚲 <i>Интересы:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($int as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        $soc = "";

        if (!empty($prof['userAge'])) {
            $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";
        }
        if (!empty($prof['location'])) {
            $sliceLocation = explode(",",$prof['location']);
            $location = "\nГород: <b>".trim($sliceLocation[0])."</b>\n";
        }else{
            $location = "";
        }

        if (!empty($socials['inst'])) {
            $inst = "<b><a href='https://www.instagram.com/".$socials['inst']."/'>Instagram</a></b>";
            if ($soc == "") {
                $soc = "\n" . $inst;
            }else{
                $soc .= ", ".$inst;
            }
        }else{
            $inst = "";
        }

        if (!empty($socials['tiktok'])) {
            $tiktok = "<b><a href='https://www.tiktok.com/@".$socials['tiktok']."/'>Tik-Tok</a></b>";
            if ($soc == "") {
                $soc = "\n" . $tiktok;
            }else{
                $soc .= ", ".$tiktok;
            }
        }else{
            $tiktok = "";
        }

        if (!empty($socials['facebook'])) {
            $facebook = "<b><a href='".$socials['facebook']."'>Facebook</a></b>";
            if ($soc == "") {
                $soc = "\n" . $facebook;
            }else{
                $soc .= ", ".$facebook;
            }
        }else{
            $facebook = "";
        }

        if (!empty($socials['viber'])) {
            $viber = "<b>Viber - ".$socials['viber']."</b>";
            if ($soc == "") {
                $soc = "\n" . $viber;
            }else{
                $soc .= ", ".$viber;
            }
        }else{
            $viber = "";
        }

        if (!empty($socials['whatsapp'])) {
            $whatsapp = "<b><a href='https://wa.me/".$socials['whatsapp']."/'>WhatsApp</a></b>";
            if ($soc == "") {
                $soc = "\n" . $whatsapp;
            }else{
                $soc .= ", ".$whatsapp;
            }
        }else{
            $whatsapp = "";
        }

        if (!empty($socials['anotherSocials'])) {
            $anotherSocials = "<b><a href='".$socials['anotherSocials']."'>Другая</a></b>";
            if ($soc == "") {
                $soc = "\n" . $anotherSocials;
            }else{
                $soc .= ", ".$anotherSocials;
            }
        }else{
            $anotherSocials = "";
        }

        
        // Если кол-во найденных профилей = 1
        if ($counter == 1) {
            if ($prof['userPhoto'] == "") {
                $method = 'editMessageText';
                $send_data = [
                    'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc.$location."\n\n🔎 <i>Профиль</i> <b>".$num."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$id]
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $lastBotMessage['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                $response = [
                    'chat_id' => $user,
                    'message_id' => $lastBotMessage['message_id'],
                    'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc.$location."\n\n🔎 <i>Профиль</i> <b>".$num."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$id]
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];
                        
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/editMessageCaption');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }
        }else if($num == 1 && $counter > 1){
            if ($prof['userPhoto'] == "") {
                $method = 'editMessageText';
                $send_data = [
                    'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc.$location."\n\n🔎 <i>Профиль</i> <b>".$num."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Следущий профиль', 'callback_data' => 'nextProfile '.$id]
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$id]
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $lastBotMessage['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                $response = [
                    'chat_id' => $user,
                    'message_id' => $lastBotMessage['message_id'],
                    'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc.$location."\n\n🔎 <i>Профиль</i> <b>".$num."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Следущий профиль', 'callback_data' => 'nextProfile '.$id]
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$id]
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];
                        
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/editMessageCaption');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }
        }else if($num == $counter){
            if (empty($prof['userPhoto'])) {
                $method = 'editMessageText';
                $send_data = [
                    'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc.$location."\n\n🔎 <i>Профиль</i> <b>".$num."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id] 
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$id]
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $lastBotMessage['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                $response = [
                    'chat_id' => $user,
                    'message_id' => $lastBotMessage['message_id'],
                    'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc.$location."\n\n🔎 <i>Профиль</i> <b>".$num."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id]
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$id]
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];
                        
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/editMessageCaption');  

                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }
        }else{
            if (empty($prof['userPhoto'])) {
                $method = 'editMessageText';
                $send_data = [
                    'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc.$location."\n\n🔎 <i>Профиль</i> <b>".$num."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id],
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id]  
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$id]
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $lastBotMessage['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                $response = [
                    'chat_id' => $user,
                    'message_id' => $lastBotMessage['message_id'],
                    'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$msgText1.$msgText2.$msgText3.$soc.$location."\n\n🔎 <i>Профиль</i> <b>".$num."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id],
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id] 
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'callback_data' => 'messageTo'.$id]
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];
                        
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/editMessageCaption');  

                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }
        }
    }
    else if (strpos($data['callback_query']['data'], 'prevProfile') !== false) {
        // Достаем id из коллбека
        $prevID = preg_replace("/prevProfile/i", "", $data['callback_query']['data']);
        $prevID = trim($prevID);

        // Достаем из БД id найденных профилей
        $user = $func['from']['id'];
        $profIDs = mysqli_query ($con, "SELECT `searchIDs` FROM `TrackingMenu` WHERE userID='".$user."' ");
        $ids = mysqli_fetch_array($profIDs);

        // Создаем массив из id найденных профилей 
        $allIDs = explode(",", $ids['searchIDs']);

        // Кол-во профилей
        $counter = count($allIDs);

        foreach ($allIDs as $key => $value) {
            if ($value == $prevID) {
                $num = $key - 1;
                break;
            }
        }

        $id = $allIDs[$num];
        $num += 1;

        // Удаляем старое сообщение
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Подключаемся к БД и берем данные новой id
        $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto`, `tgUserName`, `location` FROM `MainInfo` WHERE userID='".$id."' ");
        $prof = mysqli_fetch_array($profCheck);

        if (!empty($skill[0]) or !empty($skill[1]) or !empty($skill[2]) or !empty($skill[3]) or !empty($skill[4]) or !empty($skill[5])) {
            $msgText1 = "\n🧑‍💻 <i>Навыки:</i> \n";
                // Выводим скиллы в правильном виде
            foreach ($skill as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText1 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText1 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText1 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText1 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText1 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText1 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        if (!empty($need[0]) or !empty($need[1]) or !empty($need[2]) or !empty($need[3]) or !empty($need[4]) or !empty($need[5])) {
            $msgText2 = "\n📝 <i>Ценности:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($need as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText2 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }    
        
        if (!empty($int[0]) or !empty($int[1]) or !empty($int[2]) or !empty($int[3]) or !empty($int[4]) or !empty($int[5])) {
            $msgText3 = "\n🚲 <i>Интересы:</i> \n";
                // Выводим ценности в правильном виде
            foreach ($int as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3} <b>" . trim($value) . "</b>\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "<b>" . trim($value) . "</b>\n";
                }
            }
        }

        if (!empty($prof['location'])) {
            $sliceLocation = explode(",",$prof['location']);
            $location = "\nГород: <b>".trim($sliceLocation[0])."</b>\n";
        }else{
            $location = "";
        }

        $age = "\n<i>Возраст:</i> <b>".$prof['userAge']."</b>\n";

        // Если это первый профиль в списке, то не даем возможности листать назад
        
        if ($num == 1) {
            // Проверяем наличие фото в профиле
            if (empty($prof['userPhoto'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$location."\n\n🔎 <i>Профиль</i> <b>". $num ."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id] 
                            ],
                            [
                                ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$id]
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                $response = [
                    'chat_id' => $user,
                    'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$location."\n\n🔎 <i>Профиль</i> <b>". $num ."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id]
                            ],
                            [
                                ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$id]  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];
                        
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }
        }else{
            // Проверяем наличие фото в профиле
            if (empty($prof['userPhoto'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$location."\n\n🔎 <i>Профиль</i> <b>". $num ."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id],
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id]  
                            ],
                            [
                                ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$id] 
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                $response = [
                    'chat_id' => $user,
                    'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age.$location."\n\n🔎 <i>Профиль</i> <b>". $num ."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id],
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id]  
                            ],
                            [
                                ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$id]  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];
                        
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }
        }
    }
    else if (strpos($data['callback_query']['data'], 'nextProfile') !== false) {
        // Достаем id из коллбека
        $prevID = preg_replace("/nextProfile/i", "", $data['callback_query']['data']);
        $prevID = trim($prevID);

        // Достаем из БД id найденных профилей
        $user = $func['from']['id'];
        $profIDs = mysqli_query ($con, "SELECT `searchIDs` FROM `TrackingMenu` WHERE userID='".$user."' ");
        $ids = mysqli_fetch_array($profIDs);

        // Создаем массив из id найденных профилей 
        $allIDs = explode(",", $ids['searchIDs']);

        // Кол-во профилей
        $counter = count($allIDs);

        foreach ($allIDs as $key => $value) {
            if ($value == $prevID) {
                $num = $key + 1;
                break;
            }
        }

        $id = $allIDs[$num];
        $num += 1;

        // Удаляем старое сообщение
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Подключаемся к БД и берем данные новой id
        $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto`, `tgUserName` FROM `MainInfo` WHERE userID='".$id."' ");
        $prof = mysqli_fetch_array($profCheck);

        // Если это последний профиль в списке, то не даем возможности листать дальше
        if ($num == $counter) {
            // Проверяем наличие фото в профиле
            if (empty($prof['userPhoto'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age."\n\n🔎 <i>Профиль</i> <b>". $num ."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id] 
                            ],
                            [
                                ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$id]
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                $response = [
                    'chat_id' => $user,
                    'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age."\n\n🔎 <i>Профиль</i> <b>". $num ."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id] 
                            ],
                            [
                                ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$id]  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];
                        
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }
        }else{
            // Проверяем наличие фото в профиле
            if (empty($prof['userPhoto'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age."\n\n🔎 <i>Профиль</i> <b>". $num ."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id],
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id]  
                            ],
                            [
                                ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$id] 
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                return;
            }else{
                $response = [
                    'chat_id' => $user,
                    'caption' => "<b>".$prof['name']." ".$prof['surname']."</b> ".$age."\n\n🔎 <i>Профиль</i> <b>". $num ."</b>" . " <i>из</i> " . "<b>" . $counter . "</b>",
                    "parse_mode" => "html",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id],
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id]  
                            ],
                            [
                                ['text' => 'Подробнее('.$revealCost.' монет)', 'callback_data' => 'revealUser '.$id] 
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                            ]
                        ]
                    ])
                ];
                        
                $ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendPhoto');  
                curl_setopt($ch, CURLOPT_POST, 1);  
                curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_exec($ch);
                curl_close($ch);
                return;
            }
        }   
    }
    else if (strpos($data['callback_query']['data'], 'SexOnReg') !== false) {
    
        $user = $func['from']['id'];

        // Достаем из колбека пол
        $sex = preg_replace("/SexOnReg/i", "", $data['callback_query']['data']);

        // Пушим пол в БД
        mysqli_query ($con, "UPDATE `MainInfo` SET sex = '".$sex."' WHERE userID=".$user." ");

        // Вывод ценностей пользователя
        $userNeeds = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID='".$user."' ");
        $needs = mysqli_fetch_row($userNeeds);

        $msgArray = "";
        
        if (empty($needs)) {
            $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList`");
            $finalButtonsArray = GenerateButtonsPage($needsQuery,' fourthch',1,99);
            array_push($finalButtonsArray,
                array(array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'))
            );
            $user = $func['from']['id']; 
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Просмотрите все ценности и найдите самую важную для вас!\n\nВыберите 5 ценностей начиная с самой важной:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
        }else{
            // Перебираем массив с ценностями для правильного вывода
            foreach ($needs as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgArray .= "\u{0031}\u{FE0F}\u{20E3}" . " - " . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgArray .= "\u{0032}\u{FE0F}\u{20E3}" . " - " . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgArray .= "\u{0033}\u{FE0F}\u{20E3}" . " - " . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgArray .= "\u{0034}\u{FE0F}\u{20E3}" . " - " . trim($value) . "\n";
                } 
                if ($key == 4 and !empty($value)) {
                    $msgArray .= "\u{0035}\u{FE0F}\u{20E3}" . " - " . trim($value) . "\n";
                }  
            }

            $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList`");

            $userNeedsQuery = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID='".$user."' ");
            $userNeedsAssoc = mysqli_fetch_array($userNeedsQuery);

            $finalButtonsArray = GenerateButtonsPageWithExeptions($needsQuery," fourthch",$userNeedsAssoc,"n");

            if(empty($finalButtonsArray)){
                $buttonText = "Вы добавили все ценности.";
            }else{
                $buttonText = "Просмотрите все ценности и найдите самую важную для вас!\n\nСейчас твой список выглядит так:\n" . $msgArray . "\nВыберите 5 ценностей начиная с самой важной:";
            }
            
            array_push($finalButtonsArray,
                array(array('text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'))
            );

            $user = $func['from']['id']; 
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);   
        }
        return;
    }
    // Вот тут уже просто работа с кнопками меню
    else{
        switch($data['callback_query']['data']) {

        case 'instSocial':
            $user = $func['from']['id'];
            $profCheck = mysqli_query ($con, "SELECT `inst` FROM `Socials` WHERE userID='".$user."' ");
            $prof = mysqli_fetch_array($profCheck);

            // Удаляем сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            if (empty($prof['inst'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Добавить Instagram:',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Добавить', 'callback_data' => 'Добавить инсту']  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                                ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                            ]
                        ]
                    ]
                ];
            }else{
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Изменить мой instagram ' . $prof['inst'],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Изменить', 'callback_data' => 'Добавить инсту']  
                            ],
                            [
                                ['text' => 'Удалить', 'callback_data' => 'Удалить инсту']  
                            ],
                            [
                                ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                                ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                            ]
                        ]
                    ]
                ];
            }
            break;

        case 'Удалить инсту':
            $user = $func['from']['id'];
            $updateDB = mysqli_query ($con, "UPDATE `Socials` SET inst = '' WHERE userID = ".$user." ");

            // Удаляем сообщение с профилем
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Вы успешно удалили свой instagram из профиля',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить', 'callback_data' => 'Добавить инсту']
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                            ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                        ]
                    ]
                ]
            ];
            break;

        case 'Удалить tiktok':
            $user = $func['from']['id'];
            $updateDB = mysqli_query ($con, "UPDATE `Socials` SET tiktok = '' WHERE userID = ".$user." ");
            // Удаляем сообщение с профилем
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Вы успешно удалили свой tiktok из профиля',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить', 'callback_data' => 'Добавить tiktok']
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                            ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                        ]
                    ]
                ]
            ];
            break;

        case 'Удалить facebook':
            $user = $func['from']['id'];
            $updateDB = mysqli_query ($con, "UPDATE `Socials` SET facebook = '' WHERE userID = ".$user." ");
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Вы успешно удалили свой facebook из профиля',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить', 'callback_data' => 'Добавить facebook']
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                            ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                        ]
                    ]
                ]
            ];
            break;

        case 'Удалить viber':
            $user = $func['from']['id'];
            $updateDB = mysqli_query ($con, "UPDATE `Socials` SET viber = '' WHERE userID = ".$user." ");
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Вы успешно удалили свой viber из профиля',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить', 'callback_data' => 'Добавить viber']
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                            ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                        ]
                    ]
                ]
            ];
            break;

        case 'Удалить whatsapp':
            $user = $func['from']['id'];
            $updateDB = mysqli_query ($con, "UPDATE `Socials` SET whatsapp = '' WHERE userID = ".$user." ");
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Вы успешно удалили свой whatsapp из профиля',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить', 'callback_data' => 'Добавить whatsapp']
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                            ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                        ]
                    ]
                ]
            ];
            break;

        case 'Удалить anotherSocial':
            $user = $func['from']['id'];
            $updateDB = mysqli_query ($con, "UPDATE `Socials` SET anotherSocials = '' WHERE userID = ".$user." ");
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Вы успешно удалили свой anotherSocial из профиля',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить', 'callback_data' => 'Добавить anotherSocial']
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu'],
                            ['text' => '👈 Вернуться к моим соцсетям', 'callback_data' => 'mySocial']
                        ]
                    ]
                ]
            ];
            break;

        case 'sportInts серч':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName='sportInts' ");
            $finalButtonsArray = GenerateButtonsPage($intsQuery,' serch',1,99);

            array_push($finalButtonsArray,
            array(
                array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'enterestsFinder')
            ));
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "🔎🚲 *С кем интересно провести время*\n\n_Выберите интерес для поиска нужного человека_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'funInts серч':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $funIntsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName='funInts' ");
            $finalButtonsArray = GenerateButtonsPage($funIntsQuery,' serch',1,9);
            array_push($finalButtonsArray,
            array(
                array('text' => 'Следующая страница 👉', 'callback_data' => 'funInts2 серч')
            ));
            array_push($finalButtonsArray,
            array(
                array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'enterestsFinder')
            ));
            $method = 'sendMessage';
            $send_data = [
                'text' => "🔎🚲 *С кем интересно провести время*\n\n_Выберите интерес для поиска нужного человека_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'funInts2 серч':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $funIntsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName='funInts' ");
            $finalButtonsArray = GenerateButtonsPage($funIntsQuery,' serch',2,9);
            array_push($finalButtonsArray,
            array(
                array('text' => '👈 Прошлая страница', 'callback_data' => 'funInts серч')
            ));
            array_push($finalButtonsArray,
            array(
                array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'enterestsFinder')
            ));

            $method = 'sendMessage';
            $send_data = [
                'text' => "🔎🚲 *С кем интересно провести время*\n\n_Выберите интерес для поиска нужного человека_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'businessInts серч':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName='businessInts' ");
            $finalButtonsArray = GenerateButtonsPage($intsQuery,' serch',1,99);
            array_push($finalButtonsArray,
            array(
                array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'enterestsFinder')
            ));

            $method = 'sendMessage';
            $send_data = [
                'text' => "🔎🚲 *С кем интересно провести время*\n\n_Выберите интерес для поиска нужного человека_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'premiumFinder':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Эта функция пока тестируется, для более точного и быстрого поиска.',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]
                    ]
                ]
            ];
            break;

        /*case "mySubscription":
            $method = 'editMessageText';
            $send_data = [
                'text' => '👑 Моя подписка:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '💰 Оплатить подписку', 'callback_data' => 'buySubscription']  
                        ],
                        [
                            ['text' => '🧾 Информация о подписке', 'callback_data' => 'aboutSubscription']
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]
                    ]
                ]
            ];
            break;*/

        case 'stat':
            $method = 'editMessageText';
            $send_data = [
                'text' => "📊 *Статистика:*",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Топ 10', 'callback_data' => 'top10']
                        ],
                        [
                            ['text' => 'Топ 20', 'callback_data' => 'top20']
                        ],
                        [
                            ['text' => 'Топ 100', 'callback_data' => 'top100']
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]
                    ]
                ]
            ];
            break;

        case 'mainMenu':
            // Выводим человека из всех меню
            $user = $func['from']['id'];
            mysqli_query($con, "UPDATE `TrackingMenu` SET whichMenu = '' WHERE userID = '".$user."' ");

            // Удаляем сообщение с профилем
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "📋 *Главное меню:*",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '😁 Мой профиль', 'callback_data' => 'profile']  
                        ],
                        [
                            ['text' => '🔎 Поиск людей', 'callback_data' => 'peopleFinder']
                        ],
                        /*[
                            ['text' => '👑 Моя подписка', 'callback_data' => 'mySubscription']
                        ],*/
                        [
                            ['text' => '💰 Монеты', 'callback_data' => 'myCoins']
                        ],
                        [
                            ['text' => '📈 Моя статистика', 'callback_data' => 'myStats']  
                        ],
                        /*[
                            ['text' => '📊 Топ пользователей', 'callback_data' => 'stat']
                        ],*/
                        [
                            ['text' => '🗣️ Сообщить об идее/ошибке', 'callback_data' => 'feedback']
                        ],
                        [
                            ['text' => 'FAQ', 'callback_data' => 'faq']
                        ]
                    ]
                ]
            ];
            break;

        case 'faq':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "📋 *FAQ:*",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Вопросы и Ответы', 'callback_data' => 'textFAQ']  
                        ],
                        /*[
                            ['text' => 'Видео-презентация', 'callback_data' => 'videoFAQ']
                        ],*/
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]
                    ]
                ]
            ];
            break;

        case 'textFAQ':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Вопросы:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Какая ценность ваших монет?', 'callback_data' => 'q1']  
                        ],
                        [
                            ['text' => 'По каким параметрам мне подберут друга?', 'callback_data' => 'q2']  
                        ],
                        [
                            ['text' => 'Как узнать что специалист добросовестный?', 'callback_data' => 'q3']  
                        ],
                        [
                            ['text' => 'Что такое соционика?', 'callback_data' => 'q4']  
                        ],
                        [
                            ['text' => 'Для чего разделение по психо-типам?', 'callback_data' => 'q5']  
                        ],
                        [
                            ['text' => 'Как поможет соционика найти нужного человека?', 'callback_data' => 'q6']  
                        ],
                        [
                            ['text' => 'Как использовать приложение для поиска специалиста?', 'callback_data' => 'q7']  
                        ],
                        [
                            ['text' => 'Зачем заполнять профиль, добавлять свои навыки, хобби и ценности?', 'callback_data' => 'q8']  
                        ],
                        [
                            ['text' => 'Какие еще есть функции помимо поиска людей?', 'callback_data' => 'q9']  
                        ],
                        /*[
                            ['text' => '👈 Вернуться в "FAQ"', 'callback_data' => 'faq']  
                        ],*/
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]
                    ]
                ]
            ];
            break;

        case 'myAffiliate':
            $user = $func['from']['id']; 
            $inviteCheck = mysqli_query ($con, "SELECT `inviteLink` FROM `MainInfo` WHERE userID='".$user."' ");
            $invite = mysqli_fetch_array($inviteCheck);
            
            // Удаляем сообщение с профилем
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Приглашай друзей в наше SMART сообщество!\nЗа каждого приглашенного участника, ты получаешь 1000 монет, за которые можно покупать супер-функции или в будущем обменять на криптовалюту!\n\nТвоя ссылка для приглашения: " . $invite[0],
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']  
                        ]
                    ]
                ]
            ];
            break;

        case 'myNeeds':
            $user = $func['from']['id']; 
            $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID='".$user."' ");
            $needs = mysqli_fetch_row($needsCheck);

            $needsArrTo6 = array();
            $msgText2 = "";
            $btnsArray = array();
            array_push($btnsArray, array(array('text' => '➕ Добавить ценности', 'callback_data' => 'pushNeeds')));
            // Выводим ценности в правильном виде
            foreach ($needs as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $arr = explode(",",$value);
                    $msgText2 .= "\n*Дополнительные ценности:* \n" ;
                    foreach($arr as $key => $value1){
                        $msgText2 .= trim($value1) . "\n";
                        array_push($needsArrTo6, $value1);
                    }
                }
            }

            foreach ($needs as $key => $value) {
                if (!empty($value) and $key < 5) {

                    $needQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList` WHERE name = '".$value."'");
                    $need = mysqli_fetch_array($needQuery);

                    array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value), 'callback_data' => $need['callbackData']." 4333")));
                }else {
                    if(!empty($value)){
                        foreach($needsArrTo6 as $key => $value1){

                            $needQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList` WHERE name = '".$value1."'");
                            $need = mysqli_fetch_array($needQuery);

                            array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value1), 'callback_data' => $need['callbackData']." 4333")));
                        }
                    }
                }
            }
            
            // Удаляем сообщение с профилем
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            if (empty($msgText2)) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "📝 *Мои ценности*\n\n_Сейчас у вас ничего не указано_",
                        "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '➕ Добавить ценности', 'callback_data' => 'pushNeeds']  
                            ],
                            [
                                ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']  
                            ]
                        ]
                    ]
                ];
            }else{
                array_push($btnsArray, array(array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')));

                $method = 'sendMessage';
                $send_data = [
                    'text' => "📝 *Мои ценности*\n\n_Сейчас у вас указано:_ \n".$msgText2,
                        "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => $btnsArray
                    ]
                ];
            }          
        break;

        case 'pushNeeds':
            $user = $func['from']['id']; 
            $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID='".$user."' ");
            $userNeedsAssoc = mysqli_fetch_array($needsCheck);

            $msgText2 = "";
            // Выводим ценности в правильном виде

            if (!empty($userNeedsAssoc["n1"])) {
                $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($userNeedsAssoc["n1"]) . "\n";
            }
            if (!empty($userNeedsAssoc["n2"])) {
                $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($userNeedsAssoc["n2"]) . "\n";
            }
            if (!empty($userNeedsAssoc["n3"])) {
                $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($userNeedsAssoc["n3"]) . "\n";
            }
            if (!empty($userNeedsAssoc["n4"])) {
                $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($userNeedsAssoc["n4"]) . "\n";
            }
            if (!empty($userNeedsAssoc["n5"])) {
                $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($userNeedsAssoc["n5"]) . "\n";
            }
            if (!empty($userNeedsAssoc["n6"])) {
                $msgText2 .= "\n*Дополнительные ценности:* \n" . trim($userNeedsAssoc["n6"]) . "\n";
            }

            if (empty($msgText2)) {
                $buttonText = "📝 *Мои ценности*\n\n_Просмотри все ценности и найди самую важную для тебя!\nВыбери ценности начиная с самой важной:_";
            }else{
                $buttonText = "📝 *Мои ценности*\n\n_Сейчас у вас указано:_ \n".$msgText2."\n_Просмотри все ценности и найди самую важную для тебя!\nВыбери ценности начиная с самой важной:_";
            }

            $needsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `NeedsList`");

            $finalButtonsArray = GenerateButtonsPageWithExeptions($needsQuery," SexSer3ch",$userNeedsAssoc,"n");

            if(empty($finalButtonsArray)){
                $buttonText = "Вы добавили все ценности!";
            }
            
            array_push($finalButtonsArray,
            array(array('text' => '👈 Вернуться в профиль', 'callback_data' => 'profile')));

            $method = 'editMessageText';
                $send_data = [
                    'text' => $buttonText,
                    "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => $finalButtonsArray
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
            break;

        case 'myInterests':
            $user = $func['from']['id']; 
            $interestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
            $interests = mysqli_fetch_row($interestsCheck);

            $interestsArrTo6 = array();
            $msgText3 = "";
            $btnsArray = array();
            array_push($btnsArray, array(array('text' => '➕ Добавить интересы', 'callback_data' => 'pushInterests')));
            // Выводим ценности в правильном виде
            foreach ($interests as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $arr = explode("," , $value);
                    $msgText3 .= "\n*Дополнительные интересы:*\n";
                    foreach ($arr as $key => $value1) {
                        $msgText3 .= trim($value1) . "\n";
                        array_push($interestsArrTo6, $value1);
                    }
                }
            }

            foreach ($interests as $key => $value) {
                if (!empty($value) and $key < 5) {
                    $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE name = '".$value."'");
                    $interest = mysqli_fetch_array($intsQuery);
                    array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value), 'callback_data' => $interest['callbackData']." 2333")));
                }else {
                    if(!empty($value)){
                        foreach ($interestsArrTo6 as $key => $value1) {
                            $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE name = '".$value1."'");
                            $interest = mysqli_fetch_array($intsQuery);
                            array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value1), 'callback_data' => $interest['callbackData']." 2333")));
                        }
                    }
                }
            }

            // Удаляем сообщение с меню
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            if (empty($interests)) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "🚲 *Мои интересы:*",
                    'parse_mode' => 'markdown',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '➕ Добавить интересы', 'callback_data' => 'pushInterests']  
                            ],
                            [
                                ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']  
                            ]
                        ]
                    ]
                ];
            }else {
                array_push($btnsArray, array(array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')));
                $method = 'sendMessage';
                $send_data = [
                    'text' => "🚲 *Мои интересы:*\n\n" . $msgText3,
                    'parse_mode' => 'markdown',
                    'reply_markup' => [
                        'inline_keyboard' => $btnsArray
                    ]
                ];
            }   
        break;

        case 'pushInterests':
            $user = $func['from']['id']; 
            $interestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
            $interests = mysqli_fetch_row($interestsCheck);

            $msgText3 = "";
            // Выводим ценности в правильном виде
            foreach ($interests as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "\nДополнительные интересы:\n" . trim($value) . "\n";
                }
            }

            $interestCategoriesQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestCategories`");
            $interestsCategories = mysqli_fetch_array($interestCategoriesQuery);
            $finalButtonsArray   = GenerateButtonsPage($interestCategoriesQuery,'',1,9,' 🔻');

            array_push($finalButtonsArray,array(
                array('text' => '👈 Вернуться в "Мои интересы"', 'callback_data' => 'myInterests'),
                array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu')
            ));
            
            $method = 'editMessageText';
            $send_data = [
                'text' => "У вас указаны такие интересы: \n" . $msgText3 . "\nВыбери категорию:" ,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'funInts':
           $user = $func['from']['id']; 
            $interestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
            $interests = mysqli_fetch_row($interestsCheck);
            
            $msgText3 = "";
            // Выводим ценности в правильном виде
            foreach ($interests as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "\nДополнительные интересы:\n" . trim($value) . "\n";
                }
            }
            
            $funIntsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'funInts'");
            $funInts      = mysqli_fetch_array($funIntsQuery);
            $pageNum = 1;
            $intsPerPage = 9;

            $userInterestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
            $userInterests      = mysqli_fetch_array($userInterestsCheck);

            $finalButtonsArray = GenerateButtonsPageWithExeptions($funIntsQuery," int",$userInterests,"interest");
            //Выбираем только 1 страницу
            $finalButtonsArray = array_slice($finalButtonsArray,($pageNum-1) * $intsPerPage,9);

            if(empty($finalButtonsArray)){
                $buttonText = $emptyIntsCategoryButtonText;
            }else{
                $buttonText = "У вас указаны такие интересы: \n" . $msgText3 . "\nВыбери интерес:";

                array_push($finalButtonsArray,array(
                    array('text' => 'Следующая страница 👉', 'callback_data' => 'funInts2')
                ));
            }

            array_push($finalButtonsArray,array(
                array('text' => '👈 Вернуться назад', 'callback_data' => 'pushInterests'),
                array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu')
            ));
            
            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText ,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'funInts2':
            $user = $func['from']['id']; 
            $interestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
            $interests = mysqli_fetch_row($interestsCheck);

            $msgText3 = "";
            // Выводим интересы в правильном виде
            foreach ($interests as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "\nДополнительные интересы:\n" . trim($value) . "\n";
                }
            }

            $funIntsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'funInts'");
            $funInts      = mysqli_fetch_array($funIntsQuery);

            $userInterestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
            $userInterests      = mysqli_fetch_array($userInterestsCheck);
            
            $pageNum = 2;
            $intsPerPage = 9;
            $finalButtonsArray = GenerateButtonsPageWithExeptions($funIntsQuery," int",$userInterests,"interest");
            //Выбираем только 2 страницу
            $finalButtonsArray = array_slice($finalButtonsArray,($pageNum-1) * $intsPerPage,9);

            if(empty($finalButtonsArray)){
                $buttonText = $emptyIntsCategoryButtonText;
            }else{
                $buttonText = "У вас указаны такие интересы: \n" . $msgText3 . "\nВыбери интерес:";
            }

            array_push($finalButtonsArray,array(
                array('text' => '👈 Прошлая страница', 'callback_data' => 'funInts')
            ));

            array_push($finalButtonsArray,array(
                array('text' => '👈 Вернуться назад', 'callback_data' => 'pushInterests'),
                array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu')
            ));

            $msgText3 = "";

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText ,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'businessInts':
            $user = $func['from']['id']; 
            $interestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
            $interests = mysqli_fetch_row($interestsCheck);

            $msgText3 = "";
            // Выводим ценности в правильном виде
            foreach ($interests as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "\nДополнительные интересы:\n" . trim($value) . "\n";
                }
            }
            $intsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'businessInts'");
            
            $userInterestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
            $userInterests      = mysqli_fetch_array($userInterestsCheck);

            $finalButtonsArray = GenerateButtonsPageWithExeptions($intsQuery," int",$userInterests,"interest");

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться назад', 'callback_data' => 'pushInterests'),
                    array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu')
                ));

            $method = 'editMessageText';
            $send_data = [
                'text' => "У вас указаны такие интересы: \n" . $msgText3 . "\nВыбери интерес:" ,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'sportInts':
            $user = $func['from']['id']; 
            $interestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
            $interests = mysqli_fetch_row($interestsCheck);

            $msgText3 = "";
            // Выводим ценности в правильном виде
            foreach ($interests as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "\nДополнительные интересы:\n" . trim($value) . "\n";
                }
            }

            $sportIntsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `InterestsList` WHERE categoryName = 'sportInts'");
            $userInterestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
            $userInterests      = mysqli_fetch_array($userInterestsCheck);

            $finalButtonsArray = GenerateButtonsPageWithExeptions($sportIntsQuery," int",$userInterests,"interest");

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться назад', 'callback_data' => 'pushInterests'),
                    array('text' => '👈 Главное меню', 'callback_data' => 'mainMenu')
            ));

            $method = 'editMessageText';
            $send_data = [
                'text' => "У вас указаны такие интересы: \n" . $msgText3 . "\nВыбери интерес:" ,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'НеНашелИнтерес':
            $user = $func['from']['id']; 
            $interestsCheck = mysqli_query ($con, "SELECT `userInterests` FROM `BOT` WHERE userID='".$user."' ");
            $interests = mysqli_fetch_array($interestsCheck);

            $interestsArray = explode("," , $interests['userInterests']);
            $msgText3 = "";
            // Выводим ценности в правильном виде
            foreach ($needsArray as $key => $value) {
                if ($key == 0) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
            }

            $method = 'editMessageText';
            $send_data = [
                'text' => "У вас указаны такие интересы: " . $msgText3 . "\nВы хотите добавить 'свой интерес'?" ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Да', 'callback_data' => 'НеНашелИнтересДа']  
                        ],
                        [
                            ['text' => 'Нет', 'callback_data' => 'myInterests']  
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]

                    ]
                ]
            ];
            break;

        case 'НеНашелИнтересДа':
            // Пушим в каком меню находится человек
            $user = $func['from']['id'];
            $updateDB = mysqli_query ($con, "UPDATE `BOT` SET whichMenu = 'НеНашелИнтересДа' WHERE userID = ".$user." ");

            $method = 'editMessageText';
            $send_data = [
                'text' => "Отправь мне свой интерес и когда будешь готов, нажми кнопку сохранить.\n\nУчитываться будет лишь последнее отправленное сообщение." ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сохранить', 'callback_data' => 'НеНашелИнтересСохранить']  
                        ],
                        [
                            ['text' => 'Отмена', 'callback_data' => 'НеНашелИнтересОтмена']  
                        ]
                    ]
                ]
            ];
            break;

        case 'НеНашелИнтересСохранить':
            $user = $func['from']['id'];

            // Подключаемся к БД и получаем все id сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldInterests`, `userInterests` FROM `BOT` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            if (empty($row['oldInterests'])) {
                // Выводим человеку информацию о Том, что он не заполнил интересы
                $method = 'editMessageText';
                $send_data = [
                    'text' => "Хм... Вы ничего не добавили. Хотите остаться?",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Хочу остаться', 'callback_data' => 'pushInterests']  
                            ],
                            [
                                ['text' => '👈 Нет', 'callback_data' => 'Отменить интересы']  
                            ]
                        ]
                    ]
                ];
            }else{
                // Создаем массив из строк для удаления
                $rowArray = explode(" , ", $row['rowsToDel']);

                // Удаляем все сообщения в чате
                $send_data['chat_id'] = $user;
                foreach ($rowArray as $value) {
                    $send_data['message_id'] = $value;
                    sendTelegram('deleteMessage', $send_data);
                }

                // Удаляем информацию о том, в каком меню человек, а также убираем все id сообщений из БД и старые интересы
                $updateDB = mysqli_query ($con, "UPDATE `BOT` SET rowsToDel = '', whichMenu = '', oldInterests = '' WHERE userID = ".$user." ");

                // Выводим человеку информацию об успешной отправке хобби
                $method = 'editMessageText';
                $send_data = [
                    'text' => "Отлично! Теперь список твоих интересов выглядит так: \n" . $row['userInterests'],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']  
                            ]
                        ]
                    ]
                ];
            }
            break;

        case 'НеНашелИнтересОтмена':
        
            $user = $func['from']['id'];

            // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldInterests` FROM `BOT` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            if (empty($row['oldInterests'])) {
                $updateDB = mysqli_query ($con, "UPDATE `BOT` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");
            }else{
                // Убираем все удаленные сообщения из БД и информацию о том, в каком меню человек
                $updateDB = mysqli_query ($con, "UPDATE `BOT` SET rowsToDel = '', whichMenu = '', oldInterests = '', userInterests = '".$row['oldInterests']."' WHERE userID = ".$user." ");
            }

            // Возращаем человека в меню "Мои интересы"
            $interestsCheck = mysqli_query ($con, "SELECT `userInterests` FROM `BOT` WHERE userID='".$user."' ");
            $interests = mysqli_fetch_array($interestsCheck);
            $method = 'editMessageText';
            $send_data = [
                'text' => "🚲 Мои интересы:\n\n" . $interests['userInterests'] ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить интересы', 'callback_data' => 'pushInterests']  
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']  
                        ]
                    ]
                ]
            ];
            break;

        case 'Женский Sex':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Достаем из колбека пол
            $sex = preg_replace("/Sex/i", "", $data['callback_query']['data']);

            // Пушим пол в БД
            mysqli_query ($con, "UPDATE `MainInfo` SET sex = '".trim($sex)."' WHERE userID=".$user." ");

            // Возвращаем человека в меню "Личные данные"
            $user = $func['from']['id']; 
            $nameCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `sex` FROM `MainInfo` WHERE userID='".$user."' ");
            $name = mysqli_fetch_array($nameCheck);
            $method = 'sendMessage';
            $send_data = [
                'text' => "🤴 *Личные данные*\n\n_Имя:_ *" . $name['name']."*\n_Фамилия:_ *".$name['surname'] . "*\n_Возраст:_ *" . $name['userAge'] . "*\n_Пол:_ *" . $name['sex'] . "*",
                "parse_mode" => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Изменить имя', 'callback_data' => 'changeName']
                        ],
                        [
                            ['text' => 'Изменить фамилию', 'callback_data' => 'changeSurname']
                        ],
                        [
                            ['text' => 'Изменить возраст', 'callback_data' => 'changeAge']
                        ],
                        [
                            ['text' => 'Указать пол', 'callback_data' => 'changeSex']
                        ],
                        [
                            ['text' => 'Добавить фото', 'callback_data' => 'plusPhoto']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'Мужской Sex':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Достаем из колбека пол
            $sex = preg_replace("/Sex/i", "", $data['callback_query']['data']);

            // Пушим пол в БД
            mysqli_query ($con, "UPDATE `MainInfo` SET sex = '".trim($sex)."' WHERE userID=".$user." ");

            // Возвращаем человека в меню "Личные данные"
            $user = $func['from']['id']; 
            $nameCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `sex` FROM `MainInfo` WHERE userID='".$user."' ");
            $name = mysqli_fetch_array($nameCheck);
            $method = 'sendMessage';
            $send_data = [
                'text' => "🤴 *Личные данные*\n\n_Имя:_ *" . $name['name']."*\n_Фамилия:_ *".$name['surname'] . "*\n_Возраст:_ *" . $name['userAge'] . "*\n_Пол:_ *" . $name['sex'] . "*",
                "parse_mode" => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Изменить имя', 'callback_data' => 'changeName']
                        ],
                        [
                            ['text' => 'Изменить фамилию', 'callback_data' => 'changeSurname']
                        ],
                        [
                            ['text' => 'Изменить возраст', 'callback_data' => 'changeAge']
                        ],
                        [
                            ['text' => 'Указать пол', 'callback_data' => 'changeSex']
                        ],
                        [
                            ['text' => 'Добавить фото', 'callback_data' => 'plusPhoto']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'Сохранить возраст':
            $user = $func['from']['id'];

            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $func['from']['id'];
            sendTelegram('deleteMessage', $send_data);

            // Подключаемся к БД и получаем все id сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $checkAge = mysqli_query ($con, "SELECT `userAge` FROM `MainInfo` WHERE userID='".$user."' ");
            $age = mysqli_fetch_array($checkAge);
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            // Удаляем информацию о том, в каком меню человек, а также убираем все id сообщений из БД и старые интересы
            mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldAge = '' WHERE userID = ".$user." ");

            // Возвращаем человека в меню "Личные данные"
            $nameCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `sex` FROM `MainInfo` WHERE userID='".$user."' ");
            $name = mysqli_fetch_array($nameCheck);
            $method = 'sendMessage';
            $send_data = [
                'text' => "🤴 *Личные данные*\n\n_Имя:_ *" . $name['name']."*\n_Фамилия:_ *".$name['surname'] . "*\n_Возраст:_ *" . $name['userAge'] . "*\n_Пол:_ *" . $name['sex'] . "*",
                "parse_mode" => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Изменить имя', 'callback_data' => 'changeName']
                        ],
                        [
                            ['text' => 'Изменить фамилию', 'callback_data' => 'changeSurname']
                        ],
                        [
                            ['text' => 'Изменить возраст', 'callback_data' => 'changeAge']
                        ],
                        [
                            ['text' => 'Указать пол', 'callback_data' => 'changeSex']
                        ],
                        [
                            ['text' => 'Добавить фото', 'callback_data' => 'plusPhoto']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'Отменить возраст':
            $user = $func['from']['id'];

            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $func['from']['id'];
            sendTelegram('deleteMessage', $send_data);

            // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldAge` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            if (empty($row['oldAge'])) {
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");
            }else{
                // Убираем все удаленные сообщения из БД и информацию о том, в каком меню человек
                mysqli_query ($con, "UPDATE `MainInfo` SET userAge = '".$row['oldAge']."' WHERE userID = ".$user." ");
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldAge = '' WHERE userID = ".$user." ");
            }

            // Возвращаем человека в меню "Личные данные"
            $nameCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `sex` FROM `MainInfo` WHERE userID='".$user."' ");
            $name = mysqli_fetch_array($nameCheck);
            $method = 'sendMessage';
            $send_data = [
                'text' => "🤴 *Личные данные*\n\n_Имя:_ *" . $name['name']."*\n_Фамилия:_ *".$name['surname'] . "*\n_Возраст:_ *" . $name['userAge'] . "*\n_Пол:_ *" . $name['sex'] . "*",
                "parse_mode" => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Изменить имя', 'callback_data' => 'changeName']
                        ],
                        [
                            ['text' => 'Изменить фамилию', 'callback_data' => 'changeSurname']
                        ],
                        [
                            ['text' => 'Изменить возраст', 'callback_data' => 'changeAge']
                        ],
                        [
                            ['text' => 'Указать пол', 'callback_data' => 'changeSex']
                        ],
                        [
                            ['text' => 'Добавить фото', 'callback_data' => 'plusPhoto']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'changeSurname':
            // Пушим в каком меню находится человек
            // Удаляем старое сообщение
            $user = $func['from']['id']; 
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'ФамилияИмя' WHERE userID = ".$user." ");

            // Подключаемся к БД и получаем name
            $rowsToDelete = mysqli_query ($con, "SELECT `surname` FROM `MainInfo` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            $method = 'sendMessage';
            $send_data = [
                'text' => "*Отправь мне свою фамилию, а после нажми кнопку 'Сохранить'.*\n\n_! Учитываться будет только последнее отправленное сообщение !\nПример:_ *Шевченко*\n\n_Сейчас у вас указано:_ *" . $row['surname'] ."*" ,
                "parse_mode" => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сохранить', 'callback_data' => 'Сохранить фамилию']  
                        ],
                        [
                            ['text' => 'Отмена', 'callback_data' => 'Отменить фамилию']  
                        ]
                    ]
                ]
            ];
            break;

        case 'Сохранить фамилию':
            // Удаляем старое сообщение
            $user = $func['from']['id']; 
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Подключаемся к БД и получаем все id сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            // Удаляем информацию о том, в каком меню человек, а также убираем все id сообщений из БД и старые интересы
            $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldName = '' WHERE userID = ".$user." ");

            // Возвращаем человека в меню "Личные данные"
            $nameCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `sex` FROM `MainInfo` WHERE userID='".$user."' ");
            $name = mysqli_fetch_array($nameCheck);
            $method = 'sendMessage';
            $send_data = [
                'text' => "🤴 *Личные данные*\n\n_Имя:_ *" . $name['name']."*\n_Фамилия:_ *".$name['surname'] . "*\n_Возраст:_ *" . $name['userAge'] . "*\n_Пол:_ *" . $name['sex'] . "*",
                "parse_mode" => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Изменить имя', 'callback_data' => 'changeName']
                        ],
                        [
                            ['text' => 'Изменить фамилию', 'callback_data' => 'changeSurname']
                        ],
                        [
                            ['text' => 'Изменить возраст', 'callback_data' => 'changeAge']
                        ],
                        [
                            ['text' => 'Указать пол', 'callback_data' => 'changeSex']
                        ],
                        [
                            ['text' => 'Добавить фото', 'callback_data' => 'plusPhoto']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'Отменить фамилию':
            // Удаляем старое сообщение
            $user = $func['from']['id']; 
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldName` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            if (empty($row['oldName'])) {
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");
            }else{
                // Убираем все удаленные сообщения из БД и информацию о том, в каком меню человек
                mysqli_query ($con, "UPDATE `MainInfo` SET surname = '".$row['oldName']."' WHERE userID = ".$user." ");
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldName = '' WHERE userID = ".$user." ");
            }

            // Возвращаем человека в меню "Личные данные"
            $nameCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `sex` FROM `MainInfo` WHERE userID='".$user."' ");
            $name = mysqli_fetch_array($nameCheck);
            $method = 'sendMessage';
            $send_data = [
                'text' => "🤴 *Личные данные*\n\n_Имя:_ *" . $name['name']."*\n_Фамилия:_ *".$name['surname'] . "*\n_Возраст:_ *" . $name['userAge'] . "*\n_Пол:_ *" . $name['sex'] . "*",
                "parse_mode" => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Изменить имя', 'callback_data' => 'changeName']
                        ],
                        [
                            ['text' => 'Изменить фамилию', 'callback_data' => 'changeSurname']
                        ],
                        [
                            ['text' => 'Изменить возраст', 'callback_data' => 'changeAge']
                        ],
                        [
                            ['text' => 'Указать пол', 'callback_data' => 'changeSex']
                        ],
                        [
                            ['text' => 'Добавить фото', 'callback_data' => 'plusPhoto']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'changeName':
            // Пушим в каком меню находится человек
            // Удаляем старое сообщение
            $user = $func['from']['id']; 
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'ИмяФамилия' WHERE userID = ".$user." ");

            // Подключаемся к БД и получаем name
            $rowsToDelete = mysqli_query ($con, "SELECT `name` FROM `MainInfo` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            $method = 'sendMessage';
            $send_data = [
                'text' => "*Отправь мне свое имя, а после нажми кнопку 'Сохранить'.*\n\n_! Учитываться будет только последнее отправленное сообщение !\nПример:_ *Тарас*\n\n_Сейчас у вас указано:_ *" . $row['name'] ."*" ,
                'parse_mode' => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сохранить', 'callback_data' => 'Сохранить Имя']  
                        ],
                        [
                            ['text' => 'Отмена', 'callback_data' => 'Отменить Имя']  
                        ]
                    ]
                ]
            ];
            break;

        case 'Сохранить Имя':
            // Удаляем старое сообщение
            $user = $func['from']['id']; 
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Подключаемся к БД и получаем все id сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            // Удаляем информацию о том, в каком меню человек, а также убираем все id сообщений из БД и старые интересы
            $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldName = '' WHERE userID = ".$user." ");

            // Возвращаем человека в меню "Личные данные"
            $nameCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `sex` FROM `MainInfo` WHERE userID='".$user."' ");
            $name = mysqli_fetch_array($nameCheck);
            $method = 'sendMessage';
            $send_data = [
                'text' => "🤴 *Личные данные*\n\n_Имя:_ *" . $name['name']."*\n_Фамилия:_ *".$name['surname'] . "*\n_Возраст:_ *" . $name['userAge'] . "*\n_Пол:_ *" . $name['sex'] . "*",
                "parse_mode" => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Изменить имя', 'callback_data' => 'changeName']
                        ],
                        [
                            ['text' => 'Изменить фамилию', 'callback_data' => 'changeSurname']
                        ],
                        [
                            ['text' => 'Изменить возраст', 'callback_data' => 'changeAge']
                        ],
                        [
                            ['text' => 'Указать пол', 'callback_data' => 'changeSex']
                        ],
                        [
                            ['text' => 'Добавить фото', 'callback_data' => 'plusPhoto']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'Отменить Имя':
            // Удаляем старое сообщение
            $user = $func['from']['id']; 
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldName` FROM `TrackingMenu` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            if (empty($row['oldName'])) {
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");
            }else{
                // Убираем все удаленные сообщения из БД и информацию о том, в каком меню человек
                mysqli_query ($con, "UPDATE `MainInfo` SET name = '".$row['oldName']."' WHERE userID = ".$user." ");
                mysqli_query ($con, "UPDATE `TrackingMenu` SET rowsToDel = '', whichMenu = '', oldName = '' WHERE userID = ".$user." ");
            }

            // Возвращаем человека в меню "Личные данные"
            $nameCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `sex` FROM `MainInfo` WHERE userID='".$user."' ");
            $name = mysqli_fetch_array($nameCheck);
            $method = 'sendMessage';
            $send_data = [
                'text' => "🤴 *Личные данные*\n\n_Имя:_ *" . $name['name']."*\n_Фамилия:_ *".$name['surname'] . "*\n_Возраст:_ *" . $name['userAge'] . "*\n_Пол:_ *" . $name['sex'] . "*",
                "parse_mode" => 'markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Изменить имя', 'callback_data' => 'changeName']
                        ],
                        [
                            ['text' => 'Изменить фамилию', 'callback_data' => 'changeSurname']
                        ],
                        [
                            ['text' => 'Изменить возраст', 'callback_data' => 'changeAge']
                        ],
                        [
                            ['text' => 'Указать пол', 'callback_data' => 'changeSex']
                        ],
                        [
                            ['text' => 'Добавить фото', 'callback_data' => 'plusPhoto']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'mySkills':
            // Удаляем старое сообщение
            $user = $func['from']['id']; 
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsCheck = mysqli_query ($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5`, `s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);

            $arrTo6 = array();
            $msgText3 = "";
            $btnsArray = array();
            array_push($btnsArray, array(array('text' => '➕ Добавить навыки', 'callback_data' => 'choiceSkills')));
            // Выводим ценности в правильном виде
            foreach ($skills as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}" . trim($value) . "\n";
                }
                if ($key == 5 and !empty($value)) {
                    $skills6 = explode("," , $value);
                    $msgText3 .= "\n*Дополнительные навыки:*\n";
                    foreach ($skills6 as $key => $value1) {
                        $skill6 = explode(")", $value1);
                        $msgText3 .= trim($skill6[1]) . "\n";
                        array_push($arrTo6, $skill6[1]);
                    }
                }
            }

            foreach ($skills as $key => $value) {
                if (!empty($value) and $key < 5) {
                    $skillQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE name = '".$value."'");
                    $skill = mysqli_fetch_array($skillQuery);
                    array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value), 'callback_data' => $skill['callbackData']." 1333")));
                }else{
                    if (!empty($value)) {
                        foreach ($arrTo6 as $key => $value1) {
                            $skillQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE name = '".$value1."'");
                            $skill = mysqli_fetch_array($skillQuery);
                            array_push($btnsArray, array(array('text' => '⚙️ Настройки '.trim($value1), 'callback_data' => $skill['callbackData']." 1333")));
                        }
                    }
                }
            }

            if (empty($skills)) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "🧑‍💻 *Мои навыки*",
                    'parse_mode' => 'markdown',
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '➕ Добавить навыки', 'callback_data' => 'choiceSkills']  
                            ],
                            [
                                ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']  
                            ]
                        ]
                    ]
                ];
            }else {
                array_push($btnsArray, array(array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')));
                $method = 'sendMessage';
                $send_data = [
                    'text' => "🧑‍💻 *Мои навыки*\n\n" . $msgText3,
                    'parse_mode' => 'markdown',
                    'reply_markup' => [
                        'inline_keyboard' => $btnsArray
                    ]
                ];
            }
            break;

        case 'ITSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ITSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);

            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
                
            }
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'administrSkill':
           $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'administrSkill'");

            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);

            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
                
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'designSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'designSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'beautySkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'beautySkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'showbizSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'showbizSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'logistikaSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'logistikaSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'marketingSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'marketingSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'medicinaSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'medicinaSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'nedvizhimostSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'nedvizhimostSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'naukaSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'naukaSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ohranaSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ohranaSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'prodajiSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'prodajiSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'proizvodstvoSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'proizvodstvoSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'torgovlyaSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'torgovlyaSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'sekretaringSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'sekretaringSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'agrobiznesSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'agrobiznesSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'izdatelstvoSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'izdatelstvoSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'strahovanieSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'strahovanieSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'stroitelstvoSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'stroitelstvoSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'crewingSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'crewingSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'obsluzhivanieSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'obsluzhivanieSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'telecomunikaciiSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'telecomunikaciiSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'topmenSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'topmenSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'avtobizSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'avtobizSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'hrSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'hrSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'bankSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'bankSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'yuristSkill':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_array($skillsCheck);
            $msg = "";

            $msg .= $skills["s1"]."\n".
            $skills["s2"]."\n".
            $skills["s3"]."\n".
            $skills["s4"]."\n".
            $skills["s5"]."\n".
            $skills["s6"]."\n";
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'yuristSkill'");
            
            $finalButtonsArray = GenerateButtonsPageWithExeptions($skillsQuery,' add',$skills);
            
            if(empty($finalButtonsArray)){
                $buttonText = $emptySkillCategoryButtonText;
            }else{
                $buttonText = "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:";
            }

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => $buttonText,
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ITSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ITSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'administrSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'administrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'designSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'designSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'beautySkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'beautySkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'showbizSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'showbizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'logistikaSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'logistikaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'marketingSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'marketingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'medicinaSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'medicinaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'nedvizhimostSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'nedvizhimostSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'naukaSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'naukaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ohranaSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ohranaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'prodajiSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'prodajiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'proizvodstvoSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'proizvodstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'torgovlyaSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'torgovlyaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'sekretaringSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'sekretaringSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'agrobiznesSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'agrobiznesSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'izdatelstvoSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'izdatelstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'strahovanieSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'strahovanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'stroitelstvoSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'stroitelstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'crewingSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'crewingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'obsluzhivanieSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'obsluzhivanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'telecomunikaciiSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'telecomunikaciiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'topmenSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'topmenSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'avtobizSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'avtobizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'hrSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'hrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'bankSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'bankSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'yuristSkill ser1ch':
            $user = $func['from']['id']; 
            $skillsCheck = mysqli_query ($con, "SELECT `s1`,`s2`,`s3`,`s4`,`s5`,`s6` FROM `Skills` WHERE userID='".$user."' ");
            $skills = mysqli_fetch_row($skillsCheck);
            $msg = "";
            foreach ($skills as $key => $value) {
                if (!empty($value)) {
                    if ($msg = "") {
                        $msg .= $value;
                    }else{
                        $msg .= ", ".$value;
                    }
                }
            }
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'yuristSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' ser2ch',1,99);
            
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'clientsFinder')
                ),
                array(
                    array('text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile')
                )
            );

            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ITSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ITSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'administrSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'administrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'designSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'designSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'beautySkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'beautySkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'showbizSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'showbizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'logistikaSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'logistikaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'marketingSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'marketingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'medicinaSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'medicinaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'nedvizhimostSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'nedvizhimostSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'naukaSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'naukaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ohranaSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ohranaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;
        case 'prodajiSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'prodajiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'proizvodstvoSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'prozivodstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'torgovlyaSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'torgovlyaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'sekretaringSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'sekretarinSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'agrobiznesSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'agrobiznesSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'izdatelstvoSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'izdatelstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'strahovanieSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'strahovanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'stroitelstvoSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'stroitelstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'crewingSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'crewingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'obsluzhivanieSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'obsluzhivanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'telecomunikaciiSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'telecomunikaciiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'topmenSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'topmenSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'avtobizSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'avtobizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'hrSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'hrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'bankSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'bankSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'yuristSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'yuristSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' s2erch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder')
                )
            );

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ITSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ITSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'administrSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'administrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'designSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'designSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'beautySkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'beautySkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'showbizSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'showbizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'logistikaSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'logistikaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'marketingSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'marketingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'medicinaSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'medicinaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'nedvizhimostSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'nedvizhimostSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'naukaSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'naukaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ohranaSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ohranaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'prodajiSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'prodajiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'proizvodstvoSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'proizvodstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'torgovlyaSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'torgovlyaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'sekretaringSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'sekretaringSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'agrobiznesSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'agrobiznesSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'izdatelstvoSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'izdatelstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'strahovanieSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'strahovanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'crewingSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'crewingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'stroitelstvoSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'stroitelstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'obsluzhivanieSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'obsluzhivanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'telecomunikaciiSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'telecomunikaciiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'topmenSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'topmenSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'avtobizSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'avtobizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'hrSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'hrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'bankSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'bankSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'yuristSkill first':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'yuristSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' firstch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ITSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ITSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'administrSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'administrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'designSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'designSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'beautySkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'beautySkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'showbizSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'showbizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'logistikaSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'logistikaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'marketingSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'marketingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'medicinaSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'medicinaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'nedvizhimostSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'nedvizhimostSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'naukaSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'naukaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ohranaSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ohranaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'prodajiSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'prodajiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'proizvodstvoSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'proizvodstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'torgovlyaSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'torgovlyaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'sekretaringSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'sekretaringSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'agrobiznesSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'agrobiznesSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'izdatelstvoSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'izdatelstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'strahovanieSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'strahovanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'crewingSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'crewingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'stroitelstvoSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'stroitelstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'obsluzhivanieSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'obsluzhivanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'telecomunikaciiSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'telecomunikaciiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'topmenSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'topmenSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'avtobizSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'avtobizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'hrSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'hrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'bankSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'bankSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'yuristSkill second':
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'yuristSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' secondch',1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ITSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ITSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'administrSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'administrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'designSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'designSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'beautySkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'beautySkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'showbizSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'showbizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'logistikaSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'logistikaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'marketingSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'marketingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'medicinaSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'medicinaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'nedvizhimostSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'nedvizhimostSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'naukaSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'naukaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ohranaSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ohranaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'prodajiSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'prodajiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'proizvodstvoSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'proizvodstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'torgovlyaSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'torgovlyaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'sekretaringSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'sekretaringSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'agrobiznesSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'agrobiznesSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'izdatelstvoSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'agrobiznesSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'strahovanieSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'strahovanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'crewingSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'crewingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'stroitelstvoSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'stroitelstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'obsluzhivanieSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'obsluzhivanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'telecomunikaciiSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'telecomunikaciiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'topmenSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'topmenSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'avtobizSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'avtobizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'hrSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'hrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'bankSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'bankSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'yuristSkill third':
            $method = 'sendMessage';
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'yuristSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery,' thirdch',1,99);
            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst')
                    )
            );// Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'choiceSkills':
            $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories` ");
            $finalButtonsArray = GenerateButtonsPage($categoriesArray,'',1,9);
            array_push($finalButtonsArray,
            array(
                array('text' => '👈 Вернуться в "Мои навыки"', 'callback_data' => 'mySkills'),
                array('text' => '2 страница 👉', 'callback_data' => 'choiceSkills2')
            )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери категорию:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'choiceSkills2':
            $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories` ");
            $finalButtonsArray = GenerateButtonsPage($categoriesArray,'',2,9);
            array_push($finalButtonsArray,
            array(
                array('text' => '👈 1 страница', 'callback_data' => 'choiceSkills'),
                array('text' => '3 страница 👉', 'callback_data' => 'choiceSkills3')
            )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери категорию:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'choiceSkills3':
            $categoriesArray = mysqli_query($con,"SELECT `name`, `callbackData` FROM `SkillCategories` ");
            $finalButtonsArray = GenerateButtonsPage($categoriesArray,'',3,9);
            array_push($finalButtonsArray,
            array(
                array('text' => '👈 2 страница', 'callback_data' => 'choiceSkills2')
            )
            );

            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери категорию:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                ]
            ];
            break;

        case 'ITSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ITSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'administrSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'administrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'designSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'designSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'beautySkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'beautySkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'showbizSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'showbizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'logistikaSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'logistikaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'marketingSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'marketingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'medicinaSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'medicinaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'nedvizhimostSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'nedvizhimostSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'naukaSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'naukaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'ohranaSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'ohranaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'prodajiSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'prodajiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'proizvodstvoSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'proizvodstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'torgovlyaSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'torgovlyaSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'sekretaringSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'sekretaringSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;


        case 'agrobiznesSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'agrobiznesSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;


        case 'izdatelstvoSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'izdatelstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'strahovanieSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'strahovanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'crewingSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'crewingSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'stroitelstvoSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'stroitelstvoSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'obsluzhivanieSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'obsluzhivanieSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'telecomunikaciiSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'telecomunikaciiSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'topmenSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'topmenSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'avtobizSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'avtobizSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'hrSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'hrSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'bankSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'bankSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;

        case 'yuristSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $skillsQuery = mysqli_query($con,"SELECT `name`,`callbackData` FROM `SkillsList` WHERE `skillCategory` = 'yuristSkill'");
            $finalButtonsArray = GenerateButtonsPage($skillsQuery," поиск",1,99);

            array_push($finalButtonsArray,
                array(
                    array('text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder')
                    )
                );
                
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => $finalButtonsArray
                        
                ]
            ];
            break;
        case 'shop':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $user = $func['from']['id'];

            $userData = mysqli_fetch_array(mysqli_query ($con, "SELECT `coins` FROM `MainInfo` WHERE userID='".$user."' "));

            if ($userData['coins'] == "") {
                $coins = 0;
            }else{
                $coins = $userData['coins'];
            }

            //Узнаем цену услуги из таблицы в бд
            $shopItemsPrice = mysqli_fetch_array(mysqli_query ($con, "SELECT `price` FROM `ShopItems` WHERE itemName= 'makeAccountPrivate'"));
            $privateAccountPrice = $shopItemsPrice['price'];

            $method = 'sendMessage';

            $send_data = [
                'text' => "🛒 Магазин:\n У вас на счету:".$coins." монет",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Приватный аккаунт: ' . $privateAccountPrice. " монет", 'callback_data' => 'makeAccountPrivate'],
                        ], 
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]                    
                    ]
                ]
            ];
            break;
            
        case 'makeAccountPrivate':
            $user = $func['from']['id'];
            
            $userData = mysqli_fetch_array(mysqli_query ($con, "SELECT `coins`,`isPrivate` FROM `MainInfo` WHERE userID='".$user."' "));
            
            //Узнаем цену услуги из таблицы в бд
            $shopItemsPrice = mysqli_fetch_array(mysqli_query ($con, "SELECT `price` FROM `ShopItems` WHERE itemName= 'makeAccountPrivate'"));
            $privateAccountPrice = $shopItemsPrice['price'];

            if ($userData['coins'] == "") {
                $currentCoins = 0;
            }else{
                $currentCoins = $userData['coins'];
            }
            
            if($userData['isPrivate'] == 1){
                $buyResult = "Ваш аккаунт уже приватный.";
            }else if($privateAccountPrice > $currentCoins){
                $buyResult = "Похоже,вам не хватает монет!";
            }
            else {
                $newCoinsAmount = $currentCoins - $privateAccountPrice;
                mysqli_query($con, "UPDATE `MainInfo` SET `isPrivate` = 1, `coins` = " . $newCoinsAmount . " WHERE userID = '".$user."' ");
                $buyResult = "Теперь, ваш аккаунт приватный!";
            }

            $method = 'editMessageText';
            $send_data = [
                'text' => $buyResult,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '👈 Потратить монеты', 'callback_data' => 'shop'],
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]            
                    ]
                ]
            ];
            break; 

        default:
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Ой! Кажется программист забыл написать для меня команду 😁',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']  
                        ]
                    ]
                ]
            ];
            break;
        }
    }
    $send_data['chat_id'] = $func['message']['chat']['id'];
    $send_data['message_id'] = $func['message']['message_id'];
    sendTelegram($method, $send_data);
}

function sendTelegram($method, $data, $headers = [])
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => 'https://api.telegram.org/bot' . TOKEN . '/' . $method,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array_merge(array("Content-Type: application/json"), $headers)
    ]);   
    
    $result = curl_exec($curl);
    curl_close($curl);
    return (json_decode($result, 1) ? json_decode($result, 1) : $result);
}
function GetLastBotMessage($data_var){
    if($data_var['message']){
        if($data_var['message']['from']['id'] == BOTID){
            return $data_var['message'];
        }   
    }else if($data_var['callback_query']['message']['from']['id'] == BOTID){
        return $data_var['callback_query']['message'];
    }   
    return $lastBotMessage;
}
function GenerateButtonsPage($buttonsQuery,$callbackAdditionalText = '',$buttonsPageToLoad = 1,$maxBtnsPerPage = 9,$additionalButtonText = ''){        
    $finalButtonsArray = array();
    $buttonsPageToLoad = max(0,$buttonsPageToLoad);
    $maxBtnsPerPage = max(0,$maxBtnsPerPage);
    
    foreach($buttonsQuery as $button){
            array_push($finalButtonsArray,
            array(array('text' => $button['name'].$additionalButtonText,'callback_data' => $button['callbackData'].$callbackAdditionalText))
            );
    }
    if($maxBtnsPerPage-1 > count($finalButtonsArray)){
        $finalButtonsArray = array_slice($finalButtonsArray,($buttonsPageToLoad-1) * $maxBtnsPerPage);
    }else{
        $finalButtonsArray = array_slice($finalButtonsArray,($buttonsPageToLoad-1) * $maxBtnsPerPage,$maxBtnsPerPage);
    }

    return $finalButtonsArray;
}
function GenerateButtonsPageWithExeptions($buttonsQuery,$callbackAdditionalText = '',$userSkills,$paramToExcept = "s"){        
    $finalButtonsArray = array();

    foreach($buttonsQuery as $button){
            if($button['name'] != $userSkills[$paramToExcept."1"] &&
            $button['name'] != $userSkills[$paramToExcept."2"] &&
            $button['name'] != $userSkills[$paramToExcept."3"] &&
            $button['name'] != $userSkills[$paramToExcept."4"] &&
            $button['name'] != $userSkills[$paramToExcept."5"] &&
            strpos($userSkills[$paramToExcept."6"],$button['name']) === false
            ){
                array_push($finalButtonsArray,
                array(array('text' => $button['name'],'callback_data' => $button['callbackData'].$callbackAdditionalText))
                );
            }
    }
    return $finalButtonsArray;
}
?>
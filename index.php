<?php
// Принимаем запрос
$data = json_decode(file_get_contents('php://input'), TRUE);
/*file_put_contents('file.txt', '$data: '.print_r($data, 1)."\n", FILE_APPEND);*/

// Обрабатываем ручной ввод или нажатие на кнопку
$func = $data['callback_query'] ? $data['callback_query'] : $data['message'];

// Важные константы
define('TOKEN', '6029265853:AAFd8vC7iBY2RpOcr9w_o89UsPswCH9GZfo');

// Записываем сообщение пользователя
$message = mb_strtolower(($func['text'] ? $func['text'] : $func['data']),'utf-8');

include('bd.php');

// Команда start запускает проверку
if (strpos($message, '/start') !== false) {
    // Проверяем есть ли такой пользователь по id пользователя
    $user = $func['from']['id'];
    $chatCheck = mysqli_query ($con, "SELECT `userID` FROM `MainInfo` WHERE userID='".$user."' ");
    $chatID = mysqli_fetch_array($chatCheck);

    // Удаление сообщения "/start"
    $send_data['message_id'] = $func['message_id'];
    $send_data['chat_id'] = $func['chat']['id'];
    sendTelegram('deleteMessage', $send_data);

    // Если такого пользователя в БД нет, то запрашиваем номер телефона для регистрации
    if (empty($chatID)) {
        $method = 'sendMessage';
        $send_data = [
            'text' => '👋 Приветствуем тебя в нашем SMART пространстве. Выбери кого ты ищешь:',
            'reply_markup' => [
                    'inline_keyboard' => [
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
            ]
        ];

        # Пушим первую инфу пользователя в БД #
        // Создаем реферальную ссылку
        $refURL = 'https://t.me/SMARTSYNCBOT?start=' . $func['from']['id'];

        $tgUserName = "@" . $func['from']['username'];
            // Добавляем пользователя в ОСНОВНУЮ таблицу
        mysqli_query ($con, "INSERT INTO `MainInfo` (`userID`, `tgUserName`, `name`, `surname`, `inviteLink`, `regDate`) VALUES ('".$func['from']['id']."','".$tgUserName."','".$func['from']['first_name']."', '".$func['from']['last_name']."', '".$refURL."', NOW())");
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
    }

    // Если такой пользователь есть в базе, то даем ему доступ в меню
    else{
        // Выводим человека из всех меню
        $user = $func['from']['id'];
        mysqli_query($con, "UPDATE `TrackingMenu` SET whichMenu = '' WHERE userID = '".$user."' ");

        $method = 'sendMessage';
        $send_data = [
            'text' => '👋 Привет! Давно не виделись!',
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
    }
    $send_data['chat_id'] = $func['chat']['id'];
    sendTelegram($method, $send_data);
}

# Первые кнопки #
if ($data['callback_query']['data'] == "FirsTmenu") {
    $method = 'editMessageText';
    $send_data = [
        'text' => '👋 Приветствуем тебя в нашем SMART пространстве. Выбери кого ты ищешь:',
        'reply_markup' => [
                'inline_keyboard' => [
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
        ]
    ];
    $send_data['chat_id'] = $func['message']['chat']['id'];
    $send_data['message_id'] = $func['message']['message_id'];
    sendTelegram($method, $send_data);
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

    if (empty($ui['interest1'])) {
        $method = 'editMessageText';
        $send_data = [
            'text' => "Укажите 5 своих интересов, начиная с самого важного\n\nВыбери категорию:",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Спорт / Активный отдых 🔻', 'callback_data' => '1 Спорт']
                    ],
                    [
                        ['text' => 'Развелчения 🔻', 'callback_data' => '1 Развлечения']
                    ],
                    [
                        ['text' => 'Бизнес 🔻', 'callback_data' => '1 Бизнес']
                    ],
                    [
                        ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu']
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
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
        $method = 'editMessageText';
        $send_data = [
            'text' => "Укажите 5 своих интересов, начиная с самого важного\n\nСейчас у вас указано:\n" . $msgArray . "\nВыбери категорию:" ,
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Спорт / Активный отдых 🔻', 'callback_data' => '1 Спорт']
                    ],
                    [
                        ['text' => 'Развелчения 🔻', 'callback_data' => '1 Развлечения']
                    ],
                    [
                        ['text' => 'Бизнес 🔻', 'callback_data' => '1 Бизнес']
                    ],
                    [
                        ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu']
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
    }
    return;
}

if ($data['callback_query']['data'] == "1 Развлечения") {
    $user = $func['from']['id'];
    // Вывод интересов пользователя
    $interests = mysqli_query ($con, "SELECT * FROM `Interests` WHERE userID='".$user."' ");
    $ui = mysqli_fetch_array($interests);

    // Переменная для вывода в сообщение
    $msgArray = "";

    if (empty($ui['interest1'])) {
        $method = 'editMessageText';
        $send_data = [
            'text' => "Выберите интерес:",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Пранки', 'callback_data' => 'Пранки tni']  
                    ],
                    [
                        ['text' => 'Челенджы', 'callback_data' => 'Челенджы tni']  
                    ],
                    [
                        ['text' => 'Настольные игры', 'callback_data' => 'Настольные игры tni']  
                    ],
                    [
                        ['text' => 'Трансформационные игры', 'callback_data' => 'Трансформационные игры tni']  
                    ],
                    [
                        ['text' => 'Кино', 'callback_data' => 'Кино tni']  
                    ],
                    [
                        ['text' => 'Театр', 'callback_data' => 'Театр tni']  
                    ],
                    [
                        ['text' => 'Бильярд', 'callback_data' => 'Бильярд tni']  
                    ],
                    [
                        ['text' => 'Съемка роликов', 'callback_data' => 'Съемка роликов tni']  
                    ],
                    [
                        ['text' => 'Боулинг', 'callback_data' => 'Боулинг tni']  
                    ],
                    [
                        ['text' => 'Следующая страница 👉', 'callback_data' => '2 Развлечения']  
                    ],
                    [
                        ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst']
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
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
        $method = 'editMessageText';
        $send_data = [
            'text' => "Выберите интерес:\n\nСейчас у вас указано: \n" . $msgArray,
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Пранки', 'callback_data' => 'Пранки tni']  
                    ],
                    [
                        ['text' => 'Челенджы', 'callback_data' => 'Челенджы tni']  
                    ],
                    [
                        ['text' => 'Настольные игры', 'callback_data' => 'Настольные игры tni']  
                    ],
                    [
                        ['text' => 'Трансформационные игры', 'callback_data' => 'Трансформационные игры tni']  
                    ],
                    [
                        ['text' => 'Кино', 'callback_data' => 'Кино tni']  
                    ],
                    [
                        ['text' => 'Театр', 'callback_data' => 'Театр tni']  
                    ],
                    [
                        ['text' => 'Бильярд', 'callback_data' => 'Бильярд tni']  
                    ],
                    [
                        ['text' => 'Съемка роликов', 'callback_data' => 'Съемка роликов tni']  
                    ],
                    [
                        ['text' => 'Боулинг', 'callback_data' => 'Боулинг tni']  
                    ],
                    [
                        ['text' => 'Следующая страница 👉', 'callback_data' => '2 Развлечения']  
                    ],
                    [
                        ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst']
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
    }    
    return;
}
if ($data['callback_query']['data'] == "2 Развлечения") {
    $user = $func['from']['id'];
    // Вывод интересов пользователя
    $interests = mysqli_query ($con, "SELECT * FROM `Interests` WHERE userID='".$user."' ");
    $ui = mysqli_fetch_array($interests);

    // Переменная для вывода в сообщение
    $msgArray = "";

    if (empty($ui['interest1'])) {
        $method = 'editMessageText';
        $send_data = [
            'text' => "Выберите интерес:",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Кафе', 'callback_data' => 'Кафе tni']  
                    ],
                    [
                        ['text' => 'Бар', 'callback_data' => 'Бар tni']  
                    ],
                    [
                        ['text' => 'Ресторан', 'callback_data' => 'Ресторан tni']  
                    ],
                    [
                        ['text' => 'Рисование', 'callback_data' => 'Рисование tni']  
                    ],
                    [
                        ['text' => 'Шитье', 'callback_data' => 'Шитье tni']  
                    ],
                    [
                        ['text' => 'Ганчарство', 'callback_data' => 'Ганчарство tni']  
                    ],
                    [
                        ['text' => '👈 Прошлая страница', 'callback_data' => '1 Развлечения']  
                    ],
                    [
                        ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst']
                    ]

                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
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
        $method = 'editMessageText';
        $send_data = [
            'text' => "Выберите интерес:\n\nСейчас у вас указано: \n" . $msgArray,
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Кафе', 'callback_data' => 'Кафе tni']  
                    ],
                    [
                        ['text' => 'Бар', 'callback_data' => 'Бар tni']  
                    ],
                    [
                        ['text' => 'Ресторан', 'callback_data' => 'Ресторан tni']  
                    ],
                    [
                        ['text' => 'Рисование', 'callback_data' => 'Рисование tni']  
                    ],
                    [
                        ['text' => 'Шитье', 'callback_data' => 'Шитье tni']  
                    ],
                    [
                        ['text' => 'Ганчарство', 'callback_data' => 'Ганчарство tni']  
                    ],
                    [
                        ['text' => '👈 Прошлая страница', 'callback_data' => '1 Развлечения']  
                    ],
                    [
                        ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst']
                    ]

                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
    }    
    return;
}
if ($data['callback_query']['data'] == "1 Бизнес") {
    $user = $func['from']['id'];
    // Вывод интересов пользователя
    $interests = mysqli_query ($con, "SELECT * FROM `Interests` WHERE userID='".$user."' ");
    $ui = mysqli_fetch_array($interests);

    // Переменная для вывода в сообщение
    $msgArray = "";

    if (empty($ui['interest1'])) {
        $method = 'editMessageText';
        $send_data = [
            'text' => "Выберите интерес:",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Нетворкинг', 'callback_data' => 'Нетворкинг tni']  
                    ],
                    [
                        ['text' => 'Мастермайнд', 'callback_data' => 'Мастермайнд tni']  
                    ],
                    [
                        ['text' => 'Форум', 'callback_data' => 'Форум tni']  
                    ],
                    [
                        ['text' => 'Митинг', 'callback_data' => 'Митинг tni']  
                    ],
                    [
                        ['text' => 'Дебаты', 'callback_data' => 'Дебаты tni']  
                    ],
                    [
                        ['text' => 'Тренинг', 'callback_data' => 'Тренинг tni']  
                    ],
                    [
                        ['text' => 'Мастер-класс', 'callback_data' => 'Мастер-класс tni']  
                    ],
                    [
                        ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst']
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
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
        $method = 'editMessageText';
        $send_data = [
            'text' => "Выберите интерес:\n\nСейчас у вас указано: \n" . $msgArray,
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Нетворкинг', 'callback_data' => 'Нетворкинг tni']  
                    ],
                    [
                        ['text' => 'Мастермайнд', 'callback_data' => 'Мастермайнд tni']  
                    ],
                    [
                        ['text' => 'Форум', 'callback_data' => 'Форум tni']  
                    ],
                    [
                        ['text' => 'Митинг', 'callback_data' => 'Митинг tni']  
                    ],
                    [
                        ['text' => 'Дебаты', 'callback_data' => 'Дебаты tni']  
                    ],
                    [
                        ['text' => 'Тренинг', 'callback_data' => 'Тренинг tni']  
                    ],
                    [
                        ['text' => 'Мастер-класс', 'callback_data' => 'Мастер-класс tni']  
                    ],
                    [
                        ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst']
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
    }    
    return;
}
if ($data['callback_query']['data'] == "1 Спорт") {
    $user = $func['from']['id'];
    // Вывод интересов пользователя
    $interests = mysqli_query ($con, "SELECT * FROM `Interests` WHERE userID='".$user."' ");
    $ui = mysqli_fetch_array($interests);

    // Переменная для вывода в сообщение
    $msgArray = "";

    if (empty($ui['interest1'])) {
        $method = 'editMessageText';
        $send_data = [
            'text' => "Выберите интерес:",
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Катание на роликах', 'callback_data' => 'Катание на роликах tni']  
                    ],
                    [
                        ['text' => 'Йога', 'callback_data' => 'Йога tni']  
                    ],
                    [
                        ['text' => 'Фитнес', 'callback_data' => 'Фитнес tni']  
                    ],
                    [
                        ['text' => 'Бег', 'callback_data' => 'Бег tni']  
                    ],
                    [
                        ['text' => 'Плавание', 'callback_data' => 'Плавание tni']  
                    ],
                    [
                        ['text' => 'Теннис большой', 'callback_data' => 'Теннис большой tni']  
                    ],
                    [
                        ['text' => 'Футбол', 'callback_data' => 'Футбол tni']  
                    ],
                    [
                        ['text' => 'Волейбол', 'callback_data' => 'Волейбол tni']  
                    ],
                    [
                        ['text' => 'Баскетбол', 'callback_data' => 'Баскетбол tni']  
                    ],
                    [
                        ['text' => 'Велики', 'callback_data' => 'Велики tni']  
                    ],
                    [
                        ['text' => 'Самокаты', 'callback_data' => 'Самокаты tni']  
                    ],
                    [
                        ['text' => 'Картинг', 'callback_data' => 'Картинг tni']  
                    ],
                    [
                        ['text' => 'Рафтинг', 'callback_data' => 'Рафтинг tni']  
                    ],
                    [
                        ['text' => 'Виндсерфинг', 'callback_data' => 'Виндсерфинг tni']  
                    ],
                    [
                        ['text' => 'Танцы', 'callback_data' => 'Танцы tni']  
                    ],
                    [
                        ['text' => 'Пинг понг', 'callback_data' => 'Пинг понг tni']  
                    ],
                    [
                        ['text' => 'Пилатес', 'callback_data' => 'Пилатес tni']  
                    ],
                    [
                        ['text' => 'Поход', 'callback_data' => 'Поход tni']  
                    ],
                    [
                        ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst']
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
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
        $method = 'editMessageText';
        $send_data = [
            'text' => "Выберите интерес:\n\nСейчас у вас указано: " . $msgArray,
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => 'Катание на роликах', 'callback_data' => 'Катание на роликах tni']  
                    ],
                    [
                        ['text' => 'Йога', 'callback_data' => 'Йога tni']  
                    ],
                    [
                        ['text' => 'Фитнес', 'callback_data' => 'Фитнес tni']  
                    ],
                    [
                        ['text' => 'Бег', 'callback_data' => 'Бег tni']  
                    ],
                    [
                        ['text' => 'Плавание', 'callback_data' => 'Плавание tni']  
                    ],
                    [
                        ['text' => 'Теннис большой', 'callback_data' => 'Теннис большой tni']  
                    ],
                    [
                        ['text' => 'Футбол', 'callback_data' => 'Футбол tni']  
                    ],
                    [
                        ['text' => 'Волейбол', 'callback_data' => 'Волейбол tni']  
                    ],
                    [
                        ['text' => 'Баскетбол', 'callback_data' => 'Баскетбол tni']  
                    ],
                    [
                        ['text' => 'Велики', 'callback_data' => 'Велики tni']  
                    ],
                    [
                        ['text' => 'Самокаты', 'callback_data' => 'Самокаты tni']  
                    ],
                    [
                        ['text' => 'Картинг', 'callback_data' => 'Картинг tni']  
                    ],
                    [
                        ['text' => 'Рафтинг', 'callback_data' => 'Рафтинг tni']  
                    ],
                    [
                        ['text' => 'Виндсерфинг', 'callback_data' => 'Виндсерфинг tni']  
                    ],
                    [
                        ['text' => 'Танцы', 'callback_data' => 'Танцы tni']  
                    ],
                    [
                        ['text' => 'Пинг понг', 'callback_data' => 'Пинг понг tni']  
                    ],
                    [
                        ['text' => 'Пилатес', 'callback_data' => 'Пилатес tni']  
                    ],
                    [
                        ['text' => 'Поход', 'callback_data' => 'Поход tni']  
                    ],
                    [
                        ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '1chFirst']
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $func['message']['chat']['id'];
        $send_data['message_id'] = $func['message']['message_id'];
        sendTelegram($method, $send_data);
    }    
    return;
}

// 2 кнопка
if ($data['callback_query']['data'] == "2.1chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");
    
    $method = 'editMessageText';
    $send_data = [
        'text' => 'Выберите категорию, а в ней навык, которому хотите обучаться:',
        'reply_markup' => [
            'inline_keyboard' => [
                [
                    ['text' => 'Розничная торговля', 'callback_data' => 'torgovlyaSkill first']
                ],
                [
                    ['text' => 'Секретариат, делопроизводство, АХО', 'callback_data' => 'sekretaringSkill first']
                ],
                [
                    ['text' => 'Сельское хозяйство, агробизнес', 'callback_data' => 'agrobiznesSkill first']
                ],
                [
                    ['text' => 'СМИ, издательство, полиграфия', 'callback_data' => 'izdatelstvoSkill first']
                ],
                [
                    ['text' => 'Страхование', 'callback_data' => 'strahovanieSkill first']
                ],
                [
                    ['text' => 'Строительство, архитектура', 'callback_data' => 'stroitelstvoSkill first']
                ],
                [
                    ['text' => 'Сфера обслуживания', 'callback_data' => 'obsluzhivanieSkill first']
                ],
                [
                    ['text' => 'Телекоммуникации и связь', 'callback_data' => 'telecomunikaciiSkill first']
                ],
                [
                    ['text' => 'Топ-менеджмент, руководство высшего звена', 'callback_data' => 'topmenSkill first']
                ],
                [
                    ['text' => 'Транспорт, автобизнес', 'callback_data' => 'avtobizSkill first']
                ],
                [
                    ['text' => 'Управление персоналом, HR', 'callback_data' => 'hrSkill first']
                ],
                [
                    ['text' => 'Финансы, банк', 'callback_data' => 'bankSkill first']
                ],
                [
                    ['text' => 'Юриспруденция', 'callback_data' => 'yuristSkill first']
                ],
                /*[
                    ['text' => '🆘 ДОБАВИТЬ СВОЙ НАВЫК 🆘', 'callback_data' => 'imNotFindMySkill first']
                ],*/
                [
                    ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'],
                    ['text' => '👈 Прошлая страница', 'callback_data' => '2chFirst']
                ]
            ]
        ]
    ];
    $send_data['chat_id'] = $func['message']['chat']['id'];
    $send_data['message_id'] = $func['message']['message_id'];
    sendTelegram($method, $send_data);
    return;
}
// 2 кнопка
if ($data['callback_query']['data'] == "2chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");
    
    $method = 'editMessageText';
    $send_data = [
        'text' => 'Выберите категорию, а в ней навык, которому хотите обучаться:',
        'reply_markup' => [
            'inline_keyboard' => [
                [
                    ['text' => 'IT, компьютеры, интернет', 'callback_data' => 'ITSkill first']
                ],
                [
                    ['text' => 'Администрация, руководство среднего звена', 'callback_data' => 'administrSkill first']
                ],
                [
                    ['text' => 'Дизайн, творчество', 'callback_data' => 'designSkill first']
                ],
                [
                    ['text' => 'Красота, фитнес, спорт', 'callback_data' => 'beautySkill first']
                ],
                [
                    ['text' => 'Культура, музыка, шоу-бизнес', 'callback_data' => 'showbizSkill first']
                ],
                [
                    ['text' => 'Логистика, склад, ВЭД', 'callback_data' => 'logistikaSkill first']
                ],
                [
                    ['text' => 'Маркетинг, реклама, PR', 'callback_data' => 'marketingSkill first']
                ],
                [
                    ['text' => 'Медицина, фармацевтика', 'callback_data' => 'medicinaSkill first']
                ],
                [
                    ['text' => 'Недвижимость', 'callback_data' => 'nedvizhimostSkill first']
                ],
                [
                    ['text' => 'Образование, наука', 'callback_data' => 'naukaSkill first']
                ],
                [
                    ['text' => 'Охрана, безопасность', 'callback_data' => 'ohranaSkill first']
                ],
                [
                    ['text' => 'Продажи, закупки', 'callback_data' => 'prodajiSkill first']
                ],
                [
                    ['text' => 'Рабочие специальности, производство', 'callback_data' => 'proizvodstvoSkill first']
                ],
                /*[
                    ['text' => '🆘 ДОБАВИТЬ СВОЙ НАВЫК 🆘', 'callback_data' => 'imNotFindMySkill first']
                ],*/
                [
                    ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'],
                    ['text' => 'Следующая страница 👉', 'callback_data' => '2.1chFirst']
                ]
            ]
        ]
    ];
    $send_data['chat_id'] = $func['message']['chat']['id'];
    $send_data['message_id'] = $func['message']['message_id'];
    sendTelegram($method, $send_data);
    return;
}

// 3 кнопка
if ($data['callback_query']['data'] == "3chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    $method = 'editMessageText';
    $send_data = [
        'text' => "Укажите свой пол:",
        'reply_markup' => [
            'inline_keyboard' => [
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
        ]
    ];
    $send_data['chat_id'] = $func['message']['chat']['id'];
    $send_data['message_id'] = $func['message']['message_id'];
    sendTelegram($method, $send_data);
    return;
}

// 4 кнопка
if ($data['callback_query']['data'] == "4.1chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    $method = 'editMessageText';
    $send_data = [
        'text' => 'Выберите категорию:',
        'reply_markup' => [
            'inline_keyboard' => [
                [
                    ['text' => 'Розничная торговля', 'callback_data' => 'torgovlyaSkill second']
                ],
                [
                    ['text' => 'Секретариат, делопроизводство, АХО', 'callback_data' => 'sekretaringSkill second']
                ],
                [
                    ['text' => 'Сельское хозяйство, агробизнес', 'callback_data' => 'agrobiznesSkill second']
                ],
                [
                    ['text' => 'СМИ, издательство, полиграфия', 'callback_data' => 'izdatelstvoSkill second']
                ],
                [
                    ['text' => 'Страхование', 'callback_data' => 'strahovanieSkill second']
                ],
                [
                    ['text' => 'Строительство, архитектура', 'callback_data' => 'stroitelstvoSkill second']
                ],
                [
                    ['text' => 'Сфера обслуживания', 'callback_data' => 'obsluzhivanieSkill second']
                ],
                [
                    ['text' => 'Телекоммуникации и связь', 'callback_data' => 'telecomunikaciiSkill second']
                ],
                [
                    ['text' => 'Топ-менеджмент, руководство высшего звена', 'callback_data' => 'topmenSkill second']
                ],
                [
                    ['text' => 'Транспорт, автобизнес', 'callback_data' => 'avtobizSkill second']
                ],
                [
                    ['text' => 'Управление персоналом, HR', 'callback_data' => 'hrSkill second']
                ],
                [
                    ['text' => 'Финансы, банк', 'callback_data' => 'bankSkill second']
                ],
                [
                    ['text' => 'Юриспруденция', 'callback_data' => 'yuristSkill second']
                ],
                /*[
                    ['text' => '🆘 ДОБАВИТЬ СВОЙ НАВЫК 🆘', 'callback_data' => 'imNotFindMySkill second']
                ],*/
                [
                    ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'],
                    ['text' => '👈 Прошлая страница', 'callback_data' => '4chFirst']
                ]
            ]
        ]
    ];
    $send_data['chat_id'] = $func['message']['chat']['id'];
    $send_data['message_id'] = $func['message']['message_id'];
    sendTelegram($method, $send_data);
    return;
}

// 4 кнопка
if ($data['callback_query']['data'] == "4chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    $method = 'editMessageText';
    $send_data = [
        'text' => 'Выберите категорию:',
        'reply_markup' => [
            'inline_keyboard' => [
                [
                    ['text' => 'IT, компьютеры, интернет', 'callback_data' => 'ITSkill second']
                ],
                [
                    ['text' => 'Администрация, руководство среднего звена', 'callback_data' => 'administrSkill second']
                ],
                [
                    ['text' => 'Дизайн, творчество', 'callback_data' => 'designSkill second']
                ],
                [
                    ['text' => 'Красота, фитнес, спорт', 'callback_data' => 'beautySkill second']
                ],
                [
                    ['text' => 'Культура, музыка, шоу-бизнес', 'callback_data' => 'showbizSkill second']
                ],
                [
                    ['text' => 'Логистика, склад, ВЭД', 'callback_data' => 'logistikaSkill second']
                ],
                [
                    ['text' => 'Маркетинг, реклама, PR', 'callback_data' => 'marketingSkill second']
                ],
                [
                    ['text' => 'Медицина, фармацевтика', 'callback_data' => 'medicinaSkill second']
                ],
                [
                    ['text' => 'Недвижимость', 'callback_data' => 'nedvizhimostSkill second']
                ],
                [
                    ['text' => 'Образование, наука', 'callback_data' => 'naukaSkill second']
                ],
                [
                    ['text' => 'Охрана, безопасность', 'callback_data' => 'ohranaSkill second']
                ],
                [
                    ['text' => 'Продажи, закупки', 'callback_data' => 'prodajiSkill second']
                ],
                [
                    ['text' => 'Рабочие специальности, производство', 'callback_data' => 'proizvodstvoSkill second']
                ],
                /*[
                    ['text' => '🆘 ДОБАВИТЬ СВОЙ НАВЫК 🆘', 'callback_data' => 'imNotFindMySkill second']
                ],*/
                [
                    ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'],
                    ['text' => 'Следующая страница 👉', 'callback_data' => '4.1chFirst']
                ]
            ]
        ]
    ];
    $send_data['chat_id'] = $func['message']['chat']['id'];
    $send_data['message_id'] = $func['message']['message_id'];
    sendTelegram($method, $send_data);
    return;
}

// 5 кнопка
if ($data['callback_query']['data'] == "5.1chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    $method = 'editMessageText';
    $send_data = [
        'text' => 'Выберите категорию:',
        'reply_markup' => [
            'inline_keyboard' => [
                [
                    ['text' => 'Розничная торговля', 'callback_data' => 'torgovlyaSkill third']
                ],
                [
                    ['text' => 'Секретариат, делопроизводство, АХО', 'callback_data' => 'sekretaringSkill third']
                ],
                [
                    ['text' => 'Сельское хозяйство, агробизнес', 'callback_data' => 'agrobiznesSkill third']
                ],
                [
                    ['text' => 'СМИ, издательство, полиграфия', 'callback_data' => 'izdatelstvoSkill third']
                ],
                [
                    ['text' => 'Страхование', 'callback_data' => 'strahovanieSkill third']
                ],
                [
                    ['text' => 'Строительство, архитектура', 'callback_data' => 'stroitelstvoSkill third']
                ],
                [
                    ['text' => 'Сфера обслуживания', 'callback_data' => 'obsluzhivanieSkill third']
                ],
                [
                    ['text' => 'Телекоммуникации и связь', 'callback_data' => 'telecomunikaciiSkill third']
                ],
                [
                    ['text' => 'Топ-менеджмент, руководство высшего звена', 'callback_data' => 'topmenSkill third']
                ],
                [
                    ['text' => 'Транспорт, автобизнес', 'callback_data' => 'avtobizSkill third']
                ],
                [
                    ['text' => 'Управление персоналом, HR', 'callback_data' => 'hrSkill third']
                ],
                [
                    ['text' => 'Финансы, банк', 'callback_data' => 'bankSkill third']
                ],
                [
                    ['text' => 'Юриспруденция', 'callback_data' => 'yuristSkill third']
                ],
                /*[
                    ['text' => '🆘 ДОБАВИТЬ СВОЙ НАВЫК 🆘', 'callback_data' => 'imNotFindMySkill third']
                ],*/
                [
                    ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'],
                    ['text' => '👈 Прошлая страница', 'callback_data' => '5chFirst']
                ]
            ]
        ]
    ];
    $send_data['chat_id'] = $func['message']['chat']['id'];
    $send_data['message_id'] = $func['message']['message_id'];
    sendTelegram($method, $send_data);
    return;
}

// 5 кнопка
if ($data['callback_query']['data'] == "5chFirst") {
    // Пушим id основного сообщения
    $user = $func['from']['id'];
    $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET mesToChange = ".$func['message']['message_id']." WHERE userID=".$user." ");

    $method = 'editMessageText';
    $send_data = [
        'text' => 'Выберите категорию:',
        'reply_markup' => [
            'inline_keyboard' => [
                [
                    ['text' => 'IT, компьютеры, интернет', 'callback_data' => 'ITSkill third']
                ],
                [
                    ['text' => 'Администрация, руководство среднего звена', 'callback_data' => 'administrSkill third']
                ],
                [
                    ['text' => 'Дизайн, творчество', 'callback_data' => 'designSkill third']
                ],
                [
                    ['text' => 'Красота, фитнес, спорт', 'callback_data' => 'beautySkill third']
                ],
                [
                    ['text' => 'Культура, музыка, шоу-бизнес', 'callback_data' => 'showbizSkill third']
                ],
                [
                    ['text' => 'Логистика, склад, ВЭД', 'callback_data' => 'logistikaSkill third']
                ],
                [
                    ['text' => 'Маркетинг, реклама, PR', 'callback_data' => 'marketingSkill third']
                ],
                [
                    ['text' => 'Медицина, фармацевтика', 'callback_data' => 'medicinaSkill third']
                ],
                [
                    ['text' => 'Недвижимость', 'callback_data' => 'nedvizhimostSkill third']
                ],
                [
                    ['text' => 'Образование, наука', 'callback_data' => 'naukaSkill third']
                ],
                [
                    ['text' => 'Охрана, безопасность', 'callback_data' => 'ohranaSkill third']
                ],
                [
                    ['text' => 'Продажи, закупки', 'callback_data' => 'prodajiSkill third']
                ],
                [
                    ['text' => 'Рабочие специальности, производство', 'callback_data' => 'proizvodstvoSkill third']
                ],
                /*[
                    ['text' => '🆘 ДОБАВИТЬ СВОЙ НАВЫК 🆘', 'callback_data' => 'imNotFindMySkill third']
                ],*/
                [
                    ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu'],
                    ['text' => 'Следующая страница 👉', 'callback_data' => '5.1chFirst']
                ]
            ]
        ]
    ];
    $send_data['chat_id'] = $func['message']['chat']['id'];
    $send_data['message_id'] = $func['message']['message_id'];
    sendTelegram($method, $send_data);
    return;
}

// Если мы получили локацию
if($func['location'] != ""){

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

    $method = 'sendMessage';
    $send_data = [
        'text' => "👌 Отлично, чат в твоем городе я уже нашел, но для полной регистрации мне нужно знать твой номер. \nНажми на кнопку ниже 👇",
        'reply_markup' => [
            resize_keyboard =>true,
            one_time_keyboard => true,
            'keyboard' => [
                [
                    ['text' => '📱 Поделиться номером', request_contact => true]
                ]
            ]
        ]
    ];

    $send_data['chat_id'] = $func['chat']['id'];
    sendTelegram($method, $send_data);
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
    mysqli_query ($con, "UPDATE `MainInfo` SET userNum = ".$data['contact']['phone_number']." WHERE userID=".$user." ");
    /*// Проверяем в какой ветке дать пользователю возможность писать
    if (strpos($func['reply_to_message']['text'], "общения") {
        
    }else if (strpos($func['reply_to_message']['text'], "обучения"){
        
    }else if (strpos($func['reply_to_message']['text'], "знакомств"){
        
    }else if (strpos($func['reply_to_message']['text'], "специалиста"){
        
    }else{

    }*/
        // Отправляем ссылку на чат
    $method = 'sendMessage';
    $send_data = [
        'text' => 'Вступи в чат [Деловая Одесса](https://t.me/+8mMjL5dm2c0zYTVi) и в ветке "Встречи по интересам" напиши приветственное сообщение и тебе откроются дополнительные функции!',
        'parse_mode' => 'markdown',
        'disable_web_page_preview' => true
    ];
    $send_data['chat_id'] = $func['chat']['id'];
    sendTelegram($method, $send_data);
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
        $method = 'editMessageText';
        $send_data = [
            'text' => '*Спасибо большое! Благодаря тебе, я становлюсь лучше с каждым днем!*',
            'parse_mode' => 'markdown',
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => '👈 Вернуться к главное меню', 'callback_data' => 'mainMenu']  
                    ]
                ]
            ]
        ];
        $send_data['chat_id'] = $data['message']['chat']['id'];
        $send_data['message_id'] = $mes['mesToChange'];
        sendTelegram($method, $send_data);
    }

    elseif ($track['whichMenu'] == "ИмяФамилия") {
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
            $src  = 'https://api.telegram.org/file/bot' . TOKEN . '/' . $res['result']['file_path'];
            $p = time() . '-' . basename($src);
            $dest = "../tgBot/userPhotos/" . $p;
            copy($src, $dest);

            // Пушим в БД путь до фотографии
            mysqli_query ($con, "UPDATE `MainInfo` SET userPhoto = '".$p."' WHERE userID = ".$user." ");

            // Удаляем фотку
            $send_data['message_id'] = $func['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Удаляем собщение с инструкцией
            $send_data['message_id'] = $menu['mesToChange'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            // Получаем из БД все о пользователе
            $user = $func['from']['id'];
            $mainCheck = mysqli_query ($con, "SELECT * FROM `MainInfo` WHERE userID='".$user."' ");
            $needCheck = mysqli_query ($con, "SELECT * FROM `Needs` WHERE userID='".$user."' ");
            $skillCheck = mysqli_query ($con, "SELECT * FROM `Skills` WHERE userID='".$user."' ");
            $intCheck = mysqli_query ($con, "SELECT * FROM `Interests` WHERE userID='".$user."' ");

            // Обрабатываем запросы в БД
            $main = mysqli_fetch_array($mainCheck);
            $need = mysqli_fetch_array($needCheck);
            $skill = mysqli_fetch_array($skillCheck);
            $int = mysqli_fetch_array($intCheck);

            mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = '' WHERE userID = ".$user." ");

            // Создаем переменную в которую будем заливать то что у человека уже введено
            $msgArray = "";

            if (!empty($main['name']) or !empty($main['surname'])) {
                $msgArray .= "_Имя и Фамилия:_ " . $main['name'] . " " . $main['surname'] . "\n\n";
            }

            if (!empty($main['userAge'])) {
                $msgArray .= "_Возраст:_ " . $main['userAge'] . "\n\n";
            }

            if (!empty($int['interest1'])) {
                $msgArray .= "_Мои интересы:_ \n\u{0031}\u{FE0F}\u{20E3}" . " - " . $int['interest1'] . "\n";

                if (!empty($int['interest2'])) {
                    $msgArray .= "\u{0032}\u{FE0F}\u{20E3}" . " - " . $int['interest2'] . "\n";
                }    
                if (!empty($int['interest3'])) {
                    $msgArray .= "\u{0033}\u{FE0F}\u{20E3}" . " - " . $int['interest3'] . "\n";
                }
                if (!empty($int['interest4'])) {
                    $msgArray .= "\u{0034}\u{FE0F}\u{20E3}" . " - " . $int['interest4'] . "\n";
                }
                if (!empty($int['interest5'])) {
                    $msgArray .= "\u{0035}\u{FE0F}\u{20E3}" . " - " . $int['interest5'] . "\n";
                }
                if (!empty($int['interest6'])) {
                    $msgArray .= $int['interest6'] . "\n";
                }
            }

            if (!empty($skill['s1'])) {
                $msgArray .= "_Мои навыки:_ \n\u{0031}\u{FE0F}\u{20E3}" . " - " . $skill['s1'] . "\n";
                
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
            }

            if (!empty($need['n1'])) {
                $msgArray .= "_Мои ценности:_ \n\u{0031}\u{FE0F}\u{20E3}" . " - " . $need['n1'] . "\n";
                
                if (!empty($need['n2'])) {
                    $msgArray .= "\u{0032}\u{FE0F}\u{20E3}" . " - " . $need['n2'] . "\n";
                }    
                if (!empty($need['n3'])) {
                    $msgArray .= "\u{0033}\u{FE0F}\u{20E3}" . " - " . $need['n3'] . "\n";
                }
                if (!empty($need['n4'])) {
                    $msgArray .= "\u{0034}\u{FE0F}\u{20E3}" . " - " . $need['n4'] . "\n";
                }
                if (!empty($need['n5'])) {
                    $msgArray .= "\u{0035}\u{FE0F}\u{20E3}" . " - " . $need['n5'] . "\n";
                }
                if (!empty($need['n6'])) {
                    $msgArray .= $need['n6'] . "\n";
                }
            }

            $response = [
                'chat_id' => $user,
                'caption' => "😁 *Мой профиль*\n" . $msgArray,
                "parse_mode" => "Markdown",
                'photo' => curl_file_create("../tgBot/userPhotos/".$main['userPhoto']),
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
        $newSkill = trim($msgArray[1]);
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
                    'text' => "Упс. У вас в профиле уже есть навык " . $newSkill . "\n\nСейчас список ваших навыков выглядит так: \n" . $msgArray,
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Выбрать другой навык', 'callback_data' => 'choiceSkills']
                            ],
                            [
                                ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);

            }else{
                // Если это первый навык
                if (empty($skill['s1'])) {
                    // Пушим в БД новую профессию
                    $updateDB = mysqli_query ($con, "UPDATE `Skills` SET s1 = '".$newSkill."', lvl1 = '".$level."' WHERE userID = ".$user." ");

                    $method = 'sendMessage';
                    $send_data = [
                        'text' => "Вы добавили профессию: " . $newSkill . "\nС уровнем владения: " . $level,
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                ],
                                [
                                    ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']
                                ]
                            ]
                        ]
                    ];
                    $send_data['chat_id'] = $func['message']['chat']['id'];
                    $send_data['message_id'] = $func['message']['message_id'];
                    sendTelegram($method, $send_data);
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
                        $msgArray .= $skill['s6'] . "\n";
                    }

                    if (empty($skill['s2'])) {
                        // Пушим в БД новую профессию
                        $updateDB = mysqli_query ($con, "UPDATE `Skills` SET s2 = '".$newSkill."', lvl2 = '".$level."' WHERE userID = ".$user." ");

                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "Вы добавили профессию: " . $newSkill . "\nС уровнем владения: " . $level,
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                    ],
                                    [
                                        ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']
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
                            'text' => "Вы добавили профессию: " . $newSkill . "\nС уровнем владения: " . $level,
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                    ],
                                    [
                                        ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']
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
                            'text' => "Вы добавили профессию: " . $newSkill . "\nС уровнем владения: " . $level,
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                    ],
                                    [
                                        ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']
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
                                'caption' => "Вы добавили профессию: " . $newSkill . "\nС уровнем владения: " . $level . "\n\nСейчас список ваших навыков выглядит так: " . $msgArray . "\nВы получили 100 монет за добавление 5 навыков. Узнать кол-во монет и как их получить, вы можете нажав на кнопку 'Монеты' в главном меню",
                                "parse_mode" => "Markdown",
                                'protect_content' => true,
                                'photo' => curl_file_create("../tgBot/BotPic/post_199.png"),
                                'reply_markup'=>json_encode([
                                    'inline_keyboard'=>[
                                        [
                                            ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                        ],
                                        [
                                            ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']
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
                                'caption' => "Вы добавили профессию: " . $newSkill . "\nС уровнем владения: " . $level . "\n\nСейчас список ваших навыков выглядит так: " . $msgArray,
                                "parse_mode" => "Markdown",
                                'protect_content' => true,
                                'photo' => curl_file_create("../tgBot/BotPic/post_199.png"),
                                'reply_markup'=>json_encode([
                                    'inline_keyboard'=>[
                                        [
                                            ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                        ],
                                        [
                                            ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']
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
                        'text' => "Вы добавили профессию: " . $newSkill . "\nС уровнем владения: " . $level,
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Добавить еще навыки', 'callback_data' => 'choiceSkills']
                                ],
                                [
                                    ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']
                                ]
                            ]
                        ]
                    ];
                    $send_data['chat_id'] = $func['message']['chat']['id'];
                    $send_data['message_id'] = $func['message']['message_id'];
                    sendTelegram($method, $send_data);
                }
            }    
    }
    // Удаление интересов
    else if (strpos($data['callback_query']['data'], '1135') !== false) {
        // Достаем что человек хочет удалить
        $word = preg_replace("/1135/i", "", $data['callback_query']['data']);
        $word = trim($word);

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
            $ar = explode("," , $prof[5]);
            $arr = "";
            foreach ($ar as $key => $value1) {
                if (trim($value1) == $word) {
                    $arr .= "";
                }else{
                    if ($arr == "") {
                        $arr .= trim($value1);
                    }else{
                        $arr .= ", " . trim($value1);
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
                            ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']  
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
                    foreach ($skills6 as $key => $value) {
                        $msgText3 .= trim($value) . "\n";
                        array_push($arrTo6, $value);
                    }
                }
            }

            foreach ($prof as $key => $value) {
                if (!empty($value) and $key < 5) {
                    array_push($btnsArray, array(array('text' => '❌ Удалить '.trim($value), 'callback_data' => trim($value)." 1135")));
                }else{
                    if (!empty($value)) {
                        foreach ($arrTo6 as $key => $value) {
                            array_push($btnsArray, array(array('text' => '❌ Удалить '.trim($value), 'callback_data' => trim($value1)." 1135")));
                        }
                    }
                }
            }

            array_push($btnsArray, array(array('text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile')));
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
    // Удаление скиллов
    else if (strpos($data['callback_query']['data'], '1133') !== false) {
        // Достаем что человек хочет удалить
        $word = preg_replace("/1133/i", "", $data['callback_query']['data']);
        $word = trim($word);

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
            $ar = explode("," , $prof[5]);
            $arr = "";
            foreach ($ar as $key => $value) {
                if ($value == $word) {
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
                            ['text' => 'Добавить навыки', 'callback_data' => 'choiceSkills']  
                        ],
                        [
                            ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']  
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
                        array_push($arrTo6, $skill6[1]);
                    }
                }
            }

            foreach ($prof as $key => $value) {
                if (!empty($value) and $key < 5) {
                    array_push($btnsArray, array(array('text' => '❌ Удалить '.trim($value), 'callback_data' => trim($value)." 1133")));
                }else{
                    if (!empty($value)) {
                        foreach ($arrTo6 as $key => $value) {
                            array_push($btnsArray, array(array('text' => '❌ Удалить '.trim($value), 'callback_data' => trim($value1)." 1133")));
                        }
                    }
                }
            }

            array_push($btnsArray, array(array('text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile')));
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
    // Удаление ценностей
    else if (strpos($data['callback_query']['data'], '1134') !== false) {
        // Достаем что человек хочет удалить
        $word = preg_replace("/1134/i", "", $data['callback_query']['data']);
        $word = trim($word);

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
            $ar = explode("," , $prof[5]);
            $arr = "";
            foreach ($ar as $key => $value) {
                if ($value == $word) {
                    $arr .= "";
                }else{
                    if ($arr == "") {
                        $arr .= $value;
                    }else{
                        $arr .= ", " . $value;
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
                            ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']  
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
            $arrTo6 = array();
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
                    $needs6 = explode("," , $value);
                    foreach ($needs6 as $key => $value) {
                        $msgText3 .= trim($value) . "\n";
                        array_push($arrTo6, $value);
                    }
                }
            }

            foreach ($prof as $key => $value) {
                if (!empty($value) and $key < 5) {
                    array_push($btnsArray, array(array('text' => '❌ Удалить '.trim($value), 'callback_data' => trim($value)." 1134")));
                }else{
                    if (!empty($value)) {
                        foreach ($arrTo6 as $key => $value) {
                            array_push($btnsArray, array(array('text' => '❌ Удалить '.trim($value), 'callback_data' => trim($value1)." 1134")));
                        }
                    }
                }
            }

            array_push($btnsArray, array(array('text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile')));
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
            
        $prof = mysqli_fetch_array($profCheck);
        $skill = mysqli_fetch_row($skillsCheck);
        $need = mysqli_fetch_row($needsCheck);
        $int = mysqli_fetch_row($intsCheck);

        $msgText1 = "";
        $msgText2 = "";
        $msgText3 = "";

        if (!empty($skill)) {
            $msgText1 = "\n🧑‍💻 _Мои навыки:_ \n";
                // Выводим скиллы в правильном виде
            foreach ($skill as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText1 .= "\r\u{0031}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText1 .= "\r\u{0032}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText1 .= "\r\u{0033}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText1 .= "\r\u{0034}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText1 .= "\r\u{0035}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText1 .= "*" . trim($value) . "*\n";
                }
            }
        }

        if (!empty($need)) {
            $msgText2 = "\n📝 _Мои ценности:_ \n";
                // Выводим ценности в правильном виде
            foreach ($need as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText2 .= "\r\u{0031}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText2 .= "\r\u{0032}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText2 .= "\r\u{0033}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText2 .= "\r\u{0034}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText2 .= "\r\u{0035}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText2 .= "*" . trim($value) . "*\n";
                }
            }
        }    
        
        if (!empty($int)) {
            $msgText3 = "\n🚲 _Мои интересы:_ \n";
                // Выводим ценности в правильном виде
            foreach ($int as $key => $value) {
                if ($key == 0 and !empty($value)) {
                    $msgText3 .= "\r\u{0031}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 1 and !empty($value)) {
                    $msgText3 .= "\r\u{0032}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 2 and !empty($value)) {
                    $msgText3 .= "\r\u{0033}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 3 and !empty($value)) {
                    $msgText3 .= "\r\u{0034}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 4 and !empty($value)) {
                    $msgText3 .= "\r\u{0035}\u{FE0F}\u{20E3}*" . trim($value) . "*\n";
                }
                if ($key == 5 and !empty($value)) {
                    $msgText3 .= "*" . trim($value) . "*\n";
                }
            }
        }    

            if (empty($prof['userPhoto'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "😁 *Мой профиль*\n\n*".$prof['name']." ".$prof['surname']."*\n\n_Возраст:_ *".$prof['userAge']."*\n".$msgText1.$msgText2.$msgText3,
                    "parse_mode" => "Markdown",
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
                    'caption' => "😁 *Мой профиль*\n\n*".$prof['name']." ".$prof['surname']."*\n\n_Возраст:_ *".$prof['userAge']."*\n".$msgText1.$msgText2.$msgText3,
                    "parse_mode" => "Markdown",
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

        $skills = mysqli_fetch_row($skillsCheck);
        $needs = mysqli_fetch_row($needsCheck);
        $ints = mysqli_fetch_row($intsCheck);
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
        $search = preg_replace("/поиск/i", "", $data['callback_query']['data']);
        $search = trim($search);

        // Если в профиле хоть что-то не заполнено, тогда даем человеку возможность видеть только новых людей и выводим сообщение с кнопкой ведущей в профиль
        if (empty($prof['userAge']) or empty($prof['sex']) or empty($skills) or empty($needs) or empty($ints) or empty($prof['name'])) {
            $method = 'editMessageText';
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
            $skillCheck = mysqli_query ($con, "SELECT `userID` FROM `Skills` WHERE (s1 LIKE '%".$search."%') OR (s2 LIKE '%".$search."%') OR (s3 LIKE '%".$search."%') OR (s4 LIKE '%".$search."%') OR (s5 LIKE '%".$search."%') ");
            $skill = mysqli_fetch_row($skillCheck);

            $userNames = "";
            $counter = 0;

            foreach ($skillCheck as $key => $value) {
                mysqli_fetch_array($value);
                foreach ($value as $key => $value) {
                    if ($value != $user) {
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
                    'protect_content' => true,
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
                $prof = mysqli_fetch_array($profCheck);

                // Если кол-во найденных профилей = 1
                if ($counter == 1) {
                    if ($prof['userPhoto'] == "") {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "_Имя и Фамилия:_ ".$prof['name'] . " " . $prof['surname'] ."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "* " . 'tg://user?id='.$ids[0],
                            "parse_mode" => "Markdown",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]
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
                            'caption' => "_Имя и Фамилия:_ ".$prof['name'] . " " . $prof['surname'] ."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'text' => "_Имя и Фамилия:_ ".$prof['name'] . " " . $prof['surname'] ."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'caption' => "_Имя и Фамилия:_ ".$prof['name'] . " " . $prof['surname'] ."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
        $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID = ".$user." ");
        $needs = mysqli_fetch_row($needsCheck);

        // Удаляем SexSer3ch из ценностей
        $chWord = $data['callback_query']['data'];
        $word = preg_replace("/SexSer3ch/i", "", $chWord);
        $word = trim($word);

        // Если это будет первая ценность
        if (empty($needs)) {
            // Пушим новую ценность в БД
            $updateDB = mysqli_query ($con, "UPDATE `Needs` SET n1 = '".$word."' WHERE userID = ".$user." ");

            $method = 'editMessageText';
            $send_data = [
                'text' => "📝 *Мои ценности\n\nСейчас у вас указано:* ".$word."\n\n_Просмотрите все ценности и найдите самую важную для вас!\nВыберите 5 ценностей начиная с самой важной:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Здоровье', 'callback_data' => 'Здоровье SexSer3ch']
                        ],
                        [
                            ['text' => 'Карьера', 'callback_data' => 'Карьера SexSer3ch']
                        ],
                        [
                            ['text' => 'Семья', 'callback_data' => 'Семья SexSer3ch']
                        ],
                        [
                            ['text' => 'Богатство', 'callback_data' => 'Богатство SexSer3ch']
                        ],
                        [
                            ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие SexSer3ch']
                        ],
                        [
                            ['text' => 'Спорт', 'callback_data' => 'Спорт SexSer3ch']
                        ],
                        [
                            ['text' => 'Осознанность', 'callback_data' => 'Осознанность SexSer3ch']
                        ],
                        [
                            ['text' => 'Развитие', 'callback_data' => 'Развитие SexSer3ch']
                        ],
                        [
                            ['text' => 'Свобода', 'callback_data' => 'Свобода SexSer3ch']
                        ],
                        [
                            ['text' => 'Миссия', 'callback_data' => 'Миссия SexSer3ch']
                        ],
                        [
                            ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми SexSer3ch']
                        ],
                        [
                            ['text' => 'Любовь', 'callback_data' => 'Любовь SexSer3ch']
                        ],
                        [
                            ['text' => 'Амбиции', 'callback_data' => 'Амбиции SexSer3ch']
                        ],
                        [
                            ['text' => 'Отдых', 'callback_data' => 'Отдых SexSer3ch']
                        ],
                        [
                            ['text' => 'Благодарность', 'callback_data' => 'Благодарность SexSer3ch']
                        ],
                        [
                            ['text' => 'Принятие', 'callback_data' => 'Принятие SexSer3ch']
                        ],
                        [
                            ['text' => '👈 Вернуться в профиль', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{
            // Проверяем есть ли такая ценность уже у человека
            if ($needs[0] == $word or $needs[1] == $word or $needs[2] == $word or $needs[3] == $word or $needs[4] == $word or strpos($needs[5], $word) !== false) {
                $method = 'editMessageText';
                $send_data = [
                    'text' => "📝 *Мои ценности*\n\n*Упс! Такая ценность у вас уже есть*",
                    "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Здоровье', 'callback_data' => 'Здоровье SexSer3ch']
                            ],
                            [
                                ['text' => 'Карьера', 'callback_data' => 'Карьера SexSer3ch']
                            ],
                            [
                                ['text' => 'Семья', 'callback_data' => 'Семья SexSer3ch']
                            ],
                            [
                                ['text' => 'Богатство', 'callback_data' => 'Богатство SexSer3ch']
                            ],
                            [
                                ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие SexSer3ch']
                            ],
                            [
                                ['text' => 'Спорт', 'callback_data' => 'Спорт SexSer3ch']
                            ],
                            [
                                ['text' => 'Осознанность', 'callback_data' => 'Осознанность SexSer3ch']
                            ],
                            [
                                ['text' => 'Развитие', 'callback_data' => 'Развитие SexSer3ch']
                            ],
                            [
                                ['text' => 'Свобода', 'callback_data' => 'Свобода SexSer3ch']
                            ],
                            [
                                ['text' => 'Миссия', 'callback_data' => 'Миссия SexSer3ch']
                            ],
                            [
                                ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми SexSer3ch']
                            ],
                            [
                                ['text' => 'Любовь', 'callback_data' => 'Любовь SexSer3ch']
                            ],
                            [
                                ['text' => 'Амбиции', 'callback_data' => 'Амбиции SexSer3ch']
                            ],
                            [
                                ['text' => 'Отдых', 'callback_data' => 'Отдых SexSer3ch']
                            ],
                            [
                                ['text' => 'Благодарность', 'callback_data' => 'Благодарность SexSer3ch']
                            ],
                            [
                                ['text' => 'Принятие', 'callback_data' => 'Принятие SexSer3ch']
                            ],
                            [
                                ['text' => '👈 Вернуться в профиль', 'callback_data' => 'profile']
                            ]
                        ]
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

                        $method = 'editMessageText';
                        $send_data = [
                            'text' => "📝 *Мои ценности*\n\n_Вы добавили_ *".$word."*_ и получили 100 монет за добавление 5 ценностей. Узнать кол-во монет и как их получить, вы можете нажав на кнопку 'Монеты' в главном меню\n_Просмотри все ценности и найди самую важную для тебя!\nВыбери ценности начиная с самой важной:",
                            'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Здоровье', 'callback_data' => 'Здоровье SexSer3ch']
                            ],
                            [
                                ['text' => 'Карьера', 'callback_data' => 'Карьера SexSer3ch']
                            ],
                            [
                                ['text' => 'Семья', 'callback_data' => 'Семья SexSer3ch']
                            ],
                            [
                                ['text' => 'Богатство', 'callback_data' => 'Богатство SexSer3ch']
                            ],
                            [
                                ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие SexSer3ch']
                            ],
                            [
                                ['text' => 'Спорт', 'callback_data' => 'Спорт SexSer3ch']
                            ],
                            [
                                ['text' => 'Осознанность', 'callback_data' => 'Осознанность SexSer3ch']
                            ],
                            [
                                ['text' => 'Развитие', 'callback_data' => 'Развитие SexSer3ch']
                            ],
                            [
                                ['text' => 'Свобода', 'callback_data' => 'Свобода SexSer3ch']
                            ],
                            [
                                ['text' => 'Миссия', 'callback_data' => 'Миссия SexSer3ch']
                            ],
                            [
                                ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми SexSer3ch']
                            ],
                            [
                                ['text' => 'Любовь', 'callback_data' => 'Любовь SexSer3ch']
                            ],
                            [
                                ['text' => 'Амбиции', 'callback_data' => 'Амбиции SexSer3ch']
                            ],
                            [
                                ['text' => 'Отдых', 'callback_data' => 'Отдых SexSer3ch']
                            ],
                            [
                                ['text' => 'Благодарность', 'callback_data' => 'Благодарность SexSer3ch']
                            ],
                            [
                                ['text' => 'Принятие', 'callback_data' => 'Принятие SexSer3ch']
                            ],
                            [
                                ['text' => '👈 Вернуться в профиль', 'callback_data' => 'profile']
                            ]
                                ]
                            ]
                        ];
                        $send_data['chat_id'] = $func['message']['chat']['id'];
                        $send_data['message_id'] = $func['message']['message_id'];
                        sendTelegram($method, $send_data);
                        return;
                    }
                }else{
                    if ($needs[5] == "") {
                        mysqli_query ($con, "UPDATE `Needs` SET n6 = '".$word."' WHERE userID = ".$user." ");
                    }else{
                        $needs[5] .= ", " . $word;
                        mysqli_query ($con, "UPDATE `Needs` SET n6 = '".$needs[5]."' WHERE userID = ".$user." "); 
                    }
                }

                // Выводим новое сообщение
                $method = 'editMessageText';
                $send_data = [
                    'text' => "📝 *Мои ценности*\n\n_Вы добавили_ *".$word."*\n_Просмотри все ценности и найди самую важную для тебя!\nВыбери ценности начиная с самой важной:_",
                    "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Здоровье', 'callback_data' => 'Здоровье SexSer3ch']
                            ],
                            [
                                ['text' => 'Карьера', 'callback_data' => 'Карьера SexSer3ch']
                            ],
                            [
                                ['text' => 'Семья', 'callback_data' => 'Семья SexSer3ch']
                            ],
                            [
                                ['text' => 'Богатство', 'callback_data' => 'Богатство SexSer3ch']
                            ],
                            [
                                ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие SexSer3ch']
                            ],
                            [
                                ['text' => 'Спорт', 'callback_data' => 'Спорт SexSer3ch']
                            ],
                            [
                                ['text' => 'Осознанность', 'callback_data' => 'Осознанность SexSer3ch']
                            ],
                            [
                                ['text' => 'Развитие', 'callback_data' => 'Развитие SexSer3ch']
                            ],
                            [
                                ['text' => 'Свобода', 'callback_data' => 'Свобода SexSer3ch']
                            ],
                            [
                                ['text' => 'Миссия', 'callback_data' => 'Миссия SexSer3ch']
                            ],
                            [
                                ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми SexSer3ch']
                            ],
                            [
                                ['text' => 'Любовь', 'callback_data' => 'Любовь SexSer3ch']
                            ],
                            [
                                ['text' => 'Амбиции', 'callback_data' => 'Амбиции SexSer3ch']
                            ],
                            [
                                ['text' => 'Отдых', 'callback_data' => 'Отдых SexSer3ch']
                            ],
                            [
                                ['text' => 'Благодарность', 'callback_data' => 'Благодарность SexSer3ch']
                            ],
                            [
                                ['text' => 'Принятие', 'callback_data' => 'Принятие SexSer3ch']
                            ],
                            [
                                ['text' => '👈 Вернуться в профиль', 'callback_data' => 'profile']
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


    else if (strpos($data['callback_query']['data'], 'add') !== false) {

        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        // Удаляем слово add из профессии
        $prof = $data['callback_query']['data'];
        $prof = preg_replace("/add/i", "", $prof);

        // Удаляем лишние пробелы
        $prof = trim($prof);

        $response = [
            'chat_id' => $user,
            'caption' => "_Виберите уровень владения_ *" . trim($prof) . "*",
            "parse_mode" => "Markdown",
            'protect_content' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_209.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Trainee(Учусь)', 'callback_data' => 'Trainee,' . $prof]  
                    ],
                    [
                        ['text' => 'Junior(Начинающий)', 'callback_data' => 'Junior,' . $prof]  
                    ],
                    [
                        ['text' => 'Middle(Средний уровень)', 'callback_data' => 'Middle,' . $prof]  
                    ],
                    [
                        ['text' => 'Senior(Профессионал)', 'callback_data' => 'Senior,' . $prof]  
                    ],
                    [
                        ['text' => '👈 Вурнуться к выбору навыка', 'callback_data' => 'mySkills']  
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
    else if ($data['callback_query']['data'] == 'learnFinder'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'protect_content' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_216.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'IT, компьютеры, интернет', 'callback_data' => 'ITSkill s1erch']
                    ],
                    [
                        ['text' => 'Администрация, руководство среднего звена', 'callback_data' => 'administrSkill s1erch']
                    ],
                    [
                        ['text' => 'Дизайн, творчество', 'callback_data' => 'designSkill s1erch']
                    ],
                    [
                        ['text' => 'Красота, фитнес, спорт', 'callback_data' => 'beautySkill s1erch']
                    ],
                    [
                        ['text' => 'Культура, музыка, шоу-бизнес', 'callback_data' => 'showbizSkill s1erch']
                    ],
                    [
                        ['text' => 'Логистика, склад, ВЭД', 'callback_data' => 'logistikaSkill s1erch']
                    ],
                    [
                        ['text' => 'Маркетинг, реклама, PR', 'callback_data' => 'marketingSkill s1erch']
                    ],
                    [
                        ['text' => 'Медицина, фармацевтика', 'callback_data' => 'medicinaSkill s1erch']
                    ],
                    [
                        ['text' => 'Недвижимость', 'callback_data' => 'nedvizhimostSkill s1erch']
                    ],
                    [
                        ['text' => 'Образование, наука', 'callback_data' => 'naukaSkill s1erch']
                    ],
                    [
                        ['text' => 'Охрана, безопасность', 'callback_data' => 'ohranaSkill s1erch']
                    ],
                    [
                        ['text' => 'Продажи, закупки', 'callback_data' => 'prodajiSkill s1erch']
                    ],
                    [
                        ['text' => 'Рабочие специальности, производство', 'callback_data' => 'proizvodstvoSkill s1erch']
                    ],
                    /*[
                        ['text' => '🆘 ДОБАВИТЬ СВОЙ НАВЫК 🆘', 'callback_data' => 'imNotFindMySkill s1erch']
                    ],*/
                    [
                        ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                    ],
                    [
                        ['text' => 'Следующая страница 👉', 'callback_data' => 'learnFinder2']
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
    else if ($data['callback_query']['data'] == 'learnFinder2'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'protect_content' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_216.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Розничная торговля', 'callback_data' => 'torgovlyaSkill s1erch']
                    ],
                    [
                        ['text' => 'Секретариат, делопроизводство, АХО', 'callback_data' => 'sekretaringSkill s1erch']
                    ],
                    [
                        ['text' => 'Сельское хозяйство, агробизнес', 'callback_data' => 'agrobiznesSkill s1erch']
                    ],
                    [
                        ['text' => 'СМИ, издательство, полиграфия', 'callback_data' => 'izdatelstvoSkill s1erch']
                    ],
                    [
                        ['text' => 'Страхование', 'callback_data' => 'strahovanieSkill s1erch']
                    ],
                    [
                        ['text' => 'Строительство, архитектура', 'callback_data' => 'stroitelstvoSkill s1erch']
                    ],
                    [
                        ['text' => 'Сфера обслуживания', 'callback_data' => 'obsluzhivanieSkill s1erch']
                    ],
                    [
                        ['text' => 'Телекоммуникации и связь', 'callback_data' => 'telecomunikaciiSkill s1erch']
                    ],
                    [
                        ['text' => 'Топ-менеджмент, руководство высшего звена', 'callback_data' => 'topmenSkill s1erch']
                    ],
                    [
                        ['text' => 'Транспорт, автобизнес', 'callback_data' => 'avtobizSkill s1erch']
                    ],
                    [
                        ['text' => 'Управление персоналом, HR', 'callback_data' => 'hrSkill s1erch']
                    ],
                    [
                        ['text' => 'Финансы, банк', 'callback_data' => 'bankSkill s1erch']
                    ],
                    [
                        ['text' => 'Юриспруденция', 'callback_data' => 'yuristSkill s1erch']
                    ],
                    /*[
                        ['text' => '🆘 ДОБАВИТЬ СВОЙ НАВЫК 🆘', 'callback_data' => 'imNotFindMySkill s1erch']
                    ],*/
                    [
                        ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                    ],
                    [
                        ['text' => '👈 Прошлая страница', 'callback_data' => 'learnFinder']
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
    else if ($data['callback_query']['data'] == 'enterestsFinder'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'protect_content' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_217.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Спорт / Активный отдых 🔻', 'callback_data' => 'Спорт серч']  
                    ],
                    [
                        ['text' => 'Развелчения 🔻', 'callback_data' => 'Развлечения1 серч']  
                    ],
                    [
                        ['text' => 'Бизнес 🔻', 'callback_data' => 'Бизнес серч']  
                    ],
                    [
                        ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
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
                'protect_content' => true,
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
            $response = [
                'chat_id' => $user,
                'protect_content' => true,
                'photo' => curl_file_create("../tgBot/BotPic/post_212.png"),
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>[
                        [
                            ['text' => 'Здоровье', 'callback_data' => 'Здоровье SexSe3rch']
                        ],
                        [
                            ['text' => 'Карьера', 'callback_data' => 'Карьера SexSe3rch']
                        ],
                        [
                            ['text' => 'Семья', 'callback_data' => 'Семья SexSe3rch']
                        ],
                        [
                            ['text' => 'Богатство', 'callback_data' => 'Богатство SexSe3rch']
                        ],
                        [
                            ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие SexSe3rch']
                        ],
                        [
                            ['text' => 'Спорт', 'callback_data' => 'Спорт SexSe3rch']
                        ],
                        [
                            ['text' => 'Осознанность', 'callback_data' => 'Осознанность SexSe3rch']
                        ],
                        [
                            ['text' => 'Развитие', 'callback_data' => 'Развитие SexSe3rch']
                        ],
                        [
                            ['text' => 'Свобода', 'callback_data' => 'Свобода SexSe3rch']
                        ],
                        [
                            ['text' => 'Миссия', 'callback_data' => 'Миссия SexSe3rch']
                        ],
                        [
                            ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми SexSe3rch']
                        ],
                        [
                            ['text' => 'Любовь', 'callback_data' => 'Любовь SexSe3rch']
                        ],
                        [
                            ['text' => 'Амбиции', 'callback_data' => 'Амбиции SexSe3rch']
                        ],
                        [
                            ['text' => 'Отдых', 'callback_data' => 'Отдых SexSe3rch']
                        ],
                        [
                            ['text' => 'Благодарность', 'callback_data' => 'Благодарность SexSe3rch']
                        ],
                        [
                            ['text' => 'Принятие', 'callback_data' => 'Принятие SexSe3rch']
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
            $response = [
                'chat_id' => $user,
                'protect_content' => true,
                'photo' => curl_file_create("../tgBot/BotPic/post_212.png"),
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>[
                        [
                            ['text' => 'Здоровье', 'callback_data' => 'Здоровье SexSe3rch']
                        ],
                        [
                            ['text' => 'Карьера', 'callback_data' => 'Карьера SexSe3rch']
                        ],
                        [
                            ['text' => 'Семья', 'callback_data' => 'Семья SexSe3rch']
                        ],
                        [
                            ['text' => 'Богатство', 'callback_data' => 'Богатство SexSe3rch']
                        ],
                        [
                            ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие SexSe3rch']
                        ],
                        [
                            ['text' => 'Спорт', 'callback_data' => 'Спорт SexSe3rch']
                        ],
                        [
                            ['text' => 'Осознанность', 'callback_data' => 'Осознанность SexSe3rch']
                        ],
                        [
                            ['text' => 'Развитие', 'callback_data' => 'Развитие SexSe3rch']
                        ],
                        [
                            ['text' => 'Свобода', 'callback_data' => 'Свобода SexSe3rch']
                        ],
                        [
                            ['text' => 'Миссия', 'callback_data' => 'Миссия SexSe3rch']
                        ],
                        [
                            ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми SexSe3rch']
                        ],
                        [
                            ['text' => 'Любовь', 'callback_data' => 'Любовь SexSe3rch']
                        ],
                        [
                            ['text' => 'Амбиции', 'callback_data' => 'Амбиции SexSe3rch']
                        ],
                        [
                            ['text' => 'Отдых', 'callback_data' => 'Отдых SexSe3rch']
                        ],
                        [
                            ['text' => 'Благодарность', 'callback_data' => 'Благодарность SexSe3rch']
                        ],
                        [
                            ['text' => 'Принятие', 'callback_data' => 'Принятие SexSe3rch']
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
                'protect_content' => true,
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
                'protect_content' => true,
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
    else if ($data['callback_query']['data'] == 'clientsFinder'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'protect_content' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_214.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'IT, компьютеры, интернет', 'callback_data' => 'ITSkill ser1ch']
                    ],
                    [
                        ['text' => 'Администрация, руководство среднего звена', 'callback_data' => 'administrSkill ser1ch']
                    ],
                    [
                        ['text' => 'Дизайн, творчество', 'callback_data' => 'designSkill ser1ch']
                    ],
                    [
                        ['text' => 'Красота, фитнес, спорт', 'callback_data' => 'beautySkill ser1ch']
                    ],
                    [
                        ['text' => 'Культура, музыка, шоу-бизнес', 'callback_data' => 'showbizSkill ser1ch']
                    ],
                    [
                        ['text' => 'Логистика, склад, ВЭД', 'callback_data' => 'logistikaSkill ser1ch']
                    ],
                    [
                        ['text' => 'Маркетинг, реклама, PR', 'callback_data' => 'marketingSkill ser1ch']
                    ],
                    [
                        ['text' => 'Медицина, фармацевтика', 'callback_data' => 'medicinaSkill ser1ch']
                    ],
                    [
                        ['text' => 'Недвижимость', 'callback_data' => 'nedvizhimostSkill ser1ch']
                    ],
                    [
                        ['text' => 'Образование, наука', 'callback_data' => 'naukaSkill ser1ch']
                    ],
                    [
                        ['text' => 'Охрана, безопасность', 'callback_data' => 'ohranaSkill ser1ch']
                    ],
                    [
                        ['text' => 'Продажи, закупки', 'callback_data' => 'prodajiSkill ser1ch']
                    ],
                    [
                        ['text' => 'Рабочие специальности, производство', 'callback_data' => 'proizvodstvoSkill ser1ch']
                    ],
                    /*[
                        ['text' => '🆘 ДОБАВИТЬ СВОЙ НАВЫК 🆘', 'callback_data' => 'imNotFindMySkill ser1ch']
                    ],*/
                    [
                        ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                    ],
                    [
                        ['text' => 'Следующая страница 👉', 'callback_data' => 'clientsFinder2']
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
    else if ($data['callback_query']['data'] == 'clientsFinder2'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'protect_content' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_214.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Розничная торговля', 'callback_data' => 'torgovlyaSkill ser1ch']
                    ],
                    [
                        ['text' => 'Секретариат, делопроизводство, АХО', 'callback_data' => 'sekretaringSkill ser1ch']
                    ],
                    [
                        ['text' => 'Сельское хозяйство, агробизнес', 'callback_data' => 'agrobiznesSkill ser1ch']
                    ],
                    [
                        ['text' => 'СМИ, издательство, полиграфия', 'callback_data' => 'izdatelstvoSkill ser1ch']
                    ],
                    [
                        ['text' => 'Страхование', 'callback_data' => 'strahovanieSkill ser1ch']
                    ],
                    [
                        ['text' => 'Строительство, архитектура', 'callback_data' => 'stroitelstvoSkill ser1ch']
                    ],
                    [
                        ['text' => 'Сфера обслуживания', 'callback_data' => 'obsluzhivanieSkill ser1ch']
                    ],
                    [
                        ['text' => 'Телекоммуникации и связь', 'callback_data' => 'telecomunikaciiSkill ser1ch']
                    ],
                    [
                        ['text' => 'Топ-менеджмент, руководство высшего звена', 'callback_data' => 'topmenSkill ser1ch']
                    ],
                    [
                        ['text' => 'Транспорт, автобизнес', 'callback_data' => 'avtobizSkill ser1ch']
                    ],
                    [
                        ['text' => 'Управление персоналом, HR', 'callback_data' => 'hrSkill ser1ch']
                    ],
                    [
                        ['text' => 'Финансы, банк', 'callback_data' => 'bankSkill ser1ch']
                    ],
                    [
                        ['text' => 'Юриспруденция', 'callback_data' => 'yuristSkill ser1ch']
                    ],
                    /*[
                        ['text' => '🆘 ДОБАВИТЬ СВОЙ НАВЫК 🆘', 'callback_data' => 'imNotFindMySkill ser1ch']
                    ],*/
                    [
                        ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'peopleFinder']
                    ],
                    [
                        ['text' => '👈 Прошлая страница', 'callback_data' => 'clientsFinder']
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
    else if ($data['callback_query']['data'] == 'skillsFinder'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'protect_content' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_215.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'IT, компьютеры, интернет', 'callback_data' => 'ITSkill-find']
                    ],
                    [
                        ['text' => 'Администрация, руководство среднего звена', 'callback_data' => 'administrSkill-find']
                    ],
                    [
                        ['text' => 'Дизайн, творчество', 'callback_data' => 'designSkill-find']
                    ],
                    [
                        ['text' => 'Красота, фитнес, спорт', 'callback_data' => 'beautySkill-find']
                    ],
                    [
                        ['text' => 'Культура, музыка, шоу-бизнес', 'callback_data' => 'showbizSkill-find']
                    ],
                    [
                        ['text' => 'Логистика, склад, ВЭД', 'callback_data' => 'logistikaSkill-find']
                    ],
                    [
                        ['text' => 'Маркетинг, реклама, PR', 'callback_data' => 'marketingSkill-find']
                    ],
                    [
                        ['text' => 'Медицина, фармацевтика', 'callback_data' => 'medicinaSkill-find']
                    ],
                    [
                        ['text' => 'Недвижимость', 'callback_data' => 'nedvizhimostSkill-find']
                    ],
                    [
                        ['text' => 'Образование, наука', 'callback_data' => 'naukaSkill-find']
                    ],
                    [
                        ['text' => 'Охрана, безопасность', 'callback_data' => 'ohranaSkill-find']
                    ],
                    [
                        ['text' => 'Продажи, закупки', 'callback_data' => 'prodajiSkill-find']
                    ],
                    [
                        ['text' => 'Рабочие специальности, производство', 'callback_data' => 'proizvodstvoSkill-find']
                    ],
                    [
                        ['text' => '👈 Вернуться в "Поиск людей"', 'callback_data' => 'peopleFinder'],
                        ['text' => 'Следующая страница 👉', 'callback_data' => 'skillsFinder2']
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
    else if ($data['callback_query']['data'] == 'skillsFinder2'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'protect_content' => true,
            'photo' => curl_file_create("../tgBot/BotPic/post_215.png"),
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text' => 'Розничная торговля', 'callback_data' => 'torgovlyaSkill-find']
                    ],
                    [
                        ['text' => 'Секретариат, делопроизводство, АХО', 'callback_data' => 'sekretaringSkill-find']
                    ],
                    [
                        ['text' => 'Сельское хозяйство, агробизнес', 'callback_data' => 'agrobiznesSkill-find']
                    ],
                    [
                        ['text' => 'СМИ, издательство, полиграфия', 'callback_data' => 'izdatelstvoSkill-find']
                    ],
                    [
                        ['text' => 'Страхование', 'callback_data' => 'strahovanieSkill-find']
                    ],
                    [
                        ['text' => 'Строительство, архитектура', 'callback_data' => 'stroitelstvoSkill-find']
                    ],
                    [
                        ['text' => 'Сфера обслуживания', 'callback_data' => 'obsluzhivanieSkill-find']
                    ],
                    [
                        ['text' => 'Телекоммуникации и связь', 'callback_data' => 'telecomunikaciiSkill-find']
                    ],
                    [
                        ['text' => 'Топ-менеджмент, руководство высшего звена', 'callback_data' => 'topmenSkill-find']
                    ],
                    [
                        ['text' => 'Транспорт, автобизнес', 'callback_data' => 'avtobizSkill-find']
                    ],
                    [
                        ['text' => 'Управление персоналом, HR', 'callback_data' => 'hrSkill-find']
                    ],
                    [
                        ['text' => 'Финансы, банк', 'callback_data' => 'bankSkill-find']
                    ],
                    [
                        ['text' => 'Юриспруденция', 'callback_data' => 'yuristSkill-find']
                    ],
                    [
                        ['text' => '👈 Предыдущая страница', 'callback_data' => 'skillsFinder'],
                        ['text' => '👈 Вернуться в "Поиск людей"', 'callback_data' => 'peopleFinder']
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
    else if ($data['callback_query']['data'] == 'peopleFinder'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $response = [
            'chat_id' => $user,
            'protect_content' => true,
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
    else if (strpos($data['callback_query']['data'], 'Трейни') !== false || strpos($data['callback_query']['data'], 'Джуниор') !== false || strpos($data['callback_query']['data'], 'Мидл') !== false || strpos($data['callback_query']['data'], 'Сеньор') !== false){
        $user = $func['from']['id'];
        $lvl = explode("," , $data['callback_query']['data']);

        if ($lvl[0] == "Трейни") {
            $level = "Junior";
        }else if($lvl[0] == "Джуниор"){
            $level = "Trainee";
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
        $needs = mysqli_fetch_row($needsCheck);
        $skills = mysqli_fetch_row($skillsCheck);
        $ints = mysqli_fetch_row($intsCheck);

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
            $method = 'editMessageText';
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
                    if ($value != $user) {
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
                    'caption' => "_Мы не нашли людей, которым нужен_ *".$lvl[1]."* _,но когда они появятся - вы получите уведомление_",
                    "parse_mode" => "Markdown",
                    'protect_content' => true,
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
                $profCheck = mysqli_query ($con, "SELECT `name`, `userAge`, `surname`, `userPhoto`, `tgUserName` FROM `MainInfo` WHERE userID='".$ids[0]."' ");
                $prof = mysqli_fetch_array($profCheck);

                // Если кол-во найденных профилей = 1
                if ($counter == 1) {
                    if (empty($prof['userPhoto'])) {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
            $prof = $data['callback_query']['data'];
            $prof = preg_replace("/ser2ch/i", "", $prof);

            // Удаляем лишние пробелы
            $prof = trim($prof);

            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $response = [
                'chat_id' => $user,
                'caption' => "_Виберите уровень владения_ " ."*". $prof ."*",
                "parse_mode" => "Markdown",
                'protect_content' => true,
                'photo' => curl_file_create("../tgBot/BotPic/post_209.png"),
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>[
                        [
                            ['text' => 'Trainee(Учусь)', 'callback_data' => 'Трейни,' . $prof]  
                        ],
                        [
                            ['text' => 'Junior(Начинающий)', 'callback_data' => 'Джуниор,' . $prof]  
                        ],
                        [
                            ['text' => 'Middle(Средний уровень)', 'callback_data' => 'Мидл,' . $prof]  
                        ],
                        [
                            ['text' => 'Senior(Профессионал)', 'callback_data' => 'Сеньор,' . $prof]  
                        ],
                        [
                            ['text' => '👈 Вурнуться к выбору категории', 'callback_data' => 'clientsFinder']  
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
        $method = 'sendMessage';
        $send_data = [
            'text' => 'Отлично! Теперь мне нужно узнать ваше местоположение, чтоб добавить вас в чат для обучения',
            'reply_markup' => [
                resize_keyboard =>true,
                one_time_keyboard => true,
                'keyboard' => [
                    [
                        ['text' => 'Поделиться местоположением', request_location => true]
                    ]
                ]
            ]
        ];
    }
    // Поиск клиентов в регистрации
    else if (strpos($data['callback_query']['data'], 'secondch') !== false) {
        $user = $func['from']['id'];
        // Удаляем ch из профессии
        $chWord = $data['callback_query']['data'];
        $word = preg_replace("/secondch/i", "", $chWord);

        $skill = trim($word);

        // Пушим в БД
        mysqli_query ($con, "UPDATE `Skills` SET s1 = '".$skill."', lvl1 = 'Senior' WHERE userID = ".$user." ");
        mysqli_query ($con, "UPDATE `SkillAdds` SET search1 = 'Ищу клиентов' WHERE userID = ".$user." ");
        mysqli_query ($con, "INSERT INTO `Searches`(`userID`, `searchClients`, `active`, `Date`) VALUES ('".$user."', '".$skill."', '1', NOW()) ");

        // Выводим человеку сообщение об успешности операции и Спрашиваем локацию
        $method = 'sendMessage';
        $send_data = [
            'text' => 'Отлично! Теперь мне нужно узнать ваше местоположение, чтоб добавить вас в чат для поиска клиентов',
            'reply_markup' => [
                resize_keyboard =>true,
                one_time_keyboard => true,
                'keyboard' => [
                    [
                        ['text' => 'Поделиться местоположением', request_location => true]
                    ]
                ]
            ]
        ];
    }

    // Поиск специалиста в регистрации
    else if (strpos($data['callback_query']['data'], 'thirdch') !== false) {
        $user = $func['from']['id'];
        // Удаляем ch из профессии
        $chWord = $data['callback_query']['data'];
        $word = preg_replace("/thirdch/i", "", $chWord);

        $skill = trim($word);

        // Пушим кого человек ищет в БД
        mysqli_query ($con, "INSERT INTO `Searches`(`userID`, `searchSpecialist`, `active`, `Date`) VALUES ('".$user."', '".$skill."', '1', NOW()) ");

        // Выводим человеку сообщение об успешности операции и Спрашиваем локацию
        $method = 'sendMessage';
        $send_data = [
            'text' => 'Отлично! Теперь мне нужно узнать ваше местоположение, чтоб добавить вас в чат для поиска специалиста',
            'reply_markup' => [
                resize_keyboard =>true,
                one_time_keyboard => true,
                'keyboard' => [
                    [
                        ['text' => 'Поделиться местоположением', request_location => true]
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

        // Удаляем ch из ценностей
        $chWord = $data['callback_query']['data'];
        $word = preg_replace("/fourthch/i", "", $chWord);
        $word = trim($word);

        if (trim($needs[0]) == $word or trim($needs[1]) == $word or trim($needs[2]) == $word or trim($needs[3]) == $word or trim($needs[4]) == $word or strpos($needs[5], $word) !== false) {
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
                    $msgArray .= trim($value) . "\n";
                }
            }
            $method = 'editMessageText';
            $send_data = [
                'text' => "Упс! Такая ценность у вас уже есть.\n\nМои ценности:\n" . $msgArray . "\n\nВыберите 5 ценностей начиная с самой важной:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Здоровье', 'callback_data' => 'Здоровье fourthch']
                        ],
                        [
                            ['text' => 'Карьера', 'callback_data' => 'Карьера fourthch']
                        ],
                        [
                            ['text' => 'Семья', 'callback_data' => 'Семья fourthch']
                        ],
                        [
                            ['text' => 'Богатство', 'callback_data' => 'Богатство fourthch']
                        ],
                        [
                            ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие fourthch']
                        ],
                        [
                            ['text' => 'Спорт', 'callback_data' => 'Спорт fourthch']
                        ],
                        [
                            ['text' => 'Осознанность', 'callback_data' => 'Осознанность fourthch']
                        ],
                        [
                            ['text' => 'Развитие', 'callback_data' => 'Развитие fourthch']
                        ],
                        [
                            ['text' => 'Свобода', 'callback_data' => 'Свобода fourthch']
                        ],
                        [
                            ['text' => 'Миссия', 'callback_data' => 'Миссия fourthch']
                        ],
                        [
                            ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми fourthch']
                        ],
                        [
                            ['text' => 'Любовь', 'callback_data' => 'Любовь fourthch']
                        ],
                        [
                            ['text' => 'Амбиции', 'callback_data' => 'Амбиции fourthch']
                        ],
                        [
                            ['text' => 'Отдых', 'callback_data' => 'Отдых fourthch']
                        ],
                        [
                            ['text' => 'Благодарность', 'callback_data' => 'Благодарность fourthch']
                        ],
                        [
                            ['text' => 'Принятие', 'callback_data' => 'Принятие fourthch']
                        ],
                        [
                            ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);
            return;
        }else{
            // Если это будет первая ценность в профиле
            if (empty($needs[0]) and empty($needs[1]) and empty($needs[2]) and empty($needs[3]) and empty($needs[4]) and empty($needs[5])) {
                
                // Пушим новую ценность в БД
                mysqli_query ($con, "UPDATE `Needs` SET n1 = '".$word."' WHERE userID = ".$user." ");                

                $method = 'editMessageText';
                $send_data = [
                    'text' => "Просмотрите все ценности и найдите самую важную для вас!\n\nМои ценности:\n" . "\u{0031}\u{FE0F}\u{20E3}" . " - " . trim($word) . "\n\nВыберите 5 ценностей начиная с самой важной:",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Здоровье', 'callback_data' => 'Здоровье fourthch']
                            ],
                            [
                                ['text' => 'Карьера', 'callback_data' => 'Карьера fourthch']
                            ],
                            [
                                ['text' => 'Семья', 'callback_data' => 'Семья fourthch']
                            ],
                            [
                                ['text' => 'Богатство', 'callback_data' => 'Богатство fourthch']
                            ],
                            [
                                ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие fourthch']
                            ],
                            [
                                ['text' => 'Спорт', 'callback_data' => 'Спорт fourthch']
                            ],
                            [
                                ['text' => 'Осознанность', 'callback_data' => 'Осознанность fourthch']
                            ],
                            [
                                ['text' => 'Развитие', 'callback_data' => 'Развитие fourthch']
                            ],
                            [
                                ['text' => 'Свобода', 'callback_data' => 'Свобода fourthch']
                            ],
                            [
                                ['text' => 'Миссия', 'callback_data' => 'Миссия fourthch']
                            ],
                            [
                                ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми fourthch']
                            ],
                            [
                                ['text' => 'Любовь', 'callback_data' => 'Любовь fourthch']
                            ],
                            [
                                ['text' => 'Амбиции', 'callback_data' => 'Амбиции fourthch']
                            ],
                            [
                                ['text' => 'Отдых', 'callback_data' => 'Отдых fourthch']
                            ],
                            [
                                ['text' => 'Благодарность', 'callback_data' => 'Благодарность fourthch']
                            ],
                            [
                                ['text' => 'Принятие', 'callback_data' => 'Принятие fourthch']
                            ],
                            [
                                ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu']
                            ]
                        ]
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

                    $method = 'editMessageText';
                    $send_data = [
                        'text' => "Мои ценности:\n" . $msgArray . "\n\nВы получили 100 монет за добавление 5 ценностей. Узнать кол-во монет и как их получить, вы можете нажав на кнопку 'Монеты' в главном меню",
                    ];
                    $send_data['chat_id'] = $func['message']['chat']['id'];
                    $send_data['message_id'] = $func['message']['message_id'];
                    sendTelegram($method, $send_data);
                    // Выводим человеку сообщение об успешности операции и Спрашиваем локацию
                    $method = 'sendMessage';
                    $send_data = [
                        'text' => 'Отлично! Теперь мне нужно узнать ваше местоположение, чтоб добавить вас в чат для поиска специалиста',
                        'reply_markup' => [
                            resize_keyboard =>true,
                            one_time_keyboard => true,
                            'keyboard' => [
                                [
                                    ['text' => 'Поделиться местоположением', request_location => true]
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
                        $msgArray .= trim($value) . "\n";
                    }
                }

                $method = 'editMessageText';
                $send_data = [
                    'text' => "Просмотрите все ценности и найдите самую важную для вас!\n\nМои ценности:\n" . $msgArray . "\n\nВыберите 5 ценностей начиная с самой важной:",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Здоровье', 'callback_data' => 'Здоровье fourthch']
                            ],
                            [
                                ['text' => 'Карьера', 'callback_data' => 'Карьера fourthch']
                            ],
                            [
                                ['text' => 'Семья', 'callback_data' => 'Семья fourthch']
                            ],
                            [
                                ['text' => 'Богатство', 'callback_data' => 'Богатство fourthch']
                            ],
                            [
                                ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие fourthch']
                            ],
                            [
                                ['text' => 'Спорт', 'callback_data' => 'Спорт fourthch']
                            ],
                            [
                                ['text' => 'Осознанность', 'callback_data' => 'Осознанность fourthch']
                            ],
                            [
                                ['text' => 'Развитие', 'callback_data' => 'Развитие fourthch']
                            ],
                            [
                                ['text' => 'Свобода', 'callback_data' => 'Свобода fourthch']
                            ],
                            [
                                ['text' => 'Миссия', 'callback_data' => 'Миссия fourthch']
                            ],
                            [
                                ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми fourthch']
                            ],
                            [
                                ['text' => 'Любовь', 'callback_data' => 'Любовь fourthch']
                            ],
                            [
                                ['text' => 'Амбиции', 'callback_data' => 'Амбиции fourthch']
                            ],
                            [
                                ['text' => 'Отдых', 'callback_data' => 'Отдых fourthch']
                            ],
                            [
                                ['text' => 'Благодарность', 'callback_data' => 'Благодарность fourthch']
                            ],
                            [
                                ['text' => 'Принятие', 'callback_data' => 'Принятие fourthch']
                            ],
                            [
                                ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
            }
        }
    }
}
    // Поиск с кем вместе обучаться
    else if (strpos($data['callback_query']['data'], 's2erch') !== false) {
        // Узнаем сколько у человека навыков введено в профиле
        $user = $func['from']['id'];
        $intsCheck = mysqli_query($con, "SELECT `name`, `surname`, `sex`, `userAge` FROM `MainInfo` WHERE userID = " . $user . " ");
        $ints = mysqli_fetch_array($intsCheck);

        $interestsCheck = mysqli_query($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5` FROM `Interests` WHERE userID = " . $user . " ");
        $interests = mysqli_fetch_row($interestsCheck);

        $skillsCheck = mysqli_query($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5` FROM `Skills` WHERE userID = " . $user . " ");
        $skills = mysqli_fetch_row($skillsCheck);

        $needsCheck = mysqli_query($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5` FROM `Needs` WHERE userID = " . $user . " ");
        $needs = mysqli_fetch_row($needsCheck);

            // Если навыков меньше 5, тогда выводим сообщение, что нужно еще ввести интересы, чтоб 
        if (empty($skills)) {
            $number = 0;
            foreach ($skills as $key => $value) {
                if ($value == "") {
                    $number += 1;
                }
            }
            $method = 'editMessageText';
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
            $search = preg_replace("/s2erch/i", "", $data['callback_query']['data']);
            $search = trim($search);

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
                        if ($value != $user) {
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
                        'protect_content' => true,
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
                    mysqli_query($con, "UPDATE `TrackingMenu` SET searchIDs = '" . $userNames . "' WHERE userID = " . $user . " ");

                    $ids = explode(',', $userNames);

                    // Выводим данные первого человека
                    $profCheck = mysqli_query($con, "SELECT `name`, `userAge`, `surname`, `userPhoto`, `tgUserName` FROM `MainInfo` WHERE userID='" . $ids[0] . "' ");
                    $prof = mysqli_fetch_array($profCheck);

                    // Если кол-во найденных профилей = 1
                if ($counter == 1) {
                    if (empty($prof['userPhoto'])) {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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

        $statCheck = mysqli_query ($con, "SELECT `coins`, `referals`, `regDate`, `userRank` FROM `MainInfo` WHERE userID='".$user."' ");
        $stat = mysqli_fetch_array($statCheck);
        
        $response = [
            'chat_id' => $user,
            'caption' => "📈 Моя статистика:\n\nМой ранг: ".$stat['userRank']."\nМои монеты: ".$stat['coins']."\nМои рефералы: ".$stat['referals']."\nДата регистрации: ".$stat['regDate'],
            'photo' => curl_file_create("../tgBot/BotPic/post_223.png"),
            'protect_content' => true,
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
            'protect_content' => true,
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
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);
       
        $response = [
            'chat_id' => $user,
            'photo' => curl_file_create("../tgBot/BotPic/post_101.jpg"),
            'protect_content' => true,
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
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
    else if ($data['callback_query']['data'] == 'mySocial'){
        // Удаляем старое сообщение
        $user = $func['from']['id'];
        $send_data['message_id'] = $data['callback_query']['message']['message_id'];
        $send_data['chat_id'] = $user;
        sendTelegram('deleteMessage', $send_data);

        $userSocials = mysqli_query ($con, "SELECT `inst`, `facebook`, `viber`, `tiktok`, `whatsapp`, `anotherSocials` FROM `Socials` WHERE userID='".$user."' ");
        $socials = mysqli_fetch_array($userSocials);
       
        $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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
                'text' => 'Добавить anotherSocial:',
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
                'text' => 'Изменить мой anotherSocial ' . $prof['anotherSocials'],
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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
            'protect_content' => true,
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

        $method = 'sendMessage';
            $send_data = [
                'text' => 'Отправь мне свой tiktok ник:',
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

                $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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

                $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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

                $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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

                $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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

                $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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

                $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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
            $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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

            $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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
            
            $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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
            
            $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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
            
            $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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
            
            $response = [
            'chat_id' => $user,
            'caption' => "Instagram - " . $socials['inst'] . "\n" . "Tik-Tok - " . $socials['tiktok'] . "\n" . "Facebook - " . $socials['facebook'] . "\n" . "Viber - " . $socials['viber'] . "\n" . "WhatsApp - " . $socials['whatsapp'] . "\n" . "Другая - " . $socials['anotherSocials'],
            'photo' => curl_file_create("../tgBot/BotPic/post_196.png"),
            'protect_content' => true,
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
    // Поиск второй половинки
    else if (strpos($data['callback_query']['data'], 'SexSe3rch') !== false) {
        // Узнаем сколько у человека навыков введено в профиле
        $user = $func['from']['id'];
        $intsCheck = mysqli_query($con, "SELECT `name`, `surname`, `sex`, `userAge` FROM `MainInfo` WHERE userID = " . $user . " ");
        $ints = mysqli_fetch_array($intsCheck);

        $interestsCheck = mysqli_query($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5` FROM `Interests` WHERE userID = " . $user . " ");
        $interests = mysqli_fetch_row($interestsCheck);

        $skillsCheck = mysqli_query($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5` FROM `Skills` WHERE userID = " . $user . " ");
        $skills = mysqli_fetch_row($skillsCheck);

        $needsCheck = mysqli_query($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5` FROM `Needs` WHERE userID = " . $user . " ");
        $needs = mysqli_fetch_row($needsCheck);

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
        $search = preg_replace("/SexSe3rch/i", "", $data['callback_query']['data']);
        $search = trim($search);

        // Если в профиле хоть что-то не заполнено, тогда даем человеку возможность видеть только новых людей и выводим сообщение с кнопкой ведущей в профиль
        if (empty($needs) or empty($ints['name']) or empty($ints['surname']) or empty($interests) or empty($skills) or empty($ints['sex']) or empty($ints['userAge'])) {
            $method = 'editMessageText';
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
                    if ($value != $user) {
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
                    'protect_content' => true,
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
                    $profCheck = mysqli_query($con, "SELECT `name`, `userAge`, `surname`, `userPhoto`, `tgUserName` FROM `MainInfo` WHERE userID='" . $ids[0] . "' ");
                    $prof = mysqli_fetch_array($profCheck);

                // Если кол-во найденных профилей = 1
                if ($counter == 1) {
                    if (empty($prof['userPhoto'])) {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
        // Поиск в БД такого навыка
        $user = $func['from']['id'];
        $intsCheck = mysqli_query ($con, "SELECT `interest1`,`interest2`,`interest3`,`interest4`,`interest5`,`interest6` FROM `Interests` WHERE userID = ".$user." ");
        $ints = mysqli_fetch_row($intsCheck);

        // Удаляем слово int из профессии
        $word = $data['callback_query']['data'];
        $int = preg_replace("/int/i", "", $word);

        // Если такое хобби у человека уже есть
        if ($ints[0] == $int or $ints[1] == $int or $ints[2] == $int or $ints[3] == $int or $ints[4] == $int or strpos($ints[5], $int) !== false) {
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Упс! Кажется ' . trim($int) . " уже есть у вас в профиле",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Выбрать другой интерес', 'callback_data' => 'pushInterests']
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
        }else{
            // Если это будет первый интерес в профиле
            if (empty($ints[0])) {
                // Пушим новый интерес в БД
                $updateDB = mysqli_query ($con, "UPDATE `Interests` SET interest1 = '".$int."' WHERE userID = ".$user." ");

                // Выводим человеку сообщение об успешности операции и даем возможность добавить еще интересы
                $method = 'editMessageText';
                $send_data = [
                    'text' => "Отлично! Вы добавили ".$int." в список своих интересов",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Добавить еще интересы', 'callback_data' => 'pushInterests']
                            ],
                            [
                                ['text' => '👈 Вернуться к "Мои интересы"', 'callback_data' => 'myInterests']
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
                
            }
            // Если какие-то интересы в профиле у человека уже были
            else{
                if (empty($ints[1])) {
                    // Пушим новый интерес в БД
                    $updateDB = mysqli_query ($con, "UPDATE `Interests` SET interest2 = '".$int."' WHERE userID = ".$user." ");

                    $method = 'editMessageText';
                    $send_data = [
                        'text' => "Отлично! Вы добавили ".$int." в список своих интересов",
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Добавить еще интересы', 'callback_data' => 'pushInterests']
                                ],
                                [
                                    ['text' => '👈 Вернуться назад', 'callback_data' => 'myInterests']
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
                }else if (empty($ints[2])) {
                    // Пушим новый интерес в БД
                    $updateDB = mysqli_query ($con, "UPDATE `Interests` SET interest3 = '".$int."' WHERE userID = ".$user." ");

                    $method = 'editMessageText';
                    $send_data = [
                        'text' => "Отлично! Вы добавили ".$int." в список своих интересов",
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Добавить еще интересы', 'callback_data' => 'pushInterests']
                                ],
                                [
                                    ['text' => '👈 Вернуться назад', 'callback_data' => 'myInterests']
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
                }else if (empty($ints[3])) {
                    // Пушим новый интерес в БД
                    $updateDB = mysqli_query ($con, "UPDATE `Interests` SET interest4 = '".$int."' WHERE userID = ".$user." ");
                    $method = 'editMessageText';
                    $send_data = [
                        'text' => "Отлично! Вы добавили ".$int." в список своих интересов",
                        'reply_markup' => [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Добавить еще интересы', 'callback_data' => 'pushInterests']
                                ],
                                [
                                    ['text' => '👈 Вернуться назад', 'callback_data' => 'myInterests']
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
                }else if (empty($ints[4])) {
                    // Пушим новый интерес в БД
                    $updateDB = mysqli_query ($con, "UPDATE `Interests` SET interest5 = '".$int."' WHERE userID = ".$user." ");
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

                        // Выводим человеку сообщение об успешности операции и даем возможность добавить еще интересы
                        $method = 'editMessageText';
                        $send_data = [
                            'text' => "Отлично! Вы добавили ".$int." в список своих интересов\n\nВы получили 100 монет за добавление 5 интересов. Узнать кол-во монет и как их получить, вы можете нажав на кнопку 'Монеты' в главном меню",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Добавить еще интересы', 'callback_data' => 'pushInterests']
                                    ],
                                    [
                                        ['text' => '👈 Вернуться назад', 'callback_data' => 'myInterests']
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
                }
            } 
        }
    }

    // Поиск с кем интересно провести время
    else if (strpos($data['callback_query']['data'], 'serch') !== false) {
        // Узнаем сколько у человека навыков введено в профиле
        $user = $func['from']['id'];
        $intsCheck = mysqli_query($con, "SELECT `name`, `surname`, `sex`, `userAge` FROM `MainInfo` WHERE userID = " . $user . " ");
        $ints = mysqli_fetch_array($intsCheck);

        $interestsCheck = mysqli_query($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5` FROM `Interests` WHERE userID = " . $user . " ");
        $interests = mysqli_fetch_row($interestsCheck);

        $skillsCheck = mysqli_query($con, "SELECT `s1`, `s2`, `s3`, `s4`, `s5` FROM `Skills` WHERE userID = " . $user . " ");
        $skills = mysqli_fetch_row($skillsCheck);

        $needsCheck = mysqli_query($con, "SELECT `n1`, `n2`, `n3`, `n4`, `n5` FROM `Needs` WHERE userID = " . $user . " ");
        $needs = mysqli_fetch_row($needsCheck);

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
        $search = preg_replace("/serch/i", "", $data['callback_query']['data']);
        $search = trim($search);

        // Если в профиле хоть что-то не заполнено, тогда даем человеку возможность видеть только новых людей и выводим сообщение с кнопкой ведущей в профиль
        if (empty($needs) or empty($ints['name']) or empty($ints['surname']) or empty($interests) or empty($skills) or empty($ints['sex']) or empty($ints['userAge'])) {
            $method = 'editMessageText';
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
        }else{
            // Поиск в БД по запросу
            $skillCheck = mysqli_query ($con, "SELECT `userID` FROM `Interests` WHERE (`interest1` LIKE '%" . $search . "%') OR (`interest2` LIKE '%" . $search . "%') OR (`interest3` LIKE '%" . $search . "%') OR (`interest4` LIKE '%" . $search . "%') OR (`interest5` LIKE '%" . $search . "%') ");
            $skill = mysqli_fetch_array($skillCheck);

            $userNames = "";
            $counter = 0;

            foreach ($skillCheck as $key => $value) {
                mysqli_fetch_array($value);
                foreach ($value as $key => $value) {
                    if ($value != $user) {
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
                    'protect_content' => true,
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
                $profCheck = mysqli_query ($con, "SELECT `name`, `userAge`, `surname`, `userPhoto`, `tgUserName` FROM `MainInfo` WHERE userID='" . $ids[0] . "' ");
                $prof = mysqli_fetch_array($profCheck);

                // Если кол-во найденных профилей = 1
                if ($counter == 1) {
                    if (empty($prof['userPhoto'])) {
                        $method = 'sendMessage';
                        $send_data = [
                            'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'reply_markup' => [
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
                            'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *1*" . " _из_ " . "*" . $counter . "*",
                            "parse_mode" => "Markdown",
                            'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                            'reply_markup'=>json_encode([
                                'inline_keyboard'=>[
                                    [
                                        ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $ids[0]]  
                                    ],
                                    [
                                        ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$ids[0]]  
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
        $profCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `userPhoto`, `tgUserName` FROM `MainInfo` WHERE userID='".$id."' ");
        $prof = mysqli_fetch_array($profCheck);

        // Если это первый профиль в списке, то не даем возможности листать назад
        if ($num == 1) {
            // Проверяем наличие фото в профиле
            if (empty($prof['userPhoto'])) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *" . $num . "*" . " _из_ " . "*" . $counter . "*",
                    "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id] 
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$id]  
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
                    'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *" . $num . "*" . " _из_ " . "*" . $counter . "*",
                    "parse_mode" => "Markdown",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id]
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$id]  
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
                    'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *" . $num . "*" . " _из_ " . "*" . $counter . "*",
                    "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id],
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id]  
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$id]  
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
                    'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *" . $num . "*" . " _из_ " . "*" . $counter . "*",
                    "parse_mode" => "Markdown",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id],
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id]  
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$id]  
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
                    'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *" . $num . "*" . " _из_ " . "*" . $counter . "*",
                    "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id] 
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$id]  
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
                    'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *" . $num . "*" . " _из_ " . "*" . $counter . "*",
                    "parse_mode" => "Markdown",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id] 
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$id]  
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
                    'text' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *" . $num . "*" . " _из_ " . "*" . $counter . "*",
                    "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id],
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id]  
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$id]  
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
                    'caption' => "_Имя и Фамилия:_ ".$prof['name']." ".$prof['surname']."\n\n_Возраст:_ ".$prof['userAge']."\n\n🔎 _Профиль_ *" . $num . "*" . " _из_ " . "*" . $counter . "*",
                    "parse_mode" => "Markdown",
                    'photo' => curl_file_create("../tgBot/userPhotos/".$prof['userPhoto']),
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>[
                            [
                                ['text' => 'Предыдущий профиль', 'callback_data' => 'prevProfile ' . $id],
                                ['text' => 'Следующий профиль', 'callback_data' => 'nextProfile ' . $id]  
                            ],
                            [
                                ['text' => 'Связаться с человеком', 'url' => 'tg://user?id='.$id]  
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Просмотрите все ценности и найдите самую важную для вас!\n\nВыберите 5 ценностей начиная с самой важной:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Здоровье', 'callback_data' => 'Здоровье fourthch']
                        ],
                        [
                            ['text' => 'Карьера', 'callback_data' => 'Карьера fourthch']
                        ],
                        [
                            ['text' => 'Семья', 'callback_data' => 'Семья fourthch']
                        ],
                        [
                            ['text' => 'Богатство', 'callback_data' => 'Богатство fourthch']
                        ],
                        [
                            ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие fourthch']
                        ],
                        [
                            ['text' => 'Спорт', 'callback_data' => 'Спорт fourthch']
                        ],
                        [
                            ['text' => 'Осознанность', 'callback_data' => 'Осознанность fourthch']
                        ],
                        [
                            ['text' => 'Развитие', 'callback_data' => 'Развитие fourthch']
                        ],
                        [
                            ['text' => 'Свобода', 'callback_data' => 'Свобода fourthch']
                        ],
                        [
                            ['text' => 'Миссия', 'callback_data' => 'Миссия fourthch']
                        ],
                        [
                            ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми fourthch']
                        ],
                        [
                            ['text' => 'Любовь', 'callback_data' => 'Любовь fourthch']
                        ],
                        [
                            ['text' => 'Амбиции', 'callback_data' => 'Амбиции fourthch']
                        ],
                        [
                            ['text' => 'Отдых', 'callback_data' => 'Отдых fourthch']
                        ],
                        [
                            ['text' => 'Благодарность', 'callback_data' => 'Благодарность fourthch']
                        ],
                        [
                            ['text' => 'Принятие', 'callback_data' => 'Принятие fourthch']
                        ],
                        [
                            ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu']
                        ]
                    ]
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Просмотрите все ценности и найдите самую важную для вас!\n\nСейчас твой список выглядит так:\n" . $msgArray . "\nВыберите 5 ценностей начиная с самой важной:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Здоровье', 'callback_data' => 'Здоровье fourthch']
                        ],
                        [
                            ['text' => 'Карьера', 'callback_data' => 'Карьера fourthch']
                        ],
                        [
                            ['text' => 'Семья', 'callback_data' => 'Семья fourthch']
                        ],
                        [
                            ['text' => 'Богатство', 'callback_data' => 'Богатство fourthch']
                        ],
                        [
                            ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие fourthch']
                        ],
                        [
                            ['text' => 'Спорт', 'callback_data' => 'Спорт fourthch']
                        ],
                        [
                            ['text' => 'Осознанность', 'callback_data' => 'Осознанность fourthch']
                        ],
                        [
                            ['text' => 'Развитие', 'callback_data' => 'Развитие fourthch']
                        ],
                        [
                            ['text' => 'Свобода', 'callback_data' => 'Свобода fourthch']
                        ],
                        [
                            ['text' => 'Миссия', 'callback_data' => 'Миссия fourthch']
                        ],
                        [
                            ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми fourthch']
                        ],
                        [
                            ['text' => 'Любовь', 'callback_data' => 'Любовь fourthch']
                        ],
                        [
                            ['text' => 'Амбиции', 'callback_data' => 'Амбиции fourthch']
                        ],
                        [
                            ['text' => 'Отдых', 'callback_data' => 'Отдых fourthch']
                        ],
                        [
                            ['text' => 'Благодарность', 'callback_data' => 'Благодарность fourthch']
                        ],
                        [
                            ['text' => 'Принятие', 'callback_data' => 'Принятие fourthch']
                        ],
                        [
                            ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data);   
        }
        return;
    }

    else if (strpos($data['callback_query']['data'], 'tni') !== false) {
        // Поиск в БД такого навыка
        $user = $func['from']['id'];
        $intsCheck = mysqli_query ($con, "SELECT `interest1`,`interest2`,`interest3`,`interest4`,`interest5` FROM `Interests` WHERE userID = ".$user." ");
        $ints = mysqli_fetch_row($intsCheck);

        // Удаляем слово int из профессии
        $word = $data['callback_query']['data'];
        $int = preg_replace("/tni/i", "", $word);

        // Узнаем сколько интересов добавил человек
        $a = count($ints) + 1;

        $msgArray = "";
        $str = "";

        foreach ($ints as $key => $value) {
            if ($str = "") {
                $str .= $value;
            }else{
                $str .= "," . $value;
            }
        }

        // Если такое хобби у человека уже есть
        if ($ints[0] == trim($int) or $ints[1] == trim($int) or $ints[2] == trim($int) or $ints[3] == trim($int) or $ints[4] == trim($int)) {
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Упс! У вас уже есть ' . trim($int) . " в списке интересов\n\nСейчас список ваших интересов выглядит так: " . $str,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Выбрать другой интерес', 'callback_data' => '1chFirst']
                        ]
                    ]
                ]
            ];
            $send_data['chat_id'] = $func['message']['chat']['id'];
            $send_data['message_id'] = $func['message']['message_id'];
            sendTelegram($method, $send_data); 
        }else{
            // Если это будет первый интерес в профиле
            if (empty($ints[0])) {
                // Пушим новый интерес в БД
                mysqli_query ($con, "UPDATE `Interests` SET interest1 = '".trim($int)."' WHERE userID = ".$user." ");

                $method = 'editMessageText';
                $send_data = [
                    'text' => "Укажите 5 своих интересов, начиная с самого важного\n\nСейчас у вас указано:\n" . "\u{0031}\u{FE0F}\u{20E3}" . " - " . trim($int) . "\n\nВыбери категорию:",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Спорт / Активный отдых 🔻', 'callback_data' => '1 Спорт']
                            ],
                            [
                                ['text' => 'Развелчения 🔻', 'callback_data' => '1 Развлечения']
                            ],
                            [
                                ['text' => 'Бизнес 🔻', 'callback_data' => '1 Бизнес']
                            ],
                            [
                                ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
                
                return;
            }
            // Если больше 1 но меньше 5
            if ($a <= 4) {
                if (empty($ints[1])) {
                    // Пушим новый интерес в БД
                    mysqli_query ($con, "UPDATE `Interests` SET interest2 = '".trim($int)."' WHERE userID = ".$user." ");
                }else if (empty($ints[2])) {
                    // Пушим новый интерес в БД
                    mysqli_query ($con, "UPDATE `Interests` SET interest3 = '".trim($int)."' WHERE userID = ".$user." ");
                }else{
                    // Пушим новый интерес в БД
                    mysqli_query ($con, "UPDATE `Interests` SET interest4 = '".trim($int)."' WHERE userID = ".$user." ");
                }

                $method = 'editMessageText';
                $send_data = [
                    'text' => "Укажите 5 своих интересов, начиная с самого важного\n\nСейчас у вас указано:\n" . $str . "\n\nВыбери категорию:",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Спорт / Активный отдых 🔻', 'callback_data' => '1 Спорт']
                            ],
                            [
                                ['text' => 'Развелчения 🔻', 'callback_data' => '1 Развлечения']
                            ],
                            [
                                ['text' => 'Бизнес 🔻', 'callback_data' => '1 Бизнес']
                            ],
                            [
                                ['text' => '👈 Вернуться к задаче поиска', 'callback_data' => 'FirsTmenu']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
            }
            // Если 5 интерес
            if ($a == 5){
                // Пушим новый интерес в БД
                mysqli_query ($con, "UPDATE `Interests` SET interest5 = '".trim($int)."' WHERE userID = ".$user." ");

                // Пушим, что дали награду
                mysqli_query ($con, "UPDATE `userRewards` SET InterestsReward = 1 WHERE userID = ".$user." ");

                // Получаем кол-во монет пользователя
                $selectCoins = mysqli_query ($con, "SELECT `coins` FROM `MainInfo` WHERE userID='".$user."' ");
                $coins = mysqli_fetch_array($selectCoins);

                // Плюсуем к монетам награду
                $coins = $coins['coins'] + 100;

                // Выдаем монеты
                mysqli_query ($con, "UPDATE `MainInfo` SET coins = '".$coins."' WHERE userID = ".$user." ");

                $method = 'editMessageText';
                $send_data = [
                    'text' => "Вы получили 100 монет за добавление 5 интересов. Узнать кол-во монет и как их получить, вы можете нажав на кнопку 'Монеты' в главном меню",
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);

                // Выводим человеку сообщение об успешности операции и даем возможность добавить еще интересы
                $method = 'sendMessage';
                $send_data = [
                    'text' => 'Отлично! Теперь мне нужно узнать ваше местоположение, чтоб добавить вас в чат для общения',
                    'reply_markup' => [
                        resize_keyboard =>true,
                        one_time_keyboard => true,
                        'keyboard' => [
                            [
                                ['text' => 'Поделиться местоположением', request_location => true]
                            ]
                        ]
                    ]
                ];
            }
        }
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

        case 'myCoins':
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

            $method = 'sendMessage';
            $send_data = [
                'text' => "💰 *Монеты:*\n\n_У вас на счету:_ " . "*" . $coins . "*" . ' монет',
                'parse_mode' => "Markdown",
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
            break;

        case 'Спорт серч':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "🔎🚲 *С кем интересно провести время*\n\n_Выберите интерес для поиска нужного человека_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Катание на роликах', 'callback_data' => 'Катание на роликах serch']  
                        ],
                        [
                            ['text' => 'Йога', 'callback_data' => 'Йога serch']  
                        ],
                        [
                            ['text' => 'Фитнес', 'callback_data' => 'Фитнес serch']  
                        ],
                        [
                            ['text' => 'Бег', 'callback_data' => 'Бег serch']  
                        ],
                        [
                            ['text' => 'Плавание', 'callback_data' => 'Плавание serch']  
                        ],
                        [
                            ['text' => 'Теннис большой', 'callback_data' => 'Теннис большой serch']  
                        ],
                        [
                            ['text' => 'Футбол', 'callback_data' => 'Футбол serch']  
                        ],
                        [
                            ['text' => 'Волейбол', 'callback_data' => 'Волейбол serch']  
                        ],
                        [
                            ['text' => 'Баскетбол', 'callback_data' => 'Баскетбол serch']  
                        ],
                        [
                            ['text' => 'Велики', 'callback_data' => 'Велики serch']  
                        ],
                        [
                            ['text' => 'Самокаты', 'callback_data' => 'Самокаты serch']  
                        ],
                        [
                            ['text' => 'Картинг', 'callback_data' => 'Картинг serch']  
                        ],
                        [
                            ['text' => 'Рафтинг', 'callback_data' => 'Рафтинг serch']  
                        ],
                        [
                            ['text' => 'Виндсерфинг', 'callback_data' => 'Виндсерфинг serch']  
                        ],
                        [
                            ['text' => 'Танцы', 'callback_data' => 'Танцы serch']  
                        ],
                        [
                            ['text' => 'Пинг понг', 'callback_data' => 'Пинг понг serch']  
                        ],
                        [
                            ['text' => 'Пилатес', 'callback_data' => 'Пилатес serch']  
                        ],
                        [
                            ['text' => 'Поход', 'callback_data' => 'Поход serch']  
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'enterestsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'Развлечения1 серч':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "🔎🚲 *С кем интересно провести время*\n\n_Выберите интерес для поиска нужного человека_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Пранки', 'callback_data' => 'Пранки serch']  
                        ],
                        [
                            ['text' => 'Челенджы', 'callback_data' => 'Челенджы serch']  
                        ],
                        [
                            ['text' => 'Настольные игры', 'callback_data' => 'Настольные игры serch']  
                        ],
                        [
                            ['text' => 'Трансформационные игры', 'callback_data' => 'Трансформационные игры serch']  
                        ],
                        [
                            ['text' => 'Кино', 'callback_data' => 'Кино serch']  
                        ],
                        [
                            ['text' => 'Театр', 'callback_data' => 'Театр serch']  
                        ],
                        [
                            ['text' => 'Бильярд', 'callback_data' => 'Бильярд serch']  
                        ],
                        [
                            ['text' => 'Съемка роликов', 'callback_data' => 'Съемка роликов serch']  
                        ],
                        [
                            ['text' => 'Боулинг', 'callback_data' => 'Боулинг serch']  
                        ],
                        [
                            ['text' => 'Следующая страница 👉', 'callback_data' => 'Развлечения2 серч']  
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'enterestsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'Развлечения2 серч':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "🔎🚲 *С кем интересно провести время*\n\n_Выберите интерес для поиска нужного человека_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Кафе', 'callback_data' => 'Кафе serch']  
                        ],
                        [
                            ['text' => 'Бар', 'callback_data' => 'Бар serch']  
                        ],
                        [
                            ['text' => 'Ресторан', 'callback_data' => 'Ресторан serch']  
                        ],
                        [
                            ['text' => 'Рисование', 'callback_data' => 'Рисование serch']  
                        ],
                        [
                            ['text' => 'Шитье', 'callback_data' => 'Шитье serch']  
                        ],
                        [
                            ['text' => 'Ганчарство', 'callback_data' => 'Ганчарство serch']  
                        ],
                        [
                            ['text' => '👈 Прошлая страница', 'callback_data' => 'Развлечения1 серч']  
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'enterestsFinder']
                        ]

                    ]
                ]
            ];
            break;

        case 'Бизнес серч':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "🔎🚲 *С кем интересно провести время*\n\n_Выберите интерес для поиска нужного человека_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Нетворкинг', 'callback_data' => 'Нетворкинг serch']  
                        ],
                        [
                            ['text' => 'Мастермайнд', 'callback_data' => 'Мастермайнд serch']  
                        ],
                        [
                            ['text' => 'Форум', 'callback_data' => 'Форум serch']  
                        ],
                        [
                            ['text' => 'Митинг', 'callback_data' => 'Митинг serch']  
                        ],
                        [
                            ['text' => 'Дебаты', 'callback_data' => 'Дебаты serch']  
                        ],
                        [
                            ['text' => 'Тренинг', 'callback_data' => 'Тренинг serch']  
                        ],
                        [
                            ['text' => 'Мастер-класс', 'callback_data' => 'Мастер-класс serch']  
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'enterestsFinder']
                        ]
                    ]
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

        case 'feedback':
            // Записываем, что человек находится в меню ФИДБЭК
            $user = $func['from']['id'];
            mysqli_query($con, "UPDATE `TrackingMenu` SET whichMenu = 'ФИДБЭК', mesToChange = '".$data['callback_query']['message']['message_id']."' WHERE userID = '".$user."' ");
            $method = 'editMessageText';
            $send_data = [
                'text' => "🗣️ *Сообщить об идее/ошибке*\n\n_Напиши мне о своей идее или о проблеме с которой ты столкнулся._",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
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
                            ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']  
                        ]
                    ]
                ]
            ];
            break;

        case 'myNeeds':
            $user = $func['from']['id']; 
            $needsCheck = mysqli_query ($con, "SELECT `n1`,`n2`,`n3`,`n4`,`n5`,`n6` FROM `Needs` WHERE userID='".$user."' ");
            $needs = mysqli_fetch_row($needsCheck);

            $msgText2 = "";
            $btnsArray = array();
            array_push($btnsArray, array(array('text' => 'Добавить ценности', 'callback_data' => 'pushNeeds')));
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
                    $msgText2 .= trim($value) . "\n";
                }
            }

            foreach ($needs as $key => $value) {
                if (!empty($value)) {
                    array_push($btnsArray, array(array('text' => 'Удалить '.$value, 'callback_data' => $value."1134")));
                }
            }
            
            // Удаляем сообщение с профилем
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            if (empty($needs)) {
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
                                ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']  
                            ]
                        ]
                    ]
                ];
            }else{
                array_push($btnsArray, array(array('text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile')));

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
            $needs = mysqli_fetch_row($needsCheck);

            if (empty($needs)) {
                $method = 'editMessageText';
                $send_data = [
                    'text' => "📝 *Мои ценности*\n\n_Просмотри все ценности и найди самую важную для тебя!\nВыбери ценности начиная с самой важной:_",
                    "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Здоровье', 'callback_data' => 'Здоровье SexSer3ch']
                            ],
                            [
                                ['text' => 'Карьера', 'callback_data' => 'Карьера SexSer3ch']
                            ],
                            [
                                ['text' => 'Семья', 'callback_data' => 'Семья SexSer3ch']
                            ],
                            [
                                ['text' => 'Богатство', 'callback_data' => 'Богатство SexSer3ch']
                            ],
                            [
                                ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие SexSer3ch']
                            ],
                            [
                                ['text' => 'Спорт', 'callback_data' => 'Спорт SexSer3ch']
                            ],
                            [
                                ['text' => 'Осознанность', 'callback_data' => 'Осознанность SexSer3ch']
                            ],
                            [
                                ['text' => 'Развитие', 'callback_data' => 'Развитие SexSer3ch']
                            ],
                            [
                                ['text' => 'Свобода', 'callback_data' => 'Свобода SexSer3ch']
                            ],
                            [
                                ['text' => 'Миссия', 'callback_data' => 'Миссия SexSer3ch']
                            ],
                            [
                                ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми SexSer3ch']
                            ],
                            [
                                ['text' => 'Любовь', 'callback_data' => 'Любовь SexSer3ch']
                            ],
                            [
                                ['text' => 'Амбиции', 'callback_data' => 'Амбиции SexSer3ch']
                            ],
                            [
                                ['text' => 'Отдых', 'callback_data' => 'Отдых SexSer3ch']
                            ],
                            [
                                ['text' => 'Благодарность', 'callback_data' => 'Благодарность SexSer3ch']
                            ],
                            [
                                ['text' => 'Принятие', 'callback_data' => 'Принятие SexSer3ch']
                            ],
                            [
                                ['text' => '👈 Вернуться в профиль', 'callback_data' => 'profile']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
            }else{
                $msgText2 = "";
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
                        $msgText2 .= trim($value) . "\n";
                    }
                }

                $method = 'editMessageText';
                $send_data = [
                    'text' => "📝 *Мои ценности*\n\n_Сейчас у вас указано:_ \n".$msgText2."\n_Просмотри все ценности и найди самую важную для тебя!\nВыбери ценности начиная с самой важной:_",
                    "parse_mode" => "Markdown",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Здоровье', 'callback_data' => 'Здоровье SexSer3ch']
                            ],
                            [
                                ['text' => 'Карьера', 'callback_data' => 'Карьера SexSer3ch']
                            ],
                            [
                                ['text' => 'Семья', 'callback_data' => 'Семья SexSer3ch']
                            ],
                            [
                                ['text' => 'Богатство', 'callback_data' => 'Богатство SexSer3ch']
                            ],
                            [
                                ['text' => 'Духовное развитие', 'callback_data' => 'Духовное развитие SexSer3ch']
                            ],
                            [
                                ['text' => 'Спорт', 'callback_data' => 'Спорт SexSer3ch']
                            ],
                            [
                                ['text' => 'Осознанность', 'callback_data' => 'Осознанность SexSer3ch']
                            ],
                            [
                                ['text' => 'Развитие', 'callback_data' => 'Развитие SexSer3ch']
                            ],
                            [
                                ['text' => 'Свобода', 'callback_data' => 'Свобода SexSer3ch']
                            ],
                            [
                                ['text' => 'Миссия', 'callback_data' => 'Миссия SexSer3ch']
                            ],
                            [
                                ['text' => 'Отношения с людьми', 'callback_data' => 'Отношения с людьми SexSer3ch']
                            ],
                            [
                                ['text' => 'Любовь', 'callback_data' => 'Любовь SexSer3ch']
                            ],
                            [
                                ['text' => 'Амбиции', 'callback_data' => 'Амбиции SexSer3ch']
                            ],
                            [
                                ['text' => 'Отдых', 'callback_data' => 'Отдых SexSer3ch']
                            ],
                            [
                                ['text' => 'Благодарность', 'callback_data' => 'Благодарность SexSer3ch']
                            ],
                            [
                                ['text' => 'Принятие', 'callback_data' => 'Принятие SexSer3ch']
                            ],
                            [
                                ['text' => '👈 Вернуться в профиль', 'callback_data' => 'profile']
                            ]
                        ]
                    ]
                ];
                $send_data['chat_id'] = $func['message']['chat']['id'];
                $send_data['message_id'] = $func['message']['message_id'];
                sendTelegram($method, $send_data);
            }
            break;

        /*case 'Сохранить потребности':
            $user = $func['from']['id'];

            // Подключаемся к БД и получаем все id сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldNeeds`, `userNeeds` FROM `BOT` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            if (empty($row['oldNeeds'])) {
                // Выводим человеку информацию о том, тчо он ничего не ввел
                $method = 'editMessageText';
                $send_data = [
                    'text' => "Хм... Вы ничего не добавили. Хотите остаться?",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Хочу остаться', 'callback_data' => 'pushNeeds']  
                            ],
                            [
                                ['text' => '👈 Нет', 'callback_data' => 'Отменить потребности']  
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
                $updateDB = mysqli_query ($con, "UPDATE `BOT` SET rowsToDel = '', whichMenu = '', oldNeeds = '' WHERE userID = ".$user." ");

                // Выводим человеку информацию об успешной отправке хобби
                $method = 'editMessageText';
                $send_data = [
                    'text' => "Отлично! Ты добавил свои качества в профиль!\n\nСейчас у вас указано: " . $row['userNeeds'],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']  
                            ]
                        ]
                    ]
                ];
            }
            break;

        case 'Отменить потребности':
            $user = $func['from']['id'];

            // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldNeeds` FROM `BOT` WHERE userID='".$user."' ");
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
                $updateDB = mysqli_query ($con, "UPDATE `BOT` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");
            }else{
                // Убираем все удаленные сообщения из БД и информацию о том, в каком меню человек
                $updateDB = mysqli_query ($con, "UPDATE `BOT` SET rowsToDel = '', whichMenu = '', oldNeeds = '', userNeeds = '".$row['oldNeeds']."' WHERE userID = ".$user." ");
            }

            // Возращаем человека в меню "Мои качества"
            $needsCheck = mysqli_query ($con, "SELECT `userNeeds` FROM `BOT` WHERE userID='".$user."' ");
            $needs = mysqli_fetch_array($needsCheck);
            $method = 'editMessageText';
            $send_data = [
                'text' => "📝 Мои качества:\n\n" . $needs['userNeeds'] ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Добавить качества', 'callback_data' => 'pushNeeds']  
                        ],
                        [
                            ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']  
                        ]
                    ]
                ]
            ];
            break;*/

        case 'myInterests':
            $user = $func['from']['id']; 
            $interestsCheck = mysqli_query ($con, "SELECT `interest1`, `interest2`, `interest3`, `interest4`, `interest5`, `interest6` FROM `Interests` WHERE userID='".$user."' ");
            $interests = mysqli_fetch_row($interestsCheck);

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
                    $msgText3 .= trim($value) . "\n";
                }
            }

            foreach ($interests as $key => $value) {
                if (!empty($value) and $key < 5) {
                    array_push($btnsArray, array(array('text' => '❌ Удалить '.$value, 'callback_data' => $value." 1135")));
                }
            }

            // Удаляем сообщение с меню
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            if (empty($interests)) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "🚲 Мои интересы:" ,
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Добавить интересы', 'callback_data' => 'pushInterests']  
                            ],
                            [
                                ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']  
                            ]
                        ]
                    ]
                ];
            }else {
                array_push($btnsArray, array(array('text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile')));
                $method = 'sendMessage';
                $send_data = [
                    'text' => "🚲 Мои интересы:\n\n" . $msgText3,
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
                    $msgText3 .= trim($value) . "\n";
                }
            }

            $method = 'editMessageText';
            $send_data = [
                'text' => "У вас указаны такие интересы: \n" . $msgText3 . "\nВыбери категорию:" ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Спорт / Активный отдых 🔻', 'callback_data' => 'Спорт']  
                        ],
                        [
                            ['text' => 'Развелчения 🔻', 'callback_data' => 'Развлечения']  
                        ],
                        [
                            ['text' => 'Бизнес 🔻', 'callback_data' => 'Бизнес']  
                        ],
                        [
                            ['text' => '👈 Вурнуться в "Мои интересы"', 'callback_data' => 'myInterests'],
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]

                    ]
                ]
            ];
            break;

        case 'Развлечения':
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
                    $msgText3 .= trim($value) . "\n";
                }
            }

            $method = 'editMessageText';
            $send_data = [
                'text' => "У вас указаны такие интересы: \n" . $msgText3 . "\nВыбери интерес:" ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Пранки', 'callback_data' => 'Пранки int']  
                        ],
                        [
                            ['text' => 'Челенджы', 'callback_data' => 'Челенджы int']  
                        ],
                        [
                            ['text' => 'Настольные игры', 'callback_data' => 'Настольные игры int']  
                        ],
                        [
                            ['text' => 'Трансформационные игры', 'callback_data' => 'Трансформационные игры int']  
                        ],
                        [
                            ['text' => 'Кино', 'callback_data' => 'Кино int']  
                        ],
                        [
                            ['text' => 'Театр', 'callback_data' => 'Театр int']  
                        ],
                        [
                            ['text' => 'Бильярд', 'callback_data' => 'Бильярд int']  
                        ],
                        [
                            ['text' => 'Съемка роликов', 'callback_data' => 'Съемка роликов int']  
                        ],
                        [
                            ['text' => 'Боулинг', 'callback_data' => 'Боулинг int']  
                        ],
                        [
                            ['text' => 'Следующая страница 👉', 'callback_data' => 'Развлечения2']  
                        ],
                        [
                            ['text' => '👈 Вурнуться назад', 'callback_data' => 'pushInterests'],
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]

                    ]
                ]
            ];
            break;

        case 'Развлечения2':
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
                    $msgText3 .= trim($value) . "\n";
                }
            }

            $method = 'editMessageText';
            $send_data = [
                'text' => "У вас указаны такие интересы: \n" . $msgText3 . "\nВыбери интерес:" ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Кафе', 'callback_data' => 'Кафе int']  
                        ],
                        [
                            ['text' => 'Бар', 'callback_data' => 'Бар int']  
                        ],
                        [
                            ['text' => 'Ресторан', 'callback_data' => 'Ресторан int']  
                        ],
                        [
                            ['text' => 'Рисование', 'callback_data' => 'Рисование int']  
                        ],
                        [
                            ['text' => 'Шитье', 'callback_data' => 'Шитье int']  
                        ],
                        [
                            ['text' => 'Ганчарство', 'callback_data' => 'Ганчарство int']  
                        ],
                        /*[
                            ['text' => '🆘 Не нашел свой интерес 🆘', 'callback_data' => 'НеНашелИнтерес']  
                        ],*/
                        [
                            ['text' => '👈 Прошлая страница', 'callback_data' => 'Развлечения']  
                        ],
                        [
                            ['text' => '👈 Вурнуться назад', 'callback_data' => 'pushInterests'],
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]

                    ]
                ]
            ];
            break;

        case 'Бизнес':
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
                    $msgText3 .= trim($value) . "\n";
                }
            }

            $method = 'editMessageText';
            $send_data = [
                'text' => "У вас указаны такие интересы: \n" . $msgText3 . "\nВыбери интерес:" ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Нетворкинг', 'callback_data' => 'Нетворкинг int']  
                        ],
                        [
                            ['text' => 'Мастермайнд', 'callback_data' => 'Мастермайнд int']  
                        ],
                        [
                            ['text' => 'Форум', 'callback_data' => 'Форум int']  
                        ],
                        [
                            ['text' => 'Митинг', 'callback_data' => 'Митинг int']  
                        ],
                        [
                            ['text' => 'Дебаты', 'callback_data' => 'Дебаты int']  
                        ],
                        [
                            ['text' => 'Тренинг', 'callback_data' => 'Тренинг int']  
                        ],
                        [
                            ['text' => 'Мастер-класс', 'callback_data' => 'Мастер-класс int']  
                        ],
                        /*[
                            ['text' => '🆘 Не нашел свой интерес 🆘', 'callback_data' => 'НеНашелИнтерес']  
                        ],*/
                        [
                            ['text' => '👈 Вурнуться назад', 'callback_data' => 'pushInterests'],
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]

                    ]
                ]
            ];
            break;

        case 'Спорт':
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
                    $msgText3 .= trim($value) . "\n";
                }
            }

            $method = 'editMessageText';
            $send_data = [
                'text' => "У вас указаны такие интересы: \n" . $msgText3 . "\nВыбери интерес:" ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Катание на роликах', 'callback_data' => 'Катание на роликах int']  
                        ],
                        [
                            ['text' => 'Йога', 'callback_data' => 'Йога int']  
                        ],
                        [
                            ['text' => 'Фитнес', 'callback_data' => 'Фитнес int']  
                        ],
                        [
                            ['text' => 'Бег', 'callback_data' => 'Бег int']  
                        ],
                        [
                            ['text' => 'Плавание', 'callback_data' => 'Плавание int']  
                        ],
                        [
                            ['text' => 'Теннис большой', 'callback_data' => 'Теннис большой int']  
                        ],
                        [
                            ['text' => 'Футбол', 'callback_data' => 'Футбол int']  
                        ],
                        [
                            ['text' => 'Волейбол', 'callback_data' => 'Волейбол int']  
                        ],
                        [
                            ['text' => 'Баскетбол', 'callback_data' => 'Баскетбол int']  
                        ],
                        [
                            ['text' => 'Велики', 'callback_data' => 'Велики int']  
                        ],
                        [
                            ['text' => 'Самокаты', 'callback_data' => 'Самокаты int']  
                        ],
                        [
                            ['text' => 'Картинг', 'callback_data' => 'Картинг int']  
                        ],
                        [
                            ['text' => 'Рафтинг', 'callback_data' => 'Рафтинг int']  
                        ],
                        [
                            ['text' => 'Виндсерфинг', 'callback_data' => 'Виндсерфинг int']  
                        ],
                        [
                            ['text' => 'Танцы', 'callback_data' => 'Танцы int']  
                        ],
                        [
                            ['text' => 'Пинг понг', 'callback_data' => 'Пинг понг int']  
                        ],
                        [
                            ['text' => 'Пилатес', 'callback_data' => 'Пилатес int']  
                        ],
                        [
                            ['text' => 'Поход', 'callback_data' => 'Поход int']  
                        ],
                        /*[
                            ['text' => '🆘 Не нашел свой интерес 🆘', 'callback_data' => 'НеНашелИнтерес']  
                        ],*/
                        [
                            ['text' => '👈 Вурнуться назад', 'callback_data' => 'pushInterests'],
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]

                    ]
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

        /*case 'pushInterests':
            // Пушим в каком меню находится человек
            $user = $func['from']['id'];
            $updateDB = mysqli_query ($con, "UPDATE `BOT` SET whichMenu = 'Интересы' WHERE userID = ".$user." ");

            $method = 'editMessageText';
            $send_data = [
                'text' => "Отправь мне свои хобби через запятую и нажми кнопку 'Сохранить'.\n\n! Учитываться будет только последнее отправленное сообщение !\n\nПример: серфинг, играю в футбол, люблю смотреть фильмы или сериалы, люблю читать книги, компьютерные игры" ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сохранить', 'callback_data' => 'Сохранить интересы']  
                        ],
                        [
                            ['text' => 'Отмена', 'callback_data' => 'Отменить интересы']  
                        ]
                    ]
                ]
            ];
            break;

        case 'Сохранить интересы':
            $user = $func['from']['id'];

            // Подключаемся к БД и получаем все id сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel`, `oldInterests` FROM `BOT` WHERE userID='".$user."' ");
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
                    'text' => "Отлично! Ты добавил свои интересы в профиль!",
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => '👈 Выйти в меню профиля', 'callback_data' => 'profile']  
                            ]
                        ]
                    ]
                ];
            }
            break;

        case 'Отменить интересы':
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
                            ['text' => '👈 Вурнуться назад', 'callback_data' => 'profile']  
                        ]
                    ]
                ]
            ];
            break;*/

        case 'myNameAge':
            $user = $func['from']['id']; 
            $nameCheck = mysqli_query ($con, "SELECT `name`, `surname`, `userAge`, `sex` FROM `MainInfo` WHERE userID='".$user."' ");
            $name = mysqli_fetch_array($nameCheck);

            // Удаляем сообщение с профилем
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

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
            $user = $func['from']['id'];
            $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'ФамилияИмя' WHERE userID = ".$user." ");

            // Подключаемся к БД и получаем name
            $rowsToDelete = mysqli_query ($con, "SELECT `surname` FROM `MainInfo` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            $method = 'editMessageText';
            $send_data = [
                'text' => "Отправь мне свою фамилию, а после нажми кнопку 'Сохранить'.\n! Учитываться будет только последнее отправленное сообщение !\nПример: Тарас\n\nСейчас у вас указано: " . $row['surname'] ,
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
            $user = $func['from']['id'];

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
            $method = 'editMessageText';
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
            $user = $func['from']['id'];

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
            $method = 'editMessageText';
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
            $user = $func['from']['id'];
            $updateDB = mysqli_query ($con, "UPDATE `TrackingMenu` SET whichMenu = 'ИмяФамилия' WHERE userID = ".$user." ");

            // Подключаемся к БД и получаем name
            $rowsToDelete = mysqli_query ($con, "SELECT `name` FROM `MainInfo` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            $method = 'editMessageText';
            $send_data = [
                'text' => "Отправь мне свое Имя и Фамилию, а после нажми кнопку 'Сохранить'.\n! Учитываться будет только последнее отправленное сообщение !\nПример: Тарас\n\nСейчас у вас указано: " . $row['name'] ,
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
            $user = $func['from']['id'];

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
            $method = 'editMessageText';
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
            $user = $func['from']['id'];

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
            $method = 'editMessageText';
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

        /*case 'interestsFinder':
            // Пушим в каком меню находится человек
            $user = $func['from']['id'];
            $mes = $data['callback_query']['message']['message_id'];
            $updateDB = mysqli_query ($con, "UPDATE `BOT` SET whichMenu = 'Finder по интересам', mesToChange = '".$mes."' WHERE userID = ".$user." ");

            $method = 'editMessageText';
            $send_data = [
                'text' => "Отправь мне сообщение с интересующим тебя интересом(должно быть только одно слово).\n\n! Учитываться будет только последнее отправленное сообщение !\n\nПример: велоспорт" ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Отмена', 'callback_data' => 'Отменить interestsFinder']  
                        ]
                    ]
                ]
            ];
            break;

        case 'Отменить interestsFinder':
            $user = $func['from']['id'];

            // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel` FROM `BOT` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            $updateDB = mysqli_query ($con, "UPDATE `BOT` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");

            // Выводим пользователя в меню с поисками
            $method = 'editMessageText';
            $send_data = [
                'text' => '🔎 Поиск людей:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔎🧑‍💻 Поиск по навыкам', 'callback_data' => 'skillsFinder']  
                        ],
                        [
                            ['text' => '🔎🚲 Поиск по интересам', 'callback_data' => 'interestsFinder']
                        ],
                        [
                            ['text' => '🔎📝 Поиск по потребностям', 'callback_data' => 'needsFinder']
                        ],
                        [
                            ['text' => '🔎👑 Премиум поиск', 'callback_data' => 'premiumFinder']
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]
                    ]
                ]
            ];
            break;*/

        /*case 'needsFinder':
            // Пушим в каком меню находится человек
            $user = $func['from']['id'];
            $mes = $data['callback_query']['message']['message_id'];
            $updateDB = mysqli_query ($con, "UPDATE `BOT` SET whichMenu = 'Finder по потребностям', mesToChange = '".$mes."' WHERE userID = ".$user." ");

            $method = 'editMessageText';
            $send_data = [
                'text' => "Отправь мне сообщение с интересующией тебя потребностью(должно быть только одно слово).\n\n! Учитываться будет только последнее отправленное сообщение !\n\nПример: верная" ,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Отмена', 'callback_data' => 'Отменить needsFinder']  
                        ]
                    ]
                ]
            ];
            break;

        case 'Отменить needsFinder':
            $user = $func['from']['id'];

            // Получаем id всех сообщений, отправленных пользователем
            $rowsToDelete = mysqli_query ($con, "SELECT `rowsToDel` FROM `BOT` WHERE userID='".$user."' ");
            $row = mysqli_fetch_array($rowsToDelete);

            // Создаем массив из строк для удаления
            $rowArray = explode(" , ", $row['rowsToDel']);

            // Удаляем все сообщения в чате
            $send_data['chat_id'] = $user;
            foreach ($rowArray as $value) {
                $send_data['message_id'] = $value;
                sendTelegram('deleteMessage', $send_data);
            }

            $updateDB = mysqli_query ($con, "UPDATE `BOT` SET rowsToDel = '', whichMenu = '' WHERE userID = ".$user." ");

            // Выводим пользователя в меню с поисками
            $method = 'editMessageText';
            $send_data = [
                'text' => '🔎 Поиск людей:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔎🧑‍💻 Поиск по навыкам', 'callback_data' => 'skillsFinder']  
                        ],
                        [
                            ['text' => '🔎🚲 Поиск по интересам', 'callback_data' => 'interestsFinder']
                        ],
                        [
                            ['text' => '🔎📝 Поиск по потребностям', 'callback_data' => 'needsFinder']
                        ],
                        [
                            ['text' => '🔎👑 Премиум поиск', 'callback_data' => 'premiumFinder']
                        ],
                        [
                            ['text' => '👈 Главное меню', 'callback_data' => 'mainMenu']
                        ]
                    ]
                ]
            ];
            break;*/

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
                    foreach ($skills6 as $key => $value) {
                        $skill6 = explode(")", $value);
                        $msgText3 .= trim($skill6[1]) . "\n";
                        array_push($arrTo6, $skill6[1]);
                    }
                }
            }

            foreach ($skills as $key => $value) {
                if (!empty($value) and $key < 5) {
                    array_push($btnsArray, array(array('text' => '❌ Удалить '.trim($value), 'callback_data' => trim($value)." 1133")));
                }else{
                    if (!empty($value)) {
                        foreach ($arrTo6 as $key => $value) {
                            array_push($btnsArray, array(array('text' => '❌ Удалить '.trim($value), 'callback_data' => trim($value1)." 1133")));
                        }
                    }
                }
            }

            if (empty($skills)) {
                $method = 'sendMessage';
                $send_data = [
                    'text' => "🧑‍💻 Мои навыки" ,
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Добавить навыки', 'callback_data' => 'choiceSkills']  
                            ],
                            [
                                ['text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile']  
                            ]
                        ]
                    ]
                ];
            }else {
                array_push($btnsArray, array(array('text' => '👈 Вурнуться в "Мой профиль"', 'callback_data' => 'profile')));
                $method = 'sendMessage';
                $send_data = [
                    'text' => "🧑‍💻 Мои навыки\n\n" . $msgText3,
                    'reply_markup' => [
                        'inline_keyboard' => $btnsArray
                    ]
                ];
            }
            break;

        case 'ITSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер add']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист add']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор add']
                        ],
                        [
                            ['text' => 'Разработчик', 'callback_data' => 'Разработчик add']
                        ],
                        [
                            ['text' => 'Программист 1C', 'callback_data' => 'Программист 1C add']
                        ],
                        [
                            ['text' => 'Верстальщик', 'callback_data' => 'Верстальщик add']
                        ],
                        [
                            ['text' => 'PR-специалист', 'callback_data' => 'PR-специалист add']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер add']
                        ],
                        [
                            ['text' => 'Таргетолог', 'callback_data' => 'Таргетолог add']
                        ],
                        [
                            ['text' => 'Project-менеджер', 'callback_data' => 'Project-менеджер add']
                        ],
                        [
                            ['text' => 'Администратор сайта', 'callback_data' => 'Администратор сайта add']
                        ],
                        [
                            ['text' => 'Cпециалист службы поддержки', 'callback_data' => 'Cлужба поддержки add']
                        ],
                        [
                            ['text' => 'Режиссер видеомонтажа', 'callback_data' => 'Режиссер видеомонтажа add']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог add']
                        ],
                        [
                            ['text' => 'Оператор технической поддержки', 'callback_data' => 'Оператор тех-поддержки add']
                        ],
                        [
                            ['text' => 'Администратор баз данных', 'callback_data' => 'Администратор-БД add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'administrSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Управляющий магазином', 'callback_data' => 'Управляющий магазином add']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор add']
                        ],
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Начальник отдела продаж add']
                        ],
                        [
                            ['text' => 'Мастер цеха', 'callback_data' => 'Мастер цеха add']
                        ],
                        [
                            ['text' => 'Администратор рецепции', 'callback_data' => 'Администратор рецепции add']
                        ],
                        [
                            ['text' => 'Начальник отдела кадров', 'callback_data' => 'Начальник отдела кадров add']
                        ],
                        [
                            ['text' => 'Супервайзер', 'callback_data' => 'Супервайзер add']
                        ],
                        [
                            ['text' => 'Менеджер по развитию', 'callback_data' => 'Менеджер по развитию add']
                        ],
                        [
                            ['text' => 'Администратор-кассир', 'callback_data' => 'Администратор-кассир add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'designSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея add']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер add']
                        ],
                        [
                            ['text' => 'Видеограф', 'callback_data' => 'Видеограф add']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер add']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф add']
                        ],
                        [
                            ['text' => 'Дизайнер полиграфии', 'callback_data' => 'Дизайнер полиграфии add']
                        ],
                        [
                            ['text' => 'Oператор ПК', 'callback_data' => 'Oператор ПК add']
                        ],
                        [
                            ['text' => 'Флорист', 'callback_data' => 'Флорист add']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер add']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер add']
                        ],
                        [
                            ['text' => 'Дизайнер-конструктор', 'callback_data' => 'Дизайнер-конструктор add']
                        ],
                        [
                            ['text' => 'Мастер-ювелир', 'callback_data' => 'Мастер-ювелир add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'beautySkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Лешмейкер', 'callback_data' => 'Лешмейкер add']
                        ],
                        [
                            ['text' => 'Instagram-модель', 'callback_data' => 'Instagram-модель add']
                        ],
                        [
                            ['text' => 'Парикмахер', 'callback_data' => 'Парикмахер add']
                        ],
                        [
                            ['text' => 'Руководитель фитнес-клуба', 'callback_data' => 'Руководитель фитнес-клуба add']
                        ],
                        [
                            ['text' => 'Мастер маникюра', 'callback_data' => 'Мастер маникюра add']
                        ],
                        [
                            ['text' => 'Мастер лазерной эпиляции', 'callback_data' => 'Мастер лазерной эпиляции add']
                        ],
                        [
                            ['text' => 'Администратор салона красоты', 'callback_data' => 'Админ салона красоты add']
                        ],
                        [
                            ['text' => 'Барбер', 'callback_data' => 'Барбер add']
                        ],
                        [
                            ['text' => 'Косметолог', 'callback_data' => 'Косметолог add']
                        ],
                        [
                            ['text' => 'Тренер по йоге', 'callback_data' => 'Тренер по йоге add']
                        ],
                        [
                            ['text' => 'Стилист-консультант', 'callback_data' => 'Стилист-консультант add']
                        ],
                        [
                            ['text' => 'Тренер по футболу', 'callback_data' => 'Тренер по футболу add']
                        ],
                        [
                            ['text' => 'Дерматолог', 'callback_data' => 'Дерматолог add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'showbizSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Видеооператор', 'callback_data' => 'Видеооператор add']
                        ],
                        [
                            ['text' => 'Сценарист', 'callback_data' => 'Сценарист add']
                        ],
                        [
                            ['text' => 'Организатор мероприятий', 'callback_data' => 'Организатор мероприятий add']
                        ],
                        [
                            ['text' => 'Ведущий церемоний', 'callback_data' => 'Ведущий церемоний add']
                        ],
                        [
                            ['text' => 'Актриса', 'callback_data' => 'Актриса add']
                        ],
                        [
                            ['text' => 'Хореограф-постановщик', 'callback_data' => 'Хореограф-постановщик add']
                        ],
                        [
                            ['text' => 'Оператор чата', 'callback_data' => 'Оператор чата add']
                        ],
                        [
                            ['text' => 'Стример', 'callback_data' => 'Стример add']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф add']
                        ],
                        [
                            ['text' => 'Аниматор', 'callback_data' => 'Аниматор add']
                        ],
                        [
                            ['text' => 'Менеджер по бизнес-процессам', 'callback_data' => 'Менеджер по бизнес-процессам add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'logistikaSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам add']
                        ],
                        [
                            ['text' => 'Сотрудник склада', 'callback_data' => 'Сотрудник склада add']
                        ],
                        [
                            ['text' => 'Менеджер по продажам логистических услуг', 'callback_data' => 'Менеджер лог. услуг add']
                        ],
                        [
                            ['text' => 'Коммерческий менеджер', 'callback_data' => 'Коммерческий менеджер add']
                        ],
                        [
                            ['text' => 'Комплектовщик на склад', 'callback_data' => 'Комплектовщик на склад add']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист add']
                        ],
                        [
                            ['text' => 'Грузчик', 'callback_data' => 'Грузчик add']
                        ],
                        [
                            ['text' => 'Приемщик товара', 'callback_data' => 'Приемщик товара add']
                        ],
                        [
                            ['text' => 'Водитель-грузчик', 'callback_data' => 'Водитель-грузчик add']
                        ],
                        [
                            ['text' => 'Оператор-упаковщик', 'callback_data' => 'Оператор-упаковщик add']
                        ],
                        [
                            ['text' => 'Менеджер по логистике', 'callback_data' => 'Менеджер по логистике add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'marketingSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер add']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог add']
                        ],
                        [
                            ['text' => 'Маркетолог', 'callback_data' => 'Маркетолог add']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер add']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист add']
                        ],
                        [
                            ['text' => 'Категорийный менеджер', 'callback_data' => 'Категорийный менеджер add']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер add']
                        ],
                        [
                            ['text' => 'Помощник контент-менеджера', 'callback_data' => 'Помощник контент-менеджера add']
                        ],
                        [
                            ['text' => 'SEO-специалист', 'callback_data' => 'SEO-специалист add']
                        ],
                        [
                            ['text' => 'Операционный директор', 'callback_data' => 'Операционный директор add']
                        ],
                        [
                            ['text' => 'Арт-директор', 'callback_data' => 'Арт-директор add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'medicinaSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Врач-офтальмолог', 'callback_data' => 'Врач-офтальмолог add']
                        ],
                        [
                            ['text' => 'Медицинский представитель', 'callback_data' => 'Медицинский представитель add']
                        ],
                        [
                            ['text' => 'Санитарка', 'callback_data' => 'Санитарка add']
                        ],
                        [
                            ['text' => 'Семейный врач', 'callback_data' => 'Семейный врач add']
                        ],
                        [
                            ['text' => 'Медсестра', 'callback_data' => 'Медсестра add']
                        ],
                        [
                            ['text' => 'Фармацевт', 'callback_data' => 'Фармацевт add']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант add']
                        ],
                        [
                            ['text' => 'Вирусолог', 'callback_data' => 'Вирусолог add']
                        ],
                        [
                            ['text' => 'Ассистент анестезиолога', 'callback_data' => 'Ассистент анестезиолога add']
                        ],
                        [
                            ['text' => 'Анастезиолог', 'callback_data' => 'Анастезиолог add']
                        ],
                        [
                            ['text' => 'Провизор', 'callback_data' => 'Провизор add']
                        ],
                        [
                            ['text' => 'Ветеринар', 'callback_data' => 'Ветеринар add']
                        ],
                        [
                            ['text' => 'Ассистент ветеринарного врача', 'callback_data' => 'Ассистент вет. врача add']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог add']
                        ],
                        [
                            ['text' => 'Психатр', 'callback_data' => 'Психатр add']
                        ],
                        [
                            ['text' => 'Стоматолог', 'callback_data' => 'Стоматолог add']
                        ],
                        [
                            ['text' => 'Кардиолог', 'callback_data' => 'Кардиолог add']
                        ],
                        [
                            ['text' => 'Хирург', 'callback_data' => 'Хирург add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'nedvizhimostSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Руководитель отдела продаж add']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости add']
                        ],
                        [
                            ['text' => 'Риэлтор', 'callback_data' => 'Риэлтор add']
                        ],
                        [
                            ['text' => 'Инспектор', 'callback_data' => 'Инспектор add']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам add']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'naukaSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Воспитатель', 'callback_data' => 'Воспитатель add']
                        ],
                        [
                            ['text' => 'Преподаватель', 'callback_data' => 'Преподаватель add']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч add']
                        ],
                        [
                            ['text' => 'Социальный работник', 'callback_data' => 'Социальный работник add']
                        ],
                        [
                            ['text' => 'Психолог-консультант', 'callback_data' => 'Психолог-консультант add']
                        ],
                        [
                            ['text' => 'Логопед-дефектолог', 'callback_data' => 'Логопед-дефектолог add']
                        ],
                        [
                            ['text' => 'Методист', 'callback_data' => 'Методист add']
                        ],
                        [
                            ['text' => 'Репетитор', 'callback_data' => 'Репетитор add']
                        ],
                        [
                            ['text' => 'Ученый', 'callback_data' => 'Ученый add']
                        ],
                        [
                            ['text' => 'Помощник воспитателя', 'callback_data' => 'Помощник воспитателя add']
                        ],
                        [
                            ['text' => 'Няня', 'callback_data' => 'Няня add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'ohranaSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор охраны', 'callback_data' => 'Инспектор охраны add']
                        ],
                        [
                            ['text' => 'Охранник', 'callback_data' => 'Охранник add']
                        ],
                        [
                            ['text' => 'Контролер-ревизор', 'callback_data' => 'Контролер-ревизор add']
                        ],
                        [
                            ['text' => 'Оператор видеонаблюдения', 'callback_data' => 'Оператор видеонаблюдения add']
                        ],
                        [
                            ['text' => 'Инженер пожарной безопасности', 'callback_data' => 'Инженер пожарной безопасности add']
                        ],
                        [
                            ['text' => 'Техник по охранным системам', 'callback_data' => 'Техник по охранным системам add']
                        ],
                        [
                            ['text' => 'Начальник службы безопасности', 'callback_data' => 'Начальник сл. безопасности add']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный add']
                        ],
                        [
                            ['text' => 'Специалист по безопасности', 'callback_data' => 'Специалист по безопасности add']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный add']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский add']
                        ],
                        [
                            ['text' => 'Водитель-инкассатор', 'callback_data' => 'Водитель-инкассатор add']
                        ],
                        [
                            ['text' => 'Инкассатор', 'callback_data' => 'Инкассатор add']
                        ],
                        [
                            ['text' => 'Оператор ПЦС', 'callback_data' => 'Оператор ПЦС add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'prodajiSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам add']
                        ],
                        [
                            ['text' => 'Менеджер по закупке', 'callback_data' => 'Менеджер по закупке add']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец add']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель add']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам add']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер add']
                        ],
                        [
                            ['text' => 'Аналитик по продажам', 'callback_data' => 'Аналитик по продажам add']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер add']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант add']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'proizvodstvoSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер add']
                        ],
                        [
                            ['text' => 'Пекарь', 'callback_data' => 'Пекарь add']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик add']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник add']
                        ],
                        [
                            ['text' => 'Слесарь', 'callback_data' => 'Слесарь add']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий add']
                        ],
                        [
                            ['text' => 'Мастер-оптик', 'callback_data' => 'Мастер-оптик add']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея add']
                        ],
                        [
                            ['text' => 'Технолог', 'callback_data' => 'Технолог add']
                        ],
                        [
                            ['text' => 'Монтажник', 'callback_data' => 'Монтажник add']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр add']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель add']
                        ],
                        [
                            ['text' => 'Инженер-проектировщик', 'callback_data' => 'Инженер-проектировщик add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'torgovlyaSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам add']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам add']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец add']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель add']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам add']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер add']
                        ],
                        [
                            ['text' => 'Аналитик продаж', 'callback_data' => 'Аналитик продаж add']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер add']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант add']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'sekretaringSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Помощник руководителя', 'callback_data' => 'Помощник руководителя add']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист add']
                        ],
                        [
                            ['text' => 'Офис-менеджер', 'callback_data' => 'Офис-менеджер add']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор add']
                        ],
                        [
                            ['text' => 'Оператор call-centre', 'callback_data' => 'Оператор call-centre add']
                        ],
                        [
                            ['text' => 'Менеджер по моб. продажам', 'callback_data' => 'Менеджер по моб. продажам add']
                        ],
                        [
                            ['text' => 'Администратор ресепшн', 'callback_data' => 'Администратор ресепшн add']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам add']
                        ],
                        [
                            ['text' => 'Помощник по мониторингу', 'callback_data' => 'Помощник по мониторингу add']
                        ],
                        [
                            ['text' => 'Оператор ПК', 'callback_data' => 'Оператор ПК add']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам add']
                        ],
                        [
                            ['text' => 'Секретарь', 'callback_data' => 'Секретарь add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'agrobiznesSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант add']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант add']
                        ],
                        [
                            ['text' => 'Генетик', 'callback_data' => 'Генетик add']
                        ],
                        [
                            ['text' => 'Ветеринарный врач', 'callback_data' => 'Ветеринарный врач add']
                        ],
                        [
                            ['text' => 'Биолог', 'callback_data' => 'Биолог add']
                        ],
                        [
                            ['text' => 'Фермер', 'callback_data' => 'Фермер add']
                        ],
                        [
                            ['text' => 'Агроном', 'callback_data' => 'Агроном add']
                        ],
                        [
                            ['text' => 'Аналитик торгового рынка', 'callback_data' => 'Аналитик торгового рынка add']
                        ],
                        [
                            ['text' => 'Химик-хроматографист', 'callback_data' => 'Химик-хроматографист add']
                        ],
                        [
                            ['text' => 'Зернотрейдер', 'callback_data' => 'Зернотрейдер add']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам зерновых культур', 'callback_data' => 'Менеджер по закупке зерна add']
                        ],
                        [
                            ['text' => 'Пробоотборник с/х продукции', 'callback_data' => 'Пробоотборник с/х продукции add']
                        ],
                        [
                            ['text' => 'Садовник', 'callback_data' => 'Садовник add']
                        ],
                        [
                            ['text' => 'Тракторист', 'callback_data' => 'Тракторист add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'izdatelstvoSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Фотограф в интернет-магазине', 'callback_data' => 'Фотограф в интернет-магазине add']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер add']
                        ],
                        [
                            ['text' => 'Видеоредактор', 'callback_data' => 'Видеоредактор add']
                        ],
                        [
                            ['text' => 'SMM / контент-менеджер', 'callback_data' => 'SMM / контент-менеджер add']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер add']
                        ],
                        [
                            ['text' => 'Печатник', 'callback_data' => 'Печатник add']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер add']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер add']
                        ],
                        [
                            ['text' => 'Oператор цифровой печати', 'callback_data' => 'Oператор цифровой печати add']
                        ],
                        [
                            ['text' => 'Веб-журналист', 'callback_data' => 'Веб-журналист add']
                        ],
                        [
                            ['text' => 'Журналист', 'callback_data' => 'Журналист add']
                        ],
                        [
                            ['text' => 'Писатель', 'callback_data' => 'Писатель add']
                        ],
                        [
                            ['text' => 'Редактор', 'callback_data' => 'Редактор add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'strahovanieSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор-координатор', 'callback_data' => 'Инспектор-координатор add']
                        ],
                        [
                            ['text' => 'Сюрвейер', 'callback_data' => 'Сюрвейер add']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр add']
                        ],
                        [
                            ['text' => 'Менеджер по страхованию', 'callback_data' => 'Менеджер по страхованию add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'stroitelstvoSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инженер технического надзора', 'callback_data' => 'Инженер технического надзора add']
                        ],
                        [
                            ['text' => 'Электромеханик контактной сети', 'callback_data' => 'Электромеханик контактной сети add']
                        ],
                        [
                            ['text' => 'Архитектор', 'callback_data' => 'Архитектор add']
                        ],
                        [
                            ['text' => 'Электромонтер', 'callback_data' => 'Электромонтер add']
                        ],
                        [
                            ['text' => 'Газорезчик', 'callback_data' => 'Газорезчик add']
                        ],
                        [
                            ['text' => 'Строитель', 'callback_data' => 'Строитель add']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий add']
                        ],
                        [
                            ['text' => 'Помощник Архитектора', 'callback_data' => 'Помощник Архитектора add']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр add']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер add']
                        ],
                        [
                            ['text' => 'Мастер-бутафор', 'callback_data' => 'Мастер-бутафор add']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик add']
                        ],
                        [
                            ['text' => 'Штукатур', 'callback_data' => 'Штукатур add']
                        ],
                        [
                            ['text' => 'Сантехник', 'callback_data' => 'Сантехник add']
                        ],
                        [
                            ['text' => 'Инженер отдела продаж', 'callback_data' => 'Инженер отдела продаж add']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер add']
                        ],
                        [
                            ['text' => 'Электромеханик', 'callback_data' => 'Электромеханик add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'obsluzhivanieSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Повар', 'callback_data' => 'Повар add']
                        ],
                        [
                            ['text' => 'Официант', 'callback_data' => 'Официант add']
                        ],
                        [
                            ['text' => 'Бариста', 'callback_data' => 'Бариста add']
                        ],
                        [
                            ['text' => 'Консьерж', 'callback_data' => 'Консьерж add']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор add']
                        ],
                        [
                            ['text' => 'Мастер бьюти сферы', 'callback_data' => 'Мастер бьюти сферы add']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель add']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец add']
                        ],
                        [
                            ['text' => 'Оператор', 'callback_data' => 'Оператор add']
                        ],
                        [
                            ['text' => 'Массажист', 'callback_data' => 'Массажист add']
                        ],
                        [
                            ['text' => 'Хостес', 'callback_data' => 'Хостес add']
                        ],
                        [
                            ['text' => 'Сомелье', 'callback_data' => 'Сомелье add']
                        ],
                        [
                            ['text' => 'Бармен', 'callback_data' => 'Бармен add']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'telecomunikaciiSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Специалист технической поддержки', 'callback_data' => 'Специалист техподдержки add']
                        ],
                        [
                            ['text' => 'Инженер связи', 'callback_data' => 'Инженер связи add']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник add']
                        ],
                        [
                            ['text' => 'Оператор службы поддержки', 'callback_data' => 'Оператор службы поддержки add']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор add']
                        ],
                        [
                            ['text' => 'Саппорт-менеджер', 'callback_data' => 'Саппорт-менеджер add']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик add']
                        ],
                        [
                            ['text' => 'Монтажник связи', 'callback_data' => 'Монтажник связи add']
                        ],
                        [
                            ['text' => 'Диспетчер-логист', 'callback_data' => 'Диспетчер-логист add']
                        ],
                        [
                            ['text' => 'Менеджер call-центра', 'callback_data' => 'Менеджер call-центра add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'topmenSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Заместитель директора', 'callback_data' => 'Заместитель директора add']
                        ],
                        [
                            ['text' => 'Директор сервисного центра', 'callback_data' => 'Директор сервисного центра add']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель add']
                        ],
                        [
                            ['text' => 'Руководитель розничной сети', 'callback_data' => 'Руководитель розничной сети add']
                        ],
                        [
                            ['text' => 'Куратор', 'callback_data' => 'Куратор add']
                        ],
                        [
                            ['text' => 'Директор филиала', 'callback_data' => 'Директор филиала add']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор add']
                        ],
                        [
                            ['text' => 'Директор по персоналу', 'callback_data' => 'Директор по персоналу add']
                        ],
                        [
                            ['text' => 'PR-директор', 'callback_data' => 'PR-директор add']
                        ],
                        [
                            ['text' => 'Исполнительный директор', 'callback_data' => 'Исполнительный директор add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'avtobizSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель add']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик add']
                        ],
                        [
                            ['text' => 'Автослесарь', 'callback_data' => 'Автослесарь add']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист add']
                        ],
                        [
                            ['text' => 'Экспедитор', 'callback_data' => 'Экспедитор add']
                        ],
                        [
                            ['text' => 'Инкассатор-водитель', 'callback_data' => 'Инкассатор-водитель add']
                        ],
                        [
                            ['text' => 'Главный механик', 'callback_data' => 'Главный механик add']
                        ],
                        [
                            ['text' => 'Начальник отдела транспорта', 'callback_data' => 'Начальник отдела транспорта add']
                        ],
                        [
                            ['text' => 'Курьер', 'callback_data' => 'Курьер add']
                        ],
                        [
                            ['text' => 'Дальнобойщик', 'callback_data' => 'Дальнобойщик add']
                        ],
                        [
                            ['text' => 'Мастер СТО', 'callback_data' => 'Мастер СТО add']
                        ],
                        [
                            ['text' => 'Рихтовщик', 'callback_data' => 'Рихтовщик add']
                        ],
                        [
                            ['text' => 'Моторист', 'callback_data' => 'Моторист add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'hrSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по персоналу', 'callback_data' => 'Менеджер по персоналу add']
                        ],
                        [
                            ['text' => 'HR-менеджер', 'callback_data' => 'HR-менеджер add']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель add']
                        ],
                        [
                            ['text' => 'Инженер по охране труда', 'callback_data' => 'Инженер по охране труда add']
                        ],
                        [
                            ['text' => 'Рекрутер', 'callback_data' => 'Рекрутер add']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч add']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог add']
                        ],
                        [
                            ['text' => 'Инспектор отдела кадров', 'callback_data' => 'Инспектор отдела кадров add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'bankSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Кассир', 'callback_data' => 'Кассир add']
                        ],
                        [
                            ['text' => 'Финансист', 'callback_data' => 'Финансист add']
                        ],
                        [
                            ['text' => 'Банкир', 'callback_data' => 'Банкир add']
                        ],
                        [
                            ['text' => 'Экономист', 'callback_data' => 'Экономист add']
                        ],
                        [
                            ['text' => 'Заведующий кассой банка', 'callback_data' => 'Заведующий кассой банка add']
                        ],
                        [
                            ['text' => 'Финансовый контроллер', 'callback_data' => 'Финансовый контроллер add']
                        ],
                        [
                            ['text' => 'Заместитель финансового директора', 'callback_data' => 'Зам. финансового директора add']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор add']
                        ],
                        [
                            ['text' => 'Аналитик', 'callback_data' => 'Аналитик add']
                        ],
                        [
                            ['text' => 'Директор отделения банка', 'callback_data' => 'Директор отделения банка add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'yuristSkill':
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
            $method = 'editMessageText';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Юрист', 'callback_data' => 'Юрист add']
                        ],
                        [
                            ['text' => 'Адвокат', 'callback_data' => 'Адвокат add']
                        ],
                        [
                            ['text' => 'Помощник адвоката', 'callback_data' => 'Помощник адвоката add']
                        ],
                        [
                            ['text' => 'Нотариус', 'callback_data' => 'Нотариус add']
                        ],
                        [
                            ['text' => 'Регистратор', 'callback_data' => 'Регистратор add']
                        ],
                        [
                            ['text' => 'Прокурор', 'callback_data' => 'Прокурор add']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский add']
                        ],
                        [
                            ['text' => 'Судья', 'callback_data' => 'Судья add']
                        ],
                        [
                            ['text' => 'Следователь', 'callback_data' => 'Следователь add']
                        ],
                        [
                            ['text' => 'Юрисконсульт', 'callback_data' => 'Юрисконсульт add']
                        ],
                        [
                            ['text' => 'Помощник судьи', 'callback_data' => 'Помощник судьи add']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер  ser2ch']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист ser2ch']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор ser2ch']
                        ],
                        [
                            ['text' => 'Разработчик', 'callback_data' => 'Разработчик ser2ch']
                        ],
                        [
                            ['text' => 'Программист 1C', 'callback_data' => 'Программист 1C ser2ch']
                        ],
                        [
                            ['text' => 'Верстальщик', 'callback_data' => 'Верстальщик ser2ch']
                        ],
                        [
                            ['text' => 'PR-специалист', 'callback_data' => 'PR-специалист ser2ch']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер ser2ch']
                        ],
                        [
                            ['text' => 'Таргетолог', 'callback_data' => 'Таргетолог ser2ch']
                        ],
                        [
                            ['text' => 'Project-менеджер', 'callback_data' => 'Project-менеджер ser2ch']
                        ],
                        [
                            ['text' => 'Администратор сайта', 'callback_data' => 'Администратор сайта ser2ch']
                        ],
                        [
                            ['text' => 'Cпециалист службы поддержки', 'callback_data' => 'Cлужба поддержки ser2ch']
                        ],
                        [
                            ['text' => 'Режиссер видеомонтажа', 'callback_data' => 'Режиссер видеомонтажа ser2ch']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог ser2ch']
                        ],
                        [
                            ['text' => 'Оператор технической поддержки', 'callback_data' => 'Оператор тех-поддержки ser2ch']
                        ],
                        [
                            ['text' => 'Администратор баз данных', 'callback_data' => 'Администратор-БД ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Управляющий магазином', 'callback_data' => 'Управляющий магазином ser2ch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор ser2ch']
                        ],
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Начальник отдела продаж ser2ch']
                        ],
                        [
                            ['text' => 'Мастер цеха', 'callback_data' => 'Мастер цеха ser2ch']
                        ],
                        [
                            ['text' => 'Администратор рецепции', 'callback_data' => 'Администратор рецепции ser2ch']
                        ],
                        [
                            ['text' => 'Начальник отдела кадров', 'callback_data' => 'Начальник отдела кадров ser2ch']
                        ],
                        [
                            ['text' => 'Супервайзер', 'callback_data' => 'Супервайзер ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по развитию', 'callback_data' => 'Менеджер по развитию ser2ch']
                        ],
                        [
                            ['text' => 'Администратор-кассир', 'callback_data' => 'Администратор-кассир ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея ser2ch']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер ser2ch']
                        ],
                        [
                            ['text' => 'Видеограф', 'callback_data' => 'Видеограф ser2ch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер ser2ch']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф ser2ch']
                        ],
                        [
                            ['text' => 'Дизайнер полиграфии', 'callback_data' => 'Дизайнер полиграфии ser2ch']
                        ],
                        [
                            ['text' => 'Oператор ПК', 'callback_data' => 'Oператор ПК ser2ch']
                        ],
                        [
                            ['text' => 'Флорист', 'callback_data' => 'Флорист ser2ch']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер ser2ch']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер ser2ch']
                        ],
                        [
                            ['text' => 'Дизайнер-конструктор', 'callback_data' => 'Дизайнер-конструктор ser2ch']
                        ],
                        [
                            ['text' => 'Мастер-ювелир', 'callback_data' => 'Мастер-ювелир ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Лешмейкер', 'callback_data' => 'Лешмейкер ser2ch']
                        ],
                        [
                            ['text' => 'Instagram-модель', 'callback_data' => 'Instagram-модель ser2ch']
                        ],
                        [
                            ['text' => 'Парикмахер', 'callback_data' => 'Парикмахер ser2ch']
                        ],
                        [
                            ['text' => 'Руководитель фитнес-клуба', 'callback_data' => 'Руководитель фитнес-клуба ser2ch']
                        ],
                        [
                            ['text' => 'Мастер маникюра', 'callback_data' => 'Мастер маникюра ser2ch']
                        ],
                        [
                            ['text' => 'Мастер лазерной эпиляции', 'callback_data' => 'Мастер лазерной эпиляции ser2ch']
                        ],
                        [
                            ['text' => 'Администратор салона красоты', 'callback_data' => 'Админ салона красоты ser2ch']
                        ],
                        [
                            ['text' => 'Барбер', 'callback_data' => 'Барбер ser2ch']
                        ],
                        [
                            ['text' => 'Косметолог', 'callback_data' => 'Косметолог ser2ch']
                        ],
                        [
                            ['text' => 'Тренер по йоге', 'callback_data' => 'Тренер по йоге ser2ch']
                        ],
                        [
                            ['text' => 'Стилист-консультант', 'callback_data' => 'Стилист-консультант ser2ch']
                        ],
                        [
                            ['text' => 'Тренер по футболу', 'callback_data' => 'Тренер по футболу ser2ch']
                        ],
                        [
                            ['text' => 'Дерматолог', 'callback_data' => 'Дерматолог ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Видеооператор', 'callback_data' => 'Видеооператор ser2ch']
                        ],
                        [
                            ['text' => 'Сценарист', 'callback_data' => 'Сценарист ser2ch']
                        ],
                        [
                            ['text' => 'Организатор мероприятий', 'callback_data' => 'Организатор мероприятий ser2ch']
                        ],
                        [
                            ['text' => 'Ведущий церемоний', 'callback_data' => 'Ведущий церемоний ser2ch']
                        ],
                        [
                            ['text' => 'Актриса', 'callback_data' => 'Актриса ser2ch']
                        ],
                        [
                            ['text' => 'Хореограф-постановщик', 'callback_data' => 'Хореограф-постановщик ser2ch']
                        ],
                        [
                            ['text' => 'Оператор чата', 'callback_data' => 'Оператор чата ser2ch']
                        ],
                        [
                            ['text' => 'Стример', 'callback_data' => 'Стример ser2ch']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф ser2ch']
                        ],
                        [
                            ['text' => 'Аниматор', 'callback_data' => 'Аниматор ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по бизнес-процессам', 'callback_data' => 'Менеджер по бизнес-процессам ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам ser2ch']
                        ],
                        [
                            ['text' => 'Сотрудник склада', 'callback_data' => 'Сотрудник склада ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам логистических услуг', 'callback_data' => 'Менеджер лог. услуг ser2ch']
                        ],
                        [
                            ['text' => 'Коммерческий менеджер', 'callback_data' => 'Коммерческий менеджер ser2ch']
                        ],
                        [
                            ['text' => 'Комплектовщик на склад', 'callback_data' => 'Комплектовщик на склад ser2ch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист ser2ch']
                        ],
                        [
                            ['text' => 'Грузчик', 'callback_data' => 'Грузчик ser2ch']
                        ],
                        [
                            ['text' => 'Приемщик товара', 'callback_data' => 'Приемщик товара ser2ch']
                        ],
                        [
                            ['text' => 'Водитель-грузчик', 'callback_data' => 'Водитель-грузчик ser2ch']
                        ],
                        [
                            ['text' => 'Оператор-упаковщик', 'callback_data' => 'Оператор-упаковщик ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по логистике', 'callback_data' => 'Менеджер по логистике ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер ser2ch']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог ser2ch']
                        ],
                        [
                            ['text' => 'Маркетолог', 'callback_data' => 'Маркетолог ser2ch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер ser2ch']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист ser2ch']
                        ],
                        [
                            ['text' => 'Категорийный менеджер', 'callback_data' => 'Категорийный менеджер ser2ch']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер ser2ch']
                        ],
                        [
                            ['text' => 'Помощник контент-менеджера', 'callback_data' => 'Помощник контент-менеджера ser2ch']
                        ],
                        [
                            ['text' => 'SEO-специалист', 'callback_data' => 'SEO-специалист ser2ch']
                        ],
                        [
                            ['text' => 'Операционный директор', 'callback_data' => 'Операционный директор ser2ch']
                        ],
                        [
                            ['text' => 'Арт-директор', 'callback_data' => 'Арт-директор ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Врач-офтальмолог', 'callback_data' => 'Врач-офтальмолог ser2ch']
                        ],
                        [
                            ['text' => 'Медицинский представитель', 'callback_data' => 'Медицинский представитель ser2ch']
                        ],
                        [
                            ['text' => 'Санитарка', 'callback_data' => 'Санитарка ser2ch']
                        ],
                        [
                            ['text' => 'Семейный врач', 'callback_data' => 'Семейный врач ser2ch']
                        ],
                        [
                            ['text' => 'Медсестра', 'callback_data' => 'Медсестра ser2ch']
                        ],
                        [
                            ['text' => 'Фармацевт', 'callback_data' => 'Фармацевт ser2ch']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант ser2ch']
                        ],
                        [
                            ['text' => 'Вирусолог', 'callback_data' => 'Вирусолог ser2ch']
                        ],
                        [
                            ['text' => 'Ассистент анестезиолога', 'callback_data' => 'Ассистент анестезиолога ser2ch']
                        ],
                        [
                            ['text' => 'Анастезиолог', 'callback_data' => 'Анастезиолог ser2ch']
                        ],
                        [
                            ['text' => 'Провизор', 'callback_data' => 'Провизор ser2ch']
                        ],
                        [
                            ['text' => 'Ветеринар', 'callback_data' => 'Ветеринар ser2ch']
                        ],
                        [
                            ['text' => 'Ассистент ветеринарного врача', 'callback_data' => 'Ассистент вет. врача ser2ch']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог ser2ch']
                        ],
                        [
                            ['text' => 'Психатр', 'callback_data' => 'Психатр ser2ch']
                        ],
                        [
                            ['text' => 'Стоматолог', 'callback_data' => 'Стоматолог ser2ch']
                        ],
                        [
                            ['text' => 'Кардиолог', 'callback_data' => 'Кардиолог ser2ch']
                        ],
                        [
                            ['text' => 'Хирург', 'callback_data' => 'Хирург ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Руководитель отдела продаж ser2ch']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости ser2ch']
                        ],
                        [
                            ['text' => 'Риэлтор', 'callback_data' => 'Риэлтор ser2ch']
                        ],
                        [
                            ['text' => 'Инспектор', 'callback_data' => 'Инспектор ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам ser2ch']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Воспитатель', 'callback_data' => 'Воспитатель ser2ch']
                        ],
                        [
                            ['text' => 'Преподаватель', 'callback_data' => 'Преподаватель ser2ch']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч ser2ch']
                        ],
                        [
                            ['text' => 'Социальный работник', 'callback_data' => 'Социальный работник ser2ch']
                        ],
                        [
                            ['text' => 'Психолог-консультант', 'callback_data' => 'Психолог-консультант ser2ch']
                        ],
                        [
                            ['text' => 'Логопед-дефектолог', 'callback_data' => 'Логопед-дефектолог ser2ch']
                        ],
                        [
                            ['text' => 'Методист', 'callback_data' => 'Методист ser2ch']
                        ],
                        [
                            ['text' => 'Репетитор', 'callback_data' => 'Репетитор ser2ch']
                        ],
                        [
                            ['text' => 'Ученый', 'callback_data' => 'Ученый ser2ch']
                        ],
                        [
                            ['text' => 'Помощник воспитателя', 'callback_data' => 'Помощник воспитателя ser2ch']
                        ],
                        [
                            ['text' => 'Няня', 'callback_data' => 'Няня ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор охраны', 'callback_data' => 'Инспектор охраны ser2ch']
                        ],
                        [
                            ['text' => 'Охранник', 'callback_data' => 'Охранник ser2ch']
                        ],
                        [
                            ['text' => 'Контролер-ревизор', 'callback_data' => 'Контролер-ревизор ser2ch']
                        ],
                        [
                            ['text' => 'Оператор видеонаблюдения', 'callback_data' => 'Оператор видеонаблюдения ser2ch']
                        ],
                        [
                            ['text' => 'Инженер пожарной безопасности', 'callback_data' => 'Инженер пожарной безопасности ser2ch']
                        ],
                        [
                            ['text' => 'Техник по охранным системам', 'callback_data' => 'Техник по охранным системам ser2ch']
                        ],
                        [
                            ['text' => 'Начальник службы безопасности', 'callback_data' => 'Начальник сл. безопасности ser2ch']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный ser2ch']
                        ],
                        [
                            ['text' => 'Специалист по безопасности', 'callback_data' => 'Специалист по безопасности ser2ch']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный ser2ch']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский ser2ch']
                        ],
                        [
                            ['text' => 'Водитель-инкассатор', 'callback_data' => 'Водитель-инкассатор ser2ch']
                        ],
                        [
                            ['text' => 'Инкассатор', 'callback_data' => 'Инкассатор ser2ch']
                        ],
                        [
                            ['text' => 'Оператор ПЦС', 'callback_data' => 'Оператор ПЦС ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по закупке', 'callback_data' => 'Менеджер по закупке ser2ch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец ser2ch']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам ser2ch']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер ser2ch']
                        ],
                        [
                            ['text' => 'Аналитик по продажам', 'callback_data' => 'Аналитик по продажам ser2ch']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер ser2ch']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант ser2ch']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер ser2ch']
                        ],
                        [
                            ['text' => 'Пекарь', 'callback_data' => 'Пекарь ser2ch']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик ser2ch']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник ser2ch']
                        ],
                        [
                            ['text' => 'Слесарь', 'callback_data' => 'Слесарь ser2ch']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий ser2ch']
                        ],
                        [
                            ['text' => 'Мастер-оптик', 'callback_data' => 'Мастер-оптик ser2ch']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея ser2ch']
                        ],
                        [
                            ['text' => 'Технолог', 'callback_data' => 'Технолог ser2ch']
                        ],
                        [
                            ['text' => 'Монтажник', 'callback_data' => 'Монтажник ser2ch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр ser2ch']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель ser2ch']
                        ],
                        [
                            ['text' => 'Инженер-проектировщик', 'callback_data' => 'Инженер-проектировщик ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам ser2ch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец ser2ch']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам ser2ch']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер ser2ch']
                        ],
                        [
                            ['text' => 'Аналитик продаж', 'callback_data' => 'Аналитик продаж ser2ch']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер ser2ch']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант ser2ch']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Помощник руководителя', 'callback_data' => 'Помощник руководителя ser2ch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист ser2ch']
                        ],
                        [
                            ['text' => 'Офис-менеджер', 'callback_data' => 'Офис-менеджер ser2ch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор ser2ch']
                        ],
                        [
                            ['text' => 'Оператор call-centre', 'callback_data' => 'Оператор call-centre ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по моб. продажам', 'callback_data' => 'Менеджер по моб. продажам ser2ch']
                        ],
                        [
                            ['text' => 'Администратор ресепшн', 'callback_data' => 'Администратор ресепшн ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам ser2ch']
                        ],
                        [
                            ['text' => 'Помощник по мониторингу', 'callback_data' => 'Помощник по мониторингу ser2ch']
                        ],
                        [
                            ['text' => 'Оператор ПК', 'callback_data' => 'Оператор ПК ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам ser2ch']
                        ],
                        [
                            ['text' => 'Секретарь', 'callback_data' => 'Секретарь ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант ser2ch']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант ser2ch']
                        ],
                        [
                            ['text' => 'Генетик', 'callback_data' => 'Генетик ser2ch']
                        ],
                        [
                            ['text' => 'Ветеринарный врач', 'callback_data' => 'Ветеринарный врач ser2ch']
                        ],
                        [
                            ['text' => 'Биолог', 'callback_data' => 'Биолог ser2ch']
                        ],
                        [
                            ['text' => 'Фермер', 'callback_data' => 'Фермер ser2ch']
                        ],
                        [
                            ['text' => 'Агроном', 'callback_data' => 'Агроном ser2ch']
                        ],
                        [
                            ['text' => 'Аналитик торгового рынка', 'callback_data' => 'Аналитик торгового рынка ser2ch']
                        ],
                        [
                            ['text' => 'Химик-хроматографист', 'callback_data' => 'Химик-хроматографист ser2ch']
                        ],
                        [
                            ['text' => 'Зернотрейдер', 'callback_data' => 'Зернотрейдер ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам зерновых культур', 'callback_data' => 'Менеджер по закупке зерна ser2ch']
                        ],
                        [
                            ['text' => 'Пробоотборник с/х продукции', 'callback_data' => 'Пробоотборник с/х продукции ser2ch']
                        ],
                        [
                            ['text' => 'Садовник', 'callback_data' => 'Садовник ser2ch']
                        ],
                        [
                            ['text' => 'Тракторист', 'callback_data' => 'Тракторист ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Фотограф в интернет-магазине', 'callback_data' => 'Фотограф в интернет-магазине ser2ch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер ser2ch']
                        ],
                        [
                            ['text' => 'Видеоредактор', 'callback_data' => 'Видеоредактор ser2ch']
                        ],
                        [
                            ['text' => 'SMM / контент-менеджер', 'callback_data' => 'SMM / контент-менеджер ser2ch']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер ser2ch']
                        ],
                        [
                            ['text' => 'Печатник', 'callback_data' => 'Печатник ser2ch']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер ser2ch']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер ser2ch']
                        ],
                        [
                            ['text' => 'Oператор цифровой печати', 'callback_data' => 'Oператор цифровой печати ser2ch']
                        ],
                        [
                            ['text' => 'Веб-журналист', 'callback_data' => 'Веб-журналист ser2ch']
                        ],
                        [
                            ['text' => 'Журналист', 'callback_data' => 'Журналист ser2ch']
                        ],
                        [
                            ['text' => 'Писатель', 'callback_data' => 'Писатель ser2ch']
                        ],
                        [
                            ['text' => 'Редактор', 'callback_data' => 'Редактор ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор-координатор', 'callback_data' => 'Инспектор-координатор ser2ch']
                        ],
                        [
                            ['text' => 'Сюрвейер', 'callback_data' => 'Сюрвейер ser2ch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер по страхованию', 'callback_data' => 'Менеджер по страхованию ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инженер технического надзора', 'callback_data' => 'Инженер технического надзора ser2ch']
                        ],
                        [
                            ['text' => 'Электромеханик контактной сети', 'callback_data' => 'Электромеханик контактной сети ser2ch']
                        ],
                        [
                            ['text' => 'Архитектор', 'callback_data' => 'Архитектор ser2ch']
                        ],
                        [
                            ['text' => 'Электромонтер', 'callback_data' => 'Электромонтер ser2ch']
                        ],
                        [
                            ['text' => 'Газорезчик', 'callback_data' => 'Газорезчик ser2ch']
                        ],
                        [
                            ['text' => 'Строитель', 'callback_data' => 'Строитель ser2ch']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий ser2ch']
                        ],
                        [
                            ['text' => 'Помощник Архитектора', 'callback_data' => 'Помощник Архитектора ser2ch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр ser2ch']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер ser2ch']
                        ],
                        [
                            ['text' => 'Мастер-бутафор', 'callback_data' => 'Мастер-бутафор ser2ch']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик ser2ch']
                        ],
                        [
                            ['text' => 'Штукатур', 'callback_data' => 'Штукатур ser2ch']
                        ],
                        [
                            ['text' => 'Сантехник', 'callback_data' => 'Сантехник ser2ch']
                        ],
                        [
                            ['text' => 'Инженер отдела продаж', 'callback_data' => 'Инженер отдела продаж ser2ch']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер ser2ch']
                        ],
                        [
                            ['text' => 'Электромеханик', 'callback_data' => 'Электромеханик ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Повар', 'callback_data' => 'Повар ser2ch']
                        ],
                        [
                            ['text' => 'Официант', 'callback_data' => 'Официант ser2ch']
                        ],
                        [
                            ['text' => 'Бариста', 'callback_data' => 'Бариста ser2ch']
                        ],
                        [
                            ['text' => 'Консьерж', 'callback_data' => 'Консьерж ser2ch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор ser2ch']
                        ],
                        [
                            ['text' => 'Мастер бьюти сферы', 'callback_data' => 'Мастер бьюти сферы ser2ch']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель ser2ch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец ser2ch']
                        ],
                        [
                            ['text' => 'Оператор', 'callback_data' => 'Оператор ser2ch']
                        ],
                        [
                            ['text' => 'Массажист', 'callback_data' => 'Массажист ser2ch']
                        ],
                        [
                            ['text' => 'Хостес', 'callback_data' => 'Хостес ser2ch']
                        ],
                        [
                            ['text' => 'Сомелье', 'callback_data' => 'Сомелье ser2ch']
                        ],
                        [
                            ['text' => 'Бармен', 'callback_data' => 'Бармен ser2ch']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Специалист технической поддержки', 'callback_data' => 'Специалист техподдержки ser2ch']
                        ],
                        [
                            ['text' => 'Инженер связи', 'callback_data' => 'Инженер связи ser2ch']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник ser2ch']
                        ],
                        [
                            ['text' => 'Оператор службы поддержки', 'callback_data' => 'Оператор службы поддержки ser2ch']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор ser2ch']
                        ],
                        [
                            ['text' => 'Саппорт-менеджер', 'callback_data' => 'Саппорт-менеджер ser2ch']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик ser2ch']
                        ],
                        [
                            ['text' => 'Монтажник связи', 'callback_data' => 'Монтажник связи ser2ch']
                        ],
                        [
                            ['text' => 'Диспетчер-логист', 'callback_data' => 'Диспетчер-логист ser2ch']
                        ],
                        [
                            ['text' => 'Менеджер call-центра', 'callback_data' => 'Менеджер call-центра ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Заместитель директора', 'callback_data' => 'Заместитель директора ser2ch']
                        ],
                        [
                            ['text' => 'Директор сервисного центра', 'callback_data' => 'Директор сервисного центра ser2ch']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель ser2ch']
                        ],
                        [
                            ['text' => 'Руководитель розничной сети', 'callback_data' => 'Руководитель розничной сети ser2ch']
                        ],
                        [
                            ['text' => 'Куратор', 'callback_data' => 'Куратор ser2ch']
                        ],
                        [
                            ['text' => 'Директор филиала', 'callback_data' => 'Директор филиала ser2ch']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор ser2ch']
                        ],
                        [
                            ['text' => 'Директор по персоналу', 'callback_data' => 'Директор по персоналу ser2ch']
                        ],
                        [
                            ['text' => 'PR-директор', 'callback_data' => 'PR-директор ser2ch']
                        ],
                        [
                            ['text' => 'Исполнительный директор', 'callback_data' => 'Исполнительный директор ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель ser2ch']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик ser2ch']
                        ],
                        [
                            ['text' => 'Автослесарь', 'callback_data' => 'Автослесарь ser2ch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист ser2ch']
                        ],
                        [
                            ['text' => 'Экспедитор', 'callback_data' => 'Экспедитор ser2ch']
                        ],
                        [
                            ['text' => 'Инкассатор-водитель', 'callback_data' => 'Инкассатор-водитель ser2ch']
                        ],
                        [
                            ['text' => 'Главный механик', 'callback_data' => 'Главный механик ser2ch']
                        ],
                        [
                            ['text' => 'Начальник отдела транспорта', 'callback_data' => 'Начальник отдела транспорта ser2ch']
                        ],
                        [
                            ['text' => 'Курьер', 'callback_data' => 'Курьер ser2ch']
                        ],
                        [
                            ['text' => 'Дальнобойщик', 'callback_data' => 'Дальнобойщик ser2ch']
                        ],
                        [
                            ['text' => 'Мастер СТО', 'callback_data' => 'Мастер СТО ser2ch']
                        ],
                        [
                            ['text' => 'Рихтовщик', 'callback_data' => 'Рихтовщик ser2ch']
                        ],
                        [
                            ['text' => 'Моторист', 'callback_data' => 'Моторист ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по персоналу', 'callback_data' => 'Менеджер по персоналу ser2ch']
                        ],
                        [
                            ['text' => 'HR-менеджер', 'callback_data' => 'HR-менеджер ser2ch']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель ser2ch']
                        ],
                        [
                            ['text' => 'Инженер по охране труда', 'callback_data' => 'Инженер по охране труда ser2ch']
                        ],
                        [
                            ['text' => 'Рекрутер', 'callback_data' => 'Рекрутер ser2ch']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч ser2ch']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог ser2ch']
                        ],
                        [
                            ['text' => 'Инспектор отдела кадров', 'callback_data' => 'Инспектор отдела кадров ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Кассир', 'callback_data' => 'Кассир ser2ch']
                        ],
                        [
                            ['text' => 'Финансист', 'callback_data' => 'Финансист ser2ch']
                        ],
                        [
                            ['text' => 'Банкир', 'callback_data' => 'Банкир ser2ch']
                        ],
                        [
                            ['text' => 'Экономист', 'callback_data' => 'Экономист ser2ch']
                        ],
                        [
                            ['text' => 'Заведующий кассой банка', 'callback_data' => 'Заведующий кассой банка ser2ch']
                        ],
                        [
                            ['text' => 'Финансовый контроллер', 'callback_data' => 'Финансовый контроллер ser2ch']
                        ],
                        [
                            ['text' => 'Заместитель финансового директора', 'callback_data' => 'Зам. финансового директора ser2ch']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор ser2ch']
                        ],
                        [
                            ['text' => 'Аналитик', 'callback_data' => 'Аналитик ser2ch']
                        ],
                        [
                            ['text' => 'Директор отделения банка', 'callback_data' => 'Директор отделения банка ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
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
            // Удаляем старое сообщение
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "Сейчас у вас указано:" . $msg . "\n\nВыберите навык:",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Юрист', 'callback_data' => 'Юрист ser2ch']
                        ],
                        [
                            ['text' => 'Адвокат', 'callback_data' => 'Адвокат ser2ch']
                        ],
                        [
                            ['text' => 'Помощник адвоката', 'callback_data' => 'Помощник адвоката ser2ch']
                        ],
                        [
                            ['text' => 'Нотариус', 'callback_data' => 'Нотариус ser2ch']
                        ],
                        [
                            ['text' => 'Регистратор', 'callback_data' => 'Регистратор ser2ch']
                        ],
                        [
                            ['text' => 'Прокурор', 'callback_data' => 'Прокурор ser2ch']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский ser2ch']
                        ],
                        [
                            ['text' => 'Судья', 'callback_data' => 'Судья ser2ch']
                        ],
                        [
                            ['text' => 'Следователь', 'callback_data' => 'Следователь ser2ch']
                        ],
                        [
                            ['text' => 'Юрисконсульт', 'callback_data' => 'Юрисконсульт ser2ch']
                        ],
                        [
                            ['text' => 'Помощник судьи', 'callback_data' => 'Помощник судьи ser2ch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'choiceSkills']
                        ],
                        [
                            ['text' => '👈 Вернуться в "Мой профиль"', 'callback_data' => 'profile']
                        ]
                    ]
                ]
            ];
            break;

        case 'ITSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер  s2erch']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист s2erch']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор s2erch']
                        ],
                        [
                            ['text' => 'Разработчик', 'callback_data' => 'Разработчик s2erch']
                        ],
                        [
                            ['text' => 'Программист 1C', 'callback_data' => 'Программист 1C s2erch']
                        ],
                        [
                            ['text' => 'Верстальщик', 'callback_data' => 'Верстальщик s2erch']
                        ],
                        [
                            ['text' => 'PR-специалист', 'callback_data' => 'PR-специалист s2erch']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер s2erch']
                        ],
                        [
                            ['text' => 'Таргетолог', 'callback_data' => 'Таргетолог s2erch']
                        ],
                        [
                            ['text' => 'Project-менеджер', 'callback_data' => 'Project-менеджер s2erch']
                        ],
                        [
                            ['text' => 'Администратор сайта', 'callback_data' => 'Администратор сайта s2erch']
                        ],
                        [
                            ['text' => 'Cпециалист службы поддержки', 'callback_data' => 'Cлужба поддержки s2erch']
                        ],
                        [
                            ['text' => 'Режиссер видеомонтажа', 'callback_data' => 'Режиссер видеомонтажа s2erch']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог s2erch']
                        ],
                        [
                            ['text' => 'Оператор технической поддержки', 'callback_data' => 'Оператор тех-поддержки s2erch']
                        ],
                        [
                            ['text' => 'Администратор баз данных', 'callback_data' => 'Администратор-БД s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'administrSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Управляющий магазином', 'callback_data' => 'Управляющий магазином s2erch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор s2erch']
                        ],
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Начальник отдела продаж s2erch']
                        ],
                        [
                            ['text' => 'Мастер цеха', 'callback_data' => 'Мастер цеха s2erch']
                        ],
                        [
                            ['text' => 'Администратор рецепции', 'callback_data' => 'Администратор рецепции s2erch']
                        ],
                        [
                            ['text' => 'Начальник отдела кадров', 'callback_data' => 'Начальник отдела кадров s2erch']
                        ],
                        [
                            ['text' => 'Супервайзер', 'callback_data' => 'Супервайзер s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по развитию', 'callback_data' => 'Менеджер по развитию s2erch']
                        ],
                        [
                            ['text' => 'Администратор-кассир', 'callback_data' => 'Администратор-кассир s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'designSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея s2erch']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер s2erch']
                        ],
                        [
                            ['text' => 'Видеограф', 'callback_data' => 'Видеограф s2erch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер s2erch']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф s2erch']
                        ],
                        [
                            ['text' => 'Дизайнер полиграфии', 'callback_data' => 'Дизайнер полиграфии s2erch']
                        ],
                        [
                            ['text' => 'Oператор ПК', 'callback_data' => 'Oператор ПК s2erch']
                        ],
                        [
                            ['text' => 'Флорист', 'callback_data' => 'Флорист s2erch']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер s2erch']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер s2erch']
                        ],
                        [
                            ['text' => 'Дизайнер-конструктор', 'callback_data' => 'Дизайнер-конструктор s2erch']
                        ],
                        [
                            ['text' => 'Мастер-ювелир', 'callback_data' => 'Мастер-ювелир s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'beautySkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Лешмейкер', 'callback_data' => 'Лешмейкер s2erch']
                        ],
                        [
                            ['text' => 'Instagram-модель', 'callback_data' => 'Instagram-модель s2erch']
                        ],
                        [
                            ['text' => 'Парикмахер', 'callback_data' => 'Парикмахер s2erch']
                        ],
                        [
                            ['text' => 'Руководитель фитнес-клуба', 'callback_data' => 'Руководитель фитнес-клуба s2erch']
                        ],
                        [
                            ['text' => 'Мастер маникюра', 'callback_data' => 'Мастер маникюра s2erch']
                        ],
                        [
                            ['text' => 'Мастер лазерной эпиляции', 'callback_data' => 'Мастер лазерной эпиляции s2erch']
                        ],
                        [
                            ['text' => 'Администратор салона красоты', 'callback_data' => 'Админ салона красоты s2erch']
                        ],
                        [
                            ['text' => 'Барбер', 'callback_data' => 'Барбер s2erch']
                        ],
                        [
                            ['text' => 'Косметолог', 'callback_data' => 'Косметолог s2erch']
                        ],
                        [
                            ['text' => 'Тренер по йоге', 'callback_data' => 'Тренер по йоге s2erch']
                        ],
                        [
                            ['text' => 'Стилист-консультант', 'callback_data' => 'Стилист-консультант s2erch']
                        ],
                        [
                            ['text' => 'Тренер по футболу', 'callback_data' => 'Тренер по футболу s2erch']
                        ],
                        [
                            ['text' => 'Дерматолог', 'callback_data' => 'Дерматолог s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'showbizSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Видеооператор', 'callback_data' => 'Видеооператор s2erch']
                        ],
                        [
                            ['text' => 'Сценарист', 'callback_data' => 'Сценарист s2erch']
                        ],
                        [
                            ['text' => 'Организатор мероприятий', 'callback_data' => 'Организатор мероприятий s2erch']
                        ],
                        [
                            ['text' => 'Ведущий церемоний', 'callback_data' => 'Ведущий церемоний s2erch']
                        ],
                        [
                            ['text' => 'Актриса', 'callback_data' => 'Актриса s2erch']
                        ],
                        [
                            ['text' => 'Хореограф-постановщик', 'callback_data' => 'Хореограф-постановщик s2erch']
                        ],
                        [
                            ['text' => 'Оператор чата', 'callback_data' => 'Оператор чата s2erch']
                        ],
                        [
                            ['text' => 'Стример', 'callback_data' => 'Стример s2erch']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф s2erch']
                        ],
                        [
                            ['text' => 'Аниматор', 'callback_data' => 'Аниматор s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по бизнес-процессам', 'callback_data' => 'Менеджер по бизнес-процессам s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'logistikaSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам s2erch']
                        ],
                        [
                            ['text' => 'Сотрудник склада', 'callback_data' => 'Сотрудник склада s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам логистических услуг', 'callback_data' => 'Менеджер лог. услуг s2erch']
                        ],
                        [
                            ['text' => 'Коммерческий менеджер', 'callback_data' => 'Коммерческий менеджер s2erch']
                        ],
                        [
                            ['text' => 'Комплектовщик на склад', 'callback_data' => 'Комплектовщик на склад s2erch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист s2erch']
                        ],
                        [
                            ['text' => 'Грузчик', 'callback_data' => 'Грузчик s2erch']
                        ],
                        [
                            ['text' => 'Приемщик товара', 'callback_data' => 'Приемщик товара s2erch']
                        ],
                        [
                            ['text' => 'Водитель-грузчик', 'callback_data' => 'Водитель-грузчик s2erch']
                        ],
                        [
                            ['text' => 'Оператор-упаковщик', 'callback_data' => 'Оператор-упаковщик s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по логистике', 'callback_data' => 'Менеджер по логистике s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'marketingSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер s2erch']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог s2erch']
                        ],
                        [
                            ['text' => 'Маркетолог', 'callback_data' => 'Маркетолог s2erch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер s2erch']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист s2erch']
                        ],
                        [
                            ['text' => 'Категорийный менеджер', 'callback_data' => 'Категорийный менеджер s2erch']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер s2erch']
                        ],
                        [
                            ['text' => 'Помощник контент-менеджера', 'callback_data' => 'Помощник контент-менеджера s2erch']
                        ],
                        [
                            ['text' => 'SEO-специалист', 'callback_data' => 'SEO-специалист s2erch']
                        ],
                        [
                            ['text' => 'Операционный директор', 'callback_data' => 'Операционный директор s2erch']
                        ],
                        [
                            ['text' => 'Арт-директор', 'callback_data' => 'Арт-директор s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'medicinaSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Врач-офтальмолог', 'callback_data' => 'Врач-офтальмолог s2erch']
                        ],
                        [
                            ['text' => 'Медицинский представитель', 'callback_data' => 'Медицинский представитель s2erch']
                        ],
                        [
                            ['text' => 'Санитарка', 'callback_data' => 'Санитарка s2erch']
                        ],
                        [
                            ['text' => 'Семейный врач', 'callback_data' => 'Семейный врач s2erch']
                        ],
                        [
                            ['text' => 'Медсестра', 'callback_data' => 'Медсестра s2erch']
                        ],
                        [
                            ['text' => 'Фармацевт', 'callback_data' => 'Фармацевт s2erch']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант s2erch']
                        ],
                        [
                            ['text' => 'Вирусолог', 'callback_data' => 'Вирусолог s2erch']
                        ],
                        [
                            ['text' => 'Ассистент анестезиолога', 'callback_data' => 'Ассистент анестезиолога s2erch']
                        ],
                        [
                            ['text' => 'Анастезиолог', 'callback_data' => 'Анастезиолог s2erch']
                        ],
                        [
                            ['text' => 'Провизор', 'callback_data' => 'Провизор s2erch']
                        ],
                        [
                            ['text' => 'Ветеринар', 'callback_data' => 'Ветеринар s2erch']
                        ],
                        [
                            ['text' => 'Ассистент ветеринарного врача', 'callback_data' => 'Ассистент вет. врача s2erch']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог s2erch']
                        ],
                        [
                            ['text' => 'Психатр', 'callback_data' => 'Психатр s2erch']
                        ],
                        [
                            ['text' => 'Стоматолог', 'callback_data' => 'Стоматолог s2erch']
                        ],
                        [
                            ['text' => 'Кардиолог', 'callback_data' => 'Кардиолог s2erch']
                        ],
                        [
                            ['text' => 'Хирург', 'callback_data' => 'Хирург s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'nedvizhimostSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Руководитель отдела продаж s2erch']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости s2erch']
                        ],
                        [
                            ['text' => 'Риэлтор', 'callback_data' => 'Риэлтор s2erch']
                        ],
                        [
                            ['text' => 'Инспектор', 'callback_data' => 'Инспектор s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам s2erch']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'naukaSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Воспитатель', 'callback_data' => 'Воспитатель s2erch']
                        ],
                        [
                            ['text' => 'Преподаватель', 'callback_data' => 'Преподаватель s2erch']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч s2erch']
                        ],
                        [
                            ['text' => 'Социальный работник', 'callback_data' => 'Социальный работник s2erch']
                        ],
                        [
                            ['text' => 'Психолог-консультант', 'callback_data' => 'Психолог-консультант s2erch']
                        ],
                        [
                            ['text' => 'Логопед-дефектолог', 'callback_data' => 'Логопед-дефектолог s2erch']
                        ],
                        [
                            ['text' => 'Методист', 'callback_data' => 'Методист s2erch']
                        ],
                        [
                            ['text' => 'Репетитор', 'callback_data' => 'Репетитор s2erch']
                        ],
                        [
                            ['text' => 'Ученый', 'callback_data' => 'Ученый s2erch']
                        ],
                        [
                            ['text' => 'Помощник воспитателя', 'callback_data' => 'Помощник воспитателя s2erch']
                        ],
                        [
                            ['text' => 'Няня', 'callback_data' => 'Няня s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'ohranaSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор охраны', 'callback_data' => 'Инспектор охраны s2erch']
                        ],
                        [
                            ['text' => 'Охранник', 'callback_data' => 'Охранник s2erch']
                        ],
                        [
                            ['text' => 'Контролер-ревизор', 'callback_data' => 'Контролер-ревизор s2erch']
                        ],
                        [
                            ['text' => 'Оператор видеонаблюдения', 'callback_data' => 'Оператор видеонаблюдения s2erch']
                        ],
                        [
                            ['text' => 'Инженер пожарной безопасности', 'callback_data' => 'Инженер пожарной безопасности s2erch']
                        ],
                        [
                            ['text' => 'Техник по охранным системам', 'callback_data' => 'Техник по охранным системам s2erch']
                        ],
                        [
                            ['text' => 'Начальник службы безопасности', 'callback_data' => 'Начальник сл. безопасности s2erch']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный s2erch']
                        ],
                        [
                            ['text' => 'Специалист по безопасности', 'callback_data' => 'Специалист по безопасности s2erch']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный s2erch']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский s2erch']
                        ],
                        [
                            ['text' => 'Водитель-инкассатор', 'callback_data' => 'Водитель-инкассатор s2erch']
                        ],
                        [
                            ['text' => 'Инкассатор', 'callback_data' => 'Инкассатор s2erch']
                        ],
                        [
                            ['text' => 'Оператор ПЦС', 'callback_data' => 'Оператор ПЦС s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'prodajiSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по закупке', 'callback_data' => 'Менеджер по закупке s2erch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец s2erch']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам s2erch']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер s2erch']
                        ],
                        [
                            ['text' => 'Аналитик по продажам', 'callback_data' => 'Аналитик по продажам s2erch']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер s2erch']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант s2erch']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'proizvodstvoSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер s2erch']
                        ],
                        [
                            ['text' => 'Пекарь', 'callback_data' => 'Пекарь s2erch']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик s2erch']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник s2erch']
                        ],
                        [
                            ['text' => 'Слесарь', 'callback_data' => 'Слесарь s2erch']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий s2erch']
                        ],
                        [
                            ['text' => 'Мастер-оптик', 'callback_data' => 'Мастер-оптик s2erch']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея s2erch']
                        ],
                        [
                            ['text' => 'Технолог', 'callback_data' => 'Технолог s2erch']
                        ],
                        [
                            ['text' => 'Монтажник', 'callback_data' => 'Монтажник s2erch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр s2erch']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель s2erch']
                        ],
                        [
                            ['text' => 'Инженер-проектировщик', 'callback_data' => 'Инженер-проектировщик s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'torgovlyaSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам s2erch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец s2erch']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам s2erch']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер s2erch']
                        ],
                        [
                            ['text' => 'Аналитик продаж', 'callback_data' => 'Аналитик продаж s2erch']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер s2erch']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант s2erch']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'sekretaringSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Помощник руководителя', 'callback_data' => 'Помощник руководителя s2erch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист s2erch']
                        ],
                        [
                            ['text' => 'Офис-менеджер', 'callback_data' => 'Офис-менеджер s2erch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор s2erch']
                        ],
                        [
                            ['text' => 'Оператор call-centre', 'callback_data' => 'Оператор call-centre s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по моб. продажам', 'callback_data' => 'Менеджер по моб. продажам s2erch']
                        ],
                        [
                            ['text' => 'Администратор ресепшн', 'callback_data' => 'Администратор ресепшн s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам s2erch']
                        ],
                        [
                            ['text' => 'Помощник по мониторингу', 'callback_data' => 'Помощник по мониторингу s2erch']
                        ],
                        [
                            ['text' => 'Оператор ПК', 'callback_data' => 'Оператор ПК s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам s2erch']
                        ],
                        [
                            ['text' => 'Секретарь', 'callback_data' => 'Секретарь s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'agrobiznesSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант s2erch']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант s2erch']
                        ],
                        [
                            ['text' => 'Генетик', 'callback_data' => 'Генетик s2erch']
                        ],
                        [
                            ['text' => 'Ветеринарный врач', 'callback_data' => 'Ветеринарный врач s2erch']
                        ],
                        [
                            ['text' => 'Биолог', 'callback_data' => 'Биолог s2erch']
                        ],
                        [
                            ['text' => 'Фермер', 'callback_data' => 'Фермер s2erch']
                        ],
                        [
                            ['text' => 'Агроном', 'callback_data' => 'Агроном s2erch']
                        ],
                        [
                            ['text' => 'Аналитик торгового рынка', 'callback_data' => 'Аналитик торгового рынка s2erch']
                        ],
                        [
                            ['text' => 'Химик-хроматографист', 'callback_data' => 'Химик-хроматографист s2erch']
                        ],
                        [
                            ['text' => 'Зернотрейдер', 'callback_data' => 'Зернотрейдер s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам зерновых культур', 'callback_data' => 'Менеджер по закупке зерна s2erch']
                        ],
                        [
                            ['text' => 'Пробоотборник с/х продукции', 'callback_data' => 'Пробоотборник с/х продукции s2erch']
                        ],
                        [
                            ['text' => 'Садовник', 'callback_data' => 'Садовник s2erch']
                        ],
                        [
                            ['text' => 'Тракторист', 'callback_data' => 'Тракторист s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'izdatelstvoSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Фотограф в интернет-магазине', 'callback_data' => 'Фотограф в интернет-магазине s2erch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер s2erch']
                        ],
                        [
                            ['text' => 'Видеоредактор', 'callback_data' => 'Видеоредактор s2erch']
                        ],
                        [
                            ['text' => 'SMM / контент-менеджер', 'callback_data' => 'SMM / контент-менеджер s2erch']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер s2erch']
                        ],
                        [
                            ['text' => 'Печатник', 'callback_data' => 'Печатник s2erch']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер s2erch']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер s2erch']
                        ],
                        [
                            ['text' => 'Oператор цифровой печати', 'callback_data' => 'Oператор цифровой печати s2erch']
                        ],
                        [
                            ['text' => 'Веб-журналист', 'callback_data' => 'Веб-журналист s2erch']
                        ],
                        [
                            ['text' => 'Журналист', 'callback_data' => 'Журналист s2erch']
                        ],
                        [
                            ['text' => 'Писатель', 'callback_data' => 'Писатель s2erch']
                        ],
                        [
                            ['text' => 'Редактор', 'callback_data' => 'Редактор s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'strahovanieSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор-координатор', 'callback_data' => 'Инспектор-координатор s2erch']
                        ],
                        [
                            ['text' => 'Сюрвейер', 'callback_data' => 'Сюрвейер s2erch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр s2erch']
                        ],
                        [
                            ['text' => 'Менеджер по страхованию', 'callback_data' => 'Менеджер по страхованию s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'stroitelstvoSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инженер технического надзора', 'callback_data' => 'Инженер технического надзора s2erch']
                        ],
                        [
                            ['text' => 'Электромеханик контактной сети', 'callback_data' => 'Электромеханик контактной сети s2erch']
                        ],
                        [
                            ['text' => 'Архитектор', 'callback_data' => 'Архитектор s2erch']
                        ],
                        [
                            ['text' => 'Электромонтер', 'callback_data' => 'Электромонтер s2erch']
                        ],
                        [
                            ['text' => 'Газорезчик', 'callback_data' => 'Газорезчик s2erch']
                        ],
                        [
                            ['text' => 'Строитель', 'callback_data' => 'Строитель s2erch']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий s2erch']
                        ],
                        [
                            ['text' => 'Помощник Архитектора', 'callback_data' => 'Помощник Архитектора s2erch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр s2erch']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер s2erch']
                        ],
                        [
                            ['text' => 'Мастер-бутафор', 'callback_data' => 'Мастер-бутафор s2erch']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик s2erch']
                        ],
                        [
                            ['text' => 'Штукатур', 'callback_data' => 'Штукатур s2erch']
                        ],
                        [
                            ['text' => 'Сантехник', 'callback_data' => 'Сантехник s2erch']
                        ],
                        [
                            ['text' => 'Инженер отдела продаж', 'callback_data' => 'Инженер отдела продаж s2erch']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер s2erch']
                        ],
                        [
                            ['text' => 'Электромеханик', 'callback_data' => 'Электромеханик s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'obsluzhivanieSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Повар', 'callback_data' => 'Повар s2erch']
                        ],
                        [
                            ['text' => 'Официант', 'callback_data' => 'Официант s2erch']
                        ],
                        [
                            ['text' => 'Бариста', 'callback_data' => 'Бариста s2erch']
                        ],
                        [
                            ['text' => 'Консьерж', 'callback_data' => 'Консьерж s2erch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор s2erch']
                        ],
                        [
                            ['text' => 'Мастер бьюти сферы', 'callback_data' => 'Мастер бьюти сферы s2erch']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель s2erch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец s2erch']
                        ],
                        [
                            ['text' => 'Оператор', 'callback_data' => 'Оператор s2erch']
                        ],
                        [
                            ['text' => 'Массажист', 'callback_data' => 'Массажист s2erch']
                        ],
                        [
                            ['text' => 'Хостес', 'callback_data' => 'Хостес s2erch']
                        ],
                        [
                            ['text' => 'Сомелье', 'callback_data' => 'Сомелье s2erch']
                        ],
                        [
                            ['text' => 'Бармен', 'callback_data' => 'Бармен s2erch']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'telecomunikaciiSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Специалист технической поддержки', 'callback_data' => 'Специалист техподдержки s2erch']
                        ],
                        [
                            ['text' => 'Инженер связи', 'callback_data' => 'Инженер связи s2erch']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник s2erch']
                        ],
                        [
                            ['text' => 'Оператор службы поддержки', 'callback_data' => 'Оператор службы поддержки s2erch']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор s2erch']
                        ],
                        [
                            ['text' => 'Саппорт-менеджер', 'callback_data' => 'Саппорт-менеджер s2erch']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик s2erch']
                        ],
                        [
                            ['text' => 'Монтажник связи', 'callback_data' => 'Монтажник связи s2erch']
                        ],
                        [
                            ['text' => 'Диспетчер-логист', 'callback_data' => 'Диспетчер-логист s2erch']
                        ],
                        [
                            ['text' => 'Менеджер call-центра', 'callback_data' => 'Менеджер call-центра s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'topmenSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Заместитель директора', 'callback_data' => 'Заместитель директора s2erch']
                        ],
                        [
                            ['text' => 'Директор сервисного центра', 'callback_data' => 'Директор сервисного центра s2erch']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель s2erch']
                        ],
                        [
                            ['text' => 'Руководитель розничной сети', 'callback_data' => 'Руководитель розничной сети s2erch']
                        ],
                        [
                            ['text' => 'Куратор', 'callback_data' => 'Куратор s2erch']
                        ],
                        [
                            ['text' => 'Директор филиала', 'callback_data' => 'Директор филиала s2erch']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор s2erch']
                        ],
                        [
                            ['text' => 'Директор по персоналу', 'callback_data' => 'Директор по персоналу s2erch']
                        ],
                        [
                            ['text' => 'PR-директор', 'callback_data' => 'PR-директор s2erch']
                        ],
                        [
                            ['text' => 'Исполнительный директор', 'callback_data' => 'Исполнительный директор s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'avtobizSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель s2erch']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик s2erch']
                        ],
                        [
                            ['text' => 'Автослесарь', 'callback_data' => 'Автослесарь s2erch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист s2erch']
                        ],
                        [
                            ['text' => 'Экспедитор', 'callback_data' => 'Экспедитор s2erch']
                        ],
                        [
                            ['text' => 'Инкассатор-водитель', 'callback_data' => 'Инкассатор-водитель s2erch']
                        ],
                        [
                            ['text' => 'Главный механик', 'callback_data' => 'Главный механик s2erch']
                        ],
                        [
                            ['text' => 'Начальник отдела транспорта', 'callback_data' => 'Начальник отдела транспорта s2erch']
                        ],
                        [
                            ['text' => 'Курьер', 'callback_data' => 'Курьер s2erch']
                        ],
                        [
                            ['text' => 'Дальнобойщик', 'callback_data' => 'Дальнобойщик s2erch']
                        ],
                        [
                            ['text' => 'Мастер СТО', 'callback_data' => 'Мастер СТО s2erch']
                        ],
                        [
                            ['text' => 'Рихтовщик', 'callback_data' => 'Рихтовщик s2erch']
                        ],
                        [
                            ['text' => 'Моторист', 'callback_data' => 'Моторист s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'hrSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по персоналу', 'callback_data' => 'Менеджер по персоналу s2erch']
                        ],
                        [
                            ['text' => 'HR-менеджер', 'callback_data' => 'HR-менеджер s2erch']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель s2erch']
                        ],
                        [
                            ['text' => 'Инженер по охране труда', 'callback_data' => 'Инженер по охране труда s2erch']
                        ],
                        [
                            ['text' => 'Рекрутер', 'callback_data' => 'Рекрутер s2erch']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч s2erch']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог s2erch']
                        ],
                        [
                            ['text' => 'Инспектор отдела кадров', 'callback_data' => 'Инспектор отдела кадров s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'bankSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Кассир', 'callback_data' => 'Кассир s2erch']
                        ],
                        [
                            ['text' => 'Финансист', 'callback_data' => 'Финансист s2erch']
                        ],
                        [
                            ['text' => 'Банкир', 'callback_data' => 'Банкир s2erch']
                        ],
                        [
                            ['text' => 'Экономист', 'callback_data' => 'Экономист s2erch']
                        ],
                        [
                            ['text' => 'Заведующий кассой банка', 'callback_data' => 'Заведующий кассой банка s2erch']
                        ],
                        [
                            ['text' => 'Финансовый контроллер', 'callback_data' => 'Финансовый контроллер s2erch']
                        ],
                        [
                            ['text' => 'Заместитель финансового директора', 'callback_data' => 'Зам. финансового директора s2erch']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор s2erch']
                        ],
                        [
                            ['text' => 'Аналитик', 'callback_data' => 'Аналитик s2erch']
                        ],
                        [
                            ['text' => 'Директор отделения банка', 'callback_data' => 'Директор отделения банка s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'yuristSkill s1erch':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);

            $method = 'sendMessage';
            $send_data = [
                'text' => "💪 *С кем вместе обучаться*\n\n_Выберите навык:_",
                "parse_mode" => "Markdown",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Юрист', 'callback_data' => 'Юрист s2erch']
                        ],
                        [
                            ['text' => 'Адвокат', 'callback_data' => 'Адвокат s2erch']
                        ],
                        [
                            ['text' => 'Помощник адвоката', 'callback_data' => 'Помощник адвоката s2erch']
                        ],
                        [
                            ['text' => 'Нотариус', 'callback_data' => 'Нотариус s2erch']
                        ],
                        [
                            ['text' => 'Регистратор', 'callback_data' => 'Регистратор s2erch']
                        ],
                        [
                            ['text' => 'Прокурор', 'callback_data' => 'Прокурор s2erch']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский s2erch']
                        ],
                        [
                            ['text' => 'Судья', 'callback_data' => 'Судья s2erch']
                        ],
                        [
                            ['text' => 'Следователь', 'callback_data' => 'Следователь s2erch']
                        ],
                        [
                            ['text' => 'Юрисконсульт', 'callback_data' => 'Юрисконсульт s2erch']
                        ],
                        [
                            ['text' => 'Помощник судьи', 'callback_data' => 'Помощник судьи s2erch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'learnFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'ITSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер firstch']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист firstch']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор firstch']
                        ],
                        [
                            ['text' => 'Разработчик', 'callback_data' => 'Разработчик firstch']
                        ],
                        [
                            ['text' => 'Программист 1C', 'callback_data' => 'Программист 1C firstch']
                        ],
                        [
                            ['text' => 'Верстальщик', 'callback_data' => 'Верстальщик firstch']
                        ],
                        [
                            ['text' => 'PR-специалист', 'callback_data' => 'PR-специалист firstch']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер firstch']
                        ],
                        [
                            ['text' => 'Таргетолог', 'callback_data' => 'Таргетолог firstch']
                        ],
                        [
                            ['text' => 'Project-менеджер', 'callback_data' => 'Project-менеджер firstch']
                        ],
                        [
                            ['text' => 'Администратор сайта', 'callback_data' => 'Администратор сайта firstch']
                        ],
                        [
                            ['text' => 'Cпециалист службы поддержки', 'callback_data' => 'Cлужба поддержки firstch']
                        ],
                        [
                            ['text' => 'Режиссер видеомонтажа', 'callback_data' => 'Режиссер видеомонтажа firstch']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог firstch']
                        ],
                        [
                            ['text' => 'Оператор технической поддержки', 'callback_data' => 'Оператор тех-поддержки firstch']
                        ],
                        [
                            ['text' => 'Администратор баз данных', 'callback_data' => 'Администратор-БД firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'administrSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Управляющий магазином', 'callback_data' => 'Управляющий магазином firstch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор firstch']
                        ],
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Начальник отдела продаж firstch']
                        ],
                        [
                            ['text' => 'Мастер цеха', 'callback_data' => 'Мастер цеха firstch']
                        ],
                        [
                            ['text' => 'Администратор рецепции', 'callback_data' => 'Администратор рецепции firstch']
                        ],
                        [
                            ['text' => 'Начальник отдела кадров', 'callback_data' => 'Начальник отдела кадров firstch']
                        ],
                        [
                            ['text' => 'Супервайзер', 'callback_data' => 'Супервайзер firstch']
                        ],
                        [
                            ['text' => 'Менеджер по развитию', 'callback_data' => 'Менеджер по развитию firstch']
                        ],
                        [
                            ['text' => 'Администратор-кассир', 'callback_data' => 'Администратор-кассир firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'designSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея firstch']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер firstch']
                        ],
                        [
                            ['text' => 'Видеограф', 'callback_data' => 'Видеограф firstch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер firstch']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф firstch']
                        ],
                        [
                            ['text' => 'Дизайнер полиграфии', 'callback_data' => 'Дизайнер полиграфии firstch']
                        ],
                        [
                            ['text' => 'Oператор ПК', 'callback_data' => 'Oператор ПК firstch']
                        ],
                        [
                            ['text' => 'Флорист', 'callback_data' => 'Флорист firstch']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер firstch']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер firstch']
                        ],
                        [
                            ['text' => 'Дизайнер-конструктор', 'callback_data' => 'Дизайнер-конструктор firstch']
                        ],
                        [
                            ['text' => 'Мастер-ювелир', 'callback_data' => 'Мастер-ювелир firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'beautySkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Лешмейкер', 'callback_data' => 'Лешмейкер firstch']
                        ],
                        [
                            ['text' => 'Instagram-модель', 'callback_data' => 'Instagram-модель firstch']
                        ],
                        [
                            ['text' => 'Парикмахер', 'callback_data' => 'Парикмахер firstch']
                        ],
                        [
                            ['text' => 'Руководитель фитнес-клуба', 'callback_data' => 'Руководитель фитнес-клуба firstch']
                        ],
                        [
                            ['text' => 'Мастер маникюра', 'callback_data' => 'Мастер маникюра firstch']
                        ],
                        [
                            ['text' => 'Мастер лазерной эпиляции', 'callback_data' => 'Мастер лазерной эпиляции firstch']
                        ],
                        [
                            ['text' => 'Администратор салона красоты', 'callback_data' => 'Админ салона красоты firstch']
                        ],
                        [
                            ['text' => 'Барбер', 'callback_data' => 'Барбер firstch']
                        ],
                        [
                            ['text' => 'Косметолог', 'callback_data' => 'Косметолог firstch']
                        ],
                        [
                            ['text' => 'Тренер по йоге', 'callback_data' => 'Тренер по йоге firstch']
                        ],
                        [
                            ['text' => 'Стилист-консультант', 'callback_data' => 'Стилист-консультант firstch']
                        ],
                        [
                            ['text' => 'Тренер по футболу', 'callback_data' => 'Тренер по футболу firstch']
                        ],
                        [
                            ['text' => 'Дерматолог', 'callback_data' => 'Дерматолог firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'showbizSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Видеооператор', 'callback_data' => 'Видеооператор firstch']
                        ],
                        [
                            ['text' => 'Сценарист', 'callback_data' => 'Сценарист firstch']
                        ],
                        [
                            ['text' => 'Организатор мероприятий', 'callback_data' => 'Организатор мероприятий firstch']
                        ],
                        [
                            ['text' => 'Ведущий церемоний', 'callback_data' => 'Ведущий церемоний firstch']
                        ],
                        [
                            ['text' => 'Актриса', 'callback_data' => 'Актриса firstch']
                        ],
                        [
                            ['text' => 'Хореограф-постановщик', 'callback_data' => 'Хореограф-постановщик firstch']
                        ],
                        [
                            ['text' => 'Оператор чата', 'callback_data' => 'Оператор чата firstch']
                        ],
                        [
                            ['text' => 'Стример', 'callback_data' => 'Стример firstch']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф firstch']
                        ],
                        [
                            ['text' => 'Аниматор', 'callback_data' => 'Аниматор ch']
                        ],
                        [
                            ['text' => 'Менеджер по бизнес-процессам', 'callback_data' => 'Менеджер по бизнес-процессам firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'logistikaSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам firstch']
                        ],
                        [
                            ['text' => 'Сотрудник склада', 'callback_data' => 'Сотрудник склада firstch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам логистических услуг', 'callback_data' => 'Менеджер лог. услуг firstch']
                        ],
                        [
                            ['text' => 'Коммерческий менеджер', 'callback_data' => 'Коммерческий менеджер firstch']
                        ],
                        [
                            ['text' => 'Комплектовщик на склад', 'callback_data' => 'Комплектовщик на склад firstch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист firstch']
                        ],
                        [
                            ['text' => 'Грузчик', 'callback_data' => 'Грузчик firstch']
                        ],
                        [
                            ['text' => 'Приемщик товара', 'callback_data' => 'Приемщик товара firstch']
                        ],
                        [
                            ['text' => 'Водитель-грузчик', 'callback_data' => 'Водитель-грузчик firstch']
                        ],
                        [
                            ['text' => 'Оператор-упаковщик', 'callback_data' => 'Оператор-упаковщик firstch']
                        ],
                        [
                            ['text' => 'Менеджер по логистике', 'callback_data' => 'Менеджер по логистике firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'marketingSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер firstch']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог firstch']
                        ],
                        [
                            ['text' => 'Маркетолог', 'callback_data' => 'Маркетолог firstch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер firstch']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист firstch']
                        ],
                        [
                            ['text' => 'Категорийный менеджер', 'callback_data' => 'Категорийный менеджер firstch']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер firstch']
                        ],
                        [
                            ['text' => 'Помощник контент-менеджера', 'callback_data' => 'Помощник контент-менеджера firstch']
                        ],
                        [
                            ['text' => 'SEO-специалист', 'callback_data' => 'SEO-специалист firstch']
                        ],
                        [
                            ['text' => 'Операционный директор', 'callback_data' => 'Операционный директор firstch']
                        ],
                        [
                            ['text' => 'Арт-директор', 'callback_data' => 'Арт-директор firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'medicinaSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Врач-офтальмолог', 'callback_data' => 'Врач-офтальмолог firstch']
                        ],
                        [
                            ['text' => 'Медицинский представитель', 'callback_data' => 'Медицинский представитель firstch']
                        ],
                        [
                            ['text' => 'Санитарка', 'callback_data' => 'Санитарка firstch']
                        ],
                        [
                            ['text' => 'Семейный врач', 'callback_data' => 'Семейный врач firstch']
                        ],
                        [
                            ['text' => 'Медсестра', 'callback_data' => 'Медсестра firstch']
                        ],
                        [
                            ['text' => 'Фармацевт', 'callback_data' => 'Фармацевт firstch']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант firstch']
                        ],
                        [
                            ['text' => 'Вирусолог', 'callback_data' => 'Вирусолог firstch']
                        ],
                        [
                            ['text' => 'Ассистент анестезиолога', 'callback_data' => 'Ассистент анестезиолога firstch']
                        ],
                        [
                            ['text' => 'Анастезиолог', 'callback_data' => 'Анастезиолог firstch']
                        ],
                        [
                            ['text' => 'Провизор', 'callback_data' => 'Провизор firstch']
                        ],
                        [
                            ['text' => 'Ветеринар', 'callback_data' => 'Ветеринар firstch']
                        ],
                        [
                            ['text' => 'Ассистент ветеринарного врача', 'callback_data' => 'Ассистент вет. врача firstch']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог firstch']
                        ],
                        [
                            ['text' => 'Психатр', 'callback_data' => 'Психатр firstch']
                        ],
                        [
                            ['text' => 'Стоматолог', 'callback_data' => 'Стоматолог firstch']
                        ],
                        [
                            ['text' => 'Кардиолог', 'callback_data' => 'Кардиолог firstch']
                        ],
                        [
                            ['text' => 'Хирург', 'callback_data' => 'Хирург firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'nedvizhimostSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Руководитель отдела продаж firstch']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости firstch']
                        ],
                        [
                            ['text' => 'Риэлтор', 'callback_data' => 'Риэлтор firstch']
                        ],
                        [
                            ['text' => 'Инспектор', 'callback_data' => 'Инспектор firstch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам firstch']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'naukaSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Воспитатель', 'callback_data' => 'Воспитатель firstch']
                        ],
                        [
                            ['text' => 'Преподаватель', 'callback_data' => 'Преподаватель firstch']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч firstch']
                        ],
                        [
                            ['text' => 'Социальный работник', 'callback_data' => 'Социальный работник firstch']
                        ],
                        [
                            ['text' => 'Психолог-консультант', 'callback_data' => 'Психолог-консультант firstch']
                        ],
                        [
                            ['text' => 'Логопед-дефектолог', 'callback_data' => 'Логопед-дефектолог firstch']
                        ],
                        [
                            ['text' => 'Методист', 'callback_data' => 'Методист firstch']
                        ],
                        [
                            ['text' => 'Репетитор', 'callback_data' => 'Репетитор firstch']
                        ],
                        [
                            ['text' => 'Ученый', 'callback_data' => 'Ученый firstch']
                        ],
                        [
                            ['text' => 'Помощник воспитателя', 'callback_data' => 'Помощник воспитателя firstch']
                        ],
                        [
                            ['text' => 'Няня', 'callback_data' => 'Няня firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'ohranaSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор охраны', 'callback_data' => 'Инспектор охраны firstch']
                        ],
                        [
                            ['text' => 'Охранник', 'callback_data' => 'Охранник firstch']
                        ],
                        [
                            ['text' => 'Контролер-ревизор', 'callback_data' => 'Контролер-ревизор firstch']
                        ],
                        [
                            ['text' => 'Оператор видеонаблюдения', 'callback_data' => 'Оператор видеонаблюдения firstch']
                        ],
                        [
                            ['text' => 'Инженер пожарной безопасности', 'callback_data' => 'Инженер пожарной безопасности firstch']
                        ],
                        [
                            ['text' => 'Техник по охранным системам', 'callback_data' => 'Техник по охранным системам firstch']
                        ],
                        [
                            ['text' => 'Начальник службы безопасности', 'callback_data' => 'Начальник сл. безопасности firstch']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный firstch']
                        ],
                        [
                            ['text' => 'Специалист по безопасности', 'callback_data' => 'Специалист по безопасности firstch']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный firstch']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский firstch']
                        ],
                        [
                            ['text' => 'Водитель-инкассатор', 'callback_data' => 'Водитель-инкассатор firstch']
                        ],
                        [
                            ['text' => 'Инкассатор', 'callback_data' => 'Инкассатор firstch']
                        ],
                        [
                            ['text' => 'Оператор ПЦС', 'callback_data' => 'Оператор ПЦС firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'prodajiSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам firstch']
                        ],
                        [
                            ['text' => 'Менеджер по закупке', 'callback_data' => 'Менеджер по закупке firstch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец firstch']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель firstch']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам firstch']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер firstch']
                        ],
                        [
                            ['text' => 'Аналитик по продажам', 'callback_data' => 'Аналитик по продажам firstch']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер firstch']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант firstch']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'proizvodstvoSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер firstch']
                        ],
                        [
                            ['text' => 'Пекарь', 'callback_data' => 'Пекарь firstch']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик firstch']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник firstch']
                        ],
                        [
                            ['text' => 'Слесарь', 'callback_data' => 'Слесарь firstch']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий firstch']
                        ],
                        [
                            ['text' => 'Мастер-оптик', 'callback_data' => 'Мастер-оптик firstch']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея firstch']
                        ],
                        [
                            ['text' => 'Технолог', 'callback_data' => 'Технолог firstch']
                        ],
                        [
                            ['text' => 'Монтажник', 'callback_data' => 'Монтажник firstch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр firstch']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель firstch']
                        ],
                        [
                            ['text' => 'Инженер-проектировщик', 'callback_data' => 'Инженер-проектировщик firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'torgovlyaSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам firstch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам firstch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец firstch']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель firstch']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам firstch']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер firstch']
                        ],
                        [
                            ['text' => 'Аналитик продаж', 'callback_data' => 'Аналитик продаж firstch']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер firstch']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант firstch']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'sekretaringSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Помощник руководителя', 'callback_data' => 'Помощник руководителя firstch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист firstch']
                        ],
                        [
                            ['text' => 'Офис-менеджер', 'callback_data' => 'Офис-менеджер firstch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор firstch']
                        ],
                        [
                            ['text' => 'Оператор call-centre', 'callback_data' => 'Оператор call-centre firstch']
                        ],
                        [
                            ['text' => 'Менеджер по моб. продажам', 'callback_data' => 'Менеджер по моб. продажам firstch']
                        ],
                        [
                            ['text' => 'Администратор ресепшн', 'callback_data' => 'Администратор ресепшн firstch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам firstch']
                        ],
                        [
                            ['text' => 'Помощник по мониторингу', 'callback_data' => 'Помощник по мониторингу firstch']
                        ],
                        [
                            ['text' => 'Оператор ПК', 'callback_data' => 'Оператор ПК firstch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам firstch']
                        ],
                        [
                            ['text' => 'Секретарь', 'callback_data' => 'Секретарь firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'agrobiznesSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант firstch']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант firstch']
                        ],
                        [
                            ['text' => 'Генетик', 'callback_data' => 'Генетик firstch']
                        ],
                        [
                            ['text' => 'Ветеринарный врач', 'callback_data' => 'Ветеринарный врач firstch']
                        ],
                        [
                            ['text' => 'Биолог', 'callback_data' => 'Биолог firstch']
                        ],
                        [
                            ['text' => 'Фермер', 'callback_data' => 'Фермер firstch']
                        ],
                        [
                            ['text' => 'Агроном', 'callback_data' => 'Агроном firstch']
                        ],
                        [
                            ['text' => 'Аналитик торгового рынка', 'callback_data' => 'Аналитик торгового рынка firstch']
                        ],
                        [
                            ['text' => 'Химик-хроматографист', 'callback_data' => 'Химик-хроматографист firstch']
                        ],
                        [
                            ['text' => 'Зернотрейдер', 'callback_data' => 'Зернотрейдер firstch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам зерновых культур', 'callback_data' => 'Менеджер по закупке зерна firstch']
                        ],
                        [
                            ['text' => 'Пробоотборник с/х продукции', 'callback_data' => 'Пробоотборник с/х продукции firstch']
                        ],
                        [
                            ['text' => 'Садовник', 'callback_data' => 'Садовник firstch']
                        ],
                        [
                            ['text' => 'Тракторист', 'callback_data' => 'Тракторист firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'izdatelstvoSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Фотограф в интернет-магазине', 'callback_data' => 'Фотограф в интернет-магазине firstch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер firstch']
                        ],
                        [
                            ['text' => 'Видеоредактор', 'callback_data' => 'Видеоредактор firstch']
                        ],
                        [
                            ['text' => 'SMM / контент-менеджер', 'callback_data' => 'SMM / контент-менеджер firstch']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер firstch']
                        ],
                        [
                            ['text' => 'Печатник', 'callback_data' => 'Печатник firstch']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер firstch']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер firstch']
                        ],
                        [
                            ['text' => 'Oператор цифровой печати', 'callback_data' => 'Oператор цифровой печати firstch']
                        ],
                        [
                            ['text' => 'Веб-журналист', 'callback_data' => 'Веб-журналист firstch']
                        ],
                        [
                            ['text' => 'Журналист', 'callback_data' => 'Журналист firstch']
                        ],
                        [
                            ['text' => 'Писатель', 'callback_data' => 'Писатель firstch']
                        ],
                        [
                            ['text' => 'Редактор', 'callback_data' => 'Редактор firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'strahovanieSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор-координатор', 'callback_data' => 'Инспектор-координатор firstch']
                        ],
                        [
                            ['text' => 'Сюрвейер', 'callback_data' => 'Сюрвейер firstch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр firstch']
                        ],
                        [
                            ['text' => 'Менеджер по страхованию', 'callback_data' => 'Менеджер по страхованию firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'stroitelstvoSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инженер технического надзора', 'callback_data' => 'Инженер технического надзора firstch']
                        ],
                        [
                            ['text' => 'Электромеханик контактной сети', 'callback_data' => 'Электромеханик контактной сети firstch']
                        ],
                        [
                            ['text' => 'Архитектор', 'callback_data' => 'Архитектор firstch']
                        ],
                        [
                            ['text' => 'Электромонтер', 'callback_data' => 'Электромонтер firstch']
                        ],
                        [
                            ['text' => 'Газорезчик', 'callback_data' => 'Газорезчик firstch']
                        ],
                        [
                            ['text' => 'Строитель', 'callback_data' => 'Строитель firstch']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий firstch']
                        ],
                        [
                            ['text' => 'Помощник Архитектора', 'callback_data' => 'Помощник Архитектора firstch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр firstch']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер firstch']
                        ],
                        [
                            ['text' => 'Мастер-бутафор', 'callback_data' => 'Мастер-бутафор firstch']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик firstch']
                        ],
                        [
                            ['text' => 'Штукатур', 'callback_data' => 'Штукатур firstch']
                        ],
                        [
                            ['text' => 'Сантехник', 'callback_data' => 'Сантехник firstch']
                        ],
                        [
                            ['text' => 'Инженер отдела продаж', 'callback_data' => 'Инженер отдела продаж firstch']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер firstch']
                        ],
                        [
                            ['text' => 'Электромеханик', 'callback_data' => 'Электромеханик firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'obsluzhivanieSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Повар', 'callback_data' => 'Повар firstch']
                        ],
                        [
                            ['text' => 'Официант', 'callback_data' => 'Официант firstch']
                        ],
                        [
                            ['text' => 'Бариста', 'callback_data' => 'Бариста firstch']
                        ],
                        [
                            ['text' => 'Консьерж', 'callback_data' => 'Консьерж firstch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор firstch']
                        ],
                        [
                            ['text' => 'Мастер бьюти сферы', 'callback_data' => 'Мастер бьюти сферы firstch']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель firstch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец firstch']
                        ],
                        [
                            ['text' => 'Оператор', 'callback_data' => 'Оператор firstch']
                        ],
                        [
                            ['text' => 'Массажист', 'callback_data' => 'Массажист firstch']
                        ],
                        [
                            ['text' => 'Хостес', 'callback_data' => 'Хостес firstch']
                        ],
                        [
                            ['text' => 'Сомелье', 'callback_data' => 'Сомелье firstch']
                        ],
                        [
                            ['text' => 'Бармен', 'callback_data' => 'Бармен firstch']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'telecomunikaciiSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Специалист технической поддержки', 'callback_data' => 'Специалист техподдержки firstch']
                        ],
                        [
                            ['text' => 'Инженер связи', 'callback_data' => 'Инженер связи firstch']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник firstch']
                        ],
                        [
                            ['text' => 'Оператор службы поддержки', 'callback_data' => 'Оператор службы поддержки firstch']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор firstch']
                        ],
                        [
                            ['text' => 'Саппорт-менеджер', 'callback_data' => 'Саппорт-менеджер firstch']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик firstch']
                        ],
                        [
                            ['text' => 'Монтажник связи', 'callback_data' => 'Монтажник связи firstch']
                        ],
                        [
                            ['text' => 'Диспетчер-логист', 'callback_data' => 'Диспетчер-логист firstch']
                        ],
                        [
                            ['text' => 'Менеджер call-центра', 'callback_data' => 'Менеджер call-центра firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'topmenSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Заместитель директора', 'callback_data' => 'Заместитель директора firstch']
                        ],
                        [
                            ['text' => 'Директор сервисного центра', 'callback_data' => 'Директор сервисного центра firstch']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель firstch']
                        ],
                        [
                            ['text' => 'Руководитель розничной сети', 'callback_data' => 'Руководитель розничной сети firstch']
                        ],
                        [
                            ['text' => 'Куратор', 'callback_data' => 'Куратор firstch']
                        ],
                        [
                            ['text' => 'Директор филиала', 'callback_data' => 'Директор филиала firstch']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор firstch']
                        ],
                        [
                            ['text' => 'Директор по персоналу', 'callback_data' => 'Директор по персоналу firstch']
                        ],
                        [
                            ['text' => 'PR-директор', 'callback_data' => 'PR-директор firstch']
                        ],
                        [
                            ['text' => 'Исполнительный директор', 'callback_data' => 'Исполнительный директор firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'avtobizSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель firstch']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик firstch']
                        ],
                        [
                            ['text' => 'Автослесарь', 'callback_data' => 'Автослесарь firstch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист firstch']
                        ],
                        [
                            ['text' => 'Экспедитор', 'callback_data' => 'Экспедитор firstch']
                        ],
                        [
                            ['text' => 'Инкассатор-водитель', 'callback_data' => 'Инкассатор-водитель firstch']
                        ],
                        [
                            ['text' => 'Главный механик', 'callback_data' => 'Главный механик firstch']
                        ],
                        [
                            ['text' => 'Начальник отдела транспорта', 'callback_data' => 'Начальник отдела транспорта firstch']
                        ],
                        [
                            ['text' => 'Курьер', 'callback_data' => 'Курьер firstch']
                        ],
                        [
                            ['text' => 'Дальнобойщик', 'callback_data' => 'Дальнобойщик firstch']
                        ],
                        [
                            ['text' => 'Мастер СТО', 'callback_data' => 'Мастер СТО firstch']
                        ],
                        [
                            ['text' => 'Рихтовщик', 'callback_data' => 'Рихтовщик firstch']
                        ],
                        [
                            ['text' => 'Моторист', 'callback_data' => 'Моторист firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'hrSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по персоналу', 'callback_data' => 'Менеджер по персоналу firstch']
                        ],
                        [
                            ['text' => 'HR-менеджер', 'callback_data' => 'HR-менеджер firstch']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель firstch']
                        ],
                        [
                            ['text' => 'Инженер по охране труда', 'callback_data' => 'Инженер по охране труда firstch']
                        ],
                        [
                            ['text' => 'Рекрутер', 'callback_data' => 'Рекрутер firstch']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч firstch']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог firstch']
                        ],
                        [
                            ['text' => 'Инспектор отдела кадров', 'callback_data' => 'Инспектор отдела кадров firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'bankSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Кассир', 'callback_data' => 'Кассир firstch']
                        ],
                        [
                            ['text' => 'Финансист', 'callback_data' => 'Финансист firstch']
                        ],
                        [
                            ['text' => 'Банкир', 'callback_data' => 'Банкир firstch']
                        ],
                        [
                            ['text' => 'Экономист', 'callback_data' => 'Экономист firstch']
                        ],
                        [
                            ['text' => 'Заведующий кассой банка', 'callback_data' => 'Заведующий кассой банка firstch']
                        ],
                        [
                            ['text' => 'Финансовый контроллер', 'callback_data' => 'Финансовый контроллер firstch']
                        ],
                        [
                            ['text' => 'Заместитель финансового директора', 'callback_data' => 'Зам. финансового директора firstch']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор firstch']
                        ],
                        [
                            ['text' => 'Аналитик', 'callback_data' => 'Аналитик firstch']
                        ],
                        [
                            ['text' => 'Директор отделения банка', 'callback_data' => 'Директор отделения банка firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'yuristSkill first':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Юрист', 'callback_data' => 'Юрист firstch']
                        ],
                        [
                            ['text' => 'Адвокат', 'callback_data' => 'Адвокат firstch']
                        ],
                        [
                            ['text' => 'Помощник адвоката', 'callback_data' => 'Помощник адвоката firstch']
                        ],
                        [
                            ['text' => 'Нотариус', 'callback_data' => 'Нотариус firstch']
                        ],
                        [
                            ['text' => 'Регистратор', 'callback_data' => 'Регистратор firstch']
                        ],
                        [
                            ['text' => 'Прокурор', 'callback_data' => 'Прокурор firstch']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский firstch']
                        ],
                        [
                            ['text' => 'Судья', 'callback_data' => 'Судья firstch']
                        ],
                        [
                            ['text' => 'Следователь', 'callback_data' => 'Следователь firstch']
                        ],
                        [
                            ['text' => 'Юрисконсульт', 'callback_data' => 'Юрисконсульт firstch']
                        ],
                        [
                            ['text' => 'Помощник судьи', 'callback_data' => 'Помощник судьи firstch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'ITSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер secondch']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист secondch']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор secondch']
                        ],
                        [
                            ['text' => 'Разработчик', 'callback_data' => 'Разработчик secondch']
                        ],
                        [
                            ['text' => 'Программист 1C', 'callback_data' => 'Программист 1C secondch']
                        ],
                        [
                            ['text' => 'Верстальщик', 'callback_data' => 'Верстальщик secondch']
                        ],
                        [
                            ['text' => 'PR-специалист', 'callback_data' => 'PR-специалист secondch']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер secondch']
                        ],
                        [
                            ['text' => 'Таргетолог', 'callback_data' => 'Таргетолог secondch']
                        ],
                        [
                            ['text' => 'Project-менеджер', 'callback_data' => 'Project-менеджер secondch']
                        ],
                        [
                            ['text' => 'Администратор сайта', 'callback_data' => 'Администратор сайта secondch']
                        ],
                        [
                            ['text' => 'Cпециалист службы поддержки', 'callback_data' => 'Cлужба поддержки secondch']
                        ],
                        [
                            ['text' => 'Режиссер видеомонтажа', 'callback_data' => 'Режиссер видеомонтажа secondch']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог secondch']
                        ],
                        [
                            ['text' => 'Оператор технической поддержки', 'callback_data' => 'Оператор тех-поддержки secondch']
                        ],
                        [
                            ['text' => 'Администратор баз данных', 'callback_data' => 'Администратор-БД secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'administrSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Управляющий магазином', 'callback_data' => 'Управляющий магазином secondch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор secondch']
                        ],
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Начальник отдела продаж secondch']
                        ],
                        [
                            ['text' => 'Мастер цеха', 'callback_data' => 'Мастер цеха secondch']
                        ],
                        [
                            ['text' => 'Администратор рецепции', 'callback_data' => 'Администратор рецепции secondch']
                        ],
                        [
                            ['text' => 'Начальник отдела кадров', 'callback_data' => 'Начальник отдела кадров secondch']
                        ],
                        [
                            ['text' => 'Супервайзер', 'callback_data' => 'Супервайзер secondch']
                        ],
                        [
                            ['text' => 'Менеджер по развитию', 'callback_data' => 'Менеджер по развитию secondch']
                        ],
                        [
                            ['text' => 'Администратор-кассир', 'callback_data' => 'Администратор-кассир secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'designSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея secondch']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер secondch']
                        ],
                        [
                            ['text' => 'Видеограф', 'callback_data' => 'Видеограф secondch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер secondch']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф secondch']
                        ],
                        [
                            ['text' => 'Дизайнер полиграфии', 'callback_data' => 'Дизайнер полиграфии secondch']
                        ],
                        [
                            ['text' => 'Oператор ПК', 'callback_data' => 'Oператор ПК secondch']
                        ],
                        [
                            ['text' => 'Флорист', 'callback_data' => 'Флорист secondch']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер secondch']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер secondch']
                        ],
                        [
                            ['text' => 'Дизайнер-конструктор', 'callback_data' => 'Дизайнер-конструктор secondch']
                        ],
                        [
                            ['text' => 'Мастер-ювелир', 'callback_data' => 'Мастер-ювелир secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'beautySkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Лешмейкер', 'callback_data' => 'Лешмейкер secondch']
                        ],
                        [
                            ['text' => 'Instagram-модель', 'callback_data' => 'Instagram-модель secondch']
                        ],
                        [
                            ['text' => 'Парикмахер', 'callback_data' => 'Парикмахер secondch']
                        ],
                        [
                            ['text' => 'Руководитель фитнес-клуба', 'callback_data' => 'Руководитель фитнес-клуба secondch']
                        ],
                        [
                            ['text' => 'Мастер маникюра', 'callback_data' => 'Мастер маникюра secondch']
                        ],
                        [
                            ['text' => 'Мастер лазерной эпиляции', 'callback_data' => 'Мастер лазерной эпиляции secondch']
                        ],
                        [
                            ['text' => 'Администратор салона красоты', 'callback_data' => 'Админ салона красоты secondch']
                        ],
                        [
                            ['text' => 'Барбер', 'callback_data' => 'Барбер secondch']
                        ],
                        [
                            ['text' => 'Косметолог', 'callback_data' => 'Косметолог secondch']
                        ],
                        [
                            ['text' => 'Тренер по йоге', 'callback_data' => 'Тренер по йоге secondch']
                        ],
                        [
                            ['text' => 'Стилист-консультант', 'callback_data' => 'Стилист-консультант secondch']
                        ],
                        [
                            ['text' => 'Тренер по футболу', 'callback_data' => 'Тренер по футболу secondch']
                        ],
                        [
                            ['text' => 'Дерматолог', 'callback_data' => 'Дерматолог secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'showbizSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Видеооператор', 'callback_data' => 'Видеооператор secondch']
                        ],
                        [
                            ['text' => 'Сценарист', 'callback_data' => 'Сценарист secondch']
                        ],
                        [
                            ['text' => 'Организатор мероприятий', 'callback_data' => 'Организатор мероприятий secondch']
                        ],
                        [
                            ['text' => 'Ведущий церемоний', 'callback_data' => 'Ведущий церемоний secondch']
                        ],
                        [
                            ['text' => 'Актриса', 'callback_data' => 'Актриса secondch']
                        ],
                        [
                            ['text' => 'Хореограф-постановщик', 'callback_data' => 'Хореограф-постановщик secondch']
                        ],
                        [
                            ['text' => 'Оператор чата', 'callback_data' => 'Оператор чата secondch']
                        ],
                        [
                            ['text' => 'Стример', 'callback_data' => 'Стример secondch']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф secondch']
                        ],
                        [
                            ['text' => 'Аниматор', 'callback_data' => 'Аниматор secondch']
                        ],
                        [
                            ['text' => 'Менеджер по бизнес-процессам', 'callback_data' => 'Менеджер по бизнес-процессам secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'logistikaSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам secondch']
                        ],
                        [
                            ['text' => 'Сотрудник склада', 'callback_data' => 'Сотрудник склада secondch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам логистических услуг', 'callback_data' => 'Менеджер лог. услуг secondch']
                        ],
                        [
                            ['text' => 'Коммерческий менеджер', 'callback_data' => 'Коммерческий менеджер secondch']
                        ],
                        [
                            ['text' => 'Комплектовщик на склад', 'callback_data' => 'Комплектовщик на склад secondch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист secondch']
                        ],
                        [
                            ['text' => 'Грузчик', 'callback_data' => 'Грузчик secondch']
                        ],
                        [
                            ['text' => 'Приемщик товара', 'callback_data' => 'Приемщик товара secondch']
                        ],
                        [
                            ['text' => 'Водитель-грузчик', 'callback_data' => 'Водитель-грузчик secondch']
                        ],
                        [
                            ['text' => 'Оператор-упаковщик', 'callback_data' => 'Оператор-упаковщик secondch']
                        ],
                        [
                            ['text' => 'Менеджер по логистике', 'callback_data' => 'Менеджер по логистике secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'marketingSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер secondch']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог secondch']
                        ],
                        [
                            ['text' => 'Маркетолог', 'callback_data' => 'Маркетолог secondch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер secondch']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист secondch']
                        ],
                        [
                            ['text' => 'Категорийный менеджер', 'callback_data' => 'Категорийный менеджер secondch']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер secondch']
                        ],
                        [
                            ['text' => 'Помощник контент-менеджера', 'callback_data' => 'Помощник контент-менеджера secondch']
                        ],
                        [
                            ['text' => 'SEO-специалист', 'callback_data' => 'SEO-специалист secondch']
                        ],
                        [
                            ['text' => 'Операционный директор', 'callback_data' => 'Операционный директор secondch']
                        ],
                        [
                            ['text' => 'Арт-директор', 'callback_data' => 'Арт-директор secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'medicinaSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Врач-офтальмолог', 'callback_data' => 'Врач-офтальмолог secondch']
                        ],
                        [
                            ['text' => 'Медицинский представитель', 'callback_data' => 'Медицинский представитель secondch']
                        ],
                        [
                            ['text' => 'Санитарка', 'callback_data' => 'Санитарка secondch']
                        ],
                        [
                            ['text' => 'Семейный врач', 'callback_data' => 'Семейный врач secondch']
                        ],
                        [
                            ['text' => 'Медсестра', 'callback_data' => 'Медсестра secondch']
                        ],
                        [
                            ['text' => 'Фармацевт', 'callback_data' => 'Фармацевт secondch']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант secondch']
                        ],
                        [
                            ['text' => 'Вирусолог', 'callback_data' => 'Вирусолог secondch']
                        ],
                        [
                            ['text' => 'Ассистент анестезиолога', 'callback_data' => 'Ассистент анестезиолога secondch']
                        ],
                        [
                            ['text' => 'Анастезиолог', 'callback_data' => 'Анастезиолог secondch']
                        ],
                        [
                            ['text' => 'Провизор', 'callback_data' => 'Провизор secondch']
                        ],
                        [
                            ['text' => 'Ветеринар', 'callback_data' => 'Ветеринар secondch']
                        ],
                        [
                            ['text' => 'Ассистент ветеринарного врача', 'callback_data' => 'Ассистент вет. врача secondch']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог secondch']
                        ],
                        [
                            ['text' => 'Психатр', 'callback_data' => 'Психатр secondch']
                        ],
                        [
                            ['text' => 'Стоматолог', 'callback_data' => 'Стоматолог secondch']
                        ],
                        [
                            ['text' => 'Кардиолог', 'callback_data' => 'Кардиолог secondch']
                        ],
                        [
                            ['text' => 'Хирург', 'callback_data' => 'Хирург secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'nedvizhimostSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Руководитель отдела продаж secondch']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости secondch']
                        ],
                        [
                            ['text' => 'Риэлтор', 'callback_data' => 'Риэлтор secondch']
                        ],
                        [
                            ['text' => 'Инспектор', 'callback_data' => 'Инспектор secondch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам secondch']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'naukaSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Воспитатель', 'callback_data' => 'Воспитатель secondch']
                        ],
                        [
                            ['text' => 'Преподаватель', 'callback_data' => 'Преподаватель secondch']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч secondch']
                        ],
                        [
                            ['text' => 'Социальный работник', 'callback_data' => 'Социальный работник secondch']
                        ],
                        [
                            ['text' => 'Психолог-консультант', 'callback_data' => 'Психолог-консультант secondch']
                        ],
                        [
                            ['text' => 'Логопед-дефектолог', 'callback_data' => 'Логопед-дефектолог secondch']
                        ],
                        [
                            ['text' => 'Методист', 'callback_data' => 'Методист secondch']
                        ],
                        [
                            ['text' => 'Репетитор', 'callback_data' => 'Репетитор secondch']
                        ],
                        [
                            ['text' => 'Ученый', 'callback_data' => 'Ученый secondch']
                        ],
                        [
                            ['text' => 'Помощник воспитателя', 'callback_data' => 'Помощник воспитателя secondch']
                        ],
                        [
                            ['text' => 'Няня', 'callback_data' => 'Няня secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'ohranaSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор охраны', 'callback_data' => 'Инспектор охраны secondch']
                        ],
                        [
                            ['text' => 'Охранник', 'callback_data' => 'Охранник secondch']
                        ],
                        [
                            ['text' => 'Контролер-ревизор', 'callback_data' => 'Контролер-ревизор secondch']
                        ],
                        [
                            ['text' => 'Оператор видеонаблюдения', 'callback_data' => 'Оператор видеонаблюдения secondch']
                        ],
                        [
                            ['text' => 'Инженер пожарной безопасности', 'callback_data' => 'Инженер пожарной безопасности secondch']
                        ],
                        [
                            ['text' => 'Техник по охранным системам', 'callback_data' => 'Техник по охранным системам secondch']
                        ],
                        [
                            ['text' => 'Начальник службы безопасности', 'callback_data' => 'Начальник сл. безопасности secondch']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный secondch']
                        ],
                        [
                            ['text' => 'Специалист по безопасности', 'callback_data' => 'Специалист по безопасности secondch']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный secondch']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский secondch']
                        ],
                        [
                            ['text' => 'Водитель-инкассатор', 'callback_data' => 'Водитель-инкассатор secondch']
                        ],
                        [
                            ['text' => 'Инкассатор', 'callback_data' => 'Инкассатор secondch']
                        ],
                        [
                            ['text' => 'Оператор ПЦС', 'callback_data' => 'Оператор ПЦС secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'prodajiSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам secondch']
                        ],
                        [
                            ['text' => 'Менеджер по закупке', 'callback_data' => 'Менеджер по закупке secondch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец secondch']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель secondch']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам secondch']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер secondch']
                        ],
                        [
                            ['text' => 'Аналитик по продажам', 'callback_data' => 'Аналитик по продажам secondch']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер secondch']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант secondch']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'proizvodstvoSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер secondch']
                        ],
                        [
                            ['text' => 'Пекарь', 'callback_data' => 'Пекарь secondch']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик secondch']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник secondch']
                        ],
                        [
                            ['text' => 'Слесарь', 'callback_data' => 'Слесарь secondch']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий secondch']
                        ],
                        [
                            ['text' => 'Мастер-оптик', 'callback_data' => 'Мастер-оптик secondch']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея secondch']
                        ],
                        [
                            ['text' => 'Технолог', 'callback_data' => 'Технолог secondch']
                        ],
                        [
                            ['text' => 'Монтажник', 'callback_data' => 'Монтажник secondch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр secondch']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель secondch']
                        ],
                        [
                            ['text' => 'Инженер-проектировщик', 'callback_data' => 'Инженер-проектировщик secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'torgovlyaSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам secondch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам secondch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец secondch']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель secondch']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам secondch']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер secondch']
                        ],
                        [
                            ['text' => 'Аналитик продаж', 'callback_data' => 'Аналитик продаж secondch']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер secondch']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант secondch']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'sekretaringSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Помощник руководителя', 'callback_data' => 'Помощник руководителя secondch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист secondch']
                        ],
                        [
                            ['text' => 'Офис-менеджер', 'callback_data' => 'Офис-менеджер secondch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор secondch']
                        ],
                        [
                            ['text' => 'Оператор call-centre', 'callback_data' => 'Оператор call-centre secondch']
                        ],
                        [
                            ['text' => 'Менеджер по моб. продажам', 'callback_data' => 'Менеджер по моб. продажам secondch']
                        ],
                        [
                            ['text' => 'Администратор ресепшн', 'callback_data' => 'Администратор ресепшн secondch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам secondch']
                        ],
                        [
                            ['text' => 'Помощник по мониторингу', 'callback_data' => 'Помощник по мониторингу secondch']
                        ],
                        [
                            ['text' => 'Оператор ПК', 'callback_data' => 'Оператор ПК secondch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам secondch']
                        ],
                        [
                            ['text' => 'Секретарь', 'callback_data' => 'Секретарь secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'agrobiznesSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант secondch']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант secondch']
                        ],
                        [
                            ['text' => 'Генетик', 'callback_data' => 'Генетик secondch']
                        ],
                        [
                            ['text' => 'Ветеринарный врач', 'callback_data' => 'Ветеринарный врач secondch']
                        ],
                        [
                            ['text' => 'Биолог', 'callback_data' => 'Биолог secondch']
                        ],
                        [
                            ['text' => 'Фермер', 'callback_data' => 'Фермер secondch']
                        ],
                        [
                            ['text' => 'Агроном', 'callback_data' => 'Агроном secondch']
                        ],
                        [
                            ['text' => 'Аналитик торгового рынка', 'callback_data' => 'Аналитик торгового рынка secondch']
                        ],
                        [
                            ['text' => 'Химик-хроматографист', 'callback_data' => 'Химик-хроматографист secondch']
                        ],
                        [
                            ['text' => 'Зернотрейдер', 'callback_data' => 'Зернотрейдер secondch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам зерновых культур', 'callback_data' => 'Менеджер по закупке зерна secondch']
                        ],
                        [
                            ['text' => 'Пробоотборник с/х продукции', 'callback_data' => 'Пробоотборник с/х продукции secondch']
                        ],
                        [
                            ['text' => 'Садовник', 'callback_data' => 'Садовник secondch']
                        ],
                        [
                            ['text' => 'Тракторист', 'callback_data' => 'Тракторист secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'izdatelstvoSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Фотограф в интернет-магазине', 'callback_data' => 'Фотограф в интернет-магазине secondch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер secondch']
                        ],
                        [
                            ['text' => 'Видеоредактор', 'callback_data' => 'Видеоредактор secondch']
                        ],
                        [
                            ['text' => 'SMM / контент-менеджер', 'callback_data' => 'SMM / контент-менеджер secondch']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер secondch']
                        ],
                        [
                            ['text' => 'Печатник', 'callback_data' => 'Печатник secondch']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер secondch']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер secondch']
                        ],
                        [
                            ['text' => 'Oператор цифровой печати', 'callback_data' => 'Oператор цифровой печати secondch']
                        ],
                        [
                            ['text' => 'Веб-журналист', 'callback_data' => 'Веб-журналист secondch']
                        ],
                        [
                            ['text' => 'Журналист', 'callback_data' => 'Журналист secondch']
                        ],
                        [
                            ['text' => 'Писатель', 'callback_data' => 'Писатель secondch']
                        ],
                        [
                            ['text' => 'Редактор', 'callback_data' => 'Редактор secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'strahovanieSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор-координатор', 'callback_data' => 'Инспектор-координатор secondch']
                        ],
                        [
                            ['text' => 'Сюрвейер', 'callback_data' => 'Сюрвейер secondch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр secondch']
                        ],
                        [
                            ['text' => 'Менеджер по страхованию', 'callback_data' => 'Менеджер по страхованию secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'stroitelstvoSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инженер технического надзора', 'callback_data' => 'Инженер технического надзора secondch']
                        ],
                        [
                            ['text' => 'Электромеханик контактной сети', 'callback_data' => 'Электромеханик контактной сети secondch']
                        ],
                        [
                            ['text' => 'Архитектор', 'callback_data' => 'Архитектор secondch']
                        ],
                        [
                            ['text' => 'Электромонтер', 'callback_data' => 'Электромонтер secondch']
                        ],
                        [
                            ['text' => 'Газорезчик', 'callback_data' => 'Газорезчик secondch']
                        ],
                        [
                            ['text' => 'Строитель', 'callback_data' => 'Строитель secondch']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий secondch']
                        ],
                        [
                            ['text' => 'Помощник Архитектора', 'callback_data' => 'Помощник Архитектора secondch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр secondch']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер secondch']
                        ],
                        [
                            ['text' => 'Мастер-бутафор', 'callback_data' => 'Мастер-бутафор secondch']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик secondch']
                        ],
                        [
                            ['text' => 'Штукатур', 'callback_data' => 'Штукатур secondch']
                        ],
                        [
                            ['text' => 'Сантехник', 'callback_data' => 'Сантехник secondch']
                        ],
                        [
                            ['text' => 'Инженер отдела продаж', 'callback_data' => 'Инженер отдела продаж secondch']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер secondch']
                        ],
                        [
                            ['text' => 'Электромеханик', 'callback_data' => 'Электромеханик secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'obsluzhivanieSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Повар', 'callback_data' => 'Повар secondch']
                        ],
                        [
                            ['text' => 'Официант', 'callback_data' => 'Официант secondch']
                        ],
                        [
                            ['text' => 'Бариста', 'callback_data' => 'Бариста secondch']
                        ],
                        [
                            ['text' => 'Консьерж', 'callback_data' => 'Консьерж secondch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор secondch']
                        ],
                        [
                            ['text' => 'Мастер бьюти сферы', 'callback_data' => 'Мастер бьюти сферы secondch']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель secondch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец secondch']
                        ],
                        [
                            ['text' => 'Оператор', 'callback_data' => 'Оператор secondch']
                        ],
                        [
                            ['text' => 'Массажист', 'callback_data' => 'Массажист secondch']
                        ],
                        [
                            ['text' => 'Хостес', 'callback_data' => 'Хостес secondch']
                        ],
                        [
                            ['text' => 'Сомелье', 'callback_data' => 'Сомелье secondch']
                        ],
                        [
                            ['text' => 'Бармен', 'callback_data' => 'Бармен secondch']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'telecomunikaciiSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Специалист технической поддержки', 'callback_data' => 'Специалист техподдержки secondch']
                        ],
                        [
                            ['text' => 'Инженер связи', 'callback_data' => 'Инженер связи secondch']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник secondch']
                        ],
                        [
                            ['text' => 'Оператор службы поддержки', 'callback_data' => 'Оператор службы поддержки secondch']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор secondch']
                        ],
                        [
                            ['text' => 'Саппорт-менеджер', 'callback_data' => 'Саппорт-менеджер secondch']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик secondch']
                        ],
                        [
                            ['text' => 'Монтажник связи', 'callback_data' => 'Монтажник связи secondch']
                        ],
                        [
                            ['text' => 'Диспетчер-логист', 'callback_data' => 'Диспетчер-логист secondch']
                        ],
                        [
                            ['text' => 'Менеджер call-центра', 'callback_data' => 'Менеджер call-центра secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'topmenSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Заместитель директора', 'callback_data' => 'Заместитель директора secondch']
                        ],
                        [
                            ['text' => 'Директор сервисного центра', 'callback_data' => 'Директор сервисного центра secondch']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель secondch']
                        ],
                        [
                            ['text' => 'Руководитель розничной сети', 'callback_data' => 'Руководитель розничной сети secondch']
                        ],
                        [
                            ['text' => 'Куратор', 'callback_data' => 'Куратор secondch']
                        ],
                        [
                            ['text' => 'Директор филиала', 'callback_data' => 'Директор филиала secondch']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор secondch']
                        ],
                        [
                            ['text' => 'Директор по персоналу', 'callback_data' => 'Директор по персоналу secondch']
                        ],
                        [
                            ['text' => 'PR-директор', 'callback_data' => 'PR-директор secondch']
                        ],
                        [
                            ['text' => 'Исполнительный директор', 'callback_data' => 'Исполнительный директор secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'avtobizSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель secondch']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик secondch']
                        ],
                        [
                            ['text' => 'Автослесарь', 'callback_data' => 'Автослесарь secondch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист secondch']
                        ],
                        [
                            ['text' => 'Экспедитор', 'callback_data' => 'Экспедитор secondch']
                        ],
                        [
                            ['text' => 'Инкассатор-водитель', 'callback_data' => 'Инкассатор-водитель secondch']
                        ],
                        [
                            ['text' => 'Главный механик', 'callback_data' => 'Главный механик secondch']
                        ],
                        [
                            ['text' => 'Начальник отдела транспорта', 'callback_data' => 'Рук. отдела транспорта secondch']
                        ],
                        [
                            ['text' => 'Курьер', 'callback_data' => 'Курьер secondch']
                        ],
                        [
                            ['text' => 'Дальнобойщик', 'callback_data' => 'Дальнобойщик secondch']
                        ],
                        [
                            ['text' => 'Мастер СТО', 'callback_data' => 'Мастер СТО secondch']
                        ],
                        [
                            ['text' => 'Рихтовщик', 'callback_data' => 'Рихтовщик secondch']
                        ],
                        [
                            ['text' => 'Моторист', 'callback_data' => 'Моторист secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'hrSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по персоналу', 'callback_data' => 'Менеджер по персоналу secondch']
                        ],
                        [
                            ['text' => 'HR-менеджер', 'callback_data' => 'HR-менеджер secondch']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель secondch']
                        ],
                        [
                            ['text' => 'Инженер по охране труда', 'callback_data' => 'Инженер по охране труда secondch']
                        ],
                        [
                            ['text' => 'Рекрутер', 'callback_data' => 'Рекрутер secondch']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч secondch']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог secondch']
                        ],
                        [
                            ['text' => 'Инспектор отдела кадров', 'callback_data' => 'Инспектор отдела кадров secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'bankSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Кассир', 'callback_data' => 'Кассир secondch']
                        ],
                        [
                            ['text' => 'Финансист', 'callback_data' => 'Финансист secondch']
                        ],
                        [
                            ['text' => 'Банкир', 'callback_data' => 'Банкир secondch']
                        ],
                        [
                            ['text' => 'Экономист', 'callback_data' => 'Экономист secondch']
                        ],
                        [
                            ['text' => 'Заведующий кассой банка', 'callback_data' => 'Заведующий кассой банка secondch']
                        ],
                        [
                            ['text' => 'Финансовый контроллер', 'callback_data' => 'Финансовый контроллер secondch']
                        ],
                        [
                            ['text' => 'Заместитель финансового директора', 'callback_data' => 'Зам. финансового директора secondch']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор secondch']
                        ],
                        [
                            ['text' => 'Аналитик', 'callback_data' => 'Аналитик secondch']
                        ],
                        [
                            ['text' => 'Директор отделения банка', 'callback_data' => 'Директор отделения банка secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'yuristSkill second':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Юрист', 'callback_data' => 'Юрист secondch']
                        ],
                        [
                            ['text' => 'Адвокат', 'callback_data' => 'Адвокат secondch']
                        ],
                        [
                            ['text' => 'Помощник адвоката', 'callback_data' => 'Помощник адвоката secondch']
                        ],
                        [
                            ['text' => 'Нотариус', 'callback_data' => 'Нотариус secondch']
                        ],
                        [
                            ['text' => 'Регистратор', 'callback_data' => 'Регистратор secondch']
                        ],
                        [
                            ['text' => 'Прокурор', 'callback_data' => 'Прокурор secondch']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский secondch']
                        ],
                        [
                            ['text' => 'Судья', 'callback_data' => 'Судья secondch']
                        ],
                        [
                            ['text' => 'Следователь', 'callback_data' => 'Следователь secondch']
                        ],
                        [
                            ['text' => 'Юрисконсульт', 'callback_data' => 'Юрисконсульт secondch']
                        ],
                        [
                            ['text' => 'Помощник судьи', 'callback_data' => 'Помощник судьи secondch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'ITSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер thirdch']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист thirdch']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор thirdch']
                        ],
                        [
                            ['text' => 'Разработчик', 'callback_data' => 'Разработчик thirdch']
                        ],
                        [
                            ['text' => 'Программист 1C', 'callback_data' => 'Программист 1C thirdch']
                        ],
                        [
                            ['text' => 'Верстальщик', 'callback_data' => 'Верстальщик thirdch']
                        ],
                        [
                            ['text' => 'PR-специалист', 'callback_data' => 'PR-специалист thirdch']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер thirdch']
                        ],
                        [
                            ['text' => 'Таргетолог', 'callback_data' => 'Таргетолог thirdch']
                        ],
                        [
                            ['text' => 'Project-менеджер', 'callback_data' => 'Project-менеджер thirdch']
                        ],
                        [
                            ['text' => 'Администратор сайта', 'callback_data' => 'Администратор сайта thirdch']
                        ],
                        [
                            ['text' => 'Cпециалист службы поддержки', 'callback_data' => 'Cлужба поддержки thirdch']
                        ],
                        [
                            ['text' => 'Режиссер видеомонтажа', 'callback_data' => 'Режиссер видеомонтажа thirdch']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог thirdch']
                        ],
                        [
                            ['text' => 'Оператор технической поддержки', 'callback_data' => 'Оператор тех-поддержки thirdch']
                        ],
                        [
                            ['text' => 'Администратор баз данных', 'callback_data' => 'Администратор-БД thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'administrSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Управляющий магазином', 'callback_data' => 'Управляющий магазином thirdch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор thirdch']
                        ],
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Начальник отдела продаж thirdch']
                        ],
                        [
                            ['text' => 'Мастер цеха', 'callback_data' => 'Мастер цеха thirdch']
                        ],
                        [
                            ['text' => 'Администратор рецепции', 'callback_data' => 'Администратор рецепции thirdch']
                        ],
                        [
                            ['text' => 'Начальник отдела кадров', 'callback_data' => 'Начальник отдела кадров thirdch']
                        ],
                        [
                            ['text' => 'Супервайзер', 'callback_data' => 'Супервайзер thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по развитию', 'callback_data' => 'Менеджер по развитию thirdch']
                        ],
                        [
                            ['text' => 'Администратор-кассир', 'callback_data' => 'Администратор-кассир thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'designSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея thirdch']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер thirdch']
                        ],
                        [
                            ['text' => 'Видеограф', 'callback_data' => 'Видеограф thirdch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер thirdch']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф thirdch']
                        ],
                        [
                            ['text' => 'Дизайнер полиграфии', 'callback_data' => 'Дизайнер полиграфии thirdch']
                        ],
                        [
                            ['text' => 'Oператор ПК', 'callback_data' => 'Oператор ПК thirdch']
                        ],
                        [
                            ['text' => 'Флорист', 'callback_data' => 'Флорист thirdch']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер thirdch']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер thirdch']
                        ],
                        [
                            ['text' => 'Дизайнер-конструктор', 'callback_data' => 'Дизайнер-конструктор thirdch']
                        ],
                        [
                            ['text' => 'Мастер-ювелир', 'callback_data' => 'Мастер-ювелир thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'beautySkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Лешмейкер', 'callback_data' => 'Лешмейкер thirdch']
                        ],
                        [
                            ['text' => 'Instagram-модель', 'callback_data' => 'Instagram-модель thirdch']
                        ],
                        [
                            ['text' => 'Парикмахер', 'callback_data' => 'Парикмахер thirdch']
                        ],
                        [
                            ['text' => 'Руководитель фитнес-клуба', 'callback_data' => 'Руководитель фитнес-клуба thirdch']
                        ],
                        [
                            ['text' => 'Мастер маникюра', 'callback_data' => 'Мастер маникюра thirdch']
                        ],
                        [
                            ['text' => 'Мастер лазерной эпиляции', 'callback_data' => 'Мастер лазерной эпиляции thirdch']
                        ],
                        [
                            ['text' => 'Администратор салона красоты', 'callback_data' => 'Админ салона красоты thirdch']
                        ],
                        [
                            ['text' => 'Барбер', 'callback_data' => 'Барбер thirdch']
                        ],
                        [
                            ['text' => 'Косметолог', 'callback_data' => 'Косметолог thirdch']
                        ],
                        [
                            ['text' => 'Тренер по йоге', 'callback_data' => 'Тренер по йоге thirdch']
                        ],
                        [
                            ['text' => 'Стилист-консультант', 'callback_data' => 'Стилист-консультант thirdch']
                        ],
                        [
                            ['text' => 'Тренер по футболу', 'callback_data' => 'Тренер по футболу thirdch']
                        ],
                        [
                            ['text' => 'Дерматолог', 'callback_data' => 'Дерматолог thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'showbizSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Видеооператор', 'callback_data' => 'Видеооператор thirdch']
                        ],
                        [
                            ['text' => 'Сценарист', 'callback_data' => 'Сценарист thirdch']
                        ],
                        [
                            ['text' => 'Организатор мероприятий', 'callback_data' => 'Организатор мероприятий thirdch']
                        ],
                        [
                            ['text' => 'Ведущий церемоний', 'callback_data' => 'Ведущий церемоний thirdch']
                        ],
                        [
                            ['text' => 'Актриса', 'callback_data' => 'Актриса thirdch']
                        ],
                        [
                            ['text' => 'Хореограф-постановщик', 'callback_data' => 'Хореограф-постановщик thirdch']
                        ],
                        [
                            ['text' => 'Оператор чата', 'callback_data' => 'Оператор чата thirdch']
                        ],
                        [
                            ['text' => 'Стример', 'callback_data' => 'Стример thirdch']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф thirdch']
                        ],
                        [
                            ['text' => 'Аниматор', 'callback_data' => 'Аниматор thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по бизнес-процессам', 'callback_data' => 'Менеджер по бизнес-процессам thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'logistikaSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам thirdch']
                        ],
                        [
                            ['text' => 'Сотрудник склада', 'callback_data' => 'Сотрудник склада thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам логистических услуг', 'callback_data' => 'Менеджер лог. услуг thirdch']
                        ],
                        [
                            ['text' => 'Коммерческий менеджер', 'callback_data' => 'Коммерческий менеджер thirdch']
                        ],
                        [
                            ['text' => 'Комплектовщик на склад', 'callback_data' => 'Комплектовщик на склад thirdch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист thirdch']
                        ],
                        [
                            ['text' => 'Грузчик', 'callback_data' => 'Грузчик thirdch']
                        ],
                        [
                            ['text' => 'Приемщик товара', 'callback_data' => 'Приемщик товара thirdch']
                        ],
                        [
                            ['text' => 'Водитель-грузчик', 'callback_data' => 'Водитель-грузчик thirdch']
                        ],
                        [
                            ['text' => 'Оператор-упаковщик', 'callback_data' => 'Оператор-упаковщик thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по логистике', 'callback_data' => 'Менеджер по логистике thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'marketingSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер thirdch']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог thirdch']
                        ],
                        [
                            ['text' => 'Маркетолог', 'callback_data' => 'Маркетолог thirdch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер thirdch']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист thirdch']
                        ],
                        [
                            ['text' => 'Категорийный менеджер', 'callback_data' => 'Категорийный менеджер thirdch']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер thirdch']
                        ],
                        [
                            ['text' => 'Помощник контент-менеджера', 'callback_data' => 'Помощник контент-менеджера thirdch']
                        ],
                        [
                            ['text' => 'SEO-специалист', 'callback_data' => 'SEO-специалист thirdch']
                        ],
                        [
                            ['text' => 'Операционный директор', 'callback_data' => 'Операционный директор thirdch']
                        ],
                        [
                            ['text' => 'Арт-директор', 'callback_data' => 'Арт-директор thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'medicinaSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Врач-офтальмолог', 'callback_data' => 'Врач-офтальмолог thirdch']
                        ],
                        [
                            ['text' => 'Медицинский представитель', 'callback_data' => 'Медицинский представитель thirdch']
                        ],
                        [
                            ['text' => 'Санитарка', 'callback_data' => 'Санитарка thirdch']
                        ],
                        [
                            ['text' => 'Семейный врач', 'callback_data' => 'Семейный врач thirdch']
                        ],
                        [
                            ['text' => 'Медсестра', 'callback_data' => 'Медсестра thirdch']
                        ],
                        [
                            ['text' => 'Фармацевт', 'callback_data' => 'Фармацевт thirdch']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант thirdch']
                        ],
                        [
                            ['text' => 'Вирусолог', 'callback_data' => 'Вирусолог thirdch']
                        ],
                        [
                            ['text' => 'Ассистент анестезиолога', 'callback_data' => 'Ассистент анестезиолога thirdch']
                        ],
                        [
                            ['text' => 'Анастезиолог', 'callback_data' => 'Анастезиолог thirdch']
                        ],
                        [
                            ['text' => 'Провизор', 'callback_data' => 'Провизор thirdch']
                        ],
                        [
                            ['text' => 'Ветеринар', 'callback_data' => 'Ветеринар thirdch']
                        ],
                        [
                            ['text' => 'Ассистент ветеринарного врача', 'callback_data' => 'Ассистент вет. врача thirdch']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог thirdch']
                        ],
                        [
                            ['text' => 'Психатр', 'callback_data' => 'Психатр thirdch']
                        ],
                        [
                            ['text' => 'Стоматолог', 'callback_data' => 'Стоматолог thirdch']
                        ],
                        [
                            ['text' => 'Кардиолог', 'callback_data' => 'Кардиолог thirdch']
                        ],
                        [
                            ['text' => 'Хирург', 'callback_data' => 'Хирург thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'nedvizhimostSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Руководитель отдела продаж thirdch']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости thirdch']
                        ],
                        [
                            ['text' => 'Риэлтор', 'callback_data' => 'Риэлтор thirdch']
                        ],
                        [
                            ['text' => 'Инспектор', 'callback_data' => 'Инспектор thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам thirdch']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'naukaSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Воспитатель', 'callback_data' => 'Воспитатель thirdch']
                        ],
                        [
                            ['text' => 'Преподаватель', 'callback_data' => 'Преподаватель thirdch']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч thirdch']
                        ],
                        [
                            ['text' => 'Социальный работник', 'callback_data' => 'Социальный работник thirdch']
                        ],
                        [
                            ['text' => 'Психолог-консультант', 'callback_data' => 'Психолог-консультант thirdch']
                        ],
                        [
                            ['text' => 'Логопед-дефектолог', 'callback_data' => 'Логопед-дефектолог thirdch']
                        ],
                        [
                            ['text' => 'Методист', 'callback_data' => 'Методист thirdch']
                        ],
                        [
                            ['text' => 'Репетитор', 'callback_data' => 'Репетитор thirdch']
                        ],
                        [
                            ['text' => 'Ученый', 'callback_data' => 'Ученый thirdch']
                        ],
                        [
                            ['text' => 'Помощник воспитателя', 'callback_data' => 'Помощник воспитателя thirdch']
                        ],
                        [
                            ['text' => 'Няня', 'callback_data' => 'Няня thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'ohranaSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор охраны', 'callback_data' => 'Инспектор охраны thirdch']
                        ],
                        [
                            ['text' => 'Охранник', 'callback_data' => 'Охранник thirdch']
                        ],
                        [
                            ['text' => 'Контролер-ревизор', 'callback_data' => 'Контролер-ревизор thirdch']
                        ],
                        [
                            ['text' => 'Оператор видеонаблюдения', 'callback_data' => 'Оператор видеонаблюдения thirdch']
                        ],
                        [
                            ['text' => 'Инженер пожарной безопасности', 'callback_data' => 'Инженер пожарной безопасности thirdch']
                        ],
                        [
                            ['text' => 'Техник по охранным системам', 'callback_data' => 'Техник по охранным системам thirdch']
                        ],
                        [
                            ['text' => 'Начальник службы безопасности', 'callback_data' => 'Начальник сл. безопасности thirdch']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный thirdch']
                        ],
                        [
                            ['text' => 'Специалист по безопасности', 'callback_data' => 'Специалист по безопасности thirdch']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный thirdch']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский thirdch']
                        ],
                        [
                            ['text' => 'Водитель-инкассатор', 'callback_data' => 'Водитель-инкассатор thirdch']
                        ],
                        [
                            ['text' => 'Инкассатор', 'callback_data' => 'Инкассатор thirdch']
                        ],
                        [
                            ['text' => 'Оператор ПЦС', 'callback_data' => 'Оператор ПЦС thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'prodajiSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по закупке', 'callback_data' => 'Менеджер по закупке thirdch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец thirdch']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам thirdch']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер thirdch']
                        ],
                        [
                            ['text' => 'Аналитик по продажам', 'callback_data' => 'Аналитик по продажам thirdch']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер thirdch']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант thirdch']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'proizvodstvoSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер thirdch']
                        ],
                        [
                            ['text' => 'Пекарь', 'callback_data' => 'Пекарь thirdch']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик thirdch']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник thirdch']
                        ],
                        [
                            ['text' => 'Слесарь', 'callback_data' => 'Слесарь thirdch']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий thirdch']
                        ],
                        [
                            ['text' => 'Мастер-оптик', 'callback_data' => 'Мастер-оптик thirdch']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея thirdch']
                        ],
                        [
                            ['text' => 'Технолог', 'callback_data' => 'Технолог thirdch']
                        ],
                        [
                            ['text' => 'Монтажник', 'callback_data' => 'Монтажник thirdch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр thirdch']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель thirdch']
                        ],
                        [
                            ['text' => 'Инженер-проектировщик', 'callback_data' => 'Инженер-проектировщик thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'torgovlyaSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам thirdch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец thirdch']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам thirdch']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер thirdch']
                        ],
                        [
                            ['text' => 'Аналитик продаж', 'callback_data' => 'Аналитик продаж thirdch']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер thirdch']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант thirdch']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'sekretaringSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Помощник руководителя', 'callback_data' => 'Помощник руководителя thirdch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист thirdch']
                        ],
                        [
                            ['text' => 'Офис-менеджер', 'callback_data' => 'Офис-менеджер thirdch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор thirdch']
                        ],
                        [
                            ['text' => 'Оператор call-centre', 'callback_data' => 'Оператор call-centre thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по моб. продажам', 'callback_data' => 'Менеджер по моб. продажам thirdch']
                        ],
                        [
                            ['text' => 'Администратор ресепшн', 'callback_data' => 'Администратор ресепшн thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам thirdch']
                        ],
                        [
                            ['text' => 'Помощник по мониторингу', 'callback_data' => 'Помощник по мониторингу thirdch']
                        ],
                        [
                            ['text' => 'Оператор ПК', 'callback_data' => 'Оператор ПК thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам thirdch']
                        ],
                        [
                            ['text' => 'Секретарь', 'callback_data' => 'Секретарь thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'agrobiznesSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант thirdch']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант thirdch']
                        ],
                        [
                            ['text' => 'Генетик', 'callback_data' => 'Генетик thirdch']
                        ],
                        [
                            ['text' => 'Ветеринарный врач', 'callback_data' => 'Ветеринарный врач thirdch']
                        ],
                        [
                            ['text' => 'Биолог', 'callback_data' => 'Биолог thirdch']
                        ],
                        [
                            ['text' => 'Фермер', 'callback_data' => 'Фермер thirdch']
                        ],
                        [
                            ['text' => 'Агроном', 'callback_data' => 'Агроном thirdch']
                        ],
                        [
                            ['text' => 'Аналитик торгового рынка', 'callback_data' => 'Аналитик торгового рынка thirdch']
                        ],
                        [
                            ['text' => 'Химик-хроматографист', 'callback_data' => 'Химик-хроматографист thirdch']
                        ],
                        [
                            ['text' => 'Зернотрейдер', 'callback_data' => 'Зернотрейдер thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам зерновых культур', 'callback_data' => 'Менеджер по закупке зерна thirdch']
                        ],
                        [
                            ['text' => 'Пробоотборник с/х продукции', 'callback_data' => 'Пробоотборник с/х продукции thirdch']
                        ],
                        [
                            ['text' => 'Садовник', 'callback_data' => 'Садовник thirdch']
                        ],
                        [
                            ['text' => 'Тракторист', 'callback_data' => 'Тракторист thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'izdatelstvoSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Фотограф в интернет-магазине', 'callback_data' => 'Фотограф в интернет-магазине thirdch']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер thirdch']
                        ],
                        [
                            ['text' => 'Видеоредактор', 'callback_data' => 'Видеоредактор thirdch']
                        ],
                        [
                            ['text' => 'SMM / контент-менеджер', 'callback_data' => 'SMM / контент-менеджер thirdch']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер thirdch']
                        ],
                        [
                            ['text' => 'Печатник', 'callback_data' => 'Печатник thirdch']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер thirdch']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер thirdch']
                        ],
                        [
                            ['text' => 'Oператор цифровой печати', 'callback_data' => 'Oператор цифровой печати thirdch']
                        ],
                        [
                            ['text' => 'Веб-журналист', 'callback_data' => 'Веб-журналист thirdch']
                        ],
                        [
                            ['text' => 'Журналист', 'callback_data' => 'Журналист thirdch']
                        ],
                        [
                            ['text' => 'Писатель', 'callback_data' => 'Писатель thirdch']
                        ],
                        [
                            ['text' => 'Редактор', 'callback_data' => 'Редактор thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'strahovanieSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор-координатор', 'callback_data' => 'Инспектор-координатор thirdch']
                        ],
                        [
                            ['text' => 'Сюрвейер', 'callback_data' => 'Сюрвейер thirdch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр thirdch']
                        ],
                        [
                            ['text' => 'Менеджер по страхованию', 'callback_data' => 'Менеджер по страхованию thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'stroitelstvoSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инженер технического надзора', 'callback_data' => 'Инженер технического надзора thirdch']
                        ],
                        [
                            ['text' => 'Электромеханик контактной сети', 'callback_data' => 'Электромеханик контактной сети thirdch']
                        ],
                        [
                            ['text' => 'Архитектор', 'callback_data' => 'Архитектор thirdch']
                        ],
                        [
                            ['text' => 'Электромонтер', 'callback_data' => 'Электромонтер thirdch']
                        ],
                        [
                            ['text' => 'Газорезчик', 'callback_data' => 'Газорезчик thirdch']
                        ],
                        [
                            ['text' => 'Строитель', 'callback_data' => 'Строитель thirdch']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий thirdch']
                        ],
                        [
                            ['text' => 'Помощник Архитектора', 'callback_data' => 'Помощник Архитектора thirdch']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр thirdch']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер thirdch']
                        ],
                        [
                            ['text' => 'Мастер-бутафор', 'callback_data' => 'Мастер-бутафор thirdch']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик thirdch']
                        ],
                        [
                            ['text' => 'Штукатур', 'callback_data' => 'Штукатур thirdch']
                        ],
                        [
                            ['text' => 'Сантехник', 'callback_data' => 'Сантехник thirdch']
                        ],
                        [
                            ['text' => 'Инженер отдела продаж', 'callback_data' => 'Инженер отдела продаж thirdch']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер thirdch']
                        ],
                        [
                            ['text' => 'Электромеханик', 'callback_data' => 'Электромеханик thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'obsluzhivanieSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Повар', 'callback_data' => 'Повар thirdch']
                        ],
                        [
                            ['text' => 'Официант', 'callback_data' => 'Официант thirdch']
                        ],
                        [
                            ['text' => 'Бариста', 'callback_data' => 'Бариста thirdch']
                        ],
                        [
                            ['text' => 'Консьерж', 'callback_data' => 'Консьерж thirdch']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор thirdch']
                        ],
                        [
                            ['text' => 'Мастер бьюти сферы', 'callback_data' => 'Мастер бьюти сферы thirdch']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель thirdch']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец thirdch']
                        ],
                        [
                            ['text' => 'Оператор', 'callback_data' => 'Оператор thirdch']
                        ],
                        [
                            ['text' => 'Массажист', 'callback_data' => 'Массажист thirdch']
                        ],
                        [
                            ['text' => 'Хостес', 'callback_data' => 'Хостес thirdch']
                        ],
                        [
                            ['text' => 'Сомелье', 'callback_data' => 'Сомелье thirdch']
                        ],
                        [
                            ['text' => 'Бармен', 'callback_data' => 'Бармен thirdch']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'telecomunikaciiSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Специалист технической поддержки', 'callback_data' => 'Специалист техподдержки thirdch']
                        ],
                        [
                            ['text' => 'Инженер связи', 'callback_data' => 'Инженер связи thirdch']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник thirdch']
                        ],
                        [
                            ['text' => 'Оператор службы поддержки', 'callback_data' => 'Оператор службы поддержки thirdch']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор thirdch']
                        ],
                        [
                            ['text' => 'Саппорт-менеджер', 'callback_data' => 'Саппорт-менеджер thirdch']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик thirdch']
                        ],
                        [
                            ['text' => 'Монтажник связи', 'callback_data' => 'Монтажник связи thirdch']
                        ],
                        [
                            ['text' => 'Диспетчер-логист', 'callback_data' => 'Диспетчер-логист thirdch']
                        ],
                        [
                            ['text' => 'Менеджер call-центра', 'callback_data' => 'Менеджер call-центра thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'topmenSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Заместитель директора', 'callback_data' => 'Заместитель директора thirdch']
                        ],
                        [
                            ['text' => 'Директор сервисного центра', 'callback_data' => 'Директор сервисного центра thirdch']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель thirdch']
                        ],
                        [
                            ['text' => 'Руководитель розничной сети', 'callback_data' => 'Руководитель розничной сети thirdch']
                        ],
                        [
                            ['text' => 'Куратор', 'callback_data' => 'Куратор thirdch']
                        ],
                        [
                            ['text' => 'Директор филиала', 'callback_data' => 'Директор филиала thirdch']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор thirdch']
                        ],
                        [
                            ['text' => 'Директор по персоналу', 'callback_data' => 'Директор по персоналу thirdch']
                        ],
                        [
                            ['text' => 'PR-директор', 'callback_data' => 'PR-директор thirdch']
                        ],
                        [
                            ['text' => 'Исполнительный директор', 'callback_data' => 'Исполнительный директор thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'avtobizSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель thirdch']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик thirdch']
                        ],
                        [
                            ['text' => 'Автослесарь', 'callback_data' => 'Автослесарь thirdch']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист thirdch']
                        ],
                        [
                            ['text' => 'Экспедитор', 'callback_data' => 'Экспедитор thirdch']
                        ],
                        [
                            ['text' => 'Инкассатор-водитель', 'callback_data' => 'Инкассатор-водитель thirdch']
                        ],
                        [
                            ['text' => 'Главный механик', 'callback_data' => 'Главный механик thirdch']
                        ],
                        [
                            ['text' => 'Начальник отдела транспорта', 'callback_data' => 'Начальник отдела транспорта thirdch']
                        ],
                        [
                            ['text' => 'Курьер', 'callback_data' => 'Курьер thirdch']
                        ],
                        [
                            ['text' => 'Дальнобойщик', 'callback_data' => 'Дальнобойщик thirdch']
                        ],
                        [
                            ['text' => 'Мастер СТО', 'callback_data' => 'Мастер СТО thirdch']
                        ],
                        [
                            ['text' => 'Рихтовщик', 'callback_data' => 'Рихтовщик thirdch']
                        ],
                        [
                            ['text' => 'Моторист', 'callback_data' => 'Моторист thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'hrSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по персоналу', 'callback_data' => 'Менеджер по персоналу thirdch']
                        ],
                        [
                            ['text' => 'HR-менеджер', 'callback_data' => 'HR-менеджер thirdch']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель thirdch']
                        ],
                        [
                            ['text' => 'Инженер по охране труда', 'callback_data' => 'Инженер по охране труда thirdch']
                        ],
                        [
                            ['text' => 'Рекрутер', 'callback_data' => 'Рекрутер thirdch']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч thirdch']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог thirdch']
                        ],
                        [
                            ['text' => 'Инспектор отдела кадров', 'callback_data' => 'Инспектор отдела кадров thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'bankSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Кассир', 'callback_data' => 'Кассир thirdch']
                        ],
                        [
                            ['text' => 'Финансист', 'callback_data' => 'Финансист thirdch']
                        ],
                        [
                            ['text' => 'Банкир', 'callback_data' => 'Банкир thirdch']
                        ],
                        [
                            ['text' => 'Экономист', 'callback_data' => 'Экономист thirdch']
                        ],
                        [
                            ['text' => 'Заведующий кассой банка', 'callback_data' => 'Заведующий кассой банка thirdch']
                        ],
                        [
                            ['text' => 'Финансовый контроллер', 'callback_data' => 'Финансовый контроллер thirdch']
                        ],
                        [
                            ['text' => 'Заместитель финансового директора', 'callback_data' => 'Зам. финансового директора thirdch']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор thirdch']
                        ],
                        [
                            ['text' => 'Аналитик', 'callback_data' => 'Аналитик thirdch']
                        ],
                        [
                            ['text' => 'Директор отделения банка', 'callback_data' => 'Директор отделения банка thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'yuristSkill third':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Юрист', 'callback_data' => 'Юрист thirdch']
                        ],
                        [
                            ['text' => 'Адвокат', 'callback_data' => 'Адвокат thirdch']
                        ],
                        [
                            ['text' => 'Помощник адвоката', 'callback_data' => 'Помощник адвоката thirdch']
                        ],
                        [
                            ['text' => 'Нотариус', 'callback_data' => 'Нотариус thirdch']
                        ],
                        [
                            ['text' => 'Регистратор', 'callback_data' => 'Регистратор thirdch']
                        ],
                        [
                            ['text' => 'Прокурор', 'callback_data' => 'Прокурор thirdch']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский thirdch']
                        ],
                        [
                            ['text' => 'Судья', 'callback_data' => 'Судья thirdch']
                        ],
                        [
                            ['text' => 'Следователь', 'callback_data' => 'Следователь thirdch']
                        ],
                        [
                            ['text' => 'Юрисконсульт', 'callback_data' => 'Юрисконсульт thirdch']
                        ],
                        [
                            ['text' => 'Помощник судьи', 'callback_data' => 'Помощник судьи thirdch']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => '2chFirst']
                        ]
                    ]
                ]
            ];
            break;

        case 'choiceSkills':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери категорию:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'IT, компьютеры, интернет', 'callback_data' => 'ITSkill']
                        ],
                        [
                            ['text' => 'Администрация, руководство среднего звена', 'callback_data' => 'administrSkill']
                        ],
                        [
                            ['text' => 'Дизайн, творчество', 'callback_data' => 'designSkill']
                        ],
                        [
                            ['text' => 'Красота, фитнес, спорт', 'callback_data' => 'beautySkill']
                        ],
                        [
                            ['text' => 'Культура, музыка, шоу-бизнес', 'callback_data' => 'showbizSkill']
                        ],
                        [
                            ['text' => 'Логистика, склад, ВЭД', 'callback_data' => 'logistikaSkill']
                        ],
                        [
                            ['text' => 'Маркетинг, реклама, PR', 'callback_data' => 'marketingSkill']
                        ],
                        [
                            ['text' => 'Медицина, фармацевтика', 'callback_data' => 'medicinaSkill']
                        ],
                        [
                            ['text' => 'Недвижимость', 'callback_data' => 'nedvizhimostSkill']
                        ],
                        [
                            ['text' => 'Образование, наука', 'callback_data' => 'naukaSkill']
                        ],
                        [
                            ['text' => 'Охрана, безопасность', 'callback_data' => 'ohranaSkill']
                        ],
                        [
                            ['text' => 'Продажи, закупки', 'callback_data' => 'prodajiSkill']
                        ],
                        [
                            ['text' => 'Рабочие специальности, производство', 'callback_data' => 'proizvodstvoSkill']
                        ],
                        /*[
                            ['text' => '🆘 Я не нашел свой навык 🆘', 'callback_data' => 'imNotFindMySkill']
                        ],*/
                        [
                            ['text' => '👈 Вернуться в "Мои навыки"', 'callback_data' => 'mySkills'],
                            ['text' => '2 страница 👉', 'callback_data' => 'choiceSkills2']
                        ]
                    ]
                ]
            ];
            break;

        case 'choiceSkills2':
            $method = 'editMessageText';
            $send_data = [
                'text' => 'Выбери категорию:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Розничная торговля', 'callback_data' => 'torgovlyaSkill']
                        ],
                        [
                            ['text' => 'Секретариат, делопроизводство, АХО', 'callback_data' => 'sekretaringSkill']
                        ],
                        [
                            ['text' => 'Сельское хозяйство, агробизнес', 'callback_data' => 'agrobiznesSkill']
                        ],
                        [
                            ['text' => 'СМИ, издательство, полиграфия', 'callback_data' => 'izdatelstvoSkill']
                        ],
                        [
                            ['text' => 'Страхование', 'callback_data' => 'strahovanieSkill']
                        ],
                        [
                            ['text' => 'Строительство, архитектура', 'callback_data' => 'stroitelstvoSkill']
                        ],
                        [
                            ['text' => 'Сфера обслуживания', 'callback_data' => 'obsluzhivanieSkill']
                        ],
                        [
                            ['text' => 'Телекоммуникации и связь', 'callback_data' => 'telecomunikaciiSkill']
                        ],
                        [
                            ['text' => 'Топ-менеджмент, руководство высшего звена', 'callback_data' => 'topmenSkill']
                        ],
                        [
                            ['text' => 'Транспорт, автобизнес', 'callback_data' => 'avtobizSkill']
                        ],
                        [
                            ['text' => 'Управление персоналом, HR', 'callback_data' => 'hrSkill']
                        ],
                        [
                            ['text' => 'Финансы, банк', 'callback_data' => 'bankSkill']
                        ],
                        [
                            ['text' => 'Юриспруденция', 'callback_data' => 'yuristSkill']
                        ],
                        /*[
                            ['text' => '🆘 Я не нашел свой навык 🆘', 'callback_data' => 'imNotFindMySkill']
                        ],*/
                        [
                            ['text' => '👈 1 страница', 'callback_data' => 'choiceSkills']
                        ]
                    ]
                ]
            ];
            break;

        case 'ITSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер поиск']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист поиск']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор поиск']
                        ],
                        [
                            ['text' => 'Разработчик', 'callback_data' => 'Разработчик поиск']
                        ],
                        [
                            ['text' => 'Программист 1C', 'callback_data' => 'Программист 1C поиск']
                        ],
                        [
                            ['text' => 'Верстальщик', 'callback_data' => 'Верстальщик поиск']
                        ],
                        [
                            ['text' => 'PR-специалист', 'callback_data' => 'PR-специалист поиск']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер поиск']
                        ],
                        [
                            ['text' => 'Таргетолог', 'callback_data' => 'Таргетолог поиск']
                        ],
                        [
                            ['text' => 'Project-менеджер', 'callback_data' => 'Project-менеджер поиск']
                        ],
                        [
                            ['text' => 'Администратор сайта', 'callback_data' => 'Администратор сайта поиск']
                        ],
                        [
                            ['text' => 'Cпециалист службы поддержки', 'callback_data' => 'Cлужба поддержки поиск']
                        ],
                        [
                            ['text' => 'Режиссер видеомонтажа', 'callback_data' => 'Режиссер видеомонтажа поиск']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог поиск']
                        ],
                        [
                            ['text' => 'Оператор технической поддержки', 'callback_data' => 'Оператор тех-поддержки поиск']
                        ],
                        [
                            ['text' => 'Администратор баз данных', 'callback_data' => 'Администратор БД поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'administrSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Управляющий магазином', 'callback_data' => 'Управляющий магазином поиск']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор поиск']
                        ],
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Начальник отдела продаж поиск']
                        ],
                        [
                            ['text' => 'Мастер цеха', 'callback_data' => 'Мастер цеха поиск']
                        ],
                        [
                            ['text' => 'Администратор рецепции', 'callback_data' => 'Администратор рецепции поиск']
                        ],
                        [
                            ['text' => 'Начальник отдела кадров', 'callback_data' => 'Начальник отдела кадров поиск']
                        ],
                        [
                            ['text' => 'Супервайзер', 'callback_data' => 'Супервайзер поиск']
                        ],
                        [
                            ['text' => 'Менеджер по развитию', 'callback_data' => 'Менеджер по развитию поиск']
                        ],
                        [
                            ['text' => 'Администратор-кассир', 'callback_data' => 'Администратор-кассир поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'designSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея поиск']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер поиск']
                        ],
                        [
                            ['text' => 'Видеограф', 'callback_data' => 'Видеограф поиск']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер поиск']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф поиск']
                        ],
                        [
                            ['text' => 'Дизайнер полиграфии', 'callback_data' => 'Дизайнер полиграфии поиск']
                        ],
                        [
                            ['text' => 'Oператор ПК', 'callback_data' => 'Oператор ПК поиск']
                        ],
                        [
                            ['text' => 'Флорист', 'callback_data' => 'Флорист поиск']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер поиск']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер поиск']
                        ],
                        [
                            ['text' => 'Дизайнер-конструктор', 'callback_data' => 'Дизайнер-конструктор поиск']
                        ],
                        [
                            ['text' => 'Мастер-ювелир', 'callback_data' => 'Мастер-ювелир поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'beautySkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Лешмейкер', 'callback_data' => 'Лешмейкер поиск']
                        ],
                        [
                            ['text' => 'Instagram-модель', 'callback_data' => 'Instagram-модель поиск']
                        ],
                        [
                            ['text' => 'Парикмахер', 'callback_data' => 'Парикмахер поиск']
                        ],
                        [
                            ['text' => 'Руководитель фитнес-клуба', 'callback_data' => 'Руководитель фитнес-клуба поиск']
                        ],
                        [
                            ['text' => 'Мастер маникюра', 'callback_data' => 'Мастер маникюра поиск']
                        ],
                        [
                            ['text' => 'Мастер лазерной эпиляции', 'callback_data' => 'Мастер лазерной эпиляции поиск']
                        ],
                        [
                            ['text' => 'Администратор салона красоты', 'callback_data' => 'Админ салона красоты поиск']
                        ],
                        [
                            ['text' => 'Барбер', 'callback_data' => 'Барбер поиск']
                        ],
                        [
                            ['text' => 'Косметолог', 'callback_data' => 'Косметолог поиск']
                        ],
                        [
                            ['text' => 'Тренер по йоге', 'callback_data' => 'Тренер по йоге поиск']
                        ],
                        [
                            ['text' => 'Стилист-консультант', 'callback_data' => 'Стилист-консультант поиск']
                        ],
                        [
                            ['text' => 'Тренер по футболу', 'callback_data' => 'Тренер по футболу поиск']
                        ],
                        [
                            ['text' => 'Дерматолог', 'callback_data' => 'Дерматолог поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'showbizSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Видеооператор', 'callback_data' => 'Видеооператор поиск']
                        ],
                        [
                            ['text' => 'Сценарист', 'callback_data' => 'Сценарист поиск']
                        ],
                        [
                            ['text' => 'Организатор мероприятий', 'callback_data' => 'Организатор мероприятий поиск']
                        ],
                        [
                            ['text' => 'Ведущий церемоний', 'callback_data' => 'Ведущий церемоний поиск']
                        ],
                        [
                            ['text' => 'Актриса', 'callback_data' => 'Актриса поиск']
                        ],
                        [
                            ['text' => 'Хореограф-постановщик', 'callback_data' => 'Хореограф-постановщик поиск']
                        ],
                        [
                            ['text' => 'Оператор чата', 'callback_data' => 'Оператор чата поиск']
                        ],
                        [
                            ['text' => 'Стример', 'callback_data' => 'Стример поиск']
                        ],
                        [
                            ['text' => 'Фотограф', 'callback_data' => 'Фотограф поиск']
                        ],
                        [
                            ['text' => 'Аниматор', 'callback_data' => 'Аниматор поиск']
                        ],
                        [
                            ['text' => 'Менеджер по бизнес-процессам', 'callback_data' => 'Менеджер по бизнес-процессам поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'logistikaSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам поиск']
                        ],
                        [
                            ['text' => 'Сотрудник склада', 'callback_data' => 'Сотрудник склада поиск']
                        ],
                        [
                            ['text' => 'Менеджер по продажам логистических услуг', 'callback_data' => 'Менеджер лог. услуг поиск']
                        ],
                        [
                            ['text' => 'Коммерческий менеджер', 'callback_data' => 'Коммерческий менеджер поиск']
                        ],
                        [
                            ['text' => 'Комплектовщик на склад', 'callback_data' => 'Комплектовщик на склад поиск']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист поиск']
                        ],
                        [
                            ['text' => 'Грузчик', 'callback_data' => 'Грузчик поиск']
                        ],
                        [
                            ['text' => 'Приемщик товара', 'callback_data' => 'Приемщик товара поиск']
                        ],
                        [
                            ['text' => 'Водитель-грузчик', 'callback_data' => 'Водитель-грузчик поиск']
                        ],
                        [
                            ['text' => 'Оператор-упаковщик', 'callback_data' => 'Оператор-упаковщик поиск']
                        ],
                        [
                            ['text' => 'Менеджер по логистике', 'callback_data' => 'Менеджер по логистике поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'marketingSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер поиск']
                        ],
                        [
                            ['text' => 'Интернет-маркетолог', 'callback_data' => 'Интернет-маркетолог поиск']
                        ],
                        [
                            ['text' => 'Маркетолог', 'callback_data' => 'Маркетолог поиск']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер поиск']
                        ],
                        [
                            ['text' => 'SMM-специалист', 'callback_data' => 'SMM-специалист поиск']
                        ],
                        [
                            ['text' => 'Категорийный менеджер', 'callback_data' => 'Категорийный менеджер поиск']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер поиск']
                        ],
                        [
                            ['text' => 'Помощник контент-менеджера', 'callback_data' => 'Помощник контент-менеджера поиск']
                        ],
                        [
                            ['text' => 'SEO-специалист', 'callback_data' => 'SEO-специалист поиск']
                        ],
                        [
                            ['text' => 'Операционный директор', 'callback_data' => 'Операционный директор поиск']
                        ],
                        [
                            ['text' => 'Арт-директор', 'callback_data' => 'Арт-директор поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'medicinaSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Врач-офтальмолог', 'callback_data' => 'Врач-офтальмолог поиск']
                        ],
                        [
                            ['text' => 'Медицинский представитель', 'callback_data' => 'Медицинский представитель поиск']
                        ],
                        [
                            ['text' => 'Санитарка', 'callback_data' => 'Санитарка поиск']
                        ],
                        [
                            ['text' => 'Семейный врач', 'callback_data' => 'Семейный врач поиск']
                        ],
                        [
                            ['text' => 'Медсестра', 'callback_data' => 'Медсестра поиск']
                        ],
                        [
                            ['text' => 'Фармацевт', 'callback_data' => 'Фармацевт поиск']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант поиск']
                        ],
                        [
                            ['text' => 'Вирусолог', 'callback_data' => 'Вирусолог поиск']
                        ],
                        [
                            ['text' => 'Ассистент анестезиолога', 'callback_data' => 'Ассистент анестезиолога поиск']
                        ],
                        [
                            ['text' => 'Анастезиолог', 'callback_data' => 'Анастезиолог поиск']
                        ],
                        [
                            ['text' => 'Провизор', 'callback_data' => 'Провизор поиск']
                        ],
                        [
                            ['text' => 'Ветеринар', 'callback_data' => 'Ветеринар поиск']
                        ],
                        [
                            ['text' => 'Ассистент ветеринарного врача', 'callback_data' => 'Ассистент вет. врача поиск']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог поиск']
                        ],
                        [
                            ['text' => 'Психатр', 'callback_data' => 'Психатр поиск']
                        ],
                        [
                            ['text' => 'Стоматолог', 'callback_data' => 'Стоматолог поиск']
                        ],
                        [
                            ['text' => 'Кардиолог', 'callback_data' => 'Кардиолог поиск']
                        ],
                        [
                            ['text' => 'Хирург', 'callback_data' => 'Хирург поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'nedvizhimostSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Руководитель отдела продаж', 'callback_data' => 'Руководитель отдела продаж поиск']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости поиск']
                        ],
                        [
                            ['text' => 'Риэлтор', 'callback_data' => 'Риэлтор поиск']
                        ],
                        [
                            ['text' => 'Инспектор', 'callback_data' => 'Инспектор поиск']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам поиск']
                        ],
                        [
                            ['text' => 'Агент по недвижимости', 'callback_data' => 'Агент по недвижимости поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'naukaSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Воспитатель', 'callback_data' => 'Воспитатель поиск']
                        ],
                        [
                            ['text' => 'Преподаватель', 'callback_data' => 'Преподаватель поиск']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч поиск']
                        ],
                        [
                            ['text' => 'Социальный работник', 'callback_data' => 'Социальный работник поиск']
                        ],
                        [
                            ['text' => 'Психолог-консультант', 'callback_data' => 'Психолог-консультант поиск']
                        ],
                        [
                            ['text' => 'Логопед-дефектолог', 'callback_data' => 'Логопед-дефектолог поиск']
                        ],
                        [
                            ['text' => 'Методист', 'callback_data' => 'Методист поиск']
                        ],
                        [
                            ['text' => 'Репетитор', 'callback_data' => 'Репетитор поиск']
                        ],
                        [
                            ['text' => 'Ученый', 'callback_data' => 'Ученый поиск']
                        ],
                        [
                            ['text' => 'Помощник воспитателя', 'callback_data' => 'Помощник воспитателя поиск']
                        ],
                        [
                            ['text' => 'Няня', 'callback_data' => 'Няня поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'ohranaSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор охраны', 'callback_data' => 'Инспектор охраны поиск']
                        ],
                        [
                            ['text' => 'Охранник', 'callback_data' => 'Охранник поиск']
                        ],
                        [
                            ['text' => 'Контролер-ревизор', 'callback_data' => 'Контролер-ревизор поиск']
                        ],
                        [
                            ['text' => 'Оператор видеонаблюдения', 'callback_data' => 'Оператор видеонаблюдения поиск']
                        ],
                        [
                            ['text' => 'Инженер пожарной безопасности', 'callback_data' => 'Инженер пожарной безопасности поиск']
                        ],
                        [
                            ['text' => 'Техник по охранным системам', 'callback_data' => 'Техник по охранным системам поиск']
                        ],
                        [
                            ['text' => 'Начальник службы безопасности', 'callback_data' => 'Начальник сл. безопасности поиск']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный поиск']
                        ],
                        [
                            ['text' => 'Специалист по безопасности', 'callback_data' => 'Специалист по безопасности поиск']
                        ],
                        [
                            ['text' => 'Оперативный дежурный', 'callback_data' => 'Оперативный дежурный поиск']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский поиск']
                        ],
                        [
                            ['text' => 'Водитель-инкассатор', 'callback_data' => 'Водитель-инкассатор поиск']
                        ],
                        [
                            ['text' => 'Инкассатор', 'callback_data' => 'Инкассатор поиск']
                        ],
                        [
                            ['text' => 'Оператор ПЦС', 'callback_data' => 'Оператор ПЦС поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'prodajiSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам поиск']
                        ],
                        [
                            ['text' => 'Менеджер по закупке', 'callback_data' => 'Менеджер по закупке поиск']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец поиск']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель поиск']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам поиск']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер поиск']
                        ],
                        [
                            ['text' => 'Аналитик по продажам', 'callback_data' => 'Аналитик по продажам поиск']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер поиск']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант поиск']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'proizvodstvoSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер поиск']
                        ],
                        [
                            ['text' => 'Пекарь', 'callback_data' => 'Пекарь поиск']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик поиск']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник поиск']
                        ],
                        [
                            ['text' => 'Слесарь', 'callback_data' => 'Слесарь поиск']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий поиск']
                        ],
                        [
                            ['text' => 'Мастер-оптик', 'callback_data' => 'Мастер-оптик поиск']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея поиск']
                        ],
                        [
                            ['text' => 'Технолог', 'callback_data' => 'Технолог поиск']
                        ],
                        [
                            ['text' => 'Монтажник', 'callback_data' => 'Монтажник поиск']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр поиск']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель поиск']
                        ],
                        [
                            ['text' => 'Инженер-проектировщик', 'callback_data' => 'Инженер-проектировщик поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'torgovlyaSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам поиск']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам поиск']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец поиск']
                        ],
                        [
                            ['text' => 'Торговый представитель', 'callback_data' => 'Торговый представитель поиск']
                        ],
                        [
                            ['text' => 'Менеджер по оптовым продажам', 'callback_data' => 'Менеджер по оптовым продажам поиск']
                        ],
                        [
                            ['text' => 'Клиент-менеджер', 'callback_data' => 'Клиент-менеджер поиск']
                        ],
                        [
                            ['text' => 'Аналитик продаж', 'callback_data' => 'Аналитик продаж поиск']
                        ],
                        [
                            ['text' => 'Мерчендайзер', 'callback_data' => 'Мерчендайзер поиск']
                        ],
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант поиск']
                        ],
                        [
                            ['text' => 'Представитель поставщика', 'callback_data' => 'Представитель поставщика поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'sekretaringSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Помощник руководителя', 'callback_data' => 'Помощник руководителя поиск']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист поиск']
                        ],
                        [
                            ['text' => 'Офис-менеджер', 'callback_data' => 'Офис-менеджер поиск']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор поиск']
                        ],
                        [
                            ['text' => 'Оператор call-centre', 'callback_data' => 'Оператор call-centre поиск']
                        ],
                        [
                            ['text' => 'Менеджер по моб. продажам', 'callback_data' => 'Менеджер по моб. продажам поиск']
                        ],
                        [
                            ['text' => 'Администратор ресепшн', 'callback_data' => 'Администратор ресепшн поиск']
                        ],
                        [
                            ['text' => 'Менеджер по продажам', 'callback_data' => 'Менеджер по продажам поиск']
                        ],
                        [
                            ['text' => 'Помощник по мониторингу', 'callback_data' => 'Помощник по мониторингу поиск']
                        ],
                        [
                            ['text' => 'Оператор ПК', 'callback_data' => 'Оператор ПК поиск']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам', 'callback_data' => 'Менеджер по закупкам поиск']
                        ],
                        [
                            ['text' => 'Секретарь', 'callback_data' => 'Секретарь поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'agrobiznesSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Продавец-консультант', 'callback_data' => 'Продавец-консультант поиск']
                        ],
                        [
                            ['text' => 'Лаборант', 'callback_data' => 'Лаборант поиск']
                        ],
                        [
                            ['text' => 'Генетик', 'callback_data' => 'Генетик поиск']
                        ],
                        [
                            ['text' => 'Ветеринарный врач', 'callback_data' => 'Ветеринарный врач поиск']
                        ],
                        [
                            ['text' => 'Биолог', 'callback_data' => 'Биолог поиск']
                        ],
                        [
                            ['text' => 'Фермер', 'callback_data' => 'Фермер поиск']
                        ],
                        [
                            ['text' => 'Агроном', 'callback_data' => 'Агроном поиск']
                        ],
                        [
                            ['text' => 'Аналитик торгового рынка', 'callback_data' => 'Аналитик торгового рынка поиск']
                        ],
                        [
                            ['text' => 'Химик-хроматографист', 'callback_data' => 'Химик-хроматографист поиск']
                        ],
                        [
                            ['text' => 'Зернотрейдер', 'callback_data' => 'Зернотрейдер поиск']
                        ],
                        [
                            ['text' => 'Менеджер по закупкам зерновых культур', 'callback_data' => 'Менеджер по закупке зерна поиск']
                        ],
                        [
                            ['text' => 'Пробоотборник с/х продукции', 'callback_data' => 'Пробоотборник с/х продукции поиск']
                        ],
                        [
                            ['text' => 'Садовник', 'callback_data' => 'Садовник поиск']
                        ],
                        [
                            ['text' => 'Тракторист', 'callback_data' => 'Тракторист поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'izdatelstvoSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Фотограф в интернет-магазине', 'callback_data' => 'Фотограф в интернет-магазине поиск']
                        ],
                        [
                            ['text' => 'Контент-менеджер', 'callback_data' => 'Контент-менеджер поиск']
                        ],
                        [
                            ['text' => 'Видеоредактор', 'callback_data' => 'Видеоредактор поиск']
                        ],
                        [
                            ['text' => 'SMM / контент-менеджер', 'callback_data' => 'SMM / контент-менеджер поиск']
                        ],
                        [
                            ['text' => 'Графический дизайнер', 'callback_data' => 'Графический дизайнер поиск']
                        ],
                        [
                            ['text' => 'Печатник', 'callback_data' => 'Печатник поиск']
                        ],
                        [
                            ['text' => 'Копирайтер', 'callback_data' => 'Копирайтер поиск']
                        ],
                        [
                            ['text' => 'Motion-дизайнер', 'callback_data' => 'Motion-дизайнер поиск']
                        ],
                        [
                            ['text' => 'Oператор цифровой печати', 'callback_data' => 'Oператор цифровой печати поиск']
                        ],
                        [
                            ['text' => 'Веб-журналист', 'callback_data' => 'Веб-журналист поиск']
                        ],
                        [
                            ['text' => 'Журналист', 'callback_data' => 'Журналист поиск']
                        ],
                        [
                            ['text' => 'Писатель', 'callback_data' => 'Писатель поиск']
                        ],
                        [
                            ['text' => 'Редактор', 'callback_data' => 'Редактор поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'strahovanieSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инспектор-координатор', 'callback_data' => 'Инспектор-координатор поиск']
                        ],
                        [
                            ['text' => 'Сюрвейер', 'callback_data' => 'Сюрвейер поиск']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр поиск']
                        ],
                        [
                            ['text' => 'Менеджер по страхованию', 'callback_data' => 'Менеджер по страхованию поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'stroitelstvoSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Инженер технического надзора', 'callback_data' => 'Инженер технического надзора поиск']
                        ],
                        [
                            ['text' => 'Электромеханик контактной сети', 'callback_data' => 'Электромеханик контактной сети поиск']
                        ],
                        [
                            ['text' => 'Архитектор', 'callback_data' => 'Архитектор поиск']
                        ],
                        [
                            ['text' => 'Электромонтер', 'callback_data' => 'Электромонтер поиск']
                        ],
                        [
                            ['text' => 'Газорезчик', 'callback_data' => 'Газорезчик поиск']
                        ],
                        [
                            ['text' => 'Строитель', 'callback_data' => 'Строитель поиск']
                        ],
                        [
                            ['text' => 'Разнорабочий', 'callback_data' => 'Разнорабочий поиск']
                        ],
                        [
                            ['text' => 'Помощник Архитектора', 'callback_data' => 'Помощник Архитектора поиск']
                        ],
                        [
                            ['text' => 'Маляр', 'callback_data' => 'Маляр поиск']
                        ],
                        [
                            ['text' => 'Дизайнер', 'callback_data' => 'Дизайнер поиск']
                        ],
                        [
                            ['text' => 'Мастер-бутафор', 'callback_data' => 'Мастер-бутафор поиск']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик поиск']
                        ],
                        [
                            ['text' => 'Штукатур', 'callback_data' => 'Штукатур поиск']
                        ],
                        [
                            ['text' => 'Сантехник', 'callback_data' => 'Сантехник поиск']
                        ],
                        [
                            ['text' => 'Инженер отдела продаж', 'callback_data' => 'Инженер отдела продаж поиск']
                        ],
                        [
                            ['text' => 'Сервисный инженер', 'callback_data' => 'Сервисный инженер поиск']
                        ],
                        [
                            ['text' => 'Электромеханик', 'callback_data' => 'Электромеханик поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'obsluzhivanieSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Повар', 'callback_data' => 'Повар поиск']
                        ],
                        [
                            ['text' => 'Официант', 'callback_data' => 'Официант поиск']
                        ],
                        [
                            ['text' => 'Бариста', 'callback_data' => 'Бариста поиск']
                        ],
                        [
                            ['text' => 'Консьерж', 'callback_data' => 'Консьерж поиск']
                        ],
                        [
                            ['text' => 'Администратор', 'callback_data' => 'Администратор поиск']
                        ],
                        [
                            ['text' => 'Мастер бьюти сферы', 'callback_data' => 'Мастер бьюти сферы поиск']
                        ],
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель поиск']
                        ],
                        [
                            ['text' => 'Продавец', 'callback_data' => 'Продавец поиск']
                        ],
                        [
                            ['text' => 'Оператор', 'callback_data' => 'Оператор поиск']
                        ],
                        [
                            ['text' => 'Массажист', 'callback_data' => 'Массажист поиск']
                        ],
                        [
                            ['text' => 'Хостес', 'callback_data' => 'Хостес поиск']
                        ],
                        [
                            ['text' => 'Сомелье', 'callback_data' => 'Сомелье поиск']
                        ],
                        [
                            ['text' => 'Бармен', 'callback_data' => 'Бармен поиск']
                        ],
                        [
                            ['text' => 'Швея', 'callback_data' => 'Швея поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'telecomunikaciiSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Специалист технической поддержки', 'callback_data' => 'Специалист техподдержки поиск']
                        ],
                        [
                            ['text' => 'Инженер связи', 'callback_data' => 'Инженер связи поиск']
                        ],
                        [
                            ['text' => 'Техник', 'callback_data' => 'Техник поиск']
                        ],
                        [
                            ['text' => 'Оператор службы поддержки', 'callback_data' => 'Оператор службы поддержки поиск']
                        ],
                        [
                            ['text' => 'Системный администратор', 'callback_data' => 'Системный администратор поиск']
                        ],
                        [
                            ['text' => 'Саппорт-менеджер', 'callback_data' => 'Саппорт-менеджер поиск']
                        ],
                        [
                            ['text' => 'Электрик', 'callback_data' => 'Электрик поиск']
                        ],
                        [
                            ['text' => 'Монтажник связи', 'callback_data' => 'Монтажник связи поиск']
                        ],
                        [
                            ['text' => 'Диспетчер-логист', 'callback_data' => 'Диспетчер-логист поиск']
                        ],
                        [
                            ['text' => 'Менеджер call-центра', 'callback_data' => 'Менеджер call-центра поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'topmenSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Заместитель директора', 'callback_data' => 'Заместитель директора поиск']
                        ],
                        [
                            ['text' => 'Директор сервисного центра', 'callback_data' => 'Директор сервисного центра поиск']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель поиск']
                        ],
                        [
                            ['text' => 'Руководитель розничной сети', 'callback_data' => 'Руководитель розничной сети поиск']
                        ],
                        [
                            ['text' => 'Куратор', 'callback_data' => 'Куратор поиск']
                        ],
                        [
                            ['text' => 'Директор филиала', 'callback_data' => 'Директор филиала поиск']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор поиск']
                        ],
                        [
                            ['text' => 'Директор по персоналу', 'callback_data' => 'Директор по персоналу поиск']
                        ],
                        [
                            ['text' => 'PR-директор', 'callback_data' => 'PR-директор поиск']
                        ],
                        [
                            ['text' => 'Исполнительный директор', 'callback_data' => 'Исполнительный директор поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'avtobizSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Водитель', 'callback_data' => 'Водитель поиск']
                        ],
                        [
                            ['text' => 'Механик', 'callback_data' => 'Механик поиск']
                        ],
                        [
                            ['text' => 'Автослесарь', 'callback_data' => 'Автослесарь поиск']
                        ],
                        [
                            ['text' => 'Логист', 'callback_data' => 'Логист поиск']
                        ],
                        [
                            ['text' => 'Экспедитор', 'callback_data' => 'Экспедитор поиск']
                        ],
                        [
                            ['text' => 'Инкассатор-водитель', 'callback_data' => 'Инкассатор-водитель поиск']
                        ],
                        [
                            ['text' => 'Главный механик', 'callback_data' => 'Главный механик поиск']
                        ],
                        [
                            ['text' => 'Начальник отдела транспорта', 'callback_data' => 'Начальник отдела транспорта поиск']
                        ],
                        [
                            ['text' => 'Курьер', 'callback_data' => 'Курьер поиск']
                        ],
                        [
                            ['text' => 'Дальнобойщик', 'callback_data' => 'Дальнобойщик поиск']
                        ],
                        [
                            ['text' => 'Мастер СТО', 'callback_data' => 'Мастер СТО поиск']
                        ],
                        [
                            ['text' => 'Рихтовщик', 'callback_data' => 'Рихтовщик поиск']
                        ],
                        [
                            ['text' => 'Моторист', 'callback_data' => 'Моторист поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'hrSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Менеджер по персоналу', 'callback_data' => 'Менеджер по персоналу поиск']
                        ],
                        [
                            ['text' => 'HR-менеджер', 'callback_data' => 'HR-менеджер поиск']
                        ],
                        [
                            ['text' => 'HR-руководитель', 'callback_data' => 'HR-руководитель поиск']
                        ],
                        [
                            ['text' => 'Инженер по охране труда', 'callback_data' => 'Инженер по охране труда поиск']
                        ],
                        [
                            ['text' => 'Рекрутер', 'callback_data' => 'Рекрутер поиск']
                        ],
                        [
                            ['text' => 'Коуч', 'callback_data' => 'Коуч поиск']
                        ],
                        [
                            ['text' => 'Психолог', 'callback_data' => 'Психолог поиск']
                        ],
                        [
                            ['text' => 'Инспектор отдела кадров', 'callback_data' => 'Инспектор отдела кадров поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'bankSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Кассир', 'callback_data' => 'Кассир поиск']
                        ],
                        [
                            ['text' => 'Финансист', 'callback_data' => 'Финансист поиск']
                        ],
                        [
                            ['text' => 'Банкир', 'callback_data' => 'Банкир поиск']
                        ],
                        [
                            ['text' => 'Экономист', 'callback_data' => 'Экономист поиск']
                        ],
                        [
                            ['text' => 'Заведующий кассой банка', 'callback_data' => 'Заведующий кассой банка поиск']
                        ],
                        [
                            ['text' => 'Финансовый контроллер', 'callback_data' => 'Финансовый контроллер поиск']
                        ],
                        [
                            ['text' => 'Заместитель финансового директора', 'callback_data' => 'Зам. финансового директора поиск']
                        ],
                        [
                            ['text' => 'Финансовый директор', 'callback_data' => 'Финансовый директор поиск']
                        ],
                        [
                            ['text' => 'Аналитик', 'callback_data' => 'Аналитик поиск']
                        ],
                        [
                            ['text' => 'Директор отделения банка', 'callback_data' => 'Директор отделения банка поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
                        ]
                    ]
                ]
            ];
            break;

        case 'yuristSkill-find':
            // Удаляем старое сообщение
            $user = $func['from']['id'];
            $send_data['message_id'] = $data['callback_query']['message']['message_id'];
            $send_data['chat_id'] = $user;
            sendTelegram('deleteMessage', $send_data);
            
            $method = 'sendMessage';
            $send_data = [
                'text' => 'Выбери навык:',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Юрист', 'callback_data' => 'Юрист поиск']
                        ],
                        [
                            ['text' => 'Адвокат', 'callback_data' => 'Адвокат поиск']
                        ],
                        [
                            ['text' => 'Помощник адвоката', 'callback_data' => 'Помощник адвоката поиск']
                        ],
                        [
                            ['text' => 'Нотариус', 'callback_data' => 'Нотариус поиск']
                        ],
                        [
                            ['text' => 'Регистратор', 'callback_data' => 'Регистратор поиск']
                        ],
                        [
                            ['text' => 'Прокурор', 'callback_data' => 'Прокурор поиск']
                        ],
                        [
                            ['text' => 'Полицейский', 'callback_data' => 'Полицейский поиск']
                        ],
                        [
                            ['text' => 'Судья', 'callback_data' => 'Судья поиск']
                        ],
                        [
                            ['text' => 'Следователь', 'callback_data' => 'Следователь поиск']
                        ],
                        [
                            ['text' => 'Юрисконсульт', 'callback_data' => 'Юрисконсульт поиск']
                        ],
                        [
                            ['text' => 'Помощник судьи', 'callback_data' => 'Помощник судьи поиск']
                        ],
                        [
                            ['text' => '👈 Вернуться к выбору категории', 'callback_data' => 'skillsFinder']
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

?>
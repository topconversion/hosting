<?php

// Включение вывода ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Путь к файлу логов (измените на реальный путь)
$logFile = __DIR__ . '/amocrm_log.txt';

// Настройки amoCRM
$subdomain = 'topconversion';
$accessToken = 'YOUR_ACCESS_TOKEN'; // Убедитесь, что у вас есть действующий токен доступа

// Функция для записи логов
function writeLog($message) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $message\n", FILE_APPEND);
}
// Функция для получения значения куки
function getCookie($name, $cookieString) {
    // Парсим строку кук
    $cookies = explode('; ', $cookieString);
    foreach ($cookies as $cookie) {
        list($cookieName, $cookieValue) = explode('=', $cookie, 2);
        if ($cookieName == $name) {
            return urldecode($cookieValue);
        }
    }
    return '';
}

// Получаем все переменные из POST-запроса и куки
$postData = $_POST;
$cookieString = $postData['COOKIES'] ?? '';
$contactName = $postData['name'] ?? getCookie('name', $cookieString); // Имя контакта
$phone = $postData['phone'] ?? getCookie('phone', $cookieString);
$email = $postData['email'] ?? getCookie('email', $cookieString);
$visitor_uid = $postData['visitor_uid'] ?? getCookie('visitor_uid', $cookieString);
$visitor_id = $postData['visitor_id'] ?? getCookie('visitor_id', $cookieString);
$utm_content = $postData['utm_content'] ?? getCookie('utm_content', $cookieString);
$utm_medium = $postData['utm_medium'] ?? getCookie('utm_medium', $cookieString);
$utm_campaign = $postData['utm_campaign'] ?? getCookie('utm_campaign', $cookieString);
$utm_source = $postData['utm_source'] ?? getCookie('utm_source', $cookieString);
$utm_term = $postData['utm_term'] ?? getCookie('utm_term', $cookieString);
$utm_referrer = $postData['utm_referrer'] ?? getCookie('utm_referrer', $cookieString);
$roistat = $postData['roistat'] ?? getCookie('roistat', $cookieString);
$referrer = $postData['referrer'] ?? getCookie('referrer', $cookieString);
$openstat_service = $postData['openstat_service'] ?? getCookie('openstat_service', $cookieString);
$openstat_campaign = $postData['openstat_campaign'] ?? getCookie('openstat_campaign', $cookieString);
$openstat_ad = $postData['openstat_ad'] ?? getCookie('openstat_ad', $cookieString);
$openstat_source = $postData['openstat_source'] ?? getCookie('openstat_source', $cookieString);
$gclientid = $postData['gclientid'] ?? getCookie('gclientid', $cookieString);
$ym_uid = $postData['_ym_uid'] ?? getCookie('_ym_uid', $cookieString);
$ym_counter = $postData['_ym_counter'] ?? getCookie('_ym_counter', $cookieString);
$gclid = $postData['gclid'] ?? getCookie('gclid', $cookieString);
$yclid = $postData['yclid'] ?? getCookie('yclid', $cookieString);
$fbclid = $postData['fbclid'] ?? getCookie('fbclid', $cookieString);
$rb_clickid = $postData['rb_clickid'] ?? getCookie('rb_clickid', $cookieString);
$from = $postData['from'] ?? getCookie('from', $cookieString);
$tranid = $postData['tranid'] ?? getCookie('tranid', $cookieString);
$formid = $postData['formid'] ?? getCookie('formid', $cookieString);
$formname = $postData['formname'] ?? getCookie('formname', $cookieString);
$privacy_policy_agreement = isset($postData['privacy_policy_agreement']) && $postData['privacy_policy_agreement'] === 'yes';
// Функция для выполнения запросов к amoCRM
function amoCrmRequest($method, $url, $data = null) {
    global $subdomain, $accessToken;
    
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_URL, "https://$subdomain.amocrm.ru/api/v4/$url");
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($curl);
    $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    writeLog("Response: $response");
    
    if ($responseCode >= 400) {
        writeLog("Error: $response");
        throw new Exception("Error: $response");
    }
    
    return json_decode($response, true);
}

// Функция для нормализации номера телефона
function normalizePhoneNumber($phone) {
    // Удаляем все символы, кроме цифр
    $phone = preg_replace('/\\D/', '', $phone);
    
    // Преобразуем номер к формату +7 123 456-78-90
    $normalizedPhone = '+7 ' . substr($phone, -10, 3) . ' ' . substr($phone, -7, 3) . '-' . substr($phone, -4, 2) . '-' . substr($phone, -2);
    
    return $normalizedPhone;
}
// Поиск контакта по номеру телефона
function findContactByPhone($phone) {
    $normalizedPhone = normalizePhoneNumber($phone);
    
    try {
        // Выполняем запрос к amoCRM для поиска контакта
        $response = amoCrmRequest('GET', "contacts?query=$normalizedPhone");
        if (!empty($response['_embedded']['contacts'])) {
            writeLog("Contact found by phone: $normalizedPhone");
            return $response['_embedded']['contacts'][0];
        }
    } catch (Exception $e) {
        // Логируем ошибки
        writeLog($e->getMessage());
    }
    
    writeLog("No contact found by phone: $phone");
    return null;
}

// Поиск контакта по email
function findContactByEmail($email) {
    try {
        $response = amoCrmRequest('GET', "contacts?query=$email");
        if (!empty($response['_embedded']['contacts'])) {
            writeLog("Contact found by email: $email");
            return $response['_embedded']['contacts'][0];
        }
    } catch (Exception $e) {
        // Логируем ошибки
        writeLog($e->getMessage());
    }
    
    writeLog("No contact found by email: $email");
    return null;
}
// Создание нового контакта
function createContact($data) {
    try {
        $response = amoCrmRequest('POST', 'contacts', [$data]);
        writeLog("Contact created: " . json_encode($response));
        return $response['_embedded']['contacts'][0];
    } catch (Exception $e) {
        // Логируем ошибки
        writeLog($e->getMessage());
        return null;
    }
}

// Создание сделки с привязкой к контакту
function createDealWithContact($contactId, $dealData) {
    $dealData['_embedded'] = [
        'contacts' => [
            ['id' => $contactId]
        ]
    ];
    try {
        $response = amoCrmRequest('POST', 'leads/complex', [$dealData]);
        writeLog("Deal created: " . json_encode($response));
        return $response['_embedded']['leads'][0];
    } catch (Exception $e) {
        // Логируем ошибки
        writeLog($e->getMessage());
        return null;
    }
}
// Основная логика
try {
    // Получение данных из POST-запроса
    $postData = $_POST;
    $contactName = $postData['name'] ?? '';
    $phone = $postData['phone'] ?? '';
    $email = $postData['email'] ?? '';
    $statusId = 65419714; // ID статуса сделки

    // Ищем контакт по номеру телефона или email
    $contact = findContactByPhone($phone);
    if (!$contact && $email) {
        $contact = findContactByEmail($email);
    }
    
    // Если контакт найден, используем его ID, иначе создаем новый контакт
    if ($contact) {
        $contactId = $contact['id'];
    } else {
        $contactData = [
            'name' => $contactName,
            'custom_fields_values' => [
                [
                    'field_id' => 92667, // ID поля для телефона
                    'values' => [
                        ['value' => $phone]
                    ]
                ],
                [
                    'field_id' => 92669, // ID поля для email
                    'values' => [
                        ['value' => $email]
                    ]
                ]
            ]
        ];
        $newContact = createContact($contactData);
        $contactId = $newContact['id'];
    }
    // Данные для создания сделки
    $dealData = [
        'name' => 'New Deal',
        'status_id' => $statusId,
        'price' => 3000, // Пример суммы сделки
        'custom_fields_values' => [
            [
                'field_id' => 92703, // ID поля _ym_uid
                'values' => [
                    ['value' => $ym_uid]
                ]
            ],
            [
                'field_id' => 92705, // ID поля _ym_counter
                'values' => [
                    ['value' => $ym_counter]
                ]
            ],
            [
                'field_id' => 92701, // ID поля gclientid
                'values' => [
                    ['value' => $gclientid]
                ]
            ],
            [
                'field_id' => 92689, // ID поля referrer
                'values' => [
                    ['value' => $referrer]
                ]
            ],
            [
                'field_id' => 92687, // ID поля roistat
                'values' => [
                    ['value' => $roistat]
                ]
            ],
            [
                'field_id' => 92675, // ID поля utm_content
                'values' => [
                    ['value' => $utm_content]
                ]
            ],
            [
                'field_id' => 92677, // ID поля utm_medium
                'values' => [
                    ['value' => $utm_medium]
                ]
            ],
            [
                'field_id' => 92679, // ID поля utm_campaign
                'values' => [
                    ['value' => $utm_campaign]
                ]
            ],
            [
                'field_id' => 92681, // ID поля utm_source
                'values' => [
                    ['value' => $utm_source]
                ]
            ],
            [
                'field_id' => 92683, // ID поля utm_term
                'values' => [
                    ['value' => $utm_term]
                ]
            ],
            [
                'field_id' => 92685, // ID поля utm_referrer
                'values' => [
                    ['value' => $utm_referrer]
                ]
            ],
            [
                'field_id' => 92691, // ID поля openstat_service
                'values' => [
                    ['value' => $openstat_service]
                ]
            ],
            [
                'field_id' => 92693, // ID поля openstat_campaign
                'values' => [
                    ['value' => $openstat_campaign]
                ]
            ],
            [
                'field_id' => 92695, // ID поля openstat_ad
                'values' => [
                    ['value' => $openstat_ad]
                ]
            ],
            [
                'field_id' => 92697, // ID поля openstat_source
                'values' => [
                    ['value' => $openstat_source]
                ]
            ],
            [
                'field_id' => 92707, // ID поля gclid
                'values' => [
                    ['value' => $gclid]
                ]
            ],
            [
                'field_id' => 92709, // ID поля yclid
                'values' => [
                    ['value' => $yclid]
                ]
            ],
            [
                'field_id' => 92711, // ID поля fbclid
                'values' => [
                    ['value' => $fbclid]
                ]
            ],
            [
                'field_id' => 973191, // ID поля rb_clickid
                'values' => [
                    ['value' => $rb_clickid]
                ]
            ],
            [
                'field_id' => 92699, // ID поля from
                'values' => [
                    ['value' => $from]
                ]
            ],
            [
                'field_id' => 971549, // ID поля tranid
                'values' => [
                    ['value' => $tranid]
                ]
            ],
            [
                'field_id' => 971551, // ID поля formid
                'values' => [
                    ['value' => $formid]
                ]
            ],
            [
                'field_id' => 973309, // ID поля formname
                'values' => [
                    ['value' => $formname]
                ]
            ]
        ]
    ];
    // Создаем сделку с привязкой к контакту
    createDealWithContact($contactId, $dealData);

} catch (Exception $e) {
    // Логируем ошибки
    writeLog($e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Данные успешно обработаны']);
?>

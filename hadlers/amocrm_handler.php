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

// Получаем все переменные из POST-запроса
$postData = $_POST;
$contactName = $postData['name'] ?? ''; // Имя контакта
$phone = $postData['phone'] ?? '';
$email = $postData['email'] ?? '';
$visitor_uid = $postData['visitor_uid'] ?? '';
$utm_content = $postData['utm_content'] ?? '';
$utm_medium = $postData['utm_medium'] ?? '';
$utm_campaign = $postData['utm_campaign'] ?? '';
$utm_source = $postData['utm_source'] ?? '';
$utm_term = $postData['utm_term'] ?? '';
$utm_referrer = $postData['utm_referrer'] ?? '';
$roistat = $postData['roistat'] ?? '';
$referrer = $postData['referrer'] ?? '';
$openstat_service = $postData['openstat_service'] ?? '';
$openstat_campaign = $postData['openstat_campaign'] ?? '';
$openstat_ad = $postData['openstat_ad'] ?? '';
$openstat_source = $postData['openstat_source'] ?? '';
$gclientid = $postData['gclientid'] ?? '';
$ym_uid = $postData['_ym_uid'] ?? '';
$ym_counter = $postData['_ym_counter'] ?? '';
$gclid = $postData['gclid'] ?? '';
$yclid = $postData['yclid'] ?? '';
$fbclid = $postData['fbclid'] ?? '';
$rb_clickid = $postData['rb_clickid'] ?? '';
$from = $postData['from'] ?? '';

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
    
    if ($responseCode >= 400) {
        writeLog("Error: $response");
        throw new Exception("Error: $response");
    }
    
    writeLog("Response: $response");
    return json_decode($response, true);
}

// Функция для форматирования номера телефона
function formatPhoneNumber($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    $formats = [
        $phone,
        '7' . substr($phone, -10),
        '8' . substr($phone, -10),
        '+7' . substr($phone, -10),
        '+8' . substr($phone, -10)
    ];
    return $formats;
}
// Поиск контакта по номеру телефона
function findContactByPhone($phone) {
    $phoneFormats = formatPhoneNumber($phone);
    
    foreach ($phoneFormats as $formattedPhone) {
        try {
            $response = amoCrmRequest('GET', "contacts?query=$formattedPhone");
            if (!empty($response['_embedded']['contacts'])) {
                writeLog("Contact found by phone: $formattedPhone");
                return $response['_embedded']['contacts'][0];
            }
        } catch (Exception $e) {
            // Логируем ошибки
            writeLog($e->getMessage());
        }
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
        $response = amoCrmRequest('POST', 'contacts', ['add' => [$data]]);
        writeLog("Contact created: " . json_encode($response));
        return $response['_embedded']['contacts'][0];
    } catch (Exception $e) {
        // Логируем ошибки
        writeLog($e->getMessage());
        return null;
    }
}

// Обновление контакта
function updateContact($id, $data) {
    try {
        amoCrmRequest('PATCH', "contacts/$id", $data);
        writeLog("Contact updated: $id");
    } catch (Exception $e) {
        // Логируем ошибки
        writeLog($e->getMessage());
    }
}

// Создание сделки
function createDeal($data) {
    try {
        $response = amoCrmRequest('POST', 'leads', ['add' => [$data]]);
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
    // Ищем контакт по номеру телефона
    $contact = findContactByPhone($phone);
    
    // Если контакт не найден по номеру телефона, ищем по email
    if (!$contact && $email) {
        $contact = findContactByEmail($email);
    }
    
    // Данные для обновления контакта
    $contactUpdateData = [
        'name' => $contactName,
        'custom_fields_values' => []
    ];

    // Данные для создания контакта
    $contactCreateData = [
        'name' => $contactName,
        'custom_fields_values' => [
            [
                'field_id' => 123456, // Замените на реальный ID поля для телефона
                'values' => [
                    ['value' => $phone]
                ]
            ],
            [
                'field_id' => 123457, // Замените на реальный ID поля для email
                'values' => [
                    ['value' => $email]
                ]
            ]
        ]
    ];

    // Данные для создания сделки
    $dealData = [
        'name' => 'New Deal',
        'status_id' => 142, // Укажите нужный статус сделки
        'custom_fields_values' => [
            [
                'field_id' => 967291, // ID поля utm_content
                'values' => [
                    ['value' => $utm_content]
                ]
            ],
            [
                'field_id' => 967293, // ID поля utm_medium
                'values' => [
                    ['value' => $utm_medium]
                ]
            ],
            [
                'field_id' => 967295, // ID поля utm_campaign
                'values' => [
                    ['value' => $utm_campaign]
                ]
            ],
            [
                'field_id' => 967297, // ID поля utm_source
                'values' => [
                    ['value' => $utm_source]
                ]
            ],
            [
                'field_id' => 967299, // ID поля utm_term
                'values' => [
                    ['value' => $utm_term]
                ]
            ],
            [
                'field_id' => 967301, // ID поля utm_referrer
                'values' => [
                    ['value' => $utm_referrer]
                ]
            ],
            [
                'field_id' => 967303, // ID поля roistat
                'values' => [
                    ['value' => $roistat]
                ]
            ],
            [
                'field_id' => 967305, // ID поля referrer
                'values' => [
                    ['value' => $referrer]
                ]
            ],
            [
                'field_id' => 967307, // ID поля openstat_service
                'values' => [
                    ['value' => $openstat_service]
                ]
            ],
            [
                'field_id' => 967309, // ID поля openstat_campaign
                'values' => [
                    ['value' => $openstat_campaign]
                ]
            ],
            [
                'field_id' => 967311, // ID поля openstat_ad
                'values' => [
                    ['value' => $openstat_ad]
                ]
            ],
            [
                'field_id' => 967313, // ID поля openstat_source
                'values' => [
                    ['value' => $openstat_source]
                ]
            ],
            [
                'field_id' => 967315, // ID поля gclientid
                'values' => [
                    ['value' => $gclientid]
                ]
            ],
            [
                'field_id' => 967317, // ID поля _ym_uid
                'values' => [
                    ['value' => $ym_uid]
                ]
            ],
            [
                'field_id' => 967319, // ID поля _ym_counter
                'values' => [
                    ['value' => $ym_counter]
                ]
            ],
            [
                'field_id' => 967321, // ID поля gclid
                'values' => [
                    ['value' => $gclid]
                ]
            ],
            [
                'field_id' => 967323, // ID поля yclid
                'values' => [
                    ['value' => $yclid]
                ]
            ],
            [
                'field_id' => 967325, // ID поля fbclid
                'values' => [
                    ['value' => $fbclid]
                ]
            ],
            [
                'field_id' => 967327, // ID поля rb_clickid
                'values' => [
                    ['value' => $rb_clickid]
                ]
            ],
            [
                'field_id' => 967329, // ID поля from
                'values' => [
                    ['value' => $from]
                ]
            ]
        ]
    ];

?>
    if ($contact) {
        // Дополняем контакт данными из запроса, если они не заполнены
        if (empty($contact['custom_fields_values'])) {
            $contactUpdateData['custom_fields_values'] = $contactCreateData['custom_fields_values'];
        }

        // Обновляем контакт
        updateContact($contact['id'], $contactUpdateData);

        // Создаем сделку и связываем с контактом
        $dealData['contacts_id'] = [$contact['id']];
        $newDeal = createDeal($dealData);

        if ($newDeal) {
            $dealId = $newDeal['id'];
            $dealName = "Новый лид #$dealId";
            updateDealName($dealId, $dealName);
        }

    } else {
        // Создаем новый контакт и сделку
        $newContact = createContact($contactCreateData);

        if ($newContact) {
            $dealData['contacts_id'] = [$newContact['id']];
            $newDeal = createDeal($dealData);

            if ($newDeal) {
                $dealId = $newDeal['id'];
                $dealName = "Новый лид #$dealId";
                updateDealName($dealId, $dealName);
            }
        }
    }

    writeLog("Script executed successfully");
    echo "Success";

} catch (Exception $e) {
    writeLog("Exception: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}

// Функция для обновления имени сделки
function updateDealName($dealId, $dealName) {
    $data = [
        'name' => $dealName
    ];
    try {
        amoCrmRequest('PATCH', "leads/$dealId", $data);
        writeLog("Deal name updated: $dealName");
    } catch (Exception $e) {
        writeLog($e->getMessage());
    }
}

?>

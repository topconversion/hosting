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
$name = $postData['name'] ?? getCookie('name', $cookieString);
$phone = $postData['phone'] ?? getCookie('phone', $cookieString);
$email = $postData['email'] ?? getCookie('email', $cookieString);
$status_id = isset($postData['status_id']) ? (int)$postData['status_id'] : (int)getCookie('status_id', $cookieString);
$pipeline_id = isset($postData['pipeline_id']) ? (int)$postData['pipeline_id'] : (int)getCookie('pipeline_id', $cookieString);
$lead_name = isset($postData['lead_name']) ? $postData['lead_name'] : getCookie('lead_name', $cookieString);
$price = isset($postData['price']) ? (float)$postData['price'] : (float)getCookie('price', $cookieString);
$visitor_uid = isset($postData['visitor_uid']) ? $postData['visitor_uid'] : getCookie('visitor_uid', $cookieString);
$visitor_id = isset($postData['visitor_id']) ? (int)$postData['visitor_id'] : (int)getCookie('visitor_id', $cookieString);
$note = isset($postData['note']) ? $postData['note'] : getCookie('note', $cookieString);

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
            return $response['_embedded']['contacts'][0];
        }
    } catch (Exception $e) {
        writeLog($e->getMessage());
    }
    
    return null;
}

// Поиск контакта по email
function findContactByEmail($email) {
    try {
        $response = amoCrmRequest('GET', "contacts?query=$email");
        if (!empty($response['_embedded']['contacts'])) {
            return $response['_embedded']['contacts'][0];
        }
    } catch (Exception $e) {
        writeLog($e->getMessage());
    }
    
    return null;
}

// Создание нового контакта
function createContact($data) {
    try {
        $response = amoCrmRequest('POST', 'contacts', [$data]);
        return $response['_embedded']['contacts'][0];
    } catch (Exception $e) {
        writeLog($e->getMessage());
        return null;
    }
}
// Обновление контакта
function updateContact($contactId, $data) {
    try {
        $response = amoCrmRequest('PATCH', "contacts/$contactId", [$data]);
        return $response;
    } catch (Exception $e) {
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
        if (isset($response[0]['id'])) {
            return $response[0]; // Возвращаем первую сделку из массива
        } else {
            throw new Exception("Deal creation response does not contain an ID");
        }
    } catch (Exception $e) {
        writeLog($e->getMessage());
        return null;
    }
}

// Создание примечания к сделке
function createNoteForDeal($dealId, $note) {
    global $subdomain, $accessToken;
    $url = "https://$subdomain.amocrm.ru/api/v4/leads/$dealId/notes";
    $noteData = [
        [
            "note_type" => "common",
            "params" => [
                "text" => $note
            ]
        ]
    ];
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($noteData));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($curl);
    $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($responseCode >= 400) {
        writeLog("Error: $response");
        throw new Exception("Error: $response");
    }

    return json_decode($response, true);
}
// Основная логика
try {
    // Получение данных из POST-запроса
    $postData = $_POST;
    $name = $postData['name'] ?? '';
    $phone = $postData['phone'] ?? '';
    $email = $postData['email'] ?? '';
    $statusId = isset($postData['status_id']) ? (int)$postData['status_id'] : (int)getCookie('status_id', $cookieString);
    $pipelineId = isset($postData['pipeline_id']) ? (int)$postData['pipeline_id'] : (int)getCookie('pipeline_id', $cookieString);
    $leadName = isset($postData['lead_name']) ? $postData['lead_name'] : getCookie('lead_name', $cookieString);
    $price = isset($postData['price']) ? (float)$postData['price'] : (float)getCookie('price', $cookieString);
    $visitorUid = isset($postData['visitor_uid']) ? $postData['visitor_uid'] : getCookie('visitor_uid', $cookieString);
    $visitorId = isset($postData['visitor_id']) ? (int)$postData['visitor_id'] : (int)getCookie('visitor_id', $cookieString);
    $note = isset($postData['note']) ? $postData['note'] : getCookie('note', $cookieString);

    // Проверка наличия обязательных данных
    if (empty($phone) && empty($email)) {
        writeLog("Error: Neither phone nor email is provided.");
        echo json_encode(['status' => 'error', 'message' => 'Neither phone nor email is provided.']);
        exit;
    }

    // Ищем контакт по номеру телефона или email
    $contact = findContactByPhone($phone);
    if (!$contact && $email) {
        $contact = findContactByEmail($email);
    }
    
    // Если контакт найден, используем его ID, иначе создаем новый контакт
    if ($contact) {
        $contactId = $contact['id'];

        // Обновляем контакт, если visitor_id отсутствует
        if (empty($contact['custom_fields_values'])) {
            $contact['custom_fields_values'] = [];
        }
        $visitorIdFieldFound = false;
        foreach ($contact['custom_fields_values'] as &$field) {
            if ($field['field_id'] == 969159) { // ID поля visitor_id
                $visitorIdFieldFound = true;
                if (empty($field['values'][0]['value'])) {
                    $field['values'][0]['value'] = $visitorId;
                }
                break;
            }
        }
        if (!$visitorIdFieldFound) {
            $contact['custom_fields_values'][] = [
                'field_id' => 969159,
                'values' => [
                    ['value' => $visitorId]
                ]
            ];
        }
        updateContact($contactId, $contact);
    } else {
        $contactData = [
            'name' => $name,
            'custom_fields_values' => []
        ];
        if (!empty($phone)) {
            $contactData['custom_fields_values'][] = [
                'field_id' => 92667, // ID поля для телефона
                'values' => [
                    ['value' => $phone]
                ]
            ];
        }
        if (!empty($email)) {
            $contactData['custom_fields_values'][] = [
                'field_id' => 92669, // ID поля для email
                'values' => [
                    ['value' => $email]
                ]
            ];
        }
        if (!empty($visitorId)) {
            $contactData['custom_fields_values'][] = [
                'field_id' => 969159, // ID поля visitor_id
                'values' => [
                    ['value' => $visitorId]
                ]
            ];
        }
        $newContact = createContact($contactData);
        $contactId = $newContact['id'];
    }
    // Данные для создания сделки
    $dealData = [
        'name' => $leadName,
        'status_id' => $statusId,
        'pipeline_id' => $pipelineId,
        'price' => $price,
        'visitor_uid' => $visitorUid,
        'custom_fields_values' => []
    ];

    // Добавляем кастомные поля, если они есть
    $customFields = [
        'utm_content' => 92675,
        'utm_medium' => 92677,
        'utm_campaign' => 92679,
        'utm_source' => 92681,
        'utm_term' => 92683,
        'utm_referrer' => 92685,
        'roistat' => 92687,
        'referrer' => 92689,
        'openstat_service' => 92691,
        'openstat_campaign' => 92693,
        'openstat_ad' => 92695,
        'openstat_source' => 92697,
        'gclientid' => 92701,
        'ym_uid' => 92703,
        'ym_counter' => 92705,
        'gclid' => 92707,
        'yclid' => 92709,
        'fbclid' => 92711,
        'rb_clickid' => 973191,
        'from' => 92699,
        'tranid' => 971549,
        'formid' => 971551,
        'formname' => 973309
    ];

    foreach ($customFields as $field => $fieldId) {
        if (!empty($$field)) {
            $dealData['custom_fields_values'][] = [
                'field_id' => $fieldId,
                'values' => [
                    ['value' => $$field]
                ]
            ];
        }
    }
    // Создаем сделку с привязкой к контакту
    $deal = createDealWithContact($contactId, $dealData);

    // Логируем данные сделки и проверяем ID сделки
    if (isset($deal['id'])) {
        writeLog("Deal ID: " . $deal['id']);
    } else {
        writeLog("Deal ID not set");
    }

    // Добавляем примечание к сделке, если оно есть
    if (!empty($note) && isset($deal['id'])) {
        createNoteForDeal($deal['id'], $note);
    }

} catch (Exception $e) {
    writeLog($e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Данные успешно обработаны']);
?>

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

// Функция для получения значения из POST-запроса или куки
function getRequestValue($name, $cookieString) {
    return $_POST[$name] ?? getCookie($name, $cookieString);
}
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
    
    // Преобразуем номер к формату +71234567890
    $normalizedPhone = '+7' . substr($phone, -10);
    
    return $normalizedPhone;
}
// Функция для добавления кастомного поля
function addCustomField(&$fields, $fieldId, $value) {
    if (!empty($value)) {
        $fields[] = [
            'field_id' => $fieldId,
            'values' => [
                ['value' => $value]
            ]
        ];
    }
}

// Функция для поиска контакта по номеру телефона
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

// Функция для поиска контакта по email
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
// Функция для создания нового контакта
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

// Функция для обновления контакта
function updateContact($contactId, $data) {
    try {
        $response = amoCrmRequest('PATCH', "contacts/$contactId", [$data]);
        writeLog("Contact updated: " . json_encode($response));
        return $response;
    } catch (Exception $e) {
        // Логируем ошибки
        writeLog($e->getMessage());
        return null;
    }
}

// Функция для создания сделки с привязкой к контакту
function createDealWithContact($contactId, $dealData) {
    $dealData['_embedded'] = [
        'contacts' => [
            ['id' => $contactId]
        ]
    ];
    try {
        $response = amoCrmRequest('POST', 'leads/complex', [$dealData]);
        writeLog("Deal created: " . json_encode($response));
        if (!empty($response[0]['id'])) {
            return $response[0];
        }
        return null;
    } catch (Exception $e) {
        // Логируем ошибки
        writeLog($e->getMessage());
        return null;
    }
}

// Функция для создания примечания к сделке
function createNoteForDeal($dealId, $note) {
    $noteData = [
        [
            "note_type" => "common",
            "params" => [
                "text" => $note
            ]
        ]
    ];
    writeLog("Note Data: " . json_encode($noteData)); // Логируем данные примечания
    try {
        $response = amoCrmRequest('POST', "leads/$dealId/notes", $noteData);
        writeLog("Note created: " . json_encode($response));
        return $response;
    } catch (Exception $e) {
        // Логируем ошибки
        writeLog($e->getMessage());
        return null;
    }
}
// Основная логика
try {
    // Получение данных из POST-запроса
    $cookieString = $_POST['COOKIES'] ?? '';
    $contactName = getRequestValue('name', $cookieString);
    $phone = getRequestValue('phone', $cookieString);
    $email = getRequestValue('email', $cookieString);
    $status_id = (int)getRequestValue('status_id', $cookieString);
    $pipeline_id = (int)getRequestValue('pipeline_id', $cookieString);
    $lead_name = getRequestValue('lead_name', $cookieString);
    $price = (float)getRequestValue('price', $cookieString);
    $visitor_uid = getRequestValue('visitor_uid', $cookieString);
    $visitor_id = (int)getRequestValue('visitor_id', $cookieString);
    $note = getRequestValue('note', $cookieString);
    $ym_uid = getRequestValue('_ym_uid', $cookieString);
    $ym_counter = getRequestValue('_ym_counter', $cookieString);
    $gclientid = getRequestValue('gclientid', $cookieString);
    $referrer = getRequestValue('referrer', $cookieString);
    $roistat = getRequestValue('roistat', $cookieString);
    $utm_content = getRequestValue('utm_content', $cookieString);
    $utm_medium = getRequestValue('utm_medium', $cookieString);
    $utm_campaign = getRequestValue('utm_campaign', $cookieString);
    $utm_source = getRequestValue('utm_source', $cookieString);
    $utm_term = getRequestValue('utm_term', $cookieString);
    $utm_referrer = getRequestValue('utm_referrer', $cookieString);
    $openstat_service = getRequestValue('openstat_service', $cookieString);
    $openstat_campaign = getRequestValue('openstat_campaign', $cookieString);
    $openstat_ad = getRequestValue('openstat_ad', $cookieString);
    $openstat_source = getRequestValue('openstat_source', $cookieString);
    $gclid = getRequestValue('gclid', $cookieString);
    $yclid = getRequestValue('yclid', $cookieString);
    $fbclid = getRequestValue('fbclid', $cookieString);
    $rb_clickid = getRequestValue('rb_clickid', $cookieString);
    $from = getRequestValue('from', $cookieString);
    $tranid = getRequestValue('tranid', $cookieString);
    $formid = getRequestValue('formid', $cookieString);
    $formname = getRequestValue('formname', $cookieString);

    // Проверка наличия обязательных данных
    if (empty($phone) && empty($email)) {
        // Логируем ошибку
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
                    $field['values'][0]['value'] = $visitor_id;
                }
                break;
            }
        }
        if (!$visitorIdFieldFound) {
            $contact['custom_fields_values'][] = [
                'field_id' => 969159,
                'values' => [
                    ['value' => $visitor_id]
                ]
            ];
        }
        updateContact($contactId, $contact);
    } else {
        $contactData = [
            'name' => $contactName,
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
        if (!empty($visitor_id)) {
            $contactData['custom_fields_values'][] = [
                'field_id' => 969159, // ID поля visitor_id
                'values' => [
                    ['value' => $visitor_id]
                ]
            ];
        }
        $newContact = createContact($contactData);
        $contactId = $newContact['id'];
    }
    // Данные для создания сделки
    $dealData = [
        'custom_fields_values' => []
    ];
    
    if (!empty($lead_name)) {
        $dealData['name'] = $lead_name;
    }
    if (!empty($status_id)) {
        $dealData['status_id'] = $status_id;
    }
    if (!empty($pipeline_id)) {
        $dealData['pipeline_id'] = $pipeline_id;
    }
    if (!empty($price)) {
        $dealData['price'] = $price;
    }
    if (!empty($visitor_uid)) {
        $dealData['visitor_uid'] = $visitor_uid; // Добавляем visitor_uid в специальное поле
    }

    // Добавляем остальные кастомные поля, если они есть
    addCustomField($dealData['custom_fields_values'], 92703, $ym_uid);
    addCustomField($dealData['custom_fields_values'], 92705, $ym_counter);
    addCustomField($dealData['custom_fields_values'], 92701, $gclientid);
    addCustomField($dealData['custom_fields_values'], 92689, $referrer);
    addCustomField($dealData['custom_fields_values'], 92687, $roistat);
    addCustomField($dealData['custom_fields_values'], 92675, $utm_content);
    addCustomField($dealData['custom_fields_values'], 92677, $utm_medium);
    addCustomField($dealData['custom_fields_values'], 92679, $utm_campaign);
    addCustomField($dealData['custom_fields_values'], 92681, $utm_source);
    addCustomField($dealData['custom_fields_values'], 92683, $utm_term);
    addCustomField($dealData['custom_fields_values'], 92685, $utm_referrer);
    addCustomField($dealData['custom_fields_values'], 92691, $openstat_service);
    addCustomField($dealData['custom_fields_values'], 92693, $openstat_campaign);
    addCustomField($dealData['custom_fields_values'], 92695, $openstat_ad);
    addCustomField($dealData['custom_fields_values'], 92697, $openstat_source);
    addCustomField($dealData['custom_fields_values'], 92707, $gclid);
    addCustomField($dealData['custom_fields_values'], 92709, $yclid);
    addCustomField($dealData['custom_fields_values'], 92711, $fbclid);
    addCustomField($dealData['custom_fields_values'], 973191, $rb_clickid);
    addCustomField($dealData['custom_fields_values'], 92699, $from);
    addCustomField($dealData['custom_fields_values'], 971549, $tranid);
    addCustomField($dealData['custom_fields_values'], 971551, $formid);
    addCustomField($dealData['custom_fields_values'], 973309, $formname);
    // Создаем сделку с привязкой к контакту
    $deal = createDealWithContact($contactId, $dealData);

    // Логируем данные сделки
    writeLog("Deal Data: " . json_encode($dealData));

    // Проверяем наличие ID сделки перед добавлением примечания
    if ($deal && !empty($deal['id'])) {
        // Добавляем примечание к сделке, если оно есть
        if (!empty($note)) {
            writeLog("Attempting to add note to deal ID: " . $deal['id']);
            createNoteForDeal($deal['id'], $note);
        }
    } else {
        writeLog("Error: Deal ID is missing or deal creation failed.");
    }

} catch (Exception $e) {
    // Логируем ошибки
    writeLog($e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Данные успешно обработаны']);
?>

<?php

function getAccessToken() {
    // Ваш токен доступа
    return 'your_access_token';
}

function getSubdomain() {
    // Ваш субдомен amoCRM
    return 'your_subdomain';
}

function createLead($name, $phone, $email, $visitor_uid, $metrics) {
    $access_token = getAccessToken();
    $subdomain = getSubdomain();

    // Заголовки для запроса
    $headers = [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ];

    // URL для создания сделки
    $create_lead_url = 'https://' . $subdomain . '.amocrm.ru/api/v4/leads';

    // Данные для создания сделки
    $data = [
        'name' => $name,
        'status_id' => 142, // ID этапа воронки продаж
        'custom_fields_values' => [
            [
                'field_id' => 'ID_поля_visitor_uid', // Замените на ID вашего пользовательского поля для visitor_uid
                'values' => [
                    ['value' => $visitor_uid]
                ]
            ]
        ]
    ];

    // Добавляем метки статистики, если они переданы
    foreach ($metrics as $field_code => $value) {
        if (!empty($value)) {
            $data['custom_fields_values'][] = [
                'field_code' => $field_code,
                'values' => [
                    ['value' => $value]
                ]
            ];
        }
    }

    // Вложенные контакты
    $contact_data = [];
    if ($phone) {
        $contact_data[] = [
            'field_code' => 'PHONE',
            'values' => [
                ['value' => $phone, 'enum_code' => 'WORK'] // Замените на нужный enum_code для телефона
            ]
        ];
    }
    if ($email) {
        $contact_data[] = [
            'field_code' => 'EMAIL',
            'values' => [
                ['value' => $email, 'enum_code' => 'WORK'] // Замените на нужный enum_code для email
            ]
        ];
    }

    $data['_embedded'] = [
        'contacts' => [
            [
                'name' => $name,
                'custom_fields_values' => $contact_data
            ]
        ]
    ];

    // Выполнение запроса на создание сделки
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $create_lead_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);

    return json_decode($response, true);
}

// Пример использования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $visitor_uid = $_POST['amo_visitor_uid'] ?? '';

    // Метки систем статистики
    $metrics = [
        'utm_content' => $_POST['utm_content'] ?? '',
        'utm_medium' => $_POST['utm_medium'] ?? '',
        'utm_campaign' => $_POST['utm_campaign'] ?? '',
        'utm_source' => $_POST['utm_source'] ?? '',
        'utm_term' => $_POST['utm_term'] ?? '',
        'utm_referrer' => $_POST['utm_referrer'] ?? '',
        'roistat' => $_POST['roistat'] ?? '',
        'referrer' => $_POST['referrer'] ?? '',
        'openstat_service' => $_POST['openstat_service'] ?? '',
        'openstat_campaign' => $_POST['openstat_campaign'] ?? '',
        'openstat_ad' => $_POST['openstat_ad'] ?? '',
        'openstat_source' => $_POST['openstat_source'] ?? '',
        'from' => $_POST['from'] ?? '',
        'gclientid' => $_POST['gclientid'] ?? '',
        '_ym_uid' => $_POST['_ym_uid'] ?? '',
        '_ym_counter' => $_POST['_ym_counter'] ?? '',
        'gclid' => $_POST['gclid'] ?? '',
        'yclid' => $_POST['yclid'] ?? '',
        'fbclid' => $_POST['fbclid'] ?? ''
    ];

    if ($phone || $email) {
        $lead = createLead($name, $phone, $email, $visitor_uid, $metrics);
        echo json_encode($lead);
    } else {
        echo json_encode(['error' => 'Необходимо заполнить хотя бы одно поле: телефон или email']);
    }
} else {
    echo json_encode(['error' => 'Неверный метод запроса']);
}

?>

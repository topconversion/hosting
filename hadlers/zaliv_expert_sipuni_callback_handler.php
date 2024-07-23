<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'];
    $phone = $_POST['phone'];
    $callAttemptTime = $_POST['callAttemptTime'];
    $reverse = $_POST['reverse'];
    $sipnumber = $_POST['sipnumber'];
    $tree = $_POST['tree'];
    $secret = $_POST['secret'];

    $hashString = join('+', array($callAttemptTime, $phone, $reverse, $sipnumber, $tree, $user, $secret));
    $hash = md5($hashString);

    $url = 'https://sipuni.com/api/callback/call_tree';
    $query = http_build_query(array(
        'callAttemptTime' => $callAttemptTime,
        'phone' => $phone,
        'reverse' => $reverse,
        'sipnumber' => $sipnumber,
        'tree' => $tree,
        'user' => $user,
        'hash' => $hash
    ));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);

    echo $output;
} else {
    echo "Invalid request method.";
}
?>

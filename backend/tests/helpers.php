<?php

function request($method, $url, $body = null, $token = null) {
    $headers = "Accept: application/json\r\n";
    $content = '';
    if ($token !== null) {
        $headers .= "Authorization: Bearer {$token}\r\n";
    }
    if ($body !== null) {
        $headers .= "Content-Type: application/json\r\n";
        $content = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headers,
        'content' => $content,
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    $raw = file_get_contents($url, false, $context);
    if ($raw === false) {
        throw new RuntimeException("Request failed: {$method} {$url}");
    }
    $statusLine = $http_response_header[0];
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);
    $status = (int)$matches[1];
    return [$status, json_decode($raw, true), $raw];
}

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function registerUser($baseUrl, $skills = [1, 2]) {
    $email = 'smoke-' . bin2hex(random_bytes(8)) . '@example.com';
    [$status, $result] = request('POST', $baseUrl . '/auth/register', [
        'email' => $email,
        'password' => 'correct-horse-123',
        'display_name' => 'Smoke User',
        'faculty' => 'Test',
        'major' => 'Test',
        'year' => 3,
        'line_id' => 'smoke.test',
        'skill_ids' => $skills,
    ]);
    check($status === 201, 'Register did not return 201: ' . json_encode($result));
    return $result;
}

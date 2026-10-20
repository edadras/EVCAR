<?php

// سرور آزمایشی Messages API برای تست دستیار پشتیبانی (php -S ... fake-anthropic.php).
// بر اساس آخرین پیام کاربر تصمیم می‌گیرد: ابزار حساب، ثبت تیکت، امتناع یا پاسخ پایانی.
$body = file_get_contents('php://input');
$dir = getenv('FAKE_ANTHROPIC_DIR') ?: sys_get_temp_dir();
file_put_contents($dir.'/fake-anthropic-last.json', $body);
$req = json_decode($body, true) ?: [];
$messages = $req['messages'] ?? [];
$last = end($messages);
$firstUserText = '';
foreach (array_reverse($messages) as $m) {
    if ($m['role'] === 'user' && is_string($m['content'])) {
        $firstUserText = $m['content'];
        break;
    }
}
$isToolResult = is_array($last['content'] ?? null) && (($last['content'][0]['type'] ?? '') === 'tool_result');
header('Content-Type: application/json');
$msg = fn ($stop, $content) => json_encode(['id' => 'msg_'.uniqid(), 'type' => 'message', 'role' => 'assistant', 'model' => $req['model'] ?? 'x',
    'stop_reason' => $stop, 'stop_sequence' => null, 'content' => $content, 'usage' => ['input_tokens' => 120, 'output_tokens' => 30]]);

if (str_contains($firstUserText, 'ممنوع')) {
    echo $msg('refusal', []);
} elseif ($isToolResult) {
    $result = $last['content'][0]['content'] ?? '';
    echo $msg('end_turn', [['type' => 'text', 'text' => 'نتیجه: '.mb_substr(is_string($result) ? $result : json_encode($result), 0, 300)]]);
} elseif (str_contains($firstUserText, 'کارشناس')) {
    echo $msg('tool_use', [
        ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-a'],
        ['type' => 'tool_use', 'id' => 'toolu_t1', 'name' => 'create_support_ticket', 'input' => [
            'category' => 'payment', 'priority' => 'high', 'subject' => 'کسر اشتباه هزینه', 'summary' => 'مشتری می‌گوید هزینهٔ شارژ دوبار کسر شده.', 'session_reference' => null,
        ]],
    ]);
} elseif (str_contains($firstUserText, 'موجودی')) {
    echo $msg('tool_use', [
        ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-b'],
        ['type' => 'text', 'text' => 'بررسی می‌کنم.'],
        ['type' => 'tool_use', 'id' => 'toolu_a1', 'name' => 'get_account_summary', 'input' => new stdClass],
    ]);
} else {
    echo $msg('end_turn', [['type' => 'text', 'text' => 'سلام! چطور کمکتان کنم؟']]);
}

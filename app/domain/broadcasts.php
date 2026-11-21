<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/staff.php';

function broadcast_list(): array
{
    require_module('fashion.communication');
    require_permission('messages.send');
    $rows = db_query(
        'SELECT id, title, body, audience, recipient_count, created_at
         FROM fashion_broadcasts
         WHERE tenant_id = :tenant_id
         ORDER BY created_at DESC
         LIMIT 50'
    );
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = [
                'id' => (string) $row['id'],
                'title' => (string) $row['title'],
                'body' => (string) $row['body'],
                'audience' => (string) $row['audience'],
                'recipient_count' => (int) $row['recipient_count'],
                'created_at' => (string) $row['created_at'],
            ];
        }
    }
    return $out;
}

function broadcast_create(array $input): array
{
    require_module('fashion.communication');
    require_permission('messages.send');
    $title = trim((string) ($input['title'] ?? ''));
    $body = trim((string) ($input['body'] ?? ''));
    $errors = [];
    if ($title === '' || strlen($title) > 190) {
        $errors['title'] = 'required';
    }
    if ($body === '' || strlen($body) > 4000) {
        $errors['body'] = 'required';
    }
    $userId = current_user_id();
    if ($userId === null) {
        $errors['form'] = 'not_allowed';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $recipients = db_query(
        'SELECT COUNT(*) AS total FROM fashion_customers
         WHERE tenant_id = :tenant_id AND messages_opt_in = 1'
    );
    $count = (int) ($recipients[0]['total'] ?? 0);
    $id = db_insert('fashion_broadcasts', [
        'title' => $title,
        'body' => $body,
        'audience' => 'opt_in',
        'recipient_count' => $count,
        'created_by' => $userId,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    return ['broadcast_id' => $id, 'recipient_count' => $count];
}

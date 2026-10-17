<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/stock.php';

function stock_index(): void
{
    require_module('fashion.inventory');
    $search = trim((string) ($_GET['q'] ?? ''));
    $category = (string) ($_GET['category'] ?? '');
    if (!in_array($category, stock_categories(), true)) {
        $category = '';
    }
    $page = list_page($_GET['page'] ?? '1');
    $list = stock_list($search, $category, $page);
    render('stock/index', [
        'title' => t('stock'),
        'items' => $list['items'],
        'low_items' => stock_low_items(),
        'search' => $search,
        'category' => $category,
        'page' => $list['page'],
        'has_previous' => $list['has_previous'],
        'has_next' => $list['has_next'],
        'previous_path' => stock_page_path($search, $category, $page - 1),
        'next_path' => stock_page_path($search, $category, $page + 1),
    ]);
}

function stock_form(array $errors = [], array $old = []): void
{
    require_module('fashion.inventory');
    render('stock/form', [
        'title' => t('new_stock_item'),
        'errors' => $errors,
        'old' => $old,
    ]);
}

function stock_save(): void
{
    require_module('fashion.inventory');
    csrf_check();
    $input = [
        'name' => (string) ($_POST['name'] ?? ''),
        'category' => (string) ($_POST['category'] ?? ''),
        'unit' => (string) ($_POST['unit'] ?? ''),
        'colour' => (string) ($_POST['colour'] ?? ''),
        'minimum' => (string) ($_POST['minimum'] ?? ''),
    ];
    $result = stock_create($input);
    if (isset($result['error'])) {
        stock_form([$result['error'] => $result['error']], $input);
        return;
    }
    redirect('/stock/' . $result['id']);
}

function stock_show(string $id): void
{
    require_module('fashion.inventory');
    $item = stock_find($id);
    if ($item === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $page = list_page($_GET['page'] ?? '1');
    $history = stock_history($id, $page);
    render('stock/show', [
        'title' => $item['name'],
        'item' => $item,
        'rows' => $history['rows'],
        'page' => $history['page'],
        'has_previous' => $history['has_previous'],
        'has_next' => $history['has_next'],
    ]);
}

function stock_move_submit(string $id): void
{
    require_module('fashion.inventory');
    csrf_check();
    $result = stock_adjust($id, [
        'step' => (string) ($_POST['step'] ?? ''),
        'amount' => (string) ($_POST['amount'] ?? ''),
        'reason' => (string) ($_POST['reason'] ?? ''),
        'note' => (string) ($_POST['note'] ?? ''),
        'order_id' => (string) ($_POST['order_id'] ?? ''),
    ]);
    $back = (string) ($_POST['back'] ?? '');
    if ($back !== '/stock' && $back !== '/stock/' . $id) {
        $back = '/stock/' . $id;
    }
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('saved'));
    }
    redirect($back);
}

function stock_page_path(string $search, string $category, int $page): string
{
    $query = ['page' => (string) max(1, $page)];
    if ($search !== '') {
        $query['q'] = $search;
    }
    if ($category !== '') {
        $query['category'] = $category;
    }
    return '/stock?' . http_build_query($query);
}

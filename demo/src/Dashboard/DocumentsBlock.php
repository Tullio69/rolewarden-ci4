<?php

declare(strict_types=1);

namespace RoleWardenDemo\Dashboard;

use RoleWarden\Dashboard\Block;
use RoleWardenDemo\Models\DocumentModel;

/**
 * The demo's dashboard block, the model to copy for one of your own: the
 * documents by status and those written in the dashboard's period, for
 * whoever may read them.
 */
final class DocumentsBlock extends Block
{
    public function key(): string
    {
        return 'demo-documents';
    }

    public function title(): string
    {
        return lang('Demo.dashboard.title');
    }

    public function permission(): string
    {
        return 'documents.view';
    }

    public function view(): string
    {
        return 'RoleWardenDemo\Views\dashboard\documents';
    }

    public function data(array $period, int $userId): array
    {
        // A super admin passes every check, even when demo:install has not made the table.
        if (! db_connect()->tableExists(config('Demo')->table)) {
            return ['statuses' => [], 'written' => 0];
        }

        $model = new DocumentModel();
        $statuses = array_fill_keys(DocumentModel::STATUSES, 0);

        foreach ($model->select('status, COUNT(*) AS n')->groupBy('status')->get()->getResultArray() as $row) {
            if (isset($statuses[$row['status']])) {
                $statuses[$row['status']] = (int) $row['n'];
            }
        }

        return [
            'statuses' => $statuses,
            'written' => $model->where('created_at >=', $period['from'])->where('created_at <=', $period['to'])->countAllResults(),
        ];
    }
}

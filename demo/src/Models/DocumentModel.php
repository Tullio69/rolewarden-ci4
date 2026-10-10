<?php

declare(strict_types=1);

namespace RoleWardenDemo\Models;

use CodeIgniter\Model;

class DocumentModel extends Model
{
    public const STATUSES = ['draft', 'submitted', 'approved'];

    protected $returnType = 'array';
    protected $allowedFields = ['title', 'body', 'status', 'owner_id', 'approved_by', 'approved_at'];
    protected $useTimestamps = true;

    protected function initialize(): void
    {
        $this->table = config('Demo')->table;
    }

    /**
     * Every document with its owner's and approver's names, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function listing(): array
    {
        $users = config('Auth')->tables['users'];

        return $this->select("{$this->table}.*, o.username AS owner_name, a.username AS approver_name")
            ->join("{$users} o", "o.id = {$this->table}.owner_id", 'left')
            ->join("{$users} a", "a.id = {$this->table}.approved_by", 'left')
            ->orderBy("{$this->table}.updated_at", 'desc')
            ->findAll();
    }
}

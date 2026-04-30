<?php

namespace App\Services;

use App\Repositories\ConditionRepository;

class ConditionService
{
    public function __construct(private ConditionRepository $repository)
    {
    }

    public function getAllConditions(): array
    {
        return $this->repository->findAll();
    }
}
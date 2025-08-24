<?php

namespace App\Repository;

use App\Entity\Mission;

interface MissionRepositoryInterface
{
    public function search($query, $limit = null, array $options = array()): array;
    public function findOne($query, array $options = array()): ?Mission;
}

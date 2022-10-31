<?php

namespace Surin\Test\Data;

interface WriteableDatabaseInterface
{
    public function Insert(string $statement, array $params): ?int;

    public function Update(string $statement, array $params): int;

    public function UpdateWithReturning(string $statement, array $params): array;

    public function Delete(string $statement, array $values): int;

    public function DeleteReturning(string $statement, array $params): array;

    public function nextID(string $value): int;

}
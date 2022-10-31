<?php

namespace Surin\Test\Data;

interface ReadableDatabaseInterface
{
    public function getAllRows(string $statement, array $params = []): array;

    public function getFieldTypes(string $statement, array $params = []): array;

    public function getFirstRow(string $statement, array $params): ?array;

    public function prepareAndExecute(string $statement, array $params);

    public function fetchOne(string $statement, array $params): ?string;

    public function execAndFetchOnIndex(string $statement, array $params, string $idxName): array;

    public function rekeyFetch($statement, $params): array;

}
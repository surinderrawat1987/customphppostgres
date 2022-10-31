<?php

namespace Surin\Test\Data;

interface BasicDatabaseInterface
{
    public function convertStatement(string $statement, array $values = []);

    public function convertArray(string &$statement, array &$params): void;

    public function convertArrayToValues(string &$statement, array $paramNames, array &$params): void;

    public function rawExecWithoutParams(string $statement): void;

    public function Quote(string $s): string;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    public function silentRollback(): void;

    public function inTransaction(): bool;

    public function prepareValue($value, string $fieldType): string;

    public function startSavePoint(string $savePointName): void;

    public function rollbackSavePoint(string $savePointName): void;

    public function releaseSavePoint(string $savePointName): void;
}
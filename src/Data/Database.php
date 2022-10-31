<?php

namespace Surin\Test\Data;

/**
 * DataModule base class
 *
 */
class Database implements BasicDatabaseInterface, ReadableDatabaseInterface, WriteableDatabaseInterface
{
    /**
     * @const string
     */
    private const MASSINSERT_PARAMETER = 'Parameter';
    /**
     * @const string
     */
    public const MASSINSERT_FIXED = 'Fixed';

    private bool $internalTransactionStatus;
    /**
     * The PostgreSQL database handle
     *
     * @var Resource
     */
    private string $dsn;
    private $conn;
    private array $statementCache = [];
    private int $statementCacheCounter = 0;
    public int $statementCacheHit = 0;

    /**
     * constructor: Get and Set the database object
     * @param string $dsn
     */
    public function __construct(string $dsn)
    {
        $this->dsn = $dsn;
        if ($dsn == null) {
            die('No database connection string specified');
        }

        /*
         * If we are already initialized to false (eg: database connection failure), do not try
         * to connect again and do not log again as that would end us in an endless loop
         */
        if ($this->conn === false) {
            return;
        }

        $this->tryConnectDatabase();
    }


    private function tryConnectDatabase(): void {
        if (!isset($this->dsn)) {
            // property is somehow not initialized, perhaps old version of serializiation
            if (defined('MAINDB_DSN')) {
                $this->dsn = MAINDB_DSN;
            } else {
                throw new InvalidArgumentException('Unable to instantiate database connection, DSN is missing');
            }
        }

        // try to connect
        $this->conn = pg_connect($this->dsn);

        if ($this->conn === false) {
            die('Unable to connect to database');
        }

        $this->internalTransactionStatus = false;
    }


    public function __sleep(): array {
        return ['dsn'];
    }

    public function __wakeup()
    {
        $this->tryConnectDatabase();
    }

    /**
     * Converts an SQL statement (string) to a well-formed statement which can be
     * used by the PostgreSQL database layer. Basically it converts named parameters
     * to numeric indexes usable by PostgreSQL's direct database layer
     *
     * @param $statement
     * @param array $values
     * @throws Exception
     * @return mixed
     */
    public function convertStatement(string $statement, array $values = [])
    {
        if (isset($this->statementCache[$statement])) {
            $this->statementCacheHit++;
            return $this->statementCache[$statement];
        }

        /*
         * Debugging code, this tries to match up the parameters
         * with the named parameters in the query. Currently
         * used as an migration tool
         */
        if (defined('DEBUG_SQL')) {
            if (!empty($values)) {
                if (strpos($statement, '/*') !== false) {
                    preg_match('!/\*.*?\*/!s', $statement, $queryMatches);
                    $queryName = trim($queryMatches[0]);
                } else {
                    $queryName = '[UNKNOWN]';
                }

                $valuesNum = array_keys($values);
                preg_match_all('/[^:](:\w+)|(\?)/', $statement, $matches);

                $valCounter = 0;
                foreach ($matches[1] as $match) {
                    if (strpos($match, '?') !== 0) {
                        if ($valuesNum[$valCounter] !== substr($match, 1)) {
                            throw new \RuntimeException('Invalid order of parameters in query: ' . $queryName . ': ' . $valuesNum[$valCounter] . ' vs ' . substr($match, 1));
                        }
                    }

                    $valCounter++;
                }
            }
        }

        if (strpos($statement, ':') !== false) {
            $oldStatement = $statement;
            $paramRegEx = '/([^:])(:[A-Za-z]\w+)|(\?)/';

            $sqlCounter = 1;
            $statement = preg_replace_callback($paramRegEx, static function ($match) use (&$sqlCounter) {

                return $match[1] . '$' . ($sqlCounter++);

            }, $statement);

            if ($this->statementCacheCounter > 250) {
                $this->statementCache = [];
            }
            $this->statementCache[$oldStatement] = $statement;
            $this->statementCacheCounter++;
        }

        return $statement;
    }

    /**
     * Helper method to allow us to easy add arrays without manually inserting SQL
     *
     * @param $statement
     * @param $params
     */
    public function convertArray(string &$statement, array &$params): void
    {
        $newParams = array();

        foreach($params as $idx => $val) {

            if (is_array($val)) {
                /*
                 * Make sure the parameter-keys (even though we assume we make them ourselves), will never
                 * contain actual parameters, as it could theoreitcally be user input
                 */
                $escapedIdx = preg_replace('/[^A-Za-z0-9_]/', '', $idx);
                $paramList = array();

                $i = 0;
                foreach($val as $valVal) {
                    $escapedValIdx = $escapedIdx . '_' . $i++;

                    $paramList[] = ':' . $escapedValIdx;
                    $newParams[$escapedValIdx] = $valVal;
                }

                $statement = str_replace(':' . $escapedIdx, implode(',', $paramList), $statement);
            } else {
                $newParams[$idx] = $val;
            }
        }

        $params = $newParams;
    }

    /**
     * Helper method to allow us to easy add arrays without manually inserting SQL
     *
     * @param string $statement
     * @param array $paramNames
     * @param array $params
     */
    public function convertArrayToValues(string &$statement, array $paramNames, array &$params): void
    {
        $newParams = array();

        foreach($params as $idx => $val) {
            if (array_key_exists($idx, $paramNames)) {
                if (is_array($val)) {
                    /*
                     * Make sure the parameter-keys (even though we assume we make them ourselves), will never
                     * contain actual parameters, as it could theoreitcally be user input
                     */
                    $escapedIdx = preg_replace('/[^A-Za-z0-9_]/', '', $idx);

                    $paramList = [];
                    if ($paramNames[$idx] == 'int') {
                        foreach ($val as $valIdx => $valVal) {
                            $paramList[] = (int) $valVal;
                        }
                    } else {
                        foreach ($val as $valIdx => $valVal) {
                            $paramList[] = pg_escape_literal($this->conn, $valVal);
                        }
                    }

                    $statement = str_replace(':' . $escapedIdx, 'VALUES (' . implode('), (', $paramList) . ')', $statement);
                } else {
                    $newParams[$idx] = $val;
                }
            } else {
                echo 'Param not found: ' . $val . PHP_EOL;
                $newParams[$idx] = $val;
            }
        }

        $params = $newParams;
    }

    /**
     * @param string $statement
     * @param string[] $fromWherePair
     * @return string
     */
    public function parameterizeIdentifierNames(string $statement, array $fromWherePair): string
    {
        foreach ($fromWherePair as $replaceFrom => $replaceBy) {
            $statement = str_replace(pg_escape_identifier($replaceFrom), pg_escape_identifier($replaceBy), $statement);
        }
        return $statement;
    }

    /**
     * getAllRows - returns all rows
     *
     * @param mixed $statement sql string
     * @param array $params
     * @throws DatabaseException
     * @return array return value
     */
    public function getAllRows(string $statement, array $params = []): array
    {
        $statement = $this->convertStatement($statement, $params);

        /*
         * Actually run the query, afterwards we check the result. We do this in multiple
         * calls to be able to get a more detailed error reporting from PostgreSQL
         */
        pg_send_query_params($this->conn, $statement, $params);
        $result = pg_get_result($this->conn);

        if ($result === false) {
            $lastError = pg_last_error($this->conn); // save the error we have as reason for not fetching a result set
            $this->silentRollback();
            throw new DatabaseException($lastError);
        }

        if (pg_result_error($result)) {
            $this->silentRollback();
            throw new DatabaseException(pg_result_error($result));
        } //if

        $allRows = pg_fetch_all($result);
        pg_free_result($result);
        if ($allRows === false) {
            return [];
        }

        return $allRows;
    }

    /**
     * A rather crude function to get the schema of a specific statement
     *
     * @param $statement
     * @param array $params
     */
    public function getFieldTypes(string $statement, array $params = []): array
    {
        $fieldTypes = array();

        pg_send_query_params($this->conn, $statement, $params);
        $result = pg_get_result($this->conn);

        $fieldCount = pg_num_fields($result);

        for ($i = 0; $i < $fieldCount; $i++) {
            $fieldTypes[pg_field_name($result, $i)] = pg_field_type($result, $i);
        }

        pg_free_result($result);

        return $fieldTypes;
    }

    /**
     * getFirstRows - returns first row
     *
     * @param mixed $statement sql string
     * @param mixed $params array of parameter values
     * @throws DatabaseException
     * @return mixed return value
     */
    public function getFirstRow(string $statement, array $params): ?array
    {
        $statement = $this->convertStatement($statement, $params);

        /*
         * Actually run the query, afterwards we check the result. We do this in multiple
         * calls to be able to get a more detailed error reporting from PostgreSQL
         */
        pg_send_query_params($this->conn, $statement, $params);
        $result = pg_get_result($this->conn);

        if ($result === false) {
            $lastError = pg_last_error($this->conn); // save the error we have as reason for not fetching a result set
            $this->silentRollback();
            throw new DatabaseException($lastError);
        }

        if (pg_result_error($result)) {
            $this->silentRollback();
            throw new DatabaseException(pg_result_error($result));
        }

        $row = pg_fetch_assoc($result);
        pg_free_result($result);

        if ($row === false) {
            return null;
        }

        return $row;
    }

    /**
     * prepareAndExecute - prepares and executes statement
     *
     * @param mixed $statement sql string
     * @param mixed $params array of parameter values
     * @return mixed return value
     *
     */
    public function prepareAndExecute(string $statement, array $params)
    {
        echo $query = $this->prepare($statement);
        die;
        return $query->execute($params);
    }

    /**
     * @param string $insertPrologue
     * @param array $columnNames
     * @param array $valueList
     * @throws DatabaseException
     */
    public function massInsert(string $insertPrologue, array $columnNames, array $valueList, string $onConflictStmt): void
    {
        /* We need an explicit column-name list, because sometimes we want to insert fixed values, eg NOW() */
        /* We cannot send more than 65535 items per row, so we have to create chunks of inserts */

        $maxLinesPerInsert = floor(65000 / count($columnNames));
        $totalLineCount = count($valueList[array_key_first($columnNames)]);
        $iterations = ceil($totalLineCount / $maxLinesPerInsert);
        $rowCounter = 0;

        for($itCounter = 0; $itCounter < $iterations; $itCounter++) {
            $valueStr = '';
            $params = [];
            $paramIdxCounter = 0;

            for($i = 0; $i < $maxLinesPerInsert && $rowCounter < $totalLineCount; $i++) {
                $rowStr = '';

                foreach($columnNames as $columnName => $column) {
                    if ($column[0] === self::MASSINSERT_PARAMETER) {
                        $paramIdxCounter++;
                        $rowStr .= ', $' . $paramIdxCounter;
                        $colType = $column[2] ?? 'string';

                        /* If input is wrong for the massInsert */
                        if (!array_key_exists($rowCounter, $valueList[$columnName] ?? [])) {
                            throw new DatabaseException('Incorrect values provided to massInsert method - value for ' . $columnName . ' is missing for row ' . $rowCounter . ', cannot proceed');
                        }

                        if ($colType === 'bool') {
                            $params[$paramIdxCounter] = $valueList[$columnName][$rowCounter] ? 't' : 'f';
                        } elseif($colType === 'float') {
                            $params[$paramIdxCounter] = number_format($valueList[$columnName][$rowCounter], 4, '.', '');
                        } else {
                            $params[$paramIdxCounter] = $valueList[$columnName][$rowCounter];
                        }
                    } elseif ($column[0] === self::MASSINSERT_FIXED) {
                        $rowStr .= ', ' . $column[1];
                    } else {
                        throw new RuntimeException('Unable to determine what to do with column: ' . $columnName);
                    }
                }

                $valueStr .= '(' . substr($rowStr, 2) . '), ';
                $rowCounter++;
            }

            $this->Insert($insertPrologue.substr($valueStr, 0, -2) . $onConflictStmt, $params);
        }
    }

    /**
     * @param array $values
     * @return array
     */
    public function getParamsFromValuesForMassInsert(array $values): array
    {
        foreach (array_keys($values) as $value) {
            $params[$value] = [self::MASSINSERT_PARAMETER, $value];
        }
        return $params ?? [];
    }

    /**
     * @param \DateTime|NULL $values
     * @return string|NULL
     */
    public function getValueForDateField(?\DateTime $date): ?string
    {
        return empty($date) ? NULL : $date->format(DateTime::RFC3339_EXTENDED);
    }

    /**
     * Insert - executes a statement
     *
     * @param mixed $statement sql string
     * @param $params
     * @throws DatabaseException
     * @return int
     */
    public function Insert(string $statement, array $params): ?int
    {
        $statement = $this->convertStatement($statement, $params);

        /*
         * Actually run the query, afterwards we check the result. We do this in multiple
         * calls to be able to get a more detailed error reporting from PostgreSQL
         */
        pg_send_query_params($this->conn, $statement, $params);
        $result = pg_get_result($this->conn);

        // echo "<pre>";
        // print_r($result);
        // echo "</pre>";
        // die;

        if ($result === false) {
            $lastError = pg_last_error($this->conn); // save the error we have as reason for not fetching a result set
            $this->silentRollback();
            throw new DatabaseException($lastError);
        }

        if (pg_result_error($result)) {
            $this->silentRollback();
            throw new DatabaseException(pg_result_error($result));
        } //if

        /*
         * If this was an insert with the RETURNING clause, return the first
         * column from that resultset, else return a simple NULL
         */
        $row = pg_fetch_row($result);
        if ($row !== false) {
            return $row[0];
        }

        return null;
    }

    /**
     * Update - executes a statement
     *
     * @param mixed $statement sql string
     * @param $params
     * @throws DatabaseException
     *
     * @return int number of affected rows
     */
    public function Update(string $statement, array $params): int
    {
        return pg_affected_rows($this->updateInDb($statement, $params));
    }

    /**
     * @param string $statement
     * @param array $params
     * @return array
     */
    public function UpdateWithReturning(string $statement, array $params): array
    {
        $result = $this->updateInDb($statement, $params);
        $returnedRows = pg_fetch_all($result);
        pg_free_result($result);
        return empty($returnedRows) ? [] : $returnedRows;
    }

    /**
     * @param string $statement
     * @param array $params
     * @throws DatabaseException
     * @return resource
     */
    private function updateInDb(string $statement, array $params)
    {
        $statement = $this->convertStatement($statement, $params);

        /*
         * Actually run the query, afterwards we check the result. We do this in multiple
         * calls to be able to get a more detailed error reporting from PostgreSQL
         */
        pg_send_query_params($this->conn, $statement, $params);
        $result = pg_get_result($this->conn);

        if ($result === false) {
            $this->silentRollback();
            throw new DatabaseException(pg_last_error($this->conn));
        }

        if (pg_result_error($result)) {
            $this->silentRollback();
            throw new DatabaseException(pg_result_error($result));
        }

        return $result;
    }

    /**
     * Delete - executes a statement
     *
     * @param mixed $statement sql string
     * @param mixed $values array of parameter values
     *
     * @return int
     */
    public function Delete(string $statement, array $values): int
    {
        return $this->Update($statement, $values);
    }

    /**
     * @param string $statement
     * @param array $params
     * @return array
     * @throws DatabaseException
     * @throws Exception
     */
    public function DeleteReturning(string $statement, array $params): array
    {
        $statement = $this->convertStatement($statement, $params);

        /*
         * Actually run the query, afterwards we check the result. We do this in multiple
         * calls to be able to get a more detailed error reporting from PostgreSQL
         */
        pg_send_query_params($this->conn, $statement, $params);
        $result = pg_get_result($this->conn);

        if ($result === false) {
            $this->silentRollback();
            throw new DatabaseException(pg_last_error($this->conn));
        }

        if (pg_result_error($result)) {
            $this->silentRollback();
            throw new DatabaseException(pg_result_error($result));
        }

        $rows = pg_fetch_all($result);
        // returns false if none were deleted
        if ($rows) {
            return $rows;
        }

        return [];
    }

    /**
     * @param $value
     * @return mixed
     */
    public function nextID(string $value): int
    {
        return $this->getFirstRow('SELECT NEXTVAL(\'' . pg_escape_identifier($this->conn, $value . '_S') . '\') as nextval', [])['nextval'];
    }

    /**
     * Escapes a string and add double quotes
     *
     * @param string $identifier
     * @return string
     */
    public function escapeIdentifier(string $identifier): string
    {
        return pg_escape_identifier($identifier);
    }

    /**
     * @param $statement
     */
    public function rawExecWithoutParams(string $statement): void
    {
        /*
         * Actually run the query, afterwards we check the result. We do this in multiple
         * calls to be able to get a more detailed error reporting from PostgreSQL
         */
        pg_send_query($this->conn, $statement);
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($result = pg_get_result($this->conn)) {

            if (pg_result_error($result)) {
                $this->silentRollback();
                throw new DatabaseException(pg_result_error($result));
            }

            // fetch the actual result to clear any buffer within postgresql itself
            pg_fetch_assoc($result);
            pg_free_result($result);
        }

    }

    /**
     * @param $statement
     * @param $params
     * @throws DatabaseException
     * @return mixed
     */
    public function fetchOne(string $statement, array $params): ?string
    {
        $statement = $this->convertStatement($statement, $params);
        /*
         * Actually run the query, afterwards we check the result. We do this in multiple
         * calls to be able to get a more detailed error reporting from PostgreSQL
         */
        pg_send_query_params($this->conn, $statement, $params);
        $result = pg_get_result($this->conn);

        if ($result === false) {
            $this->silentRollback();
            throw new DatabaseException(pg_last_error($this->conn));
        }

        if (pg_result_error($result)) {
            $this->silentRollback();
            throw new DatabaseException(pg_result_error($result));
        } //if

        $row = pg_fetch_row($result);

        // if no rows are returned, return null
        if ($row === false) {
            return null;
        }

        // Make sure we do not get a single column row
        if (count($row) > 1) {
            throw new InvalidArgumentException('fetchOne should only be called for queries which return a single column');
        }

        return $row[0] ?? null;
    }

    /**
     * @param $s
     * @return mixed
     */
    public function Quote(string $s): string
    {
        return pg_escape_string($this->conn, $s);
    }

    /**
     * Returns an array with the index the first column, but the whole row
     * as its value
     * @param string $statement
     * @param array $params
     * @param string $idxName
     * @return array
     */
    public function execAndFetchOnIndex(string $statement, array $params, string $idxName): array
    {
        $statement = $this->convertStatement($statement, $params);

        /*
         * Actually run the query, afterwards we check the result. We do this in multiple
         * calls to be able to get a more detailed error reporting from PostgreSQL
         */
        pg_send_query_params($this->conn, $statement, $params);
        $result = pg_get_result($this->conn);

        if ($result === false) {
            $lastError = pg_last_error($this->conn); // save the error we have as reason for not fetching a result set
            $this->silentRollback();
            throw new DatabaseException($lastError);
        }

        if (pg_result_error($result)) {
            $this->silentRollback();
            throw new DatabaseException(pg_result_error($result));
        }

        $allRows = [];
        while($row = pg_fetch_row($result, null, PGSQL_ASSOC)) {
            $allRows[$row[$idxName]] = $row;
        }

        pg_free_result($result);

        if ($allRows === false) {
            return [];
        }

        return $allRows;
    }

    /**
     * Get the results where the first column is taken as the index, and
     * the second column as the value
     *
     * @param $statement
     * @param $params
     * @return mixed
     */
    public function rekeyFetch($statement, $params): array
    {
        $result = $this->getAllRows($statement, $params);
        if (empty($result)) {
            return [];
        }

        [$colName, $valName] = array_keys($result[0]);

        foreach ($result as $key => $row) {
            $result[$row[$colName]] = $row[$valName];
            unset($result[$key]);
        }
        return $result;
    }

    /**
     * Actually start a new transaction
     */
    public function beginTransaction(): void
    {
        if ($this->inTransaction()) {
            $this->silentRollback();
            throw new DatabaseException('Already in a transaction');
        }

        $this->internalTransactionStatus = true;
        pg_query($this->conn, 'BEGIN');
    }

    /**
     * Commit the current transaction
     */
    public function commit(): void
    {
        if (!$this->inTransaction()) {
            $this->silentRollback();
            throw new DatabaseException('Not currently in a transaction');
        }

        $this->internalTransactionStatus = false;

        pg_send_query($this->conn, 'COMMIT');
        $result = pg_get_result($this->conn);
        $statusStr = pg_result_status($result, PGSQL_STATUS_STRING);

        if ($statusStr != 'COMMIT') {
            $this->silentRollback();
            throw new DatabaseException('Unable to commit transaction: ' . $statusStr);
        }

        if (pg_result_error($result)) {
            $this->silentRollback();
            throw new DatabaseException(pg_result_error($result));
        } //if
    }

    /**
     * Rollback the current transaction
     */
    public function rollback(): void
    {
        if (!$this->inTransaction()) {
            throw new DatabaseException('Not currently in a transaction');
        }

        if (pg_connection_busy($this->conn)) {
            // eat the results, else a roll back mgiht fail
            pg_get_result($this->conn);
        }


        $this->internalTransactionStatus = false;
        pg_query($this->conn, 'ROLLBACK');
    }

    /**
     * Silent rollback, used during error processing to not keep triggering errors
     */
    public function silentRollback(): void
    {
        if ($this->inTransaction()) {
            $this->rollback();
        }
    }

    /*
     * Are we in a current transaction
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        // We do not use pg_transaction_status as it seems to be unreliable
        return $this->internalTransactionStatus;
    }

    public function prepareValue($value, string $fieldType): string
    {
        if ($value === null) {
            return 'NULL';
        }

        switch ($fieldType) {
            case 'bool'             :
                if ($value == 't') {
                    return 'TRUE';
                }
                return 'FALSE';

            case 'float4'           :
            case 'float8'           :
            case 'int2'             :
            case 'int4'             :
            case 'int8'             :
            case 'numeric'          :
                return $value + 0;  // use + 0 to make sure it is always injected

            case 'json'             :
            case 'jsonb'            :
            case 'date'             :
            case 'inet'             :
            case 'inet4'            :
            case 'inet6'            :
            case 'text'             :
            case 'bpchar'           :
            case 'char'             :
            case 'varchar'          :
            case 'timestamp'        :
            case 'timestamptz'      :
            case 'timetz'           :
            case 'uuid'             :
            case 'interval'         :
                return '\'' . $this->Quote($value) . '\'';

            default                 :
                throw new \RuntimeException('Unhandled field type: ' . $fieldType);
        }

    }

    /**
     * @param string $savePointName
     */
    public function startSavePoint(string $savePointName): void
    {
        pg_query($this->conn, 'SAVEPOINT ' . pg_escape_identifier($savePointName));
    }

    /**
     * @param string $savePointName
     */
    public function rollbackSavePoint(string $savePointName): void
    {
        pg_query($this->conn, 'ROLLBACK TO SAVEPOINT ' . pg_escape_identifier($savePointName));
    }

    /**
     * @param string $savePointName
     */
    public function releaseSavePoint(string $savePointName): void
    {
        pg_query($this->conn, 'RELEASE SAVEPOINT ' . pg_escape_identifier($savePointName));
    }

    /**
     * Escapes a string witout adding quotes
     *
     * @param int $columnName
     * @return string
     */
    public function escapeString(string $columnName): string
    {
        return pg_escape_string($this->conn, $columnName);
    }
}


<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use DateMalformedStringException;
use DateTime;
use Tabula17\Satelles\Omnia\Roga\Descriptor\ParamDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\ValueRequiredException;

/**
 * PDO::PARAM_BOOL (int)
 * Represents a boolean data type.
 * PDO::PARAM_NULL (int)
 * Represents the SQL NULL data type.
 * PDO::PARAM_INT (int)
 * Represents the SQL INTEGER data type.
 * PDO::PARAM_STR (int)
 * Represents the SQL CHAR, VARCHAR, or other string data type.
 * PDO::PARAM_STR_NATL (int)
 * Flag to denote a string uses the national character set. Available since PHP 7.2.0
 * PDO::PARAM_STR_CHAR (int)
 * Flag to denote a string uses the regular character set. Available since PHP 7.2.0
 * PDO::PARAM_LOB (int)
 * Represents the SQL large object data type.
 * PDO::PARAM_STMT (int)
 * Represents a recordset type. Not currently supported by any drivers.
 * PDO::PARAM_INPUT_OUTPUT (int)
 * Specifies that the parameter is an INOUT parameter for a stored procedure. You must bitwise-OR this value with an explicit PDO::PARAM_* data type.
 *  Common WHERE param
 *  <param>
 *      <name>customer_trx_id</name>
 *      <sqlexpression>eq</sqlexpression>
 *      <type>int</type>
 *  </param>
 *
 *  External Config JOIN param
 *  TODO: Generar el tipo JOINPARAM para procesarse por separado ya que se utiliza para generar joins entre distintos descriptores de consulta
 *  <param>
 *      <name>externalTrxId</name>
 *      <templateLiteral>:colname = :param</templateLiteral>
 *  </param>
 *
 *  WHERE param with formatter
 *  <param>
 *      <name>fecha_deposito_desde</name>
 *      <type>datestring</type>
 *      <format>d-m-Y</format>
 *      <sqlexpression>gte</sqlexpression>
 *  </param>
 *
 *  Composite param (template)
 *  <param>
 *      <name>fecha_vencimiento_hasta</name>
 *      <template>:colname + tl.DUE_DAYS</template>
 *      <type>datestring</type>
 *      <format>d-m-Y</format>
 *      <sqlexpression>lte</sqlexpression>
 *  </param>
 *
 *   useColExpression to use column expression as param reference in sqlexpression function or comparision
 *   <param>
 *       <name>desde</name>
 *       <required>1</required>
 *       <defaultValue>curdate</defaultValue>
 *       <type>datestring</type>
 *       <sqlexpression>gte</sqlexpression>
 *       <useColExpression>1</useColExpression>
 *   </param>
 *
 *  Procedure required param with default value
 *  <param>
 *      <name>desde</name>
 *      <required>1</required>
 *      <defaultValue>curdate</defaultValue>
 *      <type>datestring</type>
 *      <paramName>desde</paramName>
 *  </param>
 *
 *
 *  <column>
 *      <name>Colname</name>
 *      <param>
 *          <exists>1</exists>
 *          <cfg>Mp.material.humedad.medicion.Linea</cfg>
 *          <arguments>
 *              <externalJoinId>:colname</externalJoinId>
 *          </arguments>
 *      </param>
 *  </column>
 *
 */
class Param implements Paraminterface
{
    private(set) string $placeholder {
        get {
            return $this->placeholder;
        }
    }
    private(set) string $columnExpression;
    private mixed $value;
    private(set) bool $needColumnExpression;
    private(set) ?int $combined;
    /**
     * @var mixed|string
     */
    private(set) string $sqlexpression;
    private(set) bool $required;
    private(set) bool $bindable;
    private(set) bool $writeFilter = false;
    private(set) bool $onempty;
    private(set) bool $onnotempty;
    /**
     * @var mixed|null
     */
    private(set) mixed $defaultValue;
    private(set) bool $nullable;
    /**
     * @var mixed|string
     */
    private(set) mixed $type;
    /**
     * @var mixed|null
     */
    private(set) mixed $format;
    /**
     * @var mixed|null
     */
    private(set) mixed $paramName;
    /**
     * @var mixed|null
     */
    private(set) mixed $paramTemplate;
    /**
     * @var int|mixed
     */
    private(set) mixed $groupCondition;
    private(set) ?SelectStatement $subquery;
    private bool $subqueryAsArgument = false;

    /**
     * @throws ConfigException
     */
    public function __construct(
        private readonly ParamDescriptor $descriptor,
        private readonly Expression      $expression = new Expression(),
        public string                    $quote_identifier = '"',
        public string                    $quote_value = "'",
        public string                    $pre_procedure_variable = "" // @ en SQLServer
    )
    {
        if (!isset($descriptor['name'])) {
            //var_dump($descriptor);
            throw new ConfigException(ExceptionDefinitions::PARAM_WITHOUT_NAME->value);
        }
        $this->placeholder = ':' . $this->descriptor['name'];
        $this->sqlexpression = $this->descriptor['sqlexpression'] ?? 'eq';
        $this->needColumnExpression = isset($this->descriptor['usecolexpression']) && (bool)$this->descriptor['usecolexpression'] === true && !isset($this->descriptor['paramName']);
        $this->required = isset($this->descriptor['required']) && (int)$this->descriptor['required'] === 1;
        $this->defaultValue = $this->descriptor['defaultValue'] ?? null;
        $this->nullable = isset($this->descriptor['nullable']) && (int)$this->descriptor['nullable'] === 1;
        $this->type = $this->descriptor['type'] ?? 'string';
        $this->format = $this->descriptor['format'] ?? null;
        $this->paramName = $this->descriptor['paramName'] ?? null;
        $this->paramTemplate = $this->descriptor['paramTemplate'] ?? null;
        $this->combined = $this->descriptor['combined'] ?? null;
        $this->groupCondition = $this->descriptor['having'] ?? 0;
        $this->onempty = isset($this->descriptor['onempty']) && (int)$this->descriptor['onempty'] === 1;
        $this->onnotempty = isset($this->descriptor['onnotempty']) && (int)$this->descriptor['onnotempty'] === 1;
        $this->bindable = isset($this->descriptor['bindable']) && (bool)$this->descriptor['bindable'] === true;
        $this->writeFilter = isset($this->descriptor['writeFilter']) && (bool)$this->descriptor['writeFilter'] === true;
        if (isset($descriptor['subquery'])) {
            $this->subquery = new SelectStatement($descriptor['subquery']['descriptor'], $this->expression);
            $this->subquery->setValues($descriptor['subquery']['arguments'] ?? []);
            if (isset($descriptor['subqueryAsArgument'])) {
                $this->subqueryAsArgument = (bool)$descriptor['subqueryAsArgument'];
            }
        }
    }

    /**
     * @param mixed $value
     * @return Param
     */
    public function setValue(mixed $value): Param
    {
        $this->value = $value;
        return $this;
    }
    /**
     * @throws ValueRequiredException|DateMalformedStringException
     */
    public function getValue(): mixed
    {
        $value = $this->value ?? null;
        if ($value === null && $this->required) {
            if (isset($this->defaultValue)) {
                $value = $this->defaultValue;
            } else {
                if (!$this->nullable) {
                    throw new ValueRequiredException(sprintf(ExceptionDefinitions::PARAM_VALUE_EXPECTED->value, $this->placeholder));
                }
                $this->type = 'null';
                return null;
            }
        }
        if (is_array($value)) {
            $value = array_map(fn($v) => $this->setFormattedValue($v), $value);
        } else {
            $value = $this->setFormattedValue($value);
        }
        return $value;
    }

    /**
     * @return bool
     * @throws DateMalformedStringException
     * @throws ValueRequiredException
     */
    public function isValid(): bool
    {
        $value = $this->getValue();
        return $value !== null || $this->type === 'null';
    }

    /**
     * @throws DateMalformedStringException
     */
    private function setFormattedValue(mixed $value): mixed
    {

        $type = $this->descriptor['type'] ?? 'string';

        if (empty($value) && isset($this->descriptor['nullonempty'])) {
            $this->type = 'null';
            return null;
        }
        if ($value !== null) {
            if (DataTypes::fromName($type) === DataTypes::DATETIME || DataTypes::fromName($type) === DataTypes::DATE) {
                $format = $this->format ?? (DataTypes::fromName($type) === DataTypes::DATE ? 'd-m-Y' : 'd-m-Y H:i:s');
                $value = $value ? new DateTime($value)->format($format) : null;
            }
            if (DataTypes::fromName($type) === DataTypes::INT) {
                $value = (int)$value;
            }
            if (DataTypes::fromName($type) === DataTypes::BOOL) {
                $value = (bool)$value;
            }
            if (DataTypes::fromName($type) === DataTypes::NUMERIC) {
                $value = (float)$value;
                if ($this->format) {
                    // formato esperado 0,000.### | 0.000,#
                    $separators = [];
                    $decimals = 0;
                    if (str_contains($this->format, ',')) {
                        $separators[strpos($this->format, ',')] = ',';
                    }
                    if (str_contains($this->format, '.')) {
                        $separators[strpos($this->format, '.')] = '.';
                    }
                    sort($separators);

                    if (str_contains($this->format, '#')) {
                        $decimals = strlen(substr($this->format, strpos($this->format, '#') + 1));
                    }
                    $decimal_separator = array_shift($separators) ?? '';
                    $thousands_separator = array_shift($separators) ?? '';

                    $value = number_format($value, $decimals, $decimal_separator, $thousands_separator);
                }
            }
        }
        return $value;
    }

    public function setColumnExpression(string $columnExpression): Param
    {
        $this->columnExpression = $columnExpression;
        return $this;
    }

    public function getColumnExpression(): string
    {
        return $this->columnExpression;
    }

    private function quoteValue(string $value): string
    {
        if (!str_starts_with($this->quote_value, $value) && !str_ends_with($this->quote_value, $value)) {
            $value = $this->quote_value . $value . $this->quote_value;
        }
        return $value;
    }

    /**
     * @throws DateMalformedStringException
     * @throws ValueRequiredException
     */
    public function __toString(): string
    {
        $value = $this->getValue();
        if (empty($value) && DataTypes::fromName($this->type) !== DataTypes::BOOL) {
            return '';
        }
        if (is_array($value)) {
            $param = [];
            $i = 0;
            while ($i < count($value)) {
                $param[] = $this->placeholder . $i;
                $i++;
            }
        } else {
            $param = $this->placeholder;
        }
        //quotevalue

        if (!isset($this->columnExpression) && !isset($this->paramName)) {
            throw new ValueRequiredException(sprintf(ExceptionDefinitions::PARAM_COLUMN_NAME_EXPECTED->value, $this->placeholder));
        }
        $colExpression = $this->columnExpression ?? $this->paramName;

        if (isset($this->subquery)) {
            //$this->subqueriesForPath->setValues($this->descriptor['arguments'] ?? []);
            $colExpression = str_replace(':colname', $colExpression, $this->subquery);
            if (!$this->subqueryAsArgument) {
                $colExpression = '(' . $colExpression . ')';
            }
        }
        if (isset($this->sqlexpression) && !isset($this->paramName)) {
            $arguments = $this->descriptor['arguments'] ?? [];
            if (!$this->subqueryAsArgument) {
                array_unshift($arguments, $param);
            }
            array_unshift($arguments, $colExpression);
            $param = call_user_func_array(
                [$this->expression, $this->sqlexpression],
                $arguments
            );

        }
        if (isset($this->paramName)) {
            $param = $this->expression->eq($this->pre_procedure_variable . $this->paramName, $this->placeholder);
        }
        if (!empty($this->paramTemplate)) {
            $noQuotables = [
                DataTypes::BOOL,
                DataTypes::NULL,
                DataTypes::NUMERIC,
                DataTypes::EXPRESSION
            ];
            $quote = !in_array(DataTypes::fromName($this->type), $noQuotables, true);
            if (is_array($value)) {
                if ($quote) {
                    array_walk($value, fn($v, $k) => $this->quoteValue($v));
                }
                $value = implode(', ', $value);
            } else if ($quote) {
                $value = $this->quoteValue($value);
            }
            $template = $this->paramTemplate;
            $param = str_replace([":colname", ":param"], [$colExpression, $value], $template);
        }
        return $param;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;


use DateMalformedStringException;
use Stringable;
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
interface Paraminterface extends Stringable
{
    /**
     * @param mixed $value
     * @return Param
     */
    public function setValue(mixed $value): Param;

    /**
     * @throws ValueRequiredException|DateMalformedStringException
     */
    public function getValue(): mixed;

    public function getType(): string;

    /**
     * @return bool
     * @throws DateMalformedStringException
     * @throws ValueRequiredException
     */
    public function isValid(): bool;

    public function setColumnExpression(string $columnExpression): Param;

    public function getColumnExpression(): string;
}
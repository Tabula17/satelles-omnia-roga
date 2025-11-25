<?php

namespace Tabula17\Satelles\Omnia\Roga\Builder;

use Tabula17\Satelles\Omnia\Roga\Descriptor\ColumnDescriptor;
use Tabula17\Satelles\Omnia\Roga\Descriptor\OrderDescriptor;
use Tabula17\Satelles\Omnia\Roga\Exception\ConfigException;

/**
 *  Ejemplo de columna:
 *  <column>
 *      <name>Diametro</name> <!-- NOMBRE DE LA COLUMNA -->
 *      <alias>diametro</alias> <!-- ALIAS DE LA COLUMNA -->
 *      <type>int</type> <!-- TIPO DE DATO -->
 *      <visible>1</visible> <!-- Columna incluida en la consulta -->
 *      <sqlexpression>sum</sqlexpression> <!-- Función A APLICAR a la columna -->
 *      <arguments> <!-- Argumentos extras a aplicar en la función -->
 *           <argument>'PROPIO'</argument>
 *      </arguments>
 *      <excludecolname>1</excludecolname> <!-- Columna excluida al pasar argumentos a la función. No se utiliza el miembro "name" en este caso. Pensada para columnas generadas desde funciones personalizadas -->
 *      <grouped>1</grouped> <!-- Columna agrupada -->
 *      <literal>1</literal> <!-- Columna literal ( no se procesa, se utiliza el string pasado en "name" como expresión SQL. Recomendado en casos complejos donde no es necesario pasar argumentos por parámetros  ) -->
 *      <quoteliteral>1</quoteliteral> <!-- Columna literal ( no se procesa, se utiliza el string pasado en "name" y se encierra entre comillas) -->
 *      <param> <!-- Columna parametrizada, se procesas mediante la clase 'Param' y genera expresiones dentro del WHERE -->
 *          <name>cod_deposito</name>
 *          <type>int</type>
 *          <sqlexpression>eq</sqlexpression>
 *      </param>
 *      <order> <!-- Columna ordenada, se procesas mediante la clase 'Order' y genera expresiones dentro del ORDER BY -->
 *          <position>1</position>
 *          <direction>DESC</direction>
 *      </order>
 *  </column>
 *  Ejemplo de columna con subconsulta (Este ejemplo debe preprocesar la subconsulta):
 *  <column>
 *      <subqueriesForPath> <!-- Columna de subconsulta, procesa el descriptor de la subconsulta y lo devuelve como expresión SQL en la columna -->
 *          <cfg>Cp.maquina.parada.Motivo</cfg>
 *          <arguments> <!-- Argumentos extras a aplicar en la subconsulta -->
 *              <externalJoinGrupo>gmp.cod_grupo_motivos</externalJoinGrupo>
 *          </arguments>
 *      </subqueriesForPath>
 *      <alias>diametro</alias> <!-- ALIAS DE LA COLUMNA -->
 *      <visible>1</visible> <!-- Columna incluida en la consulta -->
 *      <sqlexpression>sum</sqlexpression> <!-- Función A APLICAR a la columna -->
 *      <arguments> <!-- Argumentos extras a aplicar en la función -->
 *          <argument>'PROPIO'</argument>
 *      </arguments>
 *      <grouped>1</grouped> <!-- Columna agrupada -->
 *      <param> <!-- Columna parametrizada, se procesas mediante la clase 'Param' y genera expresiones dentro del WHERE -->
 *          <name>cod_deposito</name>
 *          <type>int</type>
 *          <sqlexpression>eq</sqlexpression>
 *      </param>
 *      <order> <!-- Columna ordenada, se procesas mediante la clase 'Order' y genera expresiones dentro del ORDER BY -->
 *          <position>1</position>
 *          <direction>DESC</direction>
 *      </order>
 *   </column>
 *   Ejemplo de columna con template:
 *   <column>
 *      <name>Diametro</name> <!-- NOMBRE DE LA COLUMNA -->
 *      <template>TRUNC(:colname)</template>
 *      <alias>diametro</alias> <!-- ALIAS DE LA COLUMNA -->
 *      <visible>1</visible> <!-- Columna incluida en la consulta -->
 *      <sqlexpression>sum</sqlexpression> <!-- Función A APLICAR a la columna -->
 *      <arguments> <!-- Argumentos extras a aplicar en la función -->
 *           <argument>'PROPIO'</argument>
 *      </arguments>
 *      <excludecolname>1</excludecolname> <!-- Columna excluida al pasar argumentos a la función -->
 *      <grouped>1</grouped> <!-- Columna agrupada -->
 *      <param> <!-- Columna parametrizada, se procesas mediante la clase 'Param' y genera expresiones dentro del WHERE -->
 *           <name>cod_deposito</name>
 *           <type>int</type>
 *           <sqlexpression>eq</sqlexpression>
 *      </param>
 *      <order> <!-- Columna ordenada, se procesas mediante la clase 'Order' y genera expresiones dentro del ORDER BY -->
 *           <position>1</position>
 *           <direction>DESC</direction>
 *      </order>
 *    </column>
 */
class Column
{
    private(set) string $columnExpression;
    private(set) ?string $alias = null;
    private(set) bool $visible {
        get {
            return $this->visible;
        }
    }
    private(set) bool $grouped {
        get {
            return $this->grouped;
        }
    }
    private(set) ?OrderDescriptor $order {
        get {
            return $this->order;
        }
    }
    private(set) ?array $params {
        &get {
            return $this->params;
        }
    }
    private(set) ?array $conditions {
        &get {
            return $this->conditions;
        }
    }
    private(set) ?array $joinParams {
        &get {
            return $this->joinParams;
        }
    }
    private(set) ?array $joinConditions {
        &get {
            return $this->joinConditions;
        }
    }
    private(set) string $columnName;
    /**
     * @var mixed|null
     */
    private(set) string|array|null $tableAlias;
    private(set) ?StatementProcessorInterface $subquery;
    private(set) string $columnId;

    /**
     * @throws ConfigException
     */
    public function __construct(
        private readonly ColumnDescriptor $descriptor,
        private readonly Expression       $expression,
        public string                     $quote_identifier = '"'
    )
    {
        $this->visible = isset($descriptor['visible']) && (bool)$descriptor['visible'] === true;
        $this->grouped = isset($descriptor['grouped']) && (int)$descriptor['grouped'] === 1;
        $this->order = $descriptor['order'] ?? null;
        $this->tableAlias = $descriptor['tableAlias'] ?? null;
        $this->params = [];
        $this->conditions = [];
        $this->joinParams = [];
        $this->joinConditions = [];
        $this->columnId = uniqid(($this->tableAlias ?? 'column') . '::', false);
        if (isset($descriptor['subquery'])) {
            $this->subquery = new SelectStatement($descriptor['subquery']['descriptor'], $this->expression);
            $this->subquery->setValues($descriptor['subquery']['arguments'] ?? []);
        }
        $this->setParams()->setJoinConditions()->setConditions();
    }

    /**
     * @throws ConfigException
     */
    public function process(): Column
    {
        $this->processColumnExpression()
            ->processAlias();
        return $this;
    }

    public function getParam(string $placeholder): Param
    {
        return $this->params[$placeholder];
    }

    public function getJoinParam(string $placeholder): Param
    {
        return $this->joinParams[$placeholder];
    }

    /**
     * @throws ConfigException
     */
    private function setParams(): Column
    {
        if (isset($this->descriptor['params'])) {
            foreach ($this->descriptor['params'] as $param) {
                $param = new Param($param, $this->expression, $this->quote_identifier);
                $this->params[$param->placeholder] = $param;
            }
        }
        if (isset($this->subquery) && $this->subquery instanceof StatementProcessorInterface) {
            foreach ($this->subquery->getParams() as $placeholder => $param) {
                if (!isset($this->params[$placeholder])) {
                    $this->params[$placeholder] = $param;
                }
            }
        }
        return $this;
    }

    private function setJoinConditions(): Column
    {
        if (isset($this->descriptor['joinConditions'])) {
            foreach ($this->descriptor['joinConditions'] as $condition) {
                if (isset($condition['name'])) {
                   // echo 'JOIN PARAM ', $condition['name'], var_export($condition, true), PHP_EOL;
                    $param = new Param($condition, $this->expression, $this->quote_identifier);
                    $this->joinParams[$param->placeholder] = $param;
                } else {
                    $condition = new Condition($condition, $this->expression, $this->quote_identifier);
                    if (!in_array($condition, $this->joinConditions)) {
                        $this->joinConditions[] = $condition;
                    }
                }
            }
        }
        // echo 'JOIN PROCESS', var_export($this->joinConditions, true);
        return $this;
    }

    /**
     * @throws ConfigException
     */
    private function setConditions(): Column
    {
        if (isset($this->descriptor['conditions'])) {
            foreach ($this->descriptor['conditions'] as $condition) {
                if (isset($condition['name'])) {
                    $param = new Param($condition, $this->expression, $this->quote_identifier);
                    $this->params[$param->placeholder] = $param;
                } else {
                    $condition = new Condition($condition, $this->expression, $this->quote_identifier);
                    if (!in_array($condition, $this->conditions)) {
                        $this->conditions[] = $condition;
                    }
                }
            }
        }
        return $this;
    }

    private function processColumnExpression(): Column
    {

        if (isset($this->subquery) && $this->subquery instanceof StatementProcessorInterface) {
            $colName = '(' . $this->subquery . ')';
        } else {
            $columnParts = [];
            if (isset($this->tableAlias) && (!isset($this->descriptor['sqlexpression']) || !str_contains($this->descriptor['sqlexpression'], 'literal'))) {
                if (is_array($this->tableAlias)) {
                    $columnParts = $this->tableAlias;
                } else {
                    $columnParts[] = $this->tableAlias;
                }
            }
            $columnParts[] = $this->descriptor['name'];
            $colName = implode('.', $columnParts);
        }
        $this->columnName = $colName;

        if (isset($this->descriptor['template'])) {
            $colName = str_replace([':alias',':colname'], [$this->tableAlias, $colName], $this->descriptor['template']);
        }
        if (isset($this->descriptor['sqlexpression'])) {
            $arguments = $this->descriptor['arguments'] ?? [];
            if (!isset($this->descriptor['excludecolname']) || (bool)$this->descriptor['excludecolname'] !== true) {
                array_unshift($arguments, $colName);
            }
            $colName = call_user_func_array(
                [$this->expression, $this->descriptor['sqlexpression']],
                $arguments
            );

            //$colName = $this->expression->$this->descriptor['sqlexpression'](...$arguments);
        }
        // $columnParts[] = $colName;

        //echo var_export($columnParts, true);

        $this->columnExpression = $colName;
        return $this;
    }

    private function processAlias(): void
    {
        if (isset($this->descriptor['alias'])) {
            $this->alias = ' AS ' . $this->descriptor['alias'];
        }
    }

    /**
     * @throws ConfigException
     */
    public function __toString(): string
    {
        $this->process();
        return $this->columnExpression . $this->alias;
    }

}
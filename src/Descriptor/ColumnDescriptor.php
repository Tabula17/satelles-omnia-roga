<?php

namespace Tabula17\Satelles\Omnia\Roga\Descriptor;

use Tabula17\Satelles\Omnia\Roga\Collection\ConditionCollection;
use Tabula17\Satelles\Omnia\Roga\Collection\JoinConditionCollection;
use Tabula17\Satelles\Omnia\Roga\Collection\ParamCollection;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

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
final class ColumnDescriptor extends AbstractDescriptor
{
    protected(set) string $name;
    protected(set) ?string $type;
    protected(set) ?string $alias;
    protected(set) ?int $visible;
    protected(set) ?string $sqlexpression;
    protected(set) ?array $arguments;
    protected(set) ?int $excludecolname;
    protected(set) ?string $template;
    protected(set) SubqueryDescriptor $subquery {
        set(array|SubqueryDescriptor $value) {
            if (is_array($value)) {
                $this->subquery = new SubqueryDescriptor($value);
            } elseif ($value instanceof SubqueryDescriptor) {
                $this->subquery = $value;
            }
        }
    }
    protected(set) ?int $literal;
    protected(set) ?bool $quoteliteral;
    protected(set) ParamCollection $params {
        set(array|ParamCollection $value) {
            if (is_array($value)) {
                $params = [];
                foreach ($value as $param) {
                    if ($param instanceof ParamDescriptor) {
                        $params[] = $param;
                    } else {
                        $params[] = new ParamDescriptor($param);
                    }
                }
                $this->params = new ParamCollection(...$params);
            } elseif ($value instanceof ParamCollection) {
                $this->params = $value;
            }
        }
    }
    protected(set) ConditionCollection $conditions {
        set(array|ConditionCollection $value) {
            if (is_array($value)) {
                $params = [];
                foreach ($value as $param) {
                    if ($param instanceof ConditionDescriptor) {
                        $params[] = $param;
                    } else {
                        try {
                            $params[] = isset($param['name']) ? new ParamDescriptor($param) : new ConditionDescriptor($param);
                        }catch (\Throwable $exception){
                            echo $exception->getMessage(), PHP_EOL;
                            //echo var_export($param, true), PHP_EOL;
                        }
                    }
                }
                $this->conditions = new ConditionCollection(...$params);
            } elseif ($value instanceof ConditionCollection) {
                $this->conditions = $value;
            }
        }
    }
    protected(set) int $grouped;
    protected(set) JoinConditionCollection $joinConditions {
        set(array|JoinConditionCollection|null $value) {
            if (is_array($value)) {
                $params = [];
                foreach ($value as $param) {
                    if ($param instanceof ConditionDescriptor) {
                        $params[] = $param;
                    } else {
                        $params[] = isset($param['name']) ? new ParamDescriptor($param) : new ConditionDescriptor($param);
                    }
                }
                $this->joinConditions = new JoinConditionCollection(...$params);
            } elseif ($value instanceof JoinConditionCollection) {
                $this->joinConditions = $value;
            }
        }
    }
    protected(set) OrderDescriptor $order {
        set(array|OrderDescriptor $value) {
            if (is_array($value)) {
                $this->order = new OrderDescriptor($value);
            } elseif ($value instanceof OrderDescriptor) {
                $this->order = $value;
            }
        }
    }
    protected(set) string $tableAlias;

}
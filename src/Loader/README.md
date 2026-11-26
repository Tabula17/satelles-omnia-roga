# Documentación de Estructuras XML para Loader

Este documento detalla la estructura de los archivos XML utilizados por el `Loader` para generar consultas SQL a través de la librería.
Las definiciones están guiadas por las clases del espacio `Tabula17\Satelles\Omnia\Roga\Descriptor` y procesadas por las del espacio `Tabula17\Satelles\Omnia\Roga\Builder`.

No es obligatorio utilizar estas clases para generar consultas.
Puede construir otra manera de definir las mismas (por ejemplo, mediante `Json`, `Arrays`, `YAML`, etc.).
Solo debe respetar las estructuras definidas por las clases del espacio `Tabula17\Satelles\Omnia\Roga\Descriptor` y aplicar la interfaz `Tabula17\Satelles\Omnia\Roga\LoaderInterface` a la clase resultante.

## Uso mínimo

```php
require __DIR__ . '/../../vendor/autoload.php';

use Tabula17\Satelles\Omnia\Roga\Loader\XmlFile;
use Tabula17\Satelles\Omnia\Roga\StatementBuilder;

$loader = new XmlFile(baseDir: __DIR__ . '/Xml');
$builder = new StatementBuilder('SELECT/Tags', $loader);

// member: cualquier campo de <metadata> (normalmente allowed o client)
// value: valor exacto; también se admite el comodín '*' en la definición XML
// version: 'latest' o una versión concreta
// variant: 'default' o el valor de <variant>
$builder->loadStatementBy('allowed', 1, 'latest', 'default');

$builder->setValues([
    'estado' => 'ACT',      // equivalente a ':estado'
    'ids' => [1, 2, 3],     // se expande a :ids0, :ids1, :ids2
]);

echo $builder->getStatement();
echo $builder->getPrettyStatement();
var_export($builder->getBindings());
```

`XmlFile` resuelve el nombre lógico dentro de `baseDir`: `SELECT/Basic` carga `Xml/SELECT/Basic.xml`. La extensión `.xml` es opcional y no se permiten rutas absolutas ni `..`.

## Estructura general

Cada archivo XML debe comenzar con la etiqueta raíz `<statements>`, la cual contiene uno o más elementos `<statement>`.

```xml
<?xml version="1.0" encoding="utf-8" ?>
<statements>
    <statement>
        <type>tipo_de_sentencia</type>
        <metadata>
            <!-- Metadatos de la sentencia -->
        </metadata>
        <!-- Cuerpo de la sentencia según el tipo -->
    </statement>
</statements>
```

Valores aceptados en `<type>`: `select` (o vacío), `insert`, `update`, `delete`, `union`, `exec`, `execute`, `call`, `func`, `function`, `sp` y `procedure`.

Convenciones:

- Una etiqueta repetida (`<allowed>`, `<table>`, `<column>`, `<condition>`, etc.) se convierte en una lista.
- Los campos booleanos aceptan `0/1`, `false/true`, `no/si` según el descriptor; en la práctica se usa `0/1`.
- Los nombres de parámetro se escriben sin `:` en XML (`<name>estado</name>`); el builder los expone como `:estado`.
- Los valores se entregan a `setValues()` con o sin `:`. Las claves de `getBindings()` siempre llevan `:`.

### Bloque `<metadata>` (MetadataDescriptor)

Define información contextual para la carga, la selección de variantes y la ejecución de la sentencia.

| Etiqueta | Descripción |
| :--- | :--- |
| `<connection>` | Nombre de la conexión configurada. |
| `<operation>` | Operación o lista separada por comas: `select`, `insert`, `update`, `delete`, `execute`, `exec`, `call`, `sync`. |
| `<version>` | Versión de la definición. Se compara con `version_compare`; `latest` elige la mayor. Un prefijo `v` se ignora. |
| `<allowed>` | Identificador(es) de tenant/rol. Se usa con `loadStatementBy('allowed', ...)`. Una coincidencia exacta gana sobre `*`. Si falta, el loader trata la sentencia como `*`. |
| `<client>` | Identificador(es) de cliente. Se usa igual que `allowed` con `loadStatementBy('client', ...)`. |
| `<variant>` | Variante funcional dentro de la misma versión. Por defecto `default`; `*` actúa como comodín. |
| `<quoteIdentifier>` | `1` para entrecomillar identificadores; `0` (por defecto) para dejarlos tal cual. |
| `<quoteType>` | Alias del estilo de comillas. Solo tiene efecto si `quoteIdentifier` está activo. |

Alias soportados por `<quoteType>`:

| Alias | Resultado |
| :--- | :--- |
| `ansi`, `double`, `double_quote`, `oracle`, `postgresql`, `postgre`, `sqlite`, `identifier`, `column`, `table` | `"` |
| `backtick`, `mysql`, `mariadb` | `` ` `` |
| `single_quote`, `single`, `value`, `data` | `'` |
| `square_bracket`, `square`, `sqlsrv`, `mssql` | `[ ... ]` |
| `bracket`, `brackets`, `parenthesis`, `parens` | `( ... )` |
| `brace`, `braces`, `curly`, `curly_brace`, `arguments` | `{ ... }` |
| Cualquier otro valor o vacío | Sin entrecomillado |

> El entrecomillado de identificadores se controla en `<metadata>`, no dentro de `<from>`, `<into>` ni `<to>`.

---

## Tipos de sentencias

### 1. SELECT (SelectDescriptor)

Estructura para consultas de selección.

```xml
<statement>
    <type>select</type>
    <metadata>...</metadata>

    <distinct>0|1</distinct>
    <all>0|1</all>
    <top>10</top>

    <from>
        <!-- TableDescriptor -->
        <table>dbo</table>
        <table>Tabla_Base</table>
        <alias>t</alias>
        <columns>
            <!-- Lista de <column> -->
        </columns>
    </from>

    <joins>
        <join>
            <type>INNER|LEFT|RIGHT|FULL|CROSS</type>
            <table>Tabla_02</table>
            <alias>t2</alias>
            <columns><!-- Lista de <column> --></columns>
        </join>
    </joins>

    <forShare>1</forShare>
    <skipLocked>1</skipLocked>
</statement>
```

#### Bloque `<from>` / `<join>` (TableDescriptor / JoinDescriptor)

| Etiqueta | Descripción |
| :--- | :--- |
| `<table>` | Segmento del nombre de la tabla. Se puede repetir: `<table>dbo</table><table>Tabla</table>` produce `dbo.Tabla`. |
| `<name>` | Alternativa a `<table>` para un nombre único. |
| `<alias>` | Alias de la tabla. Si no se indica, el builder genera uno (`a0`, `a1`, ...). |
| `<subquery>` | Reemplaza la tabla por una subconsulta `SELECT`. |
| `<columns>` | Columnas de la tabla; sus condiciones alimentan `WHERE`, `HAVING` o `ON`. |
| `<type>` | Solo en `<join>`: tipo de join. Por defecto `INNER`. |

#### Modificadores de SELECT

Se emiten después de `SELECT` (`modifiersStart`) o al final de la sentencia (`modifiersEnd`).

| Etiqueta | SQL generado | Posición |
| :--- | :--- | :--- |
| `<distinct>1</distinct>` | `DISTINCT` | Después de `SELECT` |
| `<all>1</all>` | `ALL` | Después de `SELECT` |
| `<top>10</top>` | `TOP 10` | Después de `SELECT` |
| `<forUpdate>1</forUpdate>` | `FOR UPDATE` | Final |
| `<forShare>1</forShare>` | `FOR SHARE` | Final |
| `<forNoKeyUpdate>1</forNoKeyUpdate>` | `FOR NO KEY UPDATE` | Final |
| `<forKeyShare>1</forKeyShare>` | `FOR KEY SHARE` | Final |
| `<lockInShareMode>1</lockInShareMode>` | `LOCK IN SHARE MODE` | Final |
| `<forKeyNoWait>1</forKeyNoWait>` | `NOWAIT` | Final |
| `<skipLocked>1</skipLocked>` | `SKIP LOCKED` | Final |

#### Bloque `<column>` (ColumnDescriptor)

Define columnas, expresiones, ordenamientos y filtros asociados.

| Etiqueta | Descripción |
| :--- | :--- |
| `<name>` | Nombre físico de la columna o texto de la expresión cuando se usa `<literal>`/`<quoteliteral>`. |
| `<alias>` | Alias de salida (`AS ...`). |
| `<type>` | Tipo lógico del dato (`int`, `string`, `bool`, `date`, `datetime`, `numeric`, `array_int`, `array_str`, `expression`, ...). |
| `<visible>` | `1` incluye la columna en `SELECT`. Por defecto no se incluye. |
| `<sqlexpression>` | Función o predicado aplicado a la columna: `sum`, `min`, `max`, `count`, `len`, `lower`, etc. |
| `<arguments>` | Argumentos extra para `<sqlexpression>`. El nombre de columna se antepone salvo que se use `<excludecolname>1</excludecolname>`. |
| `<excludecolname>` | `1` evita anteponer el nombre de columna a `<arguments>`. |
| `<template>` | Plantilla de la columna. Reemplaza `:alias` y `:colname`. Se aplica antes que `<sqlexpression>`. |
| `<literal>` | `1` emite `<name>` como SQL crudo, sin entrecomillar ni prefijar con el alias de tabla. |
| `<quoteliteral>` | `1` emite `<name>` entrecomillado con el `quoteType` de `<metadata>`, sin alias de tabla. |
| `<grouped>` | `1` añade la expresión de la columna a `GROUP BY`. |
| `<order>` | Ordenamiento: `<position>` y `<direction>ASC\|DESC</direction>`. |
| `<params>` | Lista de `<param>` asociados a la columna (útil en `INSERT`/`UPDATE`). |
| `<conditions>` | Lista de `<condition>` o `<param>` para `WHERE`/`HAVING`. |
| `<joinConditions>` | Condiciones del `ON` del join al que pertenece la columna. |
| `<subquery>` | Subconsulta usada como expresión de la columna. |
| `<title>`, `<description>` | Metadatos descriptivos; no cambian el SQL. |

#### Bloque `<condition>` / `<param>`

Un `<condition>` con `<name>` se trata como parámetro bindable; sin `<name>` se trata como expresión SQL fija. `<param>` es la forma explícita del primer caso.

| Etiqueta | Descripción |
| :--- | :--- |
| `<name>` | Nombre del parámetro. Obligatorio en `<param>`. |
| `<type>` | Tipo del valor. Los tipos `array_int` y `array_str` expanden `:nombre` en `:nombre0`, `:nombre1`, ... |
| `<sqlexpression>` | Operador o función. Por defecto `eq`. Véase la tabla siguiente. |
| `<required>` | `1` exige valor (o `<defaultValue>`); si falta se lanza `ValueRequiredException`. |
| `<defaultValue>` | Valor usado cuando no se entrega uno. |
| `<nullable>` | `1` permite `NULL` cuando el parámetro es requerido. |
| `<format>` | Formato de fecha/hora (`d-m-Y`, `d-m-Y H:i:s`) o de número (`0,000.##`). |
| `<onempty>` | `1` incluye la condición solo cuando el valor está vacío. |
| `<onnotempty>` | `1` incluye la condición solo cuando el valor no está vacío. |
| `<bindable>` | `0` evita registrar el binding. Solo seguro cuando la expresión no consume el placeholder (por ejemplo `isnotnull`) o cuando se usa `<paramTemplate>`/`<subqueryAsArgument>`. |
| `<writeFilter>` | En `UPDATE`, mueve el parámetro de la lista `SET` a `WHERE`. |
| `<nullonempty>` | `1` trata `''`/`null` como nulo internamente; la condición se omite del SQL (no genera `IS NULL` ni binding). |
| `<paramName>` | Nombre físico del argumento en procedimientos almacenados; genera `paramName = :placeholder`. |
| `<paramTemplate>` | Plantilla que reemplaza `:colname` y `:param`. El valor se entrecomilla automáticamente salvo en tipos bool/null/numeric/expression. |
| `<subqueryAsArgument>` | `1` inyecta la subconsulta como argumento en lugar de envolverla entre paréntesis. |
| `<combined>` | Agrupa condiciones con `OR` bajo el mismo número de grupo. |
| `<collides>` | Marca filtros mutuamente excluyentes. `1` agrupa por columna; un texto agrupa por nombre (incluso entre columnas). |
| `<having>` | `1` envía la condición a `HAVING` en lugar de `WHERE`. |
| `<columnName>` | Columna explícita para una condición sin parámetro; admite `<tableAlias>` previo. |
| `<arguments>` | Argumentos extra para `<sqlexpression>`. |
| `<subquery>` | Subconsulta asociada a la condición. |
| `<description>` | Texto descriptivo; no cambia el SQL. |

Con `<collides>1</collides>`, dos condiciones activas sobre la misma columna no pueden renderizarse juntas: al intentar generar el SQL se lanza `ConfigException`. Si se usa un texto, por ejemplo `<collides>busqueda</collides>`, la exclusión se aplica a todas las condiciones activas con ese nombre, aunque pertenezcan a columnas distintas. Las condiciones inactivas no cuentan como colisión.

Valores habituales de `<sqlexpression>`:

| Categoría | Valores |
| :--- | :--- |
| Comparación | `eq`, `neq`, `lt`, `lte`, `gt`, `gte`, `between`, `notbetween`, `like`, `notLike`, `in`, `notIn`, `isNull`, `isNotNull`, `isColNull` |
| Lógica | `andX`, `orX`, `not` |
| Agregación y funciones | `avg`, `max`, `min`, `count`, `countDistinct`, `sum`, `abs`, `sqrt`, `concat`, `concatplus`, `substring`, `lower`, `upper`, `length`, `trim`, `literal` |
| Subconsultas | `exists`, `notexists`, `all`, `some`, `any` |

Cualquier otro nombre se convierte en una función SQL en mayúsculas (por ejemplo `CAST`, `TRUNC`, `COALESCE`) gracias a `Expression::__call()`.

### 2. INSERT (InsertDescriptor)

La forma recomendable define los parámetros dentro de las columnas de `<into>`:

```xml
<statement>
    <type>insert</type>
    <metadata>...</metadata>
    <into>
        <table>dbo</table>
        <table>Tabla_Base</table>
        <columns>
            <column>
                <name>columna_1</name>
                <type>int</type>
                <params>
                    <param>
                        <name>valor_1</name>
                        <type>int</type>
                        <required>1</required>
                    </param>
                </params>
            </column>
        </columns>
    </into>
</statement>
```

Para `INSERT INTO ... SELECT`, las columnas de `<into>` no llevan `<params>` y se usa `<select>`:

```xml
<select>
    <subquery>
        <path>SELECT/Basic</path>
    </subquery>
</select>
```

También se admite un `<select>` inline con la misma estructura de un `<statement>` de tipo `select`. El número de columnas debe coincidir con el número de columnas del `SELECT`.

### 3. UPDATE (UpdateDescriptor)

```xml
<statement>
    <type>update</type>
    <metadata>...</metadata>
    <to>
        <table>dbo</table>
        <table>Tabla_Base</table>
        <columns>
            <column>
                <name>columna_1</name>
                <params>
                    <param>
                        <name>nuevo_valor</name>
                        <type>int</type>
                        <required>1</required>
                    </param>
                </params>
            </column>
            <column>
                <name>id</name>
                <conditions>
                    <condition>
                        <name>filterId</name>
                        <type>int</type>
                        <sqlexpression>eq</sqlexpression>
                        <writeFilter>1</writeFilter>
                        <required>1</required>
                    </condition>
                </conditions>
            </column>
        </columns>
    </to>
</statement>
```

`<params>` alimenta el `SET`; `<conditions>` y los parámetros con `<writeFilter>1</writeFilter>` alimentan el `WHERE`. La librería rechaza `UPDATE` sin `WHERE`.

### 4. DELETE (DeleteDescriptor)

```xml
<statement>
    <type>delete</type>
    <metadata>...</metadata>
    <from>
        <table>dbo</table>
        <table>Tabla_Base</table>
        <columns>
            <column>
                <name>id</name>
                <conditions>
                    <condition>
                        <name>filterId</name>
                        <type>int</type>
                        <sqlexpression>eq</sqlexpression>
                        <required>1</required>
                    </condition>
                </conditions>
            </column>
        </columns>
    </from>
</statement>
```

La librería rechaza `DELETE` sin `WHERE`.

### 5. EXEC / CALL (ExecuteDescriptor)

```xml
<statement>
    <type>exec</type> <!-- o execute, call, func, function, sp, procedure -->
    <metadata>...</metadata>
    <execKeyword>exec|call</execKeyword>
    <namedArguments>0|1</namedArguments>
    <prefixVariable></prefixVariable>
    <argListSurrounded>0|1</argListSurrounded>
    <procedure>dbo</procedure>
    <procedure>Stored_Procedure</procedure>
    <arguments>
        <argument>
            <name>param_1</name>
            <type>int</type>
            <required>1</required>
            <paramName>param_01</paramName>
        </argument>
    </arguments>
</statement>
```

`<procedure>` se puede repetir para construir un nombre compuesto. `<prefixVariable>` es útil en SQL Server (`@`). `exec`/`execute` se renderizan como `EXECUTE` y `call` como `CALL`. El flag `<return>` existe en el descriptor pero actualmente no cambia el SQL.

### 6. UNION (UnionDescriptor)

```xml
<statement>
    <type>union</type>
    <metadata>...</metadata>
    <unionAll>true|false</unionAll>
    <unions>
        <union>
            <subquery>
                <path>SELECT/Union01</path>
            </subquery>
        </union>
        <union>
            <!-- También puede ser un SELECT inline: <from>, <columns>, ... -->
            <from>
                <table>dbo</table>
                <table>Tabla_Base</table>
                <columns>...</columns>
            </from>
        </union>
    </unions>
</statement>
```

`<unionAll>` acepta `true/false`, `1/0`, `*`, `yes/no` y `si`. Los parámetros del mismo placeholder en varias ramas se fusionan; un parámetro requerido en cualquier rama permanece requerido.

---

## Bloques especiales

### Subconsultas (`<subquery>`)

Permite referenciar otros archivos XML o definir consultas anidadas.

| Etiqueta | Descripción |
| :--- | :--- |
| `<path>` | Ruta lógica relativa a `baseDir`, sin `.xml`: `SELECT/Min`. Las rutas absolutas y `..` se rechazan. |
| `<arguments>` | Valores inyectados en la subconsulta. Las claves son los nombres de parámetro (con o sin `:`). |
| `<descriptor>` | Definición inline de la subconsulta; normalmente la genera el loader al resolver `<path>`. |

Ejemplo con `EXISTS`:

```xml
<condition>
    <sqlexpression>exists</sqlexpression>
    <subquery>
        <path>SELECT/Min</path>
        <arguments>
            <external_col>:colname</external_col>
        </arguments>
    </subquery>
</condition>
```

`:colname` se sustituye por la expresión de la columna contenedora. Con `exists`/`notexists` la columna no se añade como argumento adicional: solo se usa para resolver ese marcador.

El loader resuelve la variante de la subconsulta usando el mismo identificador (`allowed`, `client`) del padre. Si el padre declara un tenant para el que la subconsulta no tiene definición, esa variante se omite con un `WARNING`; no se usa la definición de otro tenant.

### Ciclos

El loader detecta ciclos entre subconsultas y lanza una excepción de configuración en lugar de entrar en un bucle infinito.

## Limitaciones conocidas

- `SelectDescriptor` declara `<offset>` y `<limit>`, pero el builder actual no los emite en el SQL; use `<top>` o aplique paginación en la capa de ejecución.
- Hay flags de bloqueo en `SelectDescriptor` (`forNoKey`, `forKey`, `forRead`, `forWrite`, `forAppend`, `forCheck`, `forReference`, `forShare*`) que todavía no tienen mapeo a SQL. Los soportados están en la tabla de modificadores.
- Un parámetro con `<bindable>0</bindable>` no registra binding. Si la expresión usa el placeholder, el SQL quedará incompleto; use una expresión sin placeholder o `<paramTemplate>`.

## Ejemplos

- `src/Loader/Xml/SELECT/Tags.xml`: catálogo compacto de modificadores, columnas literales, plantillas, agrupación, orden, `IN`, `collides`, `combined`, `having` y modificadores de bloqueo.
- `src/Loader/Xml/SELECT/Basic.xml`: variantes por `allowed` y subconsulta con `notexists`.
- `src/Loader/Xml/SELECT/Complex.xml`: joins, subconsultas anidadas y múltiples variantes.
- `src/Loader/Xml/SELECT/Union.xml`: uniones por ruta y definiciones inline.
- `src/Loader/Xml/INSERT/Basic.xml`: `INSERT ... VALUES`.
- `src/Loader/Xml/INSERT/FromSelect.xml`: `INSERT ... SELECT`.
- `src/Loader/Xml/UPDATE/Basic.xml`: `SET`, filtros y subconsultas.
- `src/Loader/Xml/DELETE/Basic.xml`: filtros de borrado.
- `src/Loader/Xml/EXEC/Basic.xml`, `Call.xml`, `Named.xml` y `SPSqlServer.xml`: procedimientos y llamadas.

También hay un ejemplo ejecutable en `src/Loader/example.php`.

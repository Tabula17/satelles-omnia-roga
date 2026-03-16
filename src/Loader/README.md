# Documentación de Estructuras XML para Loader

Este documento detalla la estructura de los archivos XML utilizados por el `Loader` para generar consultas SQL a través de la librería. Las definiciones están guiadas por las clases del espacio `Tabula17\Satelles\Omnia\Roga\Descriptor` y procesadas por las del espacio `Tabula17\Satelles\Omnia\Roga\Builder`.

## Estructura General

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

### Bloque `<metadata>` (MetadataDescriptor)

Define información contextual para la carga y ejecución de la sentencia.

| Etiqueta | Descripción |
| :--- | :--- |
| `<connection>` | Nombre de la conexión configurada. |
| `<operation>` | Tipo de operación (select, insert, update, delete, execute, etc.). Puede ser una lista separada por comas. |
| `<version>` | Versión de la definición del statement. |
| `<allowed>` | (Opcional) Roles o permisos permitidos para esta sentencia. |
| `<variant>` | (Opcional) Variante específica de la sentencia. |
| `<client>` | (Opcional) Identificador de cliente. |
| `<quoteIdentifier>` | Booleano (0/1) para indicar si se deben entrecomillar los identificadores. |

---

## Tipos de Sentencias

### 1. SELECT (SelectDescriptor)

Estructura para consultas de selección.

```xml
<statement>
    <type>select</type>
    <metadata>...</metadata>
    <distinct>0|1</distinct>
    <top>integer</top>
    <offset>integer</offset>
    <limit>integer</limit>
    <from>
        <!-- TableDescriptor -->
        <table>nombre_tabla</table>
        <alias>alias_tabla</alias>
        <columns>
            <!-- Lista de elementos <column> -->
        </columns>
    </from>
    <joins>
        <join>
            <type>INNER|LEFT|RIGHT|FULL</type>
            <table>nombre_tabla</table>
            <alias>alias_tabla</alias>
            <columns>...</columns>
        </join>
    </joins>
</statement>
```

#### Bloque `<column>` (ColumnDescriptor)
Define las columnas a seleccionar y sus condiciones asociadas.

| Etiqueta | Descripción |
| :--- | :--- |
| `<name>` | Nombre físico de la columna. |
| `<alias>` | Alias para la columna en el resultado SQL. |
| `<type>` | Tipo de dato (int, string, bool, etc.). |
| `<visible>` | Booleano (0/1) que indica si la columna se incluye en la cláusula SELECT. |
| `<sqlexpression>` | Expresión SQL personalizada para la columna. |
| `<conditions>` | Contenedor de elementos `<condition>`. |
| `<order>` | Definición de ordenamiento (`<direction>ASC|DESC</direction>`). |
| `<subquery>` | Definición de una subconsulta para la columna. |

#### Bloque `<condition>` (ConditionDescriptor / ParamDescriptor)
Define filtros aplicables a la columna (cláusula WHERE o HAVING).

| Etiqueta | Descripción |
| :--- | :--- |
| `<name>` | Nombre del parámetro o condición. |
| `<type>` | Tipo de dato del valor. |
| `<sqlexpression>` | Operador o expresión SQL (eq, neq, gt, lt, like, in, isnotnull, exists, etc.). |
| `<required>` | Booleano (0/1) que indica si el parámetro es obligatorio. |
| `<defaultValue>` | Valor por defecto si no se proporciona uno. |
| `<bindable>` | Booleano (0/1) que indica si el valor debe ser bindeado como parámetro. |
| `<onempty>` | Booleano que indica si se incluye la condición si el valor está vacío. |
| `<onnotempty>` | Booleano que indica si se incluye la condición si el valor NO está vacío. |

### 2. INSERT (InsertDescriptor)

Estructura para sentencias de inserción.

```xml
<statement>
    <type>insert</type>
    <metadata>...</metadata>
    <into>
        <table>nombre_tabla</table>
    </into>
    <arguments>
        <!-- Lista de elementos <column> o <condition> que actúan como parámetros -->
        <column>...</column>
    </arguments>
    <select>
        <!-- (Opcional) Definición de un SelectDescriptor para INSERT INTO ... SELECT -->
    </select>
</statement>
```

### 3. UPDATE (UpdateDescriptor)

Estructura para sentencias de actualización.

```xml
<statement>
    <type>update</type>
    <metadata>...</metadata>
    <to>
        <table>nombre_tabla</table>
    </to>
    <select>
        <!-- Define las columnas a actualizar y las condiciones (WHERE) -->
        <columns>...</columns>
    </select>
</statement>
```

### 4. DELETE (DeleteDescriptor)

Estructura para sentencias de eliminación.

```xml
<statement>
    <type>delete</type>
    <metadata>...</metadata>
    <from>
        <table>nombre_tabla</table>
        <columns>
            <!-- Se utiliza principalmente para definir las condiciones WHERE -->
            <column>
                <name>id</name>
                <conditions>...</conditions>
            </column>
        </columns>
    </from>
</statement>
```

### 5. EXEC / CALL (ExecuteDescriptor)

Estructura para ejecución de procedimientos almacenados o funciones.

```xml
<statement>
    <type>execute</type> <!-- o exec, call -->
    <metadata>...</metadata>
    <procedure>nombre_procedimiento</procedure>
    <execKeyword>exec|call</execKeyword>
    <namedArguments>0|1</namedArguments>
    <arguments>
        <!-- Lista de parámetros <condition> -->
        <condition>
            <name>param1</name>
            <type>string</type>
        </condition>
    </arguments>
</statement>
```

### 6. UNION (UnionDescriptor)

Estructura para combinar múltiples SELECTs mediante UNION o UNION ALL.

```xml
<statement>
    <type>union</type>
    <metadata>...</metadata>
    <unionAll>true|false</unionAll>
    <unions>
        <union>
            <!-- Puede ser una definición directa de SELECT (from, columns, etc.) -->
            <!-- O una referencia a otro archivo mediante subquery -->
            <subquery>
                <path>Carpeta/ArchivoXML</path>
            </subquery>
        </union>
    </unions>
</statement>
```

---

## Bloques Especiales

### Subconsultas (`<subquery>`)

Permite referenciar otros archivos XML o definir consultas anidadas.

| Etiqueta | Descripción |
| :--- | :--- |
| `<path>` | Ruta relativa dentro de la carpeta `Xml` al archivo que contiene la definición. |
| `<arguments>` | Mapeo de argumentos externos a parámetros de la subconsulta. |

## Ejemplos

Puedes encontrar ejemplos reales de cada estructura en las siguientes carpetas:
- **SELECT**: `src/Loader/Xml/SELECT/`
- **INSERT**: `src/Loader/Xml/INSERT/`
- **UPDATE**: `src/Loader/Xml/UPDATE/`
- **DELETE**: `src/Loader/Xml/DELETE/`
- **EXEC**: `src/Loader/Xml/EXEC/`

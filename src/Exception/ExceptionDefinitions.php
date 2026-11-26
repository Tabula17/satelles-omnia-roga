<?php

namespace Tabula17\Satelles\Omnia\Roga\Exception;

enum ExceptionDefinitions: string
{
    case UPDATE_STATEMENT_WITHOUT_WHERE = 'No está permitido ejecutar una sentencia del tipo "UPDATE" sin un "WHERE".';
    case DELETE_STATEMENT_WITHOUT_WHERE = 'No está permitido ejecutar una sentencia del tipo "DELETE" sin un "WHERE".';
    case STATEMENT_WITHOUT_WHERE = 'No está permitido ejecutar una sentencia del tipo "%s" sin un "WHERE".';
    case ARGUMENTS_LIST_EMPTY = 'No se proporcionaron argumentos para ejecutar la operación';
    case CONDITION_WITHOUT_COLUMN_NAME = 'No se proporcionó el nombre de columna para generar la condición';
    case CONDITION_WITHOUT_ARGUMENTS = 'No se proporcionaron argumentos suficientes para generar la condición';
    case PARAM_WITHOUT_NAME = 'No se identificó el nombre del parámetro a procesar';
    case PARAM_VALUE_EXPECTED = 'No se especificó el valor del parámetro "%s" y es requerido para ejecutar la consulta';
    case PARAM_COLUMN_NAME_EXPECTED = 'No se proporcionó el nombre de columna para aplicar al parámetro "%s"';
    case DATABASE_DRIVER_NOT_SUPPORTED = 'El controlador "%s" no está soportado';
    case DATABASE_DRIVER_NOT_FOUND = 'No se encontró el controlador "%s"';
    case DIRECTORY_NOT_FOUND = 'El directorio "%s" no existe';
    case JOINED_COLUMN_NOT_DEFINED = 'No se proporcionó el nombre de la columna a unir en el JOIN';
    case INSERT_WHITEOUT_BODY = 'No se definieron el origen de los datos a insertar (parámetros o select)';
    case INSERT_WHITEOUT_COLUMNS = 'No se definieron las columnas a insertar';
    case INSERT_WHITEOUT_TABLE = 'No se definieron la tabla a insertar';
    case INSERT_COLUMNS_AND_SELECT_COUNT_MISMATCH = 'La cantidad de columnas en el SELECT no coincide con la cantidad de columnas definidas en el INSERT';
    case STATEMENT_NOT_FOUND_FOR_VARIANT = 'No se encontró una sentencia para la variante de consulta "%s" en la definición "%s"';
    case STATEMENT_TYPE_NOT_SUPPORTED = 'El tipo de sentencia "%s" no está soportado';
    case XML_FILE_NOT_FOUND = 'No se encontró el archivo de definición "%s"';
    case XML_FILE_OUTSIDE_BASE_DIR = 'La definición "%s" reside fuera del directorio base "%s"';
    case INVALID_XML = 'El archivo "%s" no contiene XML válido';
    case INVALID_STATEMENT_STRUCTURE = 'El archivo "%s" debe contener un elemento <statements> con al menos un <statement>';
    case CIRCULAR_SUBQUERY = 'Referencia circular de subconsulta detectada en "%s"';
    case UNRESOLVED_SUBQUERY = 'No se pudo resolver la subconsulta en "%s" (%s)';
    case POOL_SIZE_GREATER_THAN_ZERO = 'El tamaño del pool debe ser mayor que cero ';
    case POOL_NOT_FOUND = 'No se encontró el pool de conexiones "%s"';
    case POOL_CONNECTION_TIMEOUT = 'Tiempo de espera agotado para la conexión al pool de conexiones "%s" (%s segundos)';
    case POOL_CONNECTION_ERROR = 'Error al conectarse al pool de conexiones "%s": %s';
    case HOST_CANNOT_BE_EMPTY = 'El host no puede estar vacío';
    case HOST_INVALID = 'El formato de host "%s" es inválido';
    case PORT_INVALID = 'El puerto debe estar entre 1 y 65535';
}

<?php

namespace Tabula17\Satelles\Omnia\Roga\Loader;

use Tabula17\Satelles\Omnia\Roga\Collection\StatementCollection;
use Tabula17\Satelles\Omnia\Roga\Descriptor\Descriptors;
use Tabula17\Satelles\Omnia\Roga\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Omnia\Roga\Exception\InvalidArgumentException;
use Tabula17\Satelles\Omnia\Roga\LoaderInterface;
use Tabula17\Satelles\Utilis\Array\ArrayUtilities;
use Tabula17\Satelles\Utilis\Cache\CacheManagerInterface;

class XmlFile implements LoaderInterface
{
    private(set) ?CacheManagerInterface $cacheManager;
    private(set) string $baseDir {
        set {
            if (!realpath($value) || !is_dir($value)) {
                throw new InvalidArgumentException(sprintf(ExceptionDefinitions::DIRECTORY_NOT_FOUND->value, $value));
            }
            if (str_ends_with($value, DIRECTORY_SEPARATOR)) {
                $value = substr($value, 0, -1);
            }
            $this->baseDir = realpath($value);
        }
    }
    public array $variantsMembers = [
        'client',
        'clients',
        'variant',
        'variants',
        'allowed',
    ];

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(string $baseDir, ?CacheManagerInterface $cacheManager = null)
    {
        $this->baseDir = $baseDir;
        $this->cacheManager = $cacheManager;
    }

    private function sanitizePath(string $path): string
    {
        $end = '';
        if (str_ends_with($path, '.xml')) {
            $path = substr($path, 0, -4);
            $end = '.xml';
        }
        $path = str_replace(['\\', '/', '.', '|'], DIRECTORY_SEPARATOR, $path);
        $pathArr = explode(DIRECTORY_SEPARATOR, $path);

        $pathArr = array_filter($pathArr, static fn($value) => !empty($value));
        array_unshift($pathArr, $this->baseDir);
        return implode(DIRECTORY_SEPARATOR, $pathArr) . $end;
    }

    private function loadXml(string $name, array &$subqueries = [], ?string $parent = null): array
    {
        if (!str_ends_with($name, '.xml')) {
            $name .= '.xml';
        }
        $array = ArrayUtilities::simpleXmlToArray(simplexml_load_string(file_get_contents($this->sanitizePath($name))));
        if (isset($array['statement']) && !array_is_list($array['statement'])) {
            $array['statement'] = [$array['statement']];
        }
        $list = $this->checkForSubQueries($this->checkArrayListsInChild($array['statement']), $parent, $subqueries);

        return [
            'statements' => $list,
            'subqueries' => $subqueries,
        ];
    }

    private function checkForSubQueries(array $collection, null|string|int $parent = null, ?array &$subqueries = null): array
    {
        $newList = [];
        foreach ($collection as $key => $value) {
            // $key = (string)$key;
            // echo '**************** >>> CHECK FOR SUBQUERY -> ',  var_export($key, true), PHP_EOL;
            //$isSubquery = strtolower($key) === 'subquery';

            if ($parent !== null && $parent !== 'statement') {
                // $keyPath = $parent . '.' . $key;
                $keys = explode('.', $parent);
                if (($k = array_search('statement', $keys)) !== false) {
                    unset($keys[$k]);
                }
                $keys[] = $key;
                $keyPath = implode('.', $keys);

            }
            if (is_array($value)) {
                //echo 'ARRAY -> ', $key, ' | ';
                if (isset($subqueries) && (strtolower($key) === 'subquery' || strtolower($key) === 'union')) {
                    $path = ($keyPath ?? $key); // en la definicion de array con subquery anidado se suma el 'descriptor
                    $newList[$key] = $path;
                    $pathParts = explode('.', $path);
                    if (is_numeric($pathParts[0])) {
                        unset($pathParts[0]);
                    }
                    $sq = false;
                    $unsetNext = false;
                    foreach ($pathParts as $k => $part) {
                        if ($sq === false) {
                            $sq = $part === 'subquery';
                        }
                        if ($unsetNext) {
                            //  unset($pathParts[$k - 1]);
                            unset($pathParts[$k]);
                        }
                        $unsetNext = $sq && $part === 'descriptor';
                        //echo 'CHECK PART -> ', $part, PHP_EOL, var_export($sq, true), PHP_EOL;
                    }
                    $sqPath = implode('.', $pathParts);
                    //echo PHP_EOL, '|__ SUBQUERY DETECTED -> ', $key, ' => ', $sqPath, ' | ', $path, PHP_EOL;//, var_export($value, true), PHP_EOL;
                    /*   if(isset($value['path'])){

                           echo '|__ SUBQUERY to LOAD -> ', $sqPath, ' => ', $value['path'], PHP_EOL;//, var_export($value, true), PHP_EOL;

                           $subquery = $this->loadXml($value['path'], $subqueries, ($path ? "$path." : '') . 'descriptor') ;
                           if (isset($value['arguments'])) {
                               $subquery['arguments'] = $value['arguments'];
                           }
                           if(!isset($subqueries[$sqPath])){
                               $subqueries[$sqPath] = [];
                           }
                           if(!in_array($subquery, $subqueries[$sqPath])){
                               $subqueries[$sqPath][] = $subquery;
                           }
                       }else{
                           $subqueries[$sqPath] = $value;
                       }*/
                    if (!isset($subqueries[$sqPath])) {
                        $subqueries[$sqPath] = [];
                    }
                    if (!isset($value['path'])) {
                        //echo '|__ ARRAY to CHECK -> ', $sqPath, ' => ', 'NO SUBQUERY PATH', PHP_EOL;
                        $newList[$key] = $this->checkForSubQueries($value, $keyPath ?? $key, $subqueries);
                        continue;
                    }
                    if (!isset($subqueries[$sqPath][$value['path']])) {
                        //echo '|__ SUBQUERY to LOAD -> ', $sqPath, ' => ', $value['path'], ' ☘️ ', $parent, PHP_EOL;//, var_export($value, true), PHP_EOL;
                        $subquery = $this->loadXml($value['path'], $subqueries, ($path ? "$path." : '') . 'descriptor');
                        // unset($subquery['subqueries']);
                        if (isset($value['arguments'])) {
                            $subquery['arguments'] = $value['arguments'];
                        }
                        unset($subquery['subqueries']);
                        $subqueries[$sqPath][$value['path']] = $subquery;
                    }
                    $newList[$key] = $value;
                } else {
                    $newList[$key] = $this->checkForSubQueries($value, $keyPath ?? $key, $subqueries);
                }
            } else {
                /*     echo '|_ not array -> ', $key, PHP_EOL;
                     if ($isSubquery) {
                         echo 'MALFORMED SUBQUERY -> ', var_export($value, true), PHP_EOL;
                         echo 'PARENT -> ', $parent, PHP_EOL;
                     }*/
                $newList[$key] = $value;
            }
            /*if ($isSubquery) {
                //var_export($newList);
                echo 'SUBQUERY FOUND -> ', $key, ' => ', $parent, ' => ', var_export($newList, true), PHP_EOL;
                echo 'SUBQUERY VALUE ==> ', $parent, ' => ', var_export($value, true), PHP_EOL;
                //var_export($newList);
            }*/
        }
        return $newList;
    }

    private function checkArrayListsInChild(array $collection, ?string $parent = null): array
    {
        $newList = [];
        foreach ($collection as $key => $value) {
            //if($key === 'statement')
            if (is_array($value)) {
                if (count($collection) === 1 && $parent && substr($parent, 0, -1) === $key) {
                    if (array_is_list($value)) {
                        foreach ($value as $v) {
                            $newList[] = is_array($v) ? $this->checkArrayListsInChild($v, $key) : $v;
                        }
                    } else {
                        $newList[] = $this->checkArrayListsInChild($value, $key);
                    }
                } else {
                    $newList[$key] = $this->checkArrayListsInChild($value, $key);
                }
            } else if ($parent && substr($parent, 0, -1) === $key) {
                $newList[] = $value;
            } else {
                $newList[$key] = $value;
            }
        }
        return $newList;
    }

    public function getStatementDescriptors(string $name, bool $forceReload = false): ?array
    {
        if ($forceReload || !$this->cacheManager || !$this->cacheManager?->has($name)) {
            $descriptors = [];
            ['statements' => $statements, 'subqueries' => $subqueries] = $this->loadXml($name);
            /*
                        echo 'STATEMENTS -> ', count($statements), PHP_EOL;
                        echo 'SUBQUERIES -> ', count($subqueries), PHP_EOL;
                        echo 'SUBQUERIES PATHS -> ', implode(', ', array_keys($subqueries)), PHP_EOL;
                        foreach ($subqueries as $sqPath => $sq) {
                            echo 'SUBQUERY PATH -> ', $sqPath, ' ** ', var_export(array_keys($sq), true), PHP_EOL;
                            foreach ($sq as $sqDescriptor) {
                                echo 'SUBQUERY DESCRIPTOR -> --> ', var_export(array_keys($sqDescriptor), true), PHP_EOL;
                                echo 'SUBQUERY DESCRIPTOR -> statements --> ', count($sqDescriptor['statements']), PHP_EOL;
                            }
                        }*/

            foreach ($statements as $statement) {
                $subsDetectedByKey = ArrayUtilities::getArrayPathsByKey($statement, 'subquery');
                $descriptorClass = Descriptors::fromName($statement['type'] ?? 'select');

                //  echo 'SUBQUERIES DETECTED BY KEY -> ', implode(', ', $subsDetectedByKey), PHP_EOL;
                //   $allowsStatement = ArrayUtilities::getArrayValueByPath($statement, 'metadata.' . $member);

                $variants = array_intersect_key($statement['metadata'], array_flip($this->variantsMembers));

                // echo 'VARIANTS -> ', var_export($variants, true), PHP_EOL;
                $subqueriesForPath = [];
                if (!empty($subsDetectedByKey)) {
                    foreach ($subsDetectedByKey as $subqueryPath) {
                        //echo 'SEARCH SUBQUERY PATH -> ', $subqueryPath, PHP_EOL;
                        $detected = ArrayUtilities::getArrayValueByPath($statement, $subqueryPath);
                        //    echo 'SUBQUERY DETECTED -> ', var_export($detected, true), PHP_EOL;
                        if (isset($detected['path'], $subqueries[$subqueryPath][$detected['path']])) {
                            if (!isset($subqueriesForPath[$subqueryPath])) {
                                $subqueriesForPath[$subqueryPath] = [];
                            }
                            if (!isset($subqueriesForPath[$subqueryPath][$detected['path']])) {
                                $subqueriesForPath[$subqueryPath][$detected['path']] = $subqueries[$subqueryPath][$detected['path']];
                            }
                        }
                    }
                }

                foreach ($variants as $member => $variant) {
                    foreach ($variant as $allowed) {
                        $descriptor = $statement;
                        //echo 'SET STATEMENT FOR -> ', $allowed, PHP_EOL;
                        ArrayUtilities::setArrayValueByPath($descriptor, 'metadata.' . $member, $allowed);

                        foreach ($subqueries as $path => $subDescriptors) {
                            // echo 'GET SUBQUERY FOR -> ', $path, PHP_EOL;

                            $currentPaths = ArrayUtilities::getArrayPathsByKey($descriptor, 'subquery', []);
                            //echo 'CURRENT SUBQUERIES -> ', implode(', ', $currentPaths), PHP_EOL;

                            if (ArrayUtilities::pathExisits($descriptor, $path)) {
                                //echo 'Path EXISTS in statement -> ', $path, PHP_EOL;
                                $subqueryBase = ArrayUtilities::getArrayValueByPath($descriptor, $path);
                                if (isset($subqueryBase['path'])) {
                                    //echo 'SEARCH SUBQUERY FOR -> ', $path, ' -> ', var_export($subqueryBase['path'], true), PHP_EOL;
                                    $subqueryDescriptors = $subDescriptors[$subqueryBase['path']] ?? [];
                                    if (!empty($subqueryDescriptors)) {
                                        //  echo 'SUBQUERY DESCRIPTORS -> ', var_export(array_keys($subqueryDescriptors), true), PHP_EOL;
                                        foreach ($subqueryDescriptors['statements'] as $sqKey => $sqDescriptor) {
                                            $allowedForSubquery = ArrayUtilities::getArrayValueByPath($sqDescriptor, 'metadata.' . $member);
                                            //echo 'SUBQUERY IS DESCRIPTOR -> ', $sqKey, ' -> ', var_export(array_keys($sqDescriptor), true), PHP_EOL;

                                            if ((isset($allowedForSubquery) && in_array($allowed, $allowedForSubquery)) || $allowed === '*') {
                                                /*if (ArrayUtilities::pathExisits($descriptor, $path)) {
                                                    echo 'SETTING SUBQUERY ALLOWED -> ', var_export($allowed, true), PHP_EOL;
                                                } else {
                                                    echo 'SETTING SUBQUERY ALLOWED -> ', var_export($allowed, true), ' (NO EXISTE) ', PHP_EOL;
                                                }*/
                                                ArrayUtilities::setArrayValueByPath($sqDescriptor, 'metadata.' . $member, $allowed);
                                                $subqueryDescriptor = ['descriptor' => $sqDescriptor];
                                                if (isset($subqueryDescriptors['arguments'])) {
                                                    $subqueryDescriptor['arguments'] = $subqueryDescriptors['arguments'];
                                                }
                                                ArrayUtilities::setArrayValueByPath($descriptor, $path, $subqueryDescriptor);
                                                //echo '*@** SUBQUERY SETTED -> ', $path, ' -> ', var_export(array_keys(ArrayUtilities::getArrayValueByPath($descriptor, $path)), true), PHP_EOL;
                                                break;
                                            }
                                        }
                                    }
                                    //   echo 'GET SUBQUERY FOR -> ', $path, ' -> ', var_export($subDescriptors[$subqueryBase['path']], true), PHP_EOL;
                                } /*else {
                                    echo 'NO SUBQUERY PATH FOUND -> ', $path, PHP_EOL;
                                    var_dump($descriptor);
                                }*/
                            }
                        }
                        /*
                                                foreach ($subqueriesForPath as $path => $pathSubqueries) {
                                                    echo 'SET SUBQUERY FOR -> ', $path, PHP_EOL;
                                                    $subqueryPath = ArrayUtilities::getArrayValueByPath($descriptor, $path);
                                                    echo 'SUBQUERY PATH xxxxs -> ', var_export($subqueryPath, true), PHP_EOL;
                                                    //$subqueryPath['arguments']

                                                    if (isset($subqueryPath['path'])) {
                                                        echo 'SUBQUERY PATH EXISTS -> ', var_export($subqueryPath['path'], true), PHP_EOL;
                                                        echo 'SUBQUERY keys -> ', implode(' | ', array_keys($pathSubqueries[$subqueryPath['path']])), PHP_EOL;
                                                        foreach ($pathSubqueries[$subqueryPath['path']]['statements'] as $subquery) {
                                                            $allowedForSubquery = ArrayUtilities::getArrayValueByPath($subquery, 'metadata.' . $member);
                                                            echo 'SUBQUERY ALLOWED FOR -> ', $allowed, ' -> ', var_export($allowedForSubquery, true), PHP_EOL;

                                                            if ((isset($allowedForSubquery) && in_array($allowed, $allowedForSubquery)) || $allowed === '*') {
                                                                if (ArrayUtilities::pathExisits($descriptor, $path)) {
                                                                    echo 'SETTING SUBQUERY ALLOWED -> ', var_export($allowed, true), PHP_EOL;
                                                                } else {
                                                                    echo 'SETTING SUBQUERY ALLOWED -> ', var_export($allowed, true), ' (NO EXISTE) ', PHP_EOL;
                                                                }
                                                                ArrayUtilities::setArrayValueByPath($subquery, 'metadata.' . $member, $allowed);
                                                                ArrayUtilities::setArrayValueByPath($descriptor, $path, $subquery);
                                                            }
                                                        }
                                                    }
                                                }*/
                        $checkOrphanPaths = ArrayUtilities::getArrayPathsByKey($descriptor, 'path', []);
                        if (empty($checkOrphanPaths)) {
                            $descriptors[] = new $descriptorClass($descriptor);// $descriptor;
                        } /*else {
                            echo 'ORPHAN PATHS -> ', $allowed, ' -> ', implode(', ', $checkOrphanPaths), PHP_EOL;
                            foreach ($checkOrphanPaths as $path) {
                                $path = substr($path, 0, strrpos($path, '.'));
                                echo 'CHECK ORPHAN PATH -> ', $path, ' -> ', var_export(ArrayUtilities::pathExisits($descriptor, $path), true), PHP_EOL;
                                echo 'CHECK ORPHAN PATH -> ', $path, ' -> ', var_export(isset($subqueries[$path]), true), PHP_EOL, implode(' | ', array_keys($subqueries)), PHP_EOL;
                            }
                            // throw new \Exception('Orphan path detected -> ' . implode(', ', $checkOrphanPaths));
                        }*/


                    }
                }
            }
            $this->cacheManager?->set($name, $descriptors);
            return $descriptors;
        }
        echo 'CACHE HIT -> ', $name, PHP_EOL;
        return $this->cacheManager->get($name);

    }

    public
    function x_getStatementDescriptors(string $name, bool $forceReload = false): ?array
    {
        if ($forceReload || !$this->cacheManager || !$this->cacheManager?->has($name)) {
            $descriptors = [];
            $subqueries = [];
            $xml = $this->loadXml($name, $subqueries);

            foreach ($xml['statements'] as $statement) { //todo: adaptar para permitir definiciones de subqueries inline!
//var_dump($statement);
                $variants = array_intersect_key($statement['metadata'], array_flip($this->variantsMembers));
                $subquery_all_variants = [];
                // echo 'VARIANTS -> ', var_export($variants, true), PHP_EOL;
                //por cada variante busco los posibles subqueries
                //en el $statement busco con el path del subquery y reeemplazo con el correspondiente a la variante
                // si la variante es "*" y hay varios subqueries genero un descriptor para cada uno como si fueran variantes del select original
                //si no hay variante en el subquery disparo error?? o no cargo el descriptor?
                if (isset($xml['subqueries']) && !empty($xml['subqueries'])) {
                    // Por cada sub query ->
                    foreach ($xml['subqueries'] as $path => $subquery) {
                        echo 'SUBQUERY -> ', $path, ' ** ', var_export(array_keys($subquery), true), PHP_EOL;

                        // Por cada variante del subquery ->
                        foreach ($subquery['statements'] as $descriptor) {
                            echo 'ENQUEUE SUBQUERY -> ', var_export($path, true), PHP_EOL;
                            $subqueries[(string)$path] = [];
                            //Busco los identificadors de variantes en el descriptor
                            //(Cada variante puede referirse a varios identificadores, por ejemplo la primera variante del loop puede referirse
                            // al tag "client" con valor 1,5 y 7 y la segunda variante al tag "client" con valor 2,6 y 8)
                            // El descriptor padre debe utilizar la variante correspondiente al identificador del subquery
                            $subquery_variants = array_intersect_key($descriptor['metadata'], array_flip($this->variantsMembers));
                            echo 'ENQUEUE SUBQUERY VARIANTS -> ', var_export($subquery_variants, true), PHP_EOL;
                            $sKey = array_keys($subquery_variants);
                            echo 'ENQUEUE SUBQUERY VARIANTS $sKey -> ', var_export($sKey, true), PHP_EOL;
                            $sKey = array_shift($sKey);
                            echo 'ENQUEUE SUBQUERY VARIANTS $sKey 2 -> ', var_export($sKey, true), PHP_EOL;
                            $subquery_variants = $subquery_variants[$sKey];
                            echo 'ENQUEUE SUBQUERY VARIANTS FINAL -> ', var_export($subquery_variants, true), PHP_EOL;
                            if (!is_array($subquery_variants)) {
                                $subquery_variants = [$subquery_variants];
                            }
                            if (empty($subquery_all_variants)) {
                                $subquery_all_variants = $subquery_variants;
                            } else {
                                $subquery_all_variants = array_intersect($subquery_all_variants, $subquery_variants);
                            }

                            //Si no hay variantes estimo que la misma es soportada en todos los casos del descriptor padre
                            if (empty($subquery_variants)) {
                                /*   $subqueries[$path]['*'] = ['descriptor' => $descriptor];
                                   if (isset($subquery['arguments']) && !empty($subquery['arguments'])) {
                                       $subqueries[$path]['arguments'] = $subquery['arguments'];
                                   }*/
                                $subquery_variants[] = '*';
                            }
                            foreach ($subquery_variants as $variant) {
                                // echo 'SUBQUERY SEARCH FOR ', $sKey ,' IN SUBQUERY VARIANT -> ', $variant, PHP_EOL;
                                $descriptor['metadata'][$sKey] = $variant;
                                $subqueries[$path][$variant] = ['descriptor' => $descriptor];
                                if (!empty($subquery['arguments'])) {
                                    $subqueries[$path][$variant]['arguments'] = $subquery['arguments'];
                                }
                            }
                        }
                    }
                }
                //var_dump($subqueries);
                $descriptorClass = Descriptors::fromName($statement['type'] ?? 'select');

                $key = array_keys($variants);
                $key = array_shift($key);
                $variants = $variants[$key] ?? ['*'];
                if (is_string($variants)) {
                    $variants = [$variants];
                }
                //var_dump($key);
                // var_dump(array_values($variants))
                if (count($variants) === 1 && $variants[0] === '*' && count($subquery_all_variants) > 0) {
                    $variants = $subquery_all_variants;
                }
                if (!empty($subqueries)) {
                    echo 'ALL SUBQUERIES -> ', count($subqueries), ' * ', PHP_EOL, implode(PHP_EOL, array_keys($subqueries)), PHP_EOL;
                }
                foreach ($variants as $variant) {
                    $descriptor = $statement;
                    $descriptor['metadata'][$key] = $variant;
                    echo 'VARIANT -> ', $variant, PHP_EOL;

                    if (!empty($subqueries)) {
                        echo 'SUBQUERIES -> ', count($subqueries), ' * ', PHP_EOL, implode(PHP_EOL, array_keys($subqueries)), PHP_EOL;
                        foreach (array_reverse($subqueries) as $path => $subquery) {
                            $sub_desc = $subquery[$variant] ?? null;
                            echo "SUBQUERY PATH $path exists? -> ", ArrayUtilities::pathExisits($descriptor, $path) ? var_export(ArrayUtilities::getArrayValueByPath($descriptor, $path), true) : 'NO', PHP_EOL;
                            //echo 'SUBQUERY -> ', $path, ' VARIANT:: ', $variant, ' ¿Empty? -> ', var_export(empty($sub_desc), true), PHP_EOL;;
                            echo 'ADDED IS EMPTY? ', var_export(empty(ArrayUtilities::getArrayValueByPath($descriptor, $path)), true), PHP_EOL, var_export($sub_desc, true), PHP_EOL;

                            if ($sub_desc && is_array($sub_desc)) {
                                // echo 'SUBQUERY VALUE setArrayValueByPath -> ', $path, ' is list? ', var_export(array_is_list($sub_desc), true), PHP_EOL;
                                ArrayUtilities::setArrayValueByPath($descriptor, $path, $sub_desc);
                            } else {
                                $path = substr($path, 0, strrpos($path, '.'));
                                //echo 'SUBQUERY VALUE unsetByPath -> ', $path, PHP_EOL, PHP_EOL;
                                ArrayUtilities::unsetByPath($descriptor, $path);
                            }
                            // echo 'SUBQUERY PATH VALUE -> ', $path, PHP_EOL, var_export(self::getArrayValueByPath($descriptor, $path), true), PHP_EOL;
                        }
                        // echo 'SUBQUERIES  ', var_export($subqueries, true), PHP_EOL;
                    }
                    echo 'NEW INSTANCE FROM ', $descriptorClass, PHP_EOL;
                    echo ' -> ', var_export(ArrayUtilities::getArrayPathsByValue($descriptor, 'subquery', [], false), true), PHP_EOL;
                    //  var_dump($descriptor);
                    $descriptors[] = new $descriptorClass($descriptor);
                    //   var_dump($statement);
                    //$variantDescriptor = array_find($statement['metadata'], fn($v) => $v['name'] === $variant['name']);
                }
            }
            $this->cacheManager?->set($name, $descriptors);
            return $descriptors;
        }
        echo 'CACHE HIT -> ', $name, PHP_EOL;
        return $this->cacheManager->get($name);

    }

    /**
     * @throws \JsonException
     */
    public function getStatementCollection(string $name, bool $forceReload = false): ?StatementCollection
    {
        return new StatementCollection(...$this->getStatementDescriptors($name, $forceReload));
    }

}
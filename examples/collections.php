<?php

declare(strict_types=1);

/**
 * -----------------------------------------------------------------------------
 * Example: Using global collection aliases in Ivi.php
 * -----------------------------------------------------------------------------
 *
 * This example demonstrates the usage of the globally available collection
 * helpers (`vector`, `hashmap`, `hashset`, `str`) defined in
 * `core/Support/Helpers.php`.
 *
 * Each helper returns a strongly-typed data structure inspired by C++ STL:
 * - Vector<T>  : dynamic array with sequential access
 * - HashMap<K,V> : associative key/value map
 * - HashSet<T> : unique unordered set
 * - Str : fluent string manipulation wrapper
 *
 * -----------------------------------------------------------------------------
 * To run:
 * -----------------------------------------------------------------------------
 * php examples/collections.php
 * -----------------------------------------------------------------------------
 */

require __DIR__ . '/../vendor/autoload.php';

echo "\n=== Ivi.php Global Collections Example ===\n\n";

/* -------------------------------------------------------------------------- */
/* 1. Vector Example                                                          */
/* -------------------------------------------------------------------------- */

$v = vector([10, 20, 30]);
$v->push(40);
$v->push(50);

dump([
    'count' => $v->count(),
    'third' => $v->get(2),
    'all'   => iterator_to_array($v),
], ['title' => 'Vector Contents']);


/* -------------------------------------------------------------------------- */
/* 2. HashMap Example                                                         */
/* -------------------------------------------------------------------------- */

$m = hashmap([
    'framework' => 'Ivi.php',
    'language'  => 'PHP',
]);
$m->put('version', '1.0.0');
$m->put('status', 'Stable');

dump([
    'version' => $m->get('version'),
    'keys'    => array_keys($m->toArray()),
    'values'  => array_values($m->toArray()),
], ['title' => 'HashMap Contents']);


/* -------------------------------------------------------------------------- */
/* 3. HashSet Example                                                         */
/* -------------------------------------------------------------------------- */

$s = hashset(['apple', 'banana', 'orange']);
$s->add('mango');
$s->add('apple'); // duplicate ignored
$s->remove('banana');

dump([
    'count' => $s->count(),
    'contains_mango' => $s->has('mango'),
    'all' => iterator_to_array($s),
], ['title' => 'HashSet Contents']);


/* -------------------------------------------------------------------------- */
/* 4. String Wrapper Example                                                  */
/* -------------------------------------------------------------------------- */

$t = str("  hello Ivi  ")
    ->trim()
    ->upper()
    ->replace("IVI", "Ivi.php");

dump([
    'original' => "  hello Ivi  ",
    'transformed' => $t->toString(),
    'contains_php' => $t->contains("PHP"),
], ['title' => 'String Manipulation']);


/* -------------------------------------------------------------------------- */
/* End of example                                                             */
/* -------------------------------------------------------------------------- */

echo "\n✅ All collection alias examples executed successfully.\n\n";

<?php
require __DIR__ . '/../vendor/autoload.php';
$v = vector([1, 2, 3]);
$v->push(4);

$m = hashmap(['name' => 'Ivi']);
$m->put('version', '1.0');

$s = hashset(['apple', 'banana']);
$s->add('orange');

$t = str(" Hello Ivi ")->trim()->upper();

dump($v);
dump($m->toArray());
dump(iterator_to_array($s));
dump($t->toString());

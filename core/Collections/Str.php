<?php

namespace Ivi\Core\Collections;

final class Str
{
    public function __construct(private string $s) {}

    public static function of(string $s): self
    {
        return new self($s);
    }
    public function toString(): string
    {
        return $this->s;
    }

    public function trim(): self
    {
        $this->s = \trim($this->s);
        return $this;
    }
    public function lower(): self
    {
        $this->s = \mb_strtolower($this->s);
        return $this;
    }
    public function upper(): self
    {
        $this->s = \mb_strtoupper($this->s);
        return $this;
    }
    public function replace(string $search, string $rep): self
    {
        $this->s = \str_replace($search, $rep, $this->s);
        return $this;
    }
    public function contains(string $needle): bool
    {
        return \mb_strpos($this->s, $needle) !== false;
    }
}

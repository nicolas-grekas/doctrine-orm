<?php

declare(strict_types=1);

namespace Doctrine\ORM\Mapping;

use ReflectionProperty;

final class ReflectionLazyProperty extends ReflectionProperty
{
    public function __construct(
        private readonly ReflectionProperty $wrappedProperty,
    ) {
        parent::__construct($wrappedProperty->class, $wrappedProperty->name);
    }

    public function getValue(object|null $object = null): mixed
    {
        return $this->wrappedProperty->getValue($object);
    }

    public function setValue(object|null $object, mixed $value = null): void
    {
        if (\is_object($object)) {
            $r = \ReflectionLazyObject::fromInstance($object);

            if ($r) {
                $r->skipProperty($this->name, $this->class);
            }
        }

        $this->wrappedProperty->setValue($object, $value);
    }
}

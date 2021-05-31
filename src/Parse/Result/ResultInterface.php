<?php


namespace Mutoco\Mplus\Parse\Result;


interface ResultInterface
{
    public function getTag(): string;

    public function getAttributes(): array;

    public function getValue();
}

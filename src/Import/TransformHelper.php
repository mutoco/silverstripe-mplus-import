<?php


namespace Mutoco\Mplus\Import;


class TransformHelper
{
    public static function extractDate($string) : string
    {
        return date('Y-m-d', strtotime($string));
    }
}

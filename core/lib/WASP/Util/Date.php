<?php

namespace WASP\Util;

use DateTimeInterface;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use DateInterval;
use InvalidArgumentException;
use WASP\Util\Functions as WF;

class Date
{
    const SECONDS_IN_MINUTE =                 60;
    const SECONDS_IN_HOUR   =            60 * 60;
    const SECONDS_IN_DAY    =       24 * 60 * 60;
    const SECONDS_IN_WEEK   =   7 * 24 * 60 * 60;
    const SECONDS_IN_MONTH  =  30 * 24 * 60 * 60;
    const SECONDS_IN_YEAR   = 365 * 24 * 60 * 60;

    public static function copy($str)
    {
        if ($str instanceof DateTime)
            return DateTime::createFromFormat(DateTime::ATOM, $str->format(DateTime::ATOM));
        if ($str instanceof DateTimeImmutable)
            return DateTimeImmutable::createFromFormat(DateTime::ATOM, $str->format(DateTime::ATOM));

        if ($str instanceof DateInterval)
        {
            $fmt = 'P' . $str->y . 'Y' . $str->m . 'M' . $str->d . 'DT' . $str->h . 'H' . $str->i . 'M' . $str->s . 'S';
            $int = new DateInterval($fmt);
            $int->invert = $str->invert;
            $int->days = $str->days;
            return $int;
        }

        throw new InvalidArgumentException("Invalid argument: " . WF::str($str));
    }

    public static function createFromFloat(float $timestamp, DateTimeZone $zone = null)
    {
        $timestamp = sprintf("%.6f", $timestamp); 
        $dt = DateTime::createFromFormat('U.u', $timestamp);
        $dt->setTimeZone($zone ?? new DateTimeZone(date_default_timezone_get()));
        return $dt;
    }
    
    public static function dateToFloat(DateTimeInterface $date)
    {
        return (float)$date->format('U.u');
    }

    public static function compareInterval(DateInterval $l, DateInterval $r)
    {
        $now = new \DateTimeImmutable();
        $a = $now->add($l);
        $b = $now->add($r);

        if ($a < $b)
            return -1;
        if ($a > $b)
            return 1;
        return 0;
    }

    public static function lessThan(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) < 0;
    }

    public static function lessThanOrEqual(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) <= 0;
    }

    public static function equal(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) === 0;
    }

    public static function greaterThan(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) > 0;
    }

    public static function greaterThanOrEqual(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) >= 0;
    }

    public static function isBefore(DateTimeInterface $l, DateTimeInterface $r)
    {
        return $l < $r;
    }

    public static function isAfter(DateTimeInterface $l, DateTimeInterface $r)
    {
        return $l > $r;
    }

    public static function isPast(DateTimeInterface $l)
    {
        $now = new DateTime();
        return $l < $now;
    }

    public static function isFuture(DateTimeInterface $l)
    {
        $now = new DateTime();
        return $l > $now;
    }

    public static function now()
    {
        return createFromFloat(microtime(true));
    }

    public static function diff(DateTimeInterface $l, DateTimeInterface $r)
    {
        $lf = self::dateToFloat($l);
        $rf = self::dateToFloat($r);

        return $lf - $rf;
    }
}

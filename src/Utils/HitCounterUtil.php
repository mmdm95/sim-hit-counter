<?php

namespace Sim\HitCounter\Utils;

use DateTime;
use Exception;

class HitCounterUtil
{
    const UNKNOWN_IP_ADDRESS = 'unknown';

    /**
     * @param int $time
     * @return int
     */
    public static function getTodayStartOfTime(int $time): int
    {
        return strtotime("today", $time);
    }

    /**
     * @param int $time
     * @return int
     */
    public static function getTodayEndOfTime(int $time): int
    {
        return strtotime("tomorrow, -1 second", $time);
    }

    /**
     * @return int
     */
    public static function getYesterdayStartTime(): int
    {
        return strtotime("yesterday");
    }

    /**
     * @return int
     */
    public static function getYesterdayEndTime(): int
    {
        return strtotime("today, -1 second");
    }

    /**
     * @return int
     */
    public static function getTodayStartTime(): int
    {
        return strtotime("today");
    }

    /**
     * @return int
     */
    public static function getTodayEndTime(): int
    {
        return strtotime("tomorrow, -1 second");
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getLastWeekStartTime(): int
    {
        $d = new DateTime('last week');
        $d->modify('today');
        return $d->getTimestamp();
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getLastWeekEndTime(): int
    {
        $d = new DateTime('last week, +6 days');
        $d->modify('tomorrow, -1 second');
        return $d->getTimestamp();
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getThisWeekStartTime(): int
    {
        $d = new DateTime('this week');
        $d->modify('today');
        return $d->getTimestamp();
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getThisWeekEndTime(): int
    {
        $d = new DateTime('this week, +6 days');
        $d->modify('tomorrow, -1 second');
        return $d->getTimestamp();
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getLastMonthStartTime(): int
    {
        $d = new DateTime('first day of last month');
        $d->modify('today');
        return $d->getTimestamp();
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getLastMonthEndTime(): int
    {
        $d = new DateTime('last day of last month');
        $d->modify('tomorrow, -1 second');
        return $d->getTimestamp();
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getThisMonthStartTime(): int
    {
        $d = new DateTime('first day of this month');
        $d->modify('today');
        return $d->getTimestamp();
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getThisMonthEndTime(): int
    {
        $d = new DateTime('last day of this month');
        $d->modify('tomorrow, -1 second');
        return $d->getTimestamp();
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getLastYearStartTime(): int
    {
        $d = new DateTime('-1 year, first day of january');
        return $d->getTimestamp();
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getLastYearEndTime(): int
    {
        $d = new DateTime('this year, first day of january, -1 second');
        return $d->getTimestamp();
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getThisYearStartTime(): int
    {
        $d = new DateTime('first day of january');
        return $d->getTimestamp();
    }

    /**
     * @return int
     * @throws Exception
     */
    public static function getThisYearEndTime(): int
    {
        $d = new DateTime('next year, first day of january, -1 second');
        return $d->getTimestamp();
    }

    /**
     * Retrieves the best guess of the client's actual IP address.
     * Takes into account numerous HTTP proxy headers due to variations
     * in how different ISPs handle IP addresses in headers between hops.
     *
     * @see https://stackoverflow.com/questions/1634782/what-is-the-most-accurate-way-to-retrieve-a-users-correct-ip-address-in-php
     * @return string
     */
    public static function getIPAddress(): string
    {
        foreach (array('HTTP_CLIENT_IP',
                     'HTTP_X_FORWARDED_FOR',
                     'HTTP_X_FORWARDED',
                     'HTTP_X_CLUSTER_CLIENT_IP',
                     'HTTP_FORWARDED_FOR',
                     'HTTP_FORWARDED',
                     'REMOTE_ADDR') as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); // just to be safe

                    if (filter_var(
                            $ip,
                            FILTER_VALIDATE_IP,
                            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                        ) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return self::UNKNOWN_IP_ADDRESS;
    }
}
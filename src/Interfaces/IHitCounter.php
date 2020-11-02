<?php

namespace Sim\HitCounter\Interfaces;

interface IHitCounter
{
    const TIME_DAILY = 1;
    const TIME_WEEKLY = 32;
    const TIME_MONTHLY = 512;
    const TIME_YEARLY = 4096;
    const TIME_ALL = self::TIME_DAILY | self::TIME_WEEKLY | self::TIME_MONTHLY | self::TIME_YEARLY;

    const TYPE_DAILY = 1;
    const TYPE_WEEKLY = 32;
    const TYPE_MONTHLY = 512;
    const TYPE_YEARLY = 4096;
    const TYPE_ALL = self::TYPE_DAILY | self::TYPE_WEEKLY | self::TYPE_MONTHLY | self::TYPE_YEARLY;

    /**
     * @return static
     */
    public function runConfig();

    /**
     * @param string $url
     * @return static
     */
    public function hitDaily(string $url);

    /**
     * @param string $url
     * @return static
     */
    public function hitWeekly(string $url);

    /**
     * @param string $url
     * @return static
     */
    public function hitMonthly(string $url);

    /**
     * @param string $url
     * @return static
     */
    public function hitYearly(string $url);

    /**
     * @param string $url
     * @param int $hit_at_times
     * @return static
     */
    public function hit(string $url, int $hit_at_times = self::TIME_ALL);

    /**
     * Get total view count and total unique view count
     * and return below structure:
     * [
     *   'view_count' => total view count,
     *   'unique_view_count' => total unique view count,
     * ]
     *
     * @param string|null $url
     * @param int $from_time
     * @param int $to_time
     * @param int $hit_with_types
     * @return array
     */
    public function report(?string $url, int $from_time, int $to_time, int $hit_with_types = self::TYPE_ALL): array;

    /**
     * @param string|null $url
     * @param string $where
     * @param array $bind_values
     * @return array
     */
    public function freeReport(?string $url, string $where, array $bind_values = []): array;

    /**
     * Save json file for daily hits
     *
     * Note:
     *   This method store the day before today
     *
     * @param string $path_to_store
     * @param bool $delete_from_database
     * @return bool
     */
    public function saveDailyHits(string $path_to_store, bool $delete_from_database = false): bool;

    /**
     * Save json file for weekly hits
     *
     * Note:
     *   This method store the week before this week
     *
     * @param string $path_to_store
     * @param bool $delete_from_database
     * @return bool
     */
    public function saveWeeklyHits(string $path_to_store, bool $delete_from_database = false): bool;

    /**
     * Save json file for monthly hits
     *
     * Note:
     *   This method store the month before this month
     *
     * @param string $path_to_store
     * @param bool $delete_from_database
     * @return bool
     */
    public function saveMonthlyHits(string $path_to_store, bool $delete_from_database = false): bool;

    /**
     * Save json file for yearly hits
     *
     * Note:
     *   This method store the year before this year
     *
     * @param string $path_to_store
     * @param bool $delete_from_database
     * @return bool
     */
    public function saveYearlyHits(string $path_to_store, bool $delete_from_database = false): bool;

    /**
     * Save json file for hits
     *
     * @param string $path_to_store
     * @param int $hit_at_times
     * @param bool $delete_from_database
     * @return bool
     */
    public function saveHits(string $path_to_store, int $hit_at_times = self::TIME_ALL, bool $delete_from_database = false): bool;
}
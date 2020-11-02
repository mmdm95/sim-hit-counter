<?php

namespace Sim\HitCounter;

use Exception;
use Jenssegers\Agent\Agent;
use PDO;
use Sim\HitCounter\Config\ConfigParser;
use Sim\HitCounter\Exceptions\ConfigException;
use Sim\HitCounter\Helpers\DB;
use Sim\HitCounter\Interfaces\IDBException;
use Sim\HitCounter\Interfaces\IHitCounter;
use Sim\HitCounter\Utils\HitCounterUtil;

class HitCounter implements IHitCounter
{
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var DB
     */
    protected $db;

    /**
     * @var Agent
     */
    protected $agent;

    /**
     * @var bool
     */
    protected $test_mode = false;

    /**
     * @var array $default_config
     */
    protected $default_config = [];

    /**
     * @var ConfigParser
     */
    protected $config_parser;

    /**
     * @var string
     */
    protected $cookie_name = '__DO_NOT_DELETE_this_please_';

    /********** table keys **********/

    /**
     * @var string
     */
    protected $hits_key = 'hits';

    /**
     * HitCounter constructor.
     * @param PDO $pdo_instance
     * @param array|null $config
     * @param bool $test_mode
     * @throws IDBException
     */
    public function __construct(PDO $pdo_instance, ?array $config = null, bool $test_mode = false)
    {
        $this->pdo = $pdo_instance;
        $this->db = new DB($pdo_instance);
        $this->agent = new Agent();

        $this->test_mode = $test_mode;

        // load default config from _Config dir
        $this->default_config = include __DIR__ . '/_Config/config.php';
        if (!is_null($config)) {
            $this->setConfig($config);
        } else {
            $this->setConfig($this->default_config);
        }

    }

    /**
     * @param array $config
     * @param bool $merge_config
     * @return static
     * @throws IDBException
     */
    public function setConfig(array $config, bool $merge_config = false)
    {
        if ($merge_config) {
            if (!empty($config)) {
                $config = array_merge_recursive($this->default_config, $config);
            }
        }

        // parse config
        $this->config_parser = new ConfigParser($config, $this->pdo);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws ConfigException
     */
    public function runConfig()
    {
        $this->config_parser->up();
        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function hitDaily(string $url)
    {
        // we don't need crawlers to hit
        if (!$this->isHitAllowed()) return $this;

        $this->hitIt($url, HitCounterUtil::getTodayStartTime(), HitCounterUtil::getTodayEndTime(), IHitCounter::TYPE_DAILY);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     * @throws Exception
     */
    public function hitWeekly(string $url)
    {
        // we don't need crawlers to hit
        if (!$this->isHitAllowed()) return $this;

        $this->hitIt($url, HitCounterUtil::getThisWeekStartTime(), HitCounterUtil::getThisWeekEndTime(), IHitCounter::TYPE_WEEKLY);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     * @throws Exception
     */
    public function hitMonthly(string $url)
    {
        // we don't need crawlers to hit
        if (!$this->isHitAllowed()) return $this;

        $this->hitIt($url, HitCounterUtil::getThisMonthStartTime(), HitCounterUtil::getThisMonthEndTime(), IHitCounter::TYPE_MONTHLY);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     * @throws Exception
     */
    public function hitYearly(string $url)
    {
        // we don't need crawlers to hit
        if (!$this->isHitAllowed()) return $this;

        $this->hitIt($url, HitCounterUtil::getThisYearStartTime(), HitCounterUtil::getThisYearEndTime(), IHitCounter::TYPE_YEARLY);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function hit(string $url, int $hit_at_times = self::TIME_ALL)
    {
        // we don't need crawlers to hit
        if (!$this->isHitAllowed()) return $this;

        // NOTE: Check priority is important
        if (self::TIME_DAILY & $hit_at_times) {
            $this->hitDaily($url);
        }
        if (self::TIME_WEEKLY & $hit_at_times) {
            $this->hitWeekly($url);
        }
        if (self::TIME_MONTHLY & $hit_at_times) {
            $this->hitMonthly($url);
        }
        if (self::TIME_YEARLY & $hit_at_times) {
            $this->hitYearly($url);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function report(?string $url, int $from_time, int $to_time, int $hit_with_types = self::TYPE_ALL): array
    {
        return $this->reportIt($url, $from_time, $to_time, $hit_with_types);
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function freeReport(?string $url, string $where, array $bind_values = []): array
    {
        return $this->reportIt($url, null, null, null, $where, $bind_values);
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function saveDailyHits(string $path_to_store, bool $delete_from_database = false): bool
    {
        return $this->saveIt(
            $path_to_store,
            $delete_from_database,
            HitCounterUtil::getYesterdayEndTime(),
            HitCounterUtil::getYesterdayStartTime(),
            IHitCounter::TYPE_DAILY
        );
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     * @throws Exception
     */
    public function saveWeeklyHits(string $path_to_store, bool $delete_from_database = false): bool
    {
        return $this->saveIt(
            $path_to_store,
            $delete_from_database,
            HitCounterUtil::getLastWeekEndTime(),
            HitCounterUtil::getLastWeekStartTime(),
            IHitCounter::TYPE_WEEKLY
        );
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     * @throws Exception
     */
    public function saveMonthlyHits(string $path_to_store, bool $delete_from_database = false): bool
    {
        return $this->saveIt(
            $path_to_store,
            $delete_from_database,
            HitCounterUtil::getLastMonthEndTime(),
            HitCounterUtil::getLastMonthStartTime(),
            IHitCounter::TYPE_MONTHLY
        );
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     * @throws Exception
     */
    public function saveYearlyHits(string $path_to_store, bool $delete_from_database = false): bool
    {
        return $this->saveIt(
            $path_to_store,
            $delete_from_database,
            HitCounterUtil::getLastYearEndTime(),
            HitCounterUtil::getLastYearStartTime(),
            IHitCounter::TYPE_YEARLY
        );
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function saveHits(string $path_to_store, int $hit_at_times = self::TIME_ALL, bool $delete_from_database = false): bool
    {
        $status = true;
        $isValidTime = false;

        if (self::TIME_DAILY & $hit_at_times) {
            $status = $status && $this->saveDailyHits($path_to_store, $delete_from_database);
            $isValidTime = true;
        }
        if (self::TIME_WEEKLY & $hit_at_times) {
            $status = $status && $this->saveWeeklyHits($path_to_store, $delete_from_database);
            $isValidTime = true;
        }
        if (self::TIME_MONTHLY & $hit_at_times) {
            $status = $status && $this->saveMonthlyHits($path_to_store, $delete_from_database);
            $isValidTime = true;
        }
        if (self::TIME_YEARLY & $hit_at_times) {
            $status = $status && $this->saveYearlyHits($path_to_store, $delete_from_database);
            $isValidTime = true;
        }

        return $status && $isValidTime;
    }

    /**
     * @param string $url
     * @param int $from_time
     * @param int $to_time
     * @param int $type
     * @throws IDBException
     */
    protected function hitIt(string $url, int $from_time, int $to_time, int $type)
    {
        $hitColumns = $this->config_parser->getTablesColumn($this->hits_key);

        $where = "{$this->db->quoteName($hitColumns['url'])}=:url " .
            "AND {$this->db->quoteName($hitColumns['from_time'])}>=:from_time " .
            "AND {$this->db->quoteName($hitColumns['to_time'])}<=:to_time " .
            "AND {$this->db->quoteName($hitColumns['type'])}=:type";
        $bindValues = [
            'url' => $url,
            'from_time' => $from_time,
            'to_time' => $to_time,
            'type' => $type,
        ];

        $hitCount = $this->db->count($this->hits_key, $where, $bindValues);

        if (0 !== $hitCount) {
            $this->db->update(
                $this->hits_key,
                [],
                $where,
                $bindValues,
                [
                    $this->db->quoteName($hitColumns['view_count']) => "{$this->db->quoteName($hitColumns['view_count'])}+1",
                ]
            );
        } else {
            $this->db->insert(
                $this->hits_key,
                [
                    $this->db->quoteName($hitColumns['url']) => $url,
                    $this->db->quoteName($hitColumns['type']) => $type,
                    $this->db->quoteName($hitColumns['view_count']) => 1,
                    $this->db->quoteName($hitColumns['from_time']) => $from_time,
                    $this->db->quoteName($hitColumns['to_time']) => $to_time,
                ]
            );
        }

        // increment unique hit count if it is a unique view
        $this->incrementUniqueCount(
            $url,
            $from_time,
            $to_time,
            $type,
            $where,
            $bindValues
        );
    }

    /**
     * @param string|null $url
     * @param int $from_time
     * @param int $to_time
     * @param string|null $hit_with_types
     * @param string|null $extra_where
     * @param array $extra_bind_values
     * @return array
     * @throws IDBException
     */
    protected function reportIt(
        ?string $url = null,
        ?int $from_time = null,
        ?int $to_time = null,
        ?string $hit_with_types = null,
        ?string $extra_where = null,
        array $extra_bind_values = []
    ): array
    {
        $hitColumns = $this->config_parser->getTablesColumn($this->hits_key);
        $viewCount = 0;
        $uniqueViewCount = 0;
        $where = '';
        $bindValues = [];

        if (!empty($url)) {
            $where .= "{$this->db->quoteName($hitColumns['url'])}=:url";
            $bindValues['url'] = $url;
        }
        if (!empty($from_time)) {
            if (!empty($where)) {
                $where .= " AND ";
            }
            $where .= "{$this->db->quoteName($hitColumns['from_time'])}>=:from_time";
            $bindValues['from_time'] = $from_time;
        }
        if (!empty($to_time)) {
            if (!empty($where)) {
                $where .= " AND ";
            }
            $where .= "{$this->db->quoteName($hitColumns['to_time'])}<=:to_time";
            $bindValues['to_time'] = $to_time;
        }
        if (!empty($hit_with_types)) {
            $times = [];
            if (self::TYPE_DAILY & $hit_with_types) {
                $times[] = self::TYPE_DAILY;
            }
            if (self::TYPE_WEEKLY & $hit_with_types) {
                $times[] = self::TYPE_WEEKLY;
            }
            if (self::TYPE_MONTHLY & $hit_with_types) {
                $times[] = self::TYPE_MONTHLY;
            }
            if (self::TYPE_YEARLY & $hit_with_types) {
                $times[] = self::TYPE_YEARLY;
            }

            if (count($times)) {
                if (!empty($where)) {
                    $where .= " AND ";
                }
                $where .= "{$this->db->quoteName($hitColumns['type'])} IN (";

                foreach ($times as $key => $t) {
                    $k = "time_$key";
                    $where .= ":$k,";
                    $bindValues[$k] = $t;
                }
                $where = rtrim($where, ',');
                $where .= ")";
            }
        }
        if (!empty($extra_where)) {
            if (!empty($where)) {
                $where .= " AND ";
            }
            $where .= "({$extra_where})";
            $bindValues = array_merge($bindValues, $extra_bind_values);
        }

        $res = $this->db->getFrom(
            $this->hits_key,
            $where,
            [
                "SUM({$this->db->quoteName($hitColumns['view_count'])}) AS view_count",
                "SUM({$this->db->quoteName($hitColumns['unique_view_count'])}) AS unique_view_count",
            ],
            $bindValues
        );

        if (count($res)) {
            $res = $res[0];
            $viewCount = (int)$res['view_count'];
            $uniqueViewCount = (int)$res['unique_view_count'];
        }

        return [
            'view_count' => $viewCount,
            'unique_view_count' => $uniqueViewCount,
        ];
    }

    /**
     * @param string $path_to_store
     * @param bool $delete_from_database
     * @param int $from_time
     * @param int $to_time
     * @param string $type
     * @return bool
     * @throws IDBException
     */
    protected function saveIt(string $path_to_store, bool $delete_from_database, int $from_time, int $to_time, string $type): bool
    {
        $hitColumns = $this->config_parser->getTablesColumn($this->hits_key);
        $columns = array_map(function ($val) {
            return $this->db->quoteName($val);
        }, $hitColumns);
        $where = "{$this->db->quoteName($hitColumns['from_time'])}>=:min_time " .
            "AND {$this->db->quoteName($hitColumns['from_time'])}=<:max_time " .
            "AND {$this->db->quoteName($hitColumns['type'])}=:type";
        $binValues = [
            'min_time' => $to_time,
            'max_time' => $from_time,
            'type' => $type,
        ];

        // get all results from database
        $res = $this->db->getFrom(
            $this->hits_key,
            $where,
            $columns,
            $binValues
        );

        // delete hits from database if needed
        if ($delete_from_database) {
            $this->db->delete(
                $this->hits_key,
                $where,
                $binValues
            );
        }

        // add columns to results array
        $res['columns'] = $columns;

        // save results to specified file
        $status = file_put_contents($path_to_store, $res);

        return false !== $status;
    }

    /**
     * @param string $url
     * @param int $from_time
     * @param int $to_time
     * @param string $where
     * @param array $bind_values
     * @param int $type
     * @throws IDBException
     * @throws Exception
     */
    protected function incrementUniqueCount(
        string $url,
        int $from_time,
        int $to_time,
        int $type,
        string $where,
        array $bind_values
    )
    {
        $hitColumns = $this->config_parser->getTablesColumn($this->hits_key);
        $hashedName = $this->getHashedName($url);

        $time = $this->getCookieTime($type);
        $time = $time > 0 ? time() + $time : $time;

        if ($this->isUniqueHit($hashedName, $type, $from_time, $to_time, $time)) {
            // get previous cookie array
            $cookieValue = $this->getCookieArray() ?: [];

            // create new cookie data
            $newData = $this->prepareCookieValue($hashedName, $type, $cookieValue, [
                $hashedName => [
                    $type => [
                        'created_at' => time(),
                    ]
                ]
            ]);

            setcookie(
                $this->getCookieName(),
                $newData,
                $time,
                '/',
                null,
                null,
                true
            );
            $_COOKIE[$this->getCookieName()] = $newData;

            $this->db->update(
                $this->hits_key,
                [],
                $where,
                $bind_values,
                [
                    $this->db->quoteName($hitColumns['unique_view_count']) => "{$this->db->quoteName($hitColumns['unique_view_count'])}+1",
                ]
            );
        }
    }

    /**
     * @param string $hashed_name
     * @param int $type
     * @param int $from_time
     * @param int $to_time
     * @param int $cookie_time
     * @return bool
     */
    protected function isUniqueHit(string $hashed_name, int $type, int $from_time, int $to_time, int $cookie_time): bool
    {
        $data = $this->getCookieArray();

        // if the cookie is set and is OK
        if (empty($data)) {
            return true;
        }

        // if there is no record in cookie array
        if (!isset($data[$hashed_name][$type])) return true;

        // if created time is not set or not between of specific start and end time
        if (
            !isset($data[$hashed_name][$type]) ||
            !isset($data[$hashed_name][$type]['created_at']) ||
            $data[$hashed_name][$type]['created_at'] < $from_time ||
            $data[$hashed_name][$type]['created_at'] > $to_time
        ) {
            // get prepared data
            $preparedData = $this->prepareCookieValue($hashed_name, $type, $data);

            setcookie(
                $this->getCookieName(),
                $preparedData,
                $cookie_time,
                '/',
                null,
                null,
                true
            );
            $_COOKIE[$this->getCookieName()] = $preparedData;
            return true;
        }

        return false;
    }

    /**
     * @return array|null
     */
    protected function getCookieArray(): ?array
    {
        $cookie = $_COOKIE[$this->getCookieName()] ?? null;
        $decodedCookie = base64_decode($cookie);
        $data = json_decode($decodedCookie, true);

        if (empty($cookie) || false === $decodedCookie || is_null($data) || !is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * @param string $hashed_name
     * @param int $type
     * @param array $cookie_value
     * @param array $new_value
     * @return string
     */
    protected function prepareCookieValue(string $hashed_name, int $type, array $cookie_value, array $new_value = []): string
    {
        if (empty($new_value) || !isset($new_value[$hashed_name][$type])) {
            unset($cookie_value[$hashed_name][$type]);
        } else {
            $cookie_value[$hashed_name][$type] = $new_value[$hashed_name][$type] ?? [];
        }
        return base64_encode(json_encode($cookie_value));
    }

    /**
     * @return string
     */
    protected function getCookieName(): string
    {
        return md5($this->cookie_name);
    }

    /**
     * @param int $type
     * @return int
     * @throws Exception
     */
    protected function getCookieTime(int $type): int
    {
        $cookieTime = -3600;

        switch ($type) {
            case self::TIME_DAILY:
                $cookieTime = HitCounterUtil::getTodayEndTime() - time();
                break;
            case self::TIME_WEEKLY:
                $cookieTime = HitCounterUtil::getThisWeekEndTime() - time();
                break;
            case self::TIME_MONTHLY:
                $cookieTime = HitCounterUtil::getThisMonthEndTime() - time();
                break;
            case self::TIME_YEARLY:
                $cookieTime = HitCounterUtil::getThisYearEndTime() - time();
                break;
        }

        return $cookieTime;
    }

    /**
     * Delete hit cookie :)
     */
    protected function deleteHitCookie()
    {
        setcookie($this->getCookieName(), "", -3600, '/', null, null, true);
        unset($_COOKIE[$this->getCookieName()]);
    }

    /**
     * @return bool
     */
    protected function isHitAllowed(): bool
    {
        // if it's in test mode
        if ($this->test_mode) return true;
        return !$this->isCrawler()
            && HitCounterUtil::getIPAddress() !== HitCounterUtil::UNKNOWN_IP_ADDRESS
            && (
                !isset($_SERVER['HTTP_DNT'])
                || (
                    isset($_SERVER['HTTP_DNT'])
                    && $_SERVER['HTTP_DNT'] != "1"
                )
            );
    }

    /**
     * @param string $url
     * @return string
     */
    protected function getHashedName(string $url): string
    {
        return md5(HitCounterUtil::getIPAddress() . '_' . $url);
    }

    /**
     * @return bool
     */
    protected function isCrawler(): bool
    {
        return $this->agent->isRobot();
    }
}
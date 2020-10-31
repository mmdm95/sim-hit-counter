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

    /********** table keys **********/

    /**
     * @var string
     */
    protected $hits_key = 'hits';

    /**
     * @var string
     */
    protected $unique_hits_key = 'unique_hits';

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
    public function report(?string $url, int $from_time, int $to_time): array
    {
        $viewCount = 0;
        $uniqueViewCount = 0;

        $hitColumns = $this->config_parser->getTablesColumn($this->hits_key);

        $where = "{$this->db->quoteName($hitColumns['from_time'])}>=:from_time " .
            "AND {$this->db->quoteName($hitColumns['to_time'])}<=:to_time";
        $bindValues = [
            'from_time' => $from_time,
            'to_time' => $to_time,
        ];
        if (!empty($url)) {
            $where .= " AND {$this->db->quoteName($hitColumns['url'])}=:url";
            $bindValues['url'] = $url;
        }

        $res = $this->db->getFrom(
            $this->hits_key,
            $where,
            [
                "COUNT({$hitColumns['view_count']}) AS view_count",
                "COUNT({$hitColumns['unique_view_count']}) AS unique_view_count",
            ],
            $bindValues
        );

        if (count($res)) {
            $res = $res[0];
            $viewCount = $res['view_count'];
            $uniqueViewCount = $res['unique_view_count'];
        }

        return [
            'view_count' => $viewCount,
            'unique_view_count' => $uniqueViewCount,
        ];
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function saveDailyHits(string $path_to_store, bool $delete_from_database = true): bool
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
    public function saveWeeklyHits(string $path_to_store, bool $delete_from_database = true): bool
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
    public function saveMonthlyHits(string $path_to_store, bool $delete_from_database = true): bool
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
    public function saveYearlyHits(string $path_to_store, bool $delete_from_database = true): bool
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
    public function saveHits(string $path_to_store, int $hit_at_times = self::TIME_ALL, bool $delete_from_database = true): bool
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
     * @param string $type
     * @throws IDBException
     */
    protected function hitIt(string $url, int $from_time, int $to_time, string $type)
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
            $where,
            $bindValues,
            $type
        );
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
     * @param string $type
     * @throws IDBException
     */
    protected function incrementUniqueCount(
        string $url,
        int $from_time,
        int $to_time,
        string $where,
        array $bind_values,
        string $type
    )
    {
        $hitColumns = $this->config_parser->getTablesColumn($this->hits_key);
        $uniqueHitColumns = $this->config_parser->getTablesColumn($this->unique_hits_key);

        if ($this->isUniqueHit($this->getHashedName($url), $from_time, $to_time, $type)) {
            $device = $this->agent->device();
            $browser = $this->agent->browser();
            $platform = $this->agent->platform();
            $res = $this->db->insert(
                $this->unique_hits_key,
                [
                    $this->db->quoteName($uniqueHitColumns['hashed_name']) => $this->getHashedName($url),
                    $this->db->quoteName($uniqueHitColumns['type']) => $type,
                    $this->db->quoteName($uniqueHitColumns['device']) => $device,
                    $this->db->quoteName($uniqueHitColumns['browser']) => $browser,
                    $this->db->quoteName($uniqueHitColumns['platform']) => $platform,
                    $this->db->quoteName($uniqueHitColumns['created_at']) => time(),
                ]
            );

            if ($res) {
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
    }

    /**
     * @param string $hashed_name
     * @param int $from_time
     * @param int $to_time
     * @param string $type
     * @return bool
     * @throws IDBException
     */
    protected function isUniqueHit(string $hashed_name, int $from_time, int $to_time, string $type): bool
    {
        $uniqueHitColumns = $this->config_parser->getTablesColumn($this->unique_hits_key);

        // delete all hashed hits before current time start
        $this->clearHashedNames($from_time, $type);

        $count = $this->db->count(
            $this->unique_hits_key,
            "{$this->db->quoteName($uniqueHitColumns['hashed_name'])}=:hn " .
            "AND {$this->db->quoteName($uniqueHitColumns['created_at'])}>=:from_time " .
            "AND {$this->db->quoteName($uniqueHitColumns['created_at'])}<=:to_time " .
            "AND {$this->db->quoteName($uniqueHitColumns['type'])}=:type",
            [
                'hn' => $hashed_name,
                'from_time' => $from_time,
                'to_time' => $to_time,
                'type' => $type,
            ]
        );

        return 0 === $count;
    }

    /**
     * @param int $from_time
     * @param string $type
     * @throws IDBException
     */
    protected function clearHashedNames(int $from_time, string $type)
    {
        $uniqueHitColumns = $this->config_parser->getTablesColumn($this->unique_hits_key);

        $this->db->delete(
            $this->unique_hits_key,
            "{$this->db->quoteName($uniqueHitColumns['created_at'])}<:created_at " .
            "AND {$this->db->quoteName($uniqueHitColumns['type'])}=:type",
            [
                'created_at' => $from_time,
                'type' => $type,
            ]
        );
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
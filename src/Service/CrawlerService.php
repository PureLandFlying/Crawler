<?php


namespace App\Service;


use Symfony\Component\DependencyInjection\ContainerInterface;

class CrawlerService
{
    public static $version = '1.0';

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function clearCache() {
        $redis = $this->getRedisService();
        $redis->del('spider:product-links-queue');
        $redis->del('spider:product-all');
        $redis->set('spider:list-running', '');
        $redis->set('spider:product-links-count', 0);
        $redis->set('spider:product-count', 0);
    }

    public function getListProcessStatus() {
        $redis = $this->container->get('snc_redis.default');

        return $redis->get('spider:list-running');
    }

    /**
     * @param boolean $status
     * @return boolean
     */
    public function setListProcessStatus($status) {
        $redis = $this->getRedisService();

        return $redis->set('spider:list-running', $status);
    }

    public function getRedisService() {
        return $this->container->get('snc_redis.default');
    }

    public function getProductReader() {
        return $this->container->get('product_reader_html');
    }

}
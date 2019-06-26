<?php

namespace App\Command;

use App\Processor\ProductReaderHtml;
use App\Service\CrawlerService;
use AppBundle\Entity\Job;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use QL\QueryList;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;


class ProductPageCommand extends Command
{
    /**
     * @var SymfonyStyle $io
     */
    private $io;

    private $pid;

    private $linkRule = '';

    private $container;

    /**
     * @var CrawlerService
     */
    private $crawlerService;

    /**
     * @var
     */
    private $productReader;

    /**
     * @var int
     */
    private $productCount = 0;


    public function __construct(ContainerInterface $container, CrawlerService $crawlerService, ProductReaderHtml $productReader)
    {
        parent::__construct();
        $this->container = $container;
        $this->crawlerService = $crawlerService;
        $this->productReader = $productReader;
    }

    protected function configure()
    {
        $this->setName('app:product');
        $this->addOption('ProductCount', 'p', InputOption::VALUE_OPTIONAL);
        $this->pid = getmypid();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($inputCount = $input->getOption('ProductCount')) {
            $this->productCount = $inputCount;
        }

        $this->io = $io = new SymfonyStyle($input, $output);

        $redis = $this->crawlerService->getRedisService();

        $result = $redis->set('spider:version', CrawlerService::$version);

        if (!$result) {
            $this->io->error('[Queue] redis connect error');
            return ;
        }

        $io->note($this->getPid() . ' ' . '[Product Worker] Product Worker start...');

        while (true) {
            if($this->productCount > 0 && $redis->get('spider:product-count') >= $this->productCount) {
                break;
            }
            $product_link = $redis->spop('spider:product-links-queue');

            if ($product_link === NULL) {
                if ($this->crawlerService->getListProcessStatus() == 'finished') {
                    $io->warning('[Product Worker] waiting list is empty, exit!');
                    break;
                }

            }

            if (!$product_link) {

                $io->note('[Product Worker] Wait for the new tasks，try after 3 sec!');
                sleep(3);
                continue;
            }
            $product =$this->crawl($product_link);

            if(empty($product)) {
                continue;
            }

            //push to redis;
            $product_count = $redis->incr('spider:product-count');

            if($this->productCount > 0 &&  $product_count > $this->productCount) {
                break;
            }

            $result = $redis->sadd('spider:product-all', json_encode($product,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_OBJECT_AS_ARRAY));
            if(0 === $result) {
                $redis->decr('spider:product-count');
            }

        }

        $this->io->success(sprintf('[Product Worker] Got %d products!', $redis->scard('spider:product-all')));

        $this->io->success('[Product Worker] Done！');
    }

    /**
     * @param Job $job
     * @return array
     * @throws \Exception
     */
    protected function crawl($link)
    {

        $this->io->note($this->getPid() . ' ' . '[Product Worker] start crawling: ' . $link);

        
        $client = new Client(array( 'curl' => array( CURLOPT_SSL_VERIFYPEER => false, ), ));

        try {
            $response = $client->get($link, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405',
                ]
            ]);
        } catch (Exception $exception) {
            $code = $exception->getCode();
            if ($code == 404) {
                $this->io->error('The link does not exist：' . $link);
            } else {
                $this->io->error($exception->getCode());
            }

            return;
        }

        $contentHtml = $response->getBody()->getContents();

        $this->productReader->addContent($contentHtml);
        $product = $this->productReader->getProuctInfo();

        $this->io->note($this->getPid() . ' ' . '[Product Worker] End crawling: ' . $link);

        return $product;
    }

    public function getPid()
    {
        return $this->pid;
    }

}
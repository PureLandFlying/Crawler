<?php


namespace App\Command;


use App\Service\CrawlerService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\EachPromise;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Psr7\Response;

class ListPageCommand extends Command
{/**
 * @var SymfonyStyle
 */
    /** @const int EXITCODE the normal code returned when exit the command */
    public const EXITCODE = 0;

    /**
     * @var SymfonyStyle $io
     */
    private $io;

    private $container;

    private $pageCount = 1;

    private $workerCount = 5;

    private $productCount = 0;

    /**
     * @var CrawlerService
     */
    private $crawlerService;

    private $uri = 'https://www.stadiumgoods.com/adidas';

    public function __construct(ContainerInterface $container, CrawlerService $crawlerService)
    {
        parent::__construct();
        $this->container = $container;
        $this->crawlerService = $crawlerService;

    }

    protected function configure()
    {
        $this->setName('app:list');
        $this->addOption('ListPageCount', 'l', InputOption::VALUE_OPTIONAL);
        $this->addOption('ListWorkerCount', 'c', InputOption::VALUE_OPTIONAL);
        $this->addOption('ProductCount', 'p', InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($inputCount = $input->getOption('ListPageCount')) {
            $this->pageCount = $inputCount;
        }

        if ($inputCount = $input->getOption('ListWorkerCount')) {
            $this->workerCount = $inputCount;
        }

        if ($inputCount = $input->getOption('ProductCount')) {
            $this->productCount = $inputCount;
        }

        $redis = $this->crawlerService->getRedisService();

        $this->io = new SymfonyStyle($input, $output);

        $result = $redis->set('spider:version', CrawlerService::$version);

        if (!$result) {
            $this->io->error('[Queue] redis connect error');
            return ;
        }

        $this->runJobQueue($this->pageCount, $this->workerCount);
    }

    /**
     * job任务
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function runJobQueue($pageCount, $workerCount)
    {
        $redis = $this->crawlerService->getRedisService();

        $this->io->success('[List Page Task] Start...');

        $client = new Client(array( 'curl' => array( CURLOPT_SSL_VERIFYPEER => false, ), ));

        $uri = $this->uri;
        $requests = function ($total) use ($client, $uri) {

            for ($i = 1; $i <= $total; $i++) {
                $page = $i > 1 ? '/page/' . $i : '';
                yield function() use ($client, $uri, $page) {
                    return $client->getAsync($uri . $page);
                };
            }
        };

        $config = [
            'concurrency' => $workerCount,
            'fulfilled' => function (Response $response, $index) {
                $this->io->note('Response: ' . $index);
                $redis = $this->crawlerService->getRedisService();
                if($this->productCount > 0 && $redis->get('spider:product-links-count') >= $this->productCount) {
                    return;
                }

                $body = $response->getBody()->getContents();
                $crawler = new Crawler();
                $crawler->addHtmlContent($body);
                $items = $crawler->filter('ul.products-grid li.item');
                $count = $items->count();
                for ( $i = 0; $i < $count; $i++ ) {
                    if($this->productCount > 0 && $redis->get('spider:product-links-count') >= $this->productCount) {
                        break;
                    }

                    $node = $items->eq($i);
                    $link = $node->filter('a.product-image')->link()->getUri();
                    $redis = $this->crawlerService->getRedisService();

                    if (!empty($link)) {
                        if($this->productCount > 0 && $redis->incr('spider:product-links-count') > $this->productCount) {
                            break;
                        }

                        $result = $redis->sadd('spider:product-links-queue', $link);

                        if(0 === $result) {
                            $redis->decr('spider:product-links-count');
                        }
                    }
                };
                // this is delivered each successful response
            },
            'rejected' => function (RequestException $reason, $index) {
                // this is delivered each failed request
                $this->io->error('[List Page Task] ' . $index . ' - ' . $reason->getMessage());
            },
        ];


        $opts = [];

        $iterable = \GuzzleHttp\Promise\iter_for($requests($pageCount));

        $requests = function () use ($iterable, $client, $opts) {
            foreach ($iterable as $key => $rfn) {
                $redis = $this->crawlerService->getRedisService();
                if($this->productCount > 0 && $redis->get('spider:product-links-count') > $this->productCount) {
                    break;
                }
                if (is_callable($rfn)) {
                    yield $key => $rfn($opts);
                }
            }
        };

        $pool = new EachPromise($requests(), $config);

        // Initiate the transfers and create a promise
        $promise = $pool->promise();


        // Force the pool of requests to complete.
        $promise->wait();

        $this->io->success(sprintf('[List Page Task] Got %d products!', $redis->scard('spider:product-links-queue')));

        $this->io->success('[List Page Task] Done！');

        $this->crawlerService->setListProcessStatus('finished');

    }

    public function setUri($uri) {
        $this->uri = $uri;
    }

}
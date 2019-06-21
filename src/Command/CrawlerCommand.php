<?php

namespace App\Command;

use App\Service\CrawlerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

class CrawlerCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:crawler';

    private $spiderName = 'sd-crawler';

    /**
     * @var Process[] list
     */
    private $jobs = [];

    /**
     * @var int the number of the crawler
     */
    private $productWorkerCount = 5;

    /**
     * @var int
     */
    private $productCount = 20;

    /**
     * @var int
     */
    private $listWorkerCount = 5;

    /**
     * @var int
     */
    private $listPageCount = 1;

    /**
     * @var int timeout
     */
    private $timeout = 0;

    private $container;

    /**
     * @var CrawlerService
     */
    private $crawlerService;

    /**
     * @var bool
     */
    private $isDebug = false;

    public function __construct(ContainerInterface $container, CrawlerService $crawlerService)
    {
        parent::__construct();
        $this->container = $container;
        $this->crawlerService = $crawlerService;

    }

    protected function configure()
    {
        $this->setName($this::$defaultName);

        $this->addOption('ProductWorkerCount', 'p', InputOption::VALUE_OPTIONAL);
        $this->addOption('ProductCount', 'pc', InputOption::VALUE_OPTIONAL);
        $this->addOption('ListWorkerCount', 'l', InputOption::VALUE_OPTIONAL);
        $this->addOption('ListPageCount', 'c', InputOption::VALUE_OPTIONAL);
        $this->addOption('Debug', 'd', InputOption::VALUE_NONE);
        $this->addOption('Timeout', 't', InputOption::VALUE_NONE);

        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Runs a crawler')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command allows you to run a crawler...')
        ;
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($inputCount = $input->getOption('ProductWorkerCount')) {
            $this->productWorkerCount = $inputCount;
        }
        if ($inputCount = $input->getOption('ProductCount')) {
            $this->productCount = $inputCount;
        }
        if ($inputCount = $input->getOption('ListWorkerCount')) {
            $this->listWorkerCount = $inputCount;
        }
        if ($inputCount = $input->getOption('ListPageCount')) {
            $this->listPageCount = $inputCount;
        }
        if ($inputCount =$input->getOption('Debug')) {
            $this->isDebug = $inputCount;
        }
        if ($inputCount = $input->getOption('Timeout')) {
            $this->timeout = $inputCount;
        }

        $this->crawlerService->clearCache();

        // outputs multiple lines to the console (adding "\n" at the end of each line)
        $message = new SymfonyStyle($input, $output);
        $message->note('Start Crawling...');

        $listProcess = $this->createListWorker();

        for ($i = 0; $i < $this->productWorkerCount; $i++) {
            $this->jobs[$i] = $this->createOneWorker();
        }

        $redis = $this->container->get('snc_redis.default');

        for ($i = 0; $i < $this->productWorkerCount; $i++) {

            $process = $this->jobs[$i];

            $break = false;

            if ($this->timeout) {
                try {
                    $process->checkTimeout();
                } catch (RuntimeException $exception) {
                    $message->error(sprintf('PROCESS:%s timeout!', $i));
                    $process->stop();

                    $this->jobs[$i] = $this->createOneWorker();
                    $break = true;
                }
            }

            if ($this->isDebug) {
                echo $process->getIncrementalOutput();
                echo $listProcess->getIncrementalOutput();
            }

            echo $process->getIncrementalErrorOutput();
            echo $listProcess->getIncrementalErrorOutput();

            if (!$break) {
                if (!$process->isRunning() ) {
                    if($this->crawlerService->getListProcessStatus() != 'finished') {

                        $message->warning(sprintf('PROCESS:%S ended!', $i));

                        $process->stop();
                        $this->jobs[$i] = $this->createOneWorker();
                    }else {
                        $this->productWorkerCount--;
                        unset($this->jobs[$i]);
                        $i--;
                        $this->jobs = array_values($this->jobs);
                    }
                }
            }

            if ($i === $this->productWorkerCount - 1) {
                if (!$listProcess->isRunning() && !$listProcess->isSuccessful()) {
                    $message->warning('List worker is not running ,restart!');
                    $listProcess->stop();
                    $listProcess = $this->createListWorker();
                }

                $i = -1;
                sleep(1);
            }
        }

        $message->note('Dumping the products to the file...');
        $filesystem = new Filesystem();
        try {
            $filesystem->remove(getcwd() . '/products.json');
        } catch (IOExceptionInterface $exception) {
            echo "An error occurred while creating your file at " . $exception->getPath();
        } catch (\Exception $e) {

        }

        $filesystem->appendToFile(getcwd() . '/products.json', "[");
        $i = 0;

        while (true) {
            $product = $redis->spop('spider:product-all');

            if ($product === NULL) {
                break;
            }

            try {
                $tmp  = '';
                if ($i++ > 0) {
                    $tmp  = ",\n";
                }

                $filesystem->appendToFile(getcwd() . '/products.json', $tmp . $product);

            } catch (IOExceptionInterface $exception) {
                $i--;
                echo "An error occurred while creating your file at ". $exception->getPath();
            }

        }

        $filesystem->appendToFile(getcwd() . '/products.json', "]");

        $message->success('Dumped ' . $i . ' Product(s)!');

    }

    /**
     * @return Process
     */
    protected function createOneWorker()
    {
        $process = Process::fromShellCommandline(sprintf("php bin/console app:product  --ProductCount={$this->productCount}"));
        //$process->setPty(false);

        if ($this->timeout) {
            $process->setTimeout($this->timeout);
        }

        $process->start(function ($type, $buffer) {
            //echo $buffer;
        });

        return $process;
    }

    /**
     *
     * @return Process
     */
    protected function createListWorker() {

        $jobQueueCommand = sprintf("php bin/console app:list --ListPageCount={$this->listPageCount} --ListWorkerCount={$this->listWorkerCount} --ProductCount={$this->productCount}");

        $jobQueue = Process::fromShellCommandline($jobQueueCommand);

        $jobQueue->start(function ($type, $buffer) {
            //echo $buffer;
        });

        return $jobQueue;
    }
}
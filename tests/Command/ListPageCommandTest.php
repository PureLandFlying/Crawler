<?php

namespace App\Command;


use App\Service\CrawlerService;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;


class ListPageCommandTest extends KernelTestCase
{//** @var Application */
    static private $application;
    /** @var Command */
    static private $command;
    /** @var Container */
    static protected $container;

    static private $redis;

    /**
     * @var CrawlerService
     */
    static private $crawlerService;

    /*#######################
     # ONCE BEFORE ALL TEST #
     #######################*/
    static public function setUpBeforeClass()
    {
        // Get the kernel
        self::$kernel = self::bootKernel();
        self::$application = new Application(self::$kernel);
        self::$application->setAutoExit(false);
        self::$container = self::$kernel->getContainer();

        self::$crawlerService = self::$container->get('crawler_service');

        // Get Redis
        self::$redis = self::$crawlerService->getRedisService();

        // Get the commmand
        self::$command = self::$application
            ->find('app:list');
    }

    public function setUp()
    {

    }

    /**
     * Test the Execute
     */
    public function testExecute(): void
    {
        // SCENARIO
        //  1
        $this->clearCache();

        $crawlerService = $this->getMockBuilder(CrawlerService::class)
            ->setConstructorArgs([self::$container])
            ->setMethods(['getRedisService'])
            ->getMock();
        $crawlerService->expects($this->once())->method("getRedisService")->willReturnCallback(function () {
            return new \App\Tests\Stub\Redis();
        });

        $command = $this->getMockBuilder(ListPageCommand::class)
            ->setConstructorArgs([self::$container, $crawlerService])
            ->setMethods()
            ->getMock();
        
        $commandTester = new CommandTester($command);
        $product_count = 10;
        $this->assertEquals(
            ListPageCommand::EXITCODE,
            $commandTester->execute(['--ProductCount' => $product_count, '--ListWorkerCount' => 5, '--ListPageCount' => 10])
        );
        $output = $commandTester->getDisplay();

        $this->assertContains('[ERROR] [Queue] redis connect error', $output);

        //-----
        $commandTester = new CommandTester(self::$command);
        // SCENARIO
        //  2

        $this->clearCache();

        $product_count = 10;
        self::$redis->set('spider:product-links-count', $product_count+1);
        $this->assertEquals(
            ListPageCommand::EXITCODE,
            $commandTester->execute(['--ProductCount' => $product_count, '--ListWorkerCount' => 6, '--ListPageCount' => 20])
        );

        $output = $commandTester->getDisplay();

        $this->assertContains('[OK] [List Page Task] Got 0 products!', $output);

        //Test if content is well displayed
        $this->assertContains('[OK] [List Page Task] Done！', $output);


        //SCENARIO
        // 3
        $this->clearCache();

        $commandTester = new CommandTester(self::$command);
        $this->assertEquals(
            ListPageCommand::EXITCODE,
            $commandTester->execute([])
        );
        $output = $commandTester->getDisplay();

        $this->assertNotContains('[OK] [List Page Task] Got 0 products!', $output);
        //Test if content is well displayed
        $this->assertContains('[OK] [List Page Task] Done！', $output);

        //-----
        // SCENARIO
        //  4
        $this->clearCache();

        $command = self::$application->find('app:list');
        $command->setUri('https://test1.stadiumgoods.com/adidas');
        $commandTester = new CommandTester($command);
        $product_count = 10;
        $this->assertEquals(
            ListPageCommand::EXITCODE,
            $commandTester->execute(['--ProductCount' => $product_count, '--ListWorkerCount' => 6, '--ListPageCount' => 20])
        );

        $output = $commandTester->getDisplay();

        $this->assertContains('[OK] [List Page Task] Got 0 products!', $output);

        //Test if content is well displayed
        $this->assertContains('[OK] [List Page Task] Done！', $output);

    }

    private function clearCache() {
        self::$redis->del('spider:product-links-count');
        self::$redis->del('spider:product-links-queue');
    }

}
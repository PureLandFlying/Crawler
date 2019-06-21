<?php

namespace App\Command;

use App\Service\CrawlerService;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;

class CrawlerCommandTest extends KernelTestCase
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
        self::$command = self::$application->find('app:crawler');
    }

    public function setUp()
    {

    }

    /**
     * Test the Execute
     */
    public function testExecute(): void
    {
        $i =1;
        // SCENARIO
        //  1
        $command = $this->getMockBuilder(CrawlerCommand::class)
            ->setConstructorArgs([self::$container, self::$crawlerService])
            ->setMethods()
            ->getMock();

        $commandTester = new CommandTester($command);
        $product_count = 1;
        $this->assertEquals(
            ListPageCommand::EXITCODE,
            $commandTester->execute(['-pc' => $product_count, '-d' => true , '-t' => 5000])
        );
        $output = $commandTester->getDisplay();

        $this->assertContains(sprintf('[OK] Dumped %s Product(s)! ', $product_count), $output);


        $commandTester = new CommandTester(self::$command);
        $product_count = 20;
        $this->assertEquals(
            ListPageCommand::EXITCODE,
            $commandTester->execute(['--ProductCount' => $product_count, '--ProductWorkerCount' => 5, '--ListWorkerCount' => 5, '--ListPageCount' => 10, '--Debug' => true])
        );
        $output = $commandTester->getDisplay();

        $this->assertContains(sprintf('[OK] Dumped %s Product(s)! ', $product_count), $output);

    }

}